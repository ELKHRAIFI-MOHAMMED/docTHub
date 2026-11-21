<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\ReferenceDataSeeder::class, \Database\Seeders\MedicalCatalogSeeder::class]);
    }

    private function cityId(string $name): int
    {
        return City::where('name', $name)->value('id');
    }

    private function specialtyId(string $code): int
    {
        return Specialty::where('code', $code)->value('id');
    }

    public function test_a_location_context_is_required(): void
    {
        $this->getJson('/api/v1/doctors')->assertUnprocessable()->assertJsonValidationErrors('country_id');
        $this->getJson('/api/v1/doctors?specialty_id='.$this->specialtyId('cardiology'))->assertUnprocessable();
    }

    public function test_filters_by_city_and_specialty_without_distance(): void
    {
        $casaCardio = $this->makeDoctor('Casablanca', 'cardiology');
        $this->makeDoctor('Casablanca', 'neurology');
        $this->makeDoctor('Rabat', 'cardiology');

        $res = $this->getJson('/api/v1/doctors?city_id='.$this->cityId('Casablanca').'&specialty_id='.$this->specialtyId('cardiology'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.distance_basis', 'none');

        $this->assertSame($casaCardio->id, $res->json('data.0.id'));
        $this->assertNull($res->json('data.0.distance_km'), 'no distance without patient coordinates');
    }

    public function test_unverified_and_unpublished_doctors_are_hidden(): void
    {
        $this->makeDoctor('Casablanca', 'cardiology', ['verification_status' => 'pending']);
        $this->makeDoctor('Casablanca', 'cardiology', ['status' => 'draft']);
        $this->makeDoctor('Casablanca', 'cardiology', ['verification_status' => 'suspended']);
        $hidden = $this->makeDoctor('Casablanca', 'cardiology', ['verification_status' => 'rejected']);

        $this->getJson('/api/v1/doctors?city_id='.$this->cityId('Casablanca'))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/doctors/{$hidden->id}")->assertNotFound();
    }

    public function test_distance_sort_and_radius_with_coordinates(): void
    {
        $city = City::where('name', 'Casablanca')->first();
        $far = $this->makeDoctor('Casablanca', 'cardiology', [], [$city->latitude + 0.09, $city->longitude]);   // ~10 km
        $near = $this->makeDoctor('Casablanca', 'cardiology', [], [$city->latitude + 0.009, $city->longitude]); // ~1 km
        $this->makeDoctor('Rabat', 'cardiology'); // ~87 km

        $res = $this->getJson("/api/v1/doctors?lat={$city->latitude}&lng={$city->longitude}&radius_km=25")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.distance_basis', 'coordinates');

        $this->assertSame([$near->id, $far->id], array_column($res->json('data'), 'id'));
        $this->assertEqualsWithDelta(1.0, $res->json('data.0.distance_km'), 0.2);
        $this->assertEqualsWithDelta(10.0, $res->json('data.1.distance_km'), 0.5);

        $this->getJson("/api/v1/doctors/nearby?lat={$city->latitude}&lng={$city->longitude}&radius_km=100")
            ->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_name_search_language_and_fee_filters(): void
    {
        $country = Country::where('iso2', 'MA')->value('id');
        $amina = $this->makeDoctor('Casablanca', 'cardiology', ['first_name' => 'Amina', 'consultation_fee' => 300, 'fee_currency' => 'MAD']);
        $amina->languages()->attach(Language::where('code', 'ar')->value('id'));
        $this->makeDoctor('Rabat', 'neurology', ['first_name' => 'Bruno', 'consultation_fee' => 600, 'fee_currency' => 'MAD']);

        $this->getJson("/api/v1/doctors?country_id=$country&q=amin")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $amina->id);
        $this->getJson("/api/v1/doctors?country_id=$country&q=%25")->assertJsonPath('meta.total', 0); // LIKE wildcard escaped
        $this->getJson("/api/v1/doctors?country_id=$country&language=ar")->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/doctors?country_id=$country&max_fee=400")->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/doctors/specialty/{$this->specialtyId('neurology')}?country_id=$country")->assertJsonPath('meta.total', 1);
    }

    public function test_pagination(): void
    {
        foreach (range(1, 5) as $i) {
            $this->makeDoctor('Paris', 'general-practice');
        }
        $this->getJson('/api/v1/doctors?city_id='.$this->cityId('Paris').'&per_page=2&page=3')
            ->assertOk()->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3)->assertJsonCount(1, 'data');
    }

    public function test_doctor_profile_by_id_and_slug(): void
    {
        $doctor = $this->makeDoctor('Paris', 'cardiology', ['bio' => 'Bio EN']);
        $doctor->syncTranslations(['fr' => ['bio' => 'Bio FR']]);

        $this->getJson("/api/v1/doctors/{$doctor->id}")->assertOk()->assertJsonPath('data.bio', 'Bio EN')->assertJsonCount(1, 'data.locations');
        $this->getJson("/api/v1/doctors/{$doctor->slug}", ['X-Locale' => 'fr'])->assertOk()->assertJsonPath('data.bio', 'Bio FR');
    }

    public function test_doctor_locations_endpoints_only_list_places_with_public_doctors(): void
    {
        $this->makeDoctor('Lyon', 'cardiology');
        $this->makeDoctor('Rabat', 'cardiology', ['verification_status' => 'pending']);

        $cities = collect($this->getJson('/api/v1/doctors/cities')->assertOk()->json('data'))->pluck('name');
        $this->assertEquals(['Lyon'], $cities->all());
        $countries = collect($this->getJson('/api/v1/doctors/countries')->json('data'))->pluck('iso2');
        $this->assertEquals(['FR'], $countries->all());
    }

    public function test_patient_can_manage_favorites(): void
    {
        $doctor = $this->makeDoctor('Casablanca', 'cardiology');
        $pending = $this->makeDoctor('Casablanca', 'cardiology', ['verification_status' => 'pending']);

        $this->postJson("/api/v1/favorites/{$doctor->id}")->assertUnauthorized();

        $this->actingAsUser($this->makePatient());
        $this->postJson("/api/v1/favorites/{$doctor->id}")->assertCreated();
        $this->postJson("/api/v1/favorites/{$doctor->id}")->assertCreated(); // idempotent
        $this->postJson("/api/v1/favorites/{$pending->id}")->assertNotFound();

        $this->getJson('/api/v1/patient/favorites')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_favorite', true);
        $this->getJson("/api/v1/doctors/{$doctor->id}")->assertJsonPath('data.is_favorite', true);

        $this->deleteJson("/api/v1/favorites/{$doctor->id}")->assertOk();
        $this->getJson('/api/v1/patient/favorites')->assertJsonCount(0, 'data');
    }
}
