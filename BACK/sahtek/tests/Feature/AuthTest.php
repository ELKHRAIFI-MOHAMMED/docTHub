<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        $country = Country::where('iso2', 'FR')->first();

        return array_merge([
            'first_name' => 'Jeanne',
            'last_name' => 'Martin',
            'email' => 'Jeanne@Example.com',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'country_id' => $country->id,
            'city_id' => $country->cities()->first()->id,
            'preferred_language' => 'fr',
            'consents' => ['terms' => true, 'privacy' => true, 'health_data' => true],
        ], $overrides);
    }

    public function test_patient_can_register_and_consents_are_recorded(): void
    {
        $res = $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'jeanne@example.com')
            ->assertJsonPath('data.user.roles.0', 'patient');

        $this->assertNotEmpty($res->json('data.token'));
        $user = User::where('email', 'jeanne@example.com')->first();
        $this->assertNotEquals('Secret1234', $user->password, 'password must be hashed');
        $this->assertSame(3, $user->patient->consents()->count());
    }

    public function test_registration_requires_all_consents(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['consents' => ['terms' => true, 'privacy' => true]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consents.health_data');
    }

    public function test_registration_rejects_city_from_another_country(): void
    {
        $moroccanCity = Country::where('iso2', 'MA')->first()->cities()->first();
        $this->postJson('/api/v1/auth/register', $this->payload(['city_id' => $moroccanCity->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('city_id');
    }

    public function test_registration_rejects_weak_password_and_duplicate_email(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_me_and_logout(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'jeanne@example.com', 'password' => 'Secret1234'])
            ->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.patient.first_name', 'Jeanne');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_wrong_password_and_disabled_account_cannot_log_in(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/login', ['email' => 'jeanne@example.com', 'password' => 'Wrong12345'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        User::where('email', 'jeanne@example.com')->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => 'jeanne@example.com', 'password' => 'Secret1234'])
            ->assertUnprocessable();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])->assertStatus(429);
    }
}
