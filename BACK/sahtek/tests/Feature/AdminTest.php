<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Doctor;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Specialty;
use App\Models\SymptomQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_admin_routes_require_admin_role(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

        $this->actingAsUser($this->makePatient());
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->postJson('/api/v1/admin/specialties', ['code' => 'x', 'name' => 'X'])->assertForbidden();

        $this->actingAsUser($this->makeAdmin());
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonStructure(['data' => ['patients', 'doctors', 'assessments_total']]);
    }

    public function test_disabled_admin_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['is_active' => false]);
        $this->actingAsUser($admin);
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_specialty_crud_with_translations_status_and_audit(): void
    {
        $this->actingAsUser($this->makeAdmin());

        $id = $this->postJson('/api/v1/admin/specialties', [
            'code' => 'endocrinology', 'name' => 'Endocrinology',
            'translations' => ['fr' => ['name' => 'Endocrinologie'], 'ar' => ['name' => 'الغدد الصماء']],
        ])->assertCreated()->assertJsonPath('data.translations.fr.name', 'Endocrinologie')->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.created', 'auditable_type' => 'specialty', 'auditable_id' => $id]);

        // Draft by default: not public.
        $this->assertNotContains('endocrinology', collect($this->getJson('/api/v1/specialties')->json('data'))->pluck('code'));

        $this->patchJson("/api/v1/admin/specialties/$id/status", ['status' => 'published'])->assertOk();
        $names = collect($this->getJson('/api/v1/specialties', ['X-Locale' => 'ar'])->json('data'))->pluck('name', 'code');
        $this->assertSame('الغدد الصماء', $names['endocrinology']);

        $this->putJson("/api/v1/admin/specialties/$id", ['name' => 'Endocrinology & diabetes'])->assertOk();
        $this->postJson('/api/v1/admin/specialties', ['code' => 'endocrinology', 'name' => 'Dup'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/specialties', ['code' => 'x', 'name' => 'X', 'translations' => ['de' => ['name' => 'X']]])->assertUnprocessable();

        $this->deleteJson("/api/v1/admin/specialties/$id")->assertNoContent();
        $this->assertSoftDeleted('specialties', ['id' => $id]);
    }

    public function test_question_with_options_and_conditions(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $parent = SymptomQuestion::where('code', 'pain_onset')->first();

        $id = $this->postJson('/api/v1/admin/questions', [
            'code' => 'test_q', 'text' => 'Test?', 'type' => 'single_choice', 'status' => 'published',
            'options' => [['code' => 'a', 'label' => 'A', 'translations' => ['fr' => ['label' => 'A fr']]], ['code' => 'b', 'label' => 'B']],
            'conditions' => [['depends_on_question_id' => $parent->id, 'operator' => 'equals', 'value' => 'sudden']],
        ])->assertCreated()->assertJsonCount(2, 'data.options')->json('data.id');

        $this->putJson("/api/v1/admin/questions/$id", ['options' => [['code' => 'a', 'label' => 'A2']]])
            ->assertOk()->assertJsonCount(1, 'data.options')->assertJsonPath('data.options.0.label', 'A2');

        $this->postJson('/api/v1/admin/questions', ['code' => 'bad', 'text' => 'x', 'type' => 'scale'])
            ->assertUnprocessable()->assertJsonValidationErrors(['min_value', 'max_value']);
    }

    public function test_rule_requires_a_criterion_and_logic_edit_resets_approval(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $specialty = Specialty::where('code', 'cardiology')->value('id');

        $this->postJson('/api/v1/admin/rules', [
            'code' => 'no-criterion', 'name' => 'x', 'result_type' => 'specialty', 'specialty_id' => $specialty, 'source_name' => 'Test',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('orientation_rules', ['code' => 'no-criterion']);

        $this->postJson('/api/v1/admin/rules', ['code' => 'em', 'name' => 'x', 'result_type' => 'emergency', 'source_name' => 'Test', 'body_part_id' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('emergency_severity');

        $rule = OrientationRule::where('code', 'dz-migraine')->first();
        $this->patchJson("/api/v1/admin/rules/{$rule->id}/status", ['status' => 'approved'])->assertOk();
        $this->putJson("/api/v1/admin/rules/{$rule->id}", ['expected_value' => ['nausea']])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.validation_status', 'in_review');
    }

    public function test_guideline_approval_needs_reviewer_and_edit_bumps_version(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $g = MedicalGuideline::first();

        $this->putJson("/api/v1/admin/guidelines/{$g->id}", ['review_status' => 'approved'])
            ->assertUnprocessable()->assertJsonValidationErrors('reviewed_by_name');
        $this->putJson("/api/v1/admin/guidelines/{$g->id}", ['review_status' => 'approved', 'reviewed_by_name' => 'Dr Reviewer'])
            ->assertOk()->assertJsonPath('data.review_status', 'approved');
        $this->putJson("/api/v1/admin/guidelines/{$g->id}", ['content' => 'Changed content'])
            ->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.review_status', 'in_review');
    }

    public function test_doctor_management_and_verification_requires_approved_document(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAsUser($this->makeAdmin());
        $city = City::where('name', 'Rabat')->first();

        $payload = [
            'first_name' => 'Real', 'last_name' => 'Doctor',
            'specialties' => [['id' => Specialty::where('code', 'neurology')->value('id')]],
            'locations' => [['country_id' => $city->country_id, 'city_id' => $city->id, 'address' => '1 Street']],
        ];
        $id = $this->postJson('/api/v1/admin/doctors', $payload)->assertCreated()->assertJsonPath('data.verification_status', 'pending')->json('data.id');

        // City / country mismatch is rejected.
        $paris = City::where('name', 'Paris')->first();
        $this->putJson("/api/v1/admin/doctors/$id", ['locations' => [['country_id' => $city->country_id, 'city_id' => $paris->id]]])->assertUnprocessable();

        $this->patchJson("/api/v1/admin/doctors/$id/verification", ['verification_status' => 'verified'])->assertUnprocessable();

        $docId = $this->post("/api/v1/admin/doctors/$id/documents", [
            'type' => 'license', 'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');

        $this->post("/api/v1/admin/doctors/$id/documents", [
            'type' => 'license', 'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->patchJson("/api/v1/admin/doctors/$id/documents/$docId", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/v1/admin/doctors/$id/verification", ['verification_status' => 'verified'])
            ->assertOk()->assertJsonPath('data.verification_status', 'verified');

        $this->post("/api/v1/admin/doctors/$id/photo", ['photo' => UploadedFile::fake()->image('p.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull(Doctor::find($id)->photo_path);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.doctor_verification', 'auditable_id' => $id]);
    }

    public function test_admin_can_deactivate_patient_and_revoke_tokens(): void
    {
        $patient = $this->makePatient();
        $patient->createToken('web');
        $this->actingAsUser($this->makeAdmin());

        $this->getJson('/api/v1/admin/patients')->assertOk()->assertJsonPath('total', 1);
        $this->patchJson("/api/v1/admin/patients/{$patient->patient->id}/active", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(0, $patient->tokens()->count());
    }
}
