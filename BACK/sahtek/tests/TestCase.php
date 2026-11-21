<?php

namespace Tests;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Orientation\ContentGate;
use Database\Seeders\MedicalCatalogSeeder;
use Database\Seeders\QuestionnaireSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function seedCatalog(): void
    {
        $this->seed([ReferenceDataSeeder::class, MedicalCatalogSeeder::class, QuestionnaireSeeder::class]);
    }

    /** Simulates a development environment where sample (unreviewed) medical content may be used. */
    protected function allowUnreviewedContent(bool $allow = true): void
    {
        $this->app->instance(ContentGate::class, new ContentGate($allow));
    }

    protected function makeAdmin(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin'.uniqid().'@test.local', 'password' => 'Password123']);
        $user->assignRole(User::ROLE_ADMIN);

        return $user;
    }

    protected function makePatient(string $cityName = 'Casablanca'): User
    {
        $city = City::where('name', $cityName)->firstOrFail();
        $user = User::create(['name' => 'Pat Ient', 'email' => 'patient'.uniqid().'@test.local', 'password' => 'Password123']);
        $user->assignRole(User::ROLE_PATIENT);
        Patient::create(['user_id' => $user->id, 'first_name' => 'Pat', 'last_name' => 'Ient', 'country_id' => $city->country_id, 'city_id' => $city->id]);

        return $user->fresh();
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function makeDoctor(string $cityName, string $specialtyCode, array $attrs = [], ?array $coords = null): Doctor
    {
        $city = City::where('name', $cityName)->firstOrFail();
        $doctor = Doctor::create($attrs + [
            'first_name' => 'Test',
            'last_name' => 'Doctor'.uniqid(),
            'verification_status' => 'verified',
            'status' => 'published',
            'is_demo' => true,
            'years_of_experience' => 10,
        ]);
        $doctor->specialties()->attach(Specialty::where('code', $specialtyCode)->value('id'), ['is_primary' => true]);
        $doctor->locations()->create([
            'country_id' => $city->country_id,
            'city_id' => $city->id,
            'latitude' => $coords[0] ?? $city->latitude,
            'longitude' => $coords[1] ?? $city->longitude,
            'is_primary' => true,
        ]);

        return $doctor;
    }
}
