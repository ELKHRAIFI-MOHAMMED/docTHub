<?php

namespace Tests\Feature;

use App\Models\BodyPart;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_only_published_body_parts_are_listed_and_translated(): void
    {
        BodyPart::where('code', 'neck')->update(['status' => 'draft']);

        $codes = collect($this->getJson('/api/v1/body-parts')->assertOk()->json('data'))->pluck('code');
        $this->assertNotContains('neck', $codes);

        $this->getJson('/api/v1/body-parts', ['X-Locale' => 'ar'])
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.0.name', 'الرأس');
        $this->getJson('/api/v1/body-parts?lang=fr')->assertJsonPath('data.0.name', 'Tête');
        $this->getJson('/api/v1/body-parts', ['X-Locale' => 'de'])->assertJsonPath('data.0.name', 'Head');
    }

    public function test_unreviewed_diseases_are_hidden_from_public_catalog_in_production_mode(): void
    {
        $this->getJson('/api/v1/diseases')->assertOk()->assertJsonPath('meta.total', 0);
        $this->allowUnreviewedContent();
        $this->assertGreaterThan(0, $this->getJson('/api/v1/diseases')->json('meta.total'));
    }

    public function test_geo_reference_endpoints(): void
    {
        $this->getJson('/api/v1/languages')->assertOk()->assertJsonCount(3, 'data');
        $ma = Country::where('iso2', 'MA')->value('id');
        $this->getJson('/api/v1/countries', ['X-Locale' => 'fr'])->assertOk();
        $this->getJson("/api/v1/cities?country_id=$ma")->assertOk()->assertJsonPath('data.0.country_id', $ma);
        $this->getJson('/api/v1/cities')->assertUnprocessable();
    }

    public function test_unknown_resource_returns_json_404(): void
    {
        $this->get('/api/v1/doctors/99999')->assertNotFound()->assertJson(['message' => 'Resource not found.']);
    }
}
