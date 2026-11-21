<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_profile_update_and_city_must_match_country(): void
    {
        $user = $this->makePatient('Casablanca');
        $this->actingAsUser($user);
        $paris = City::where('name', 'Paris')->first();

        $this->putJson('/api/v1/patient/profile', ['city_id' => $paris->id])->assertUnprocessable();
        $this->putJson('/api/v1/patient/profile', [
            'country_id' => $paris->country_id, 'city_id' => $paris->id, 'date_of_birth' => '1985-03-02',
            'preferred_language' => 'ar', 'share_location' => true,
        ])->assertOk()->assertJsonPath('data.city.name', 'Paris')->assertJsonPath('data.preferences.share_location', true);

        $this->assertSame('ar', $user->fresh()->preferred_language);
        // Date of birth is encrypted at rest.
        $raw = DB::table('patients')->where('id', $user->patient->id)->value('date_of_birth');
        $this->assertNotSame('1985-03-02', $raw);
        $this->getJson('/api/v1/patient/profile')->assertJsonPath('data.date_of_birth', '1985-03-02');
    }

    public function test_admin_without_patient_profile_cannot_use_patient_routes(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $this->getJson('/api/v1/patient/profile')->assertForbidden();
    }

    public function test_data_export(): void
    {
        $this->actingAsUser($this->makePatient());
        $this->getJson('/api/v1/patient/export')->assertOk()
            ->assertJsonStructure(['data' => ['exported_at', 'account', 'profile', 'consents', 'favorite_doctors', 'assessments']]);
    }

    public function test_account_deletion_anonymises_and_requires_password(): void
    {
        $user = $this->makePatient();
        $this->actingAsUser($user);

        $this->deleteJson('/api/v1/patient/account', ['password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/patient/account', ['password' => 'Password123'])->assertOk();

        $deleted = User::withTrashed()->find($user->id);
        $this->assertTrue($deleted->trashed());
        $this->assertStringEndsWith('@deleted.invalid', $deleted->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'patient.account_deleted']);
    }
}
