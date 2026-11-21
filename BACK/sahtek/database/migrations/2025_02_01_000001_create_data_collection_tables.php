<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-collection phase:
 *  - knowledge provenance (where each medical fact comes from, under which licence, and whether it changed);
 *  - version stamps on assessments (comparable data over time);
 *  - post-consultation outcomes (ground truth for analytics);
 *  - per-question templates for the readable case summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('publisher', 150)->nullable();
            $table->string('base_url', 500)->nullable();
            $table->string('license', 255);
            $table->string('license_url', 500)->nullable();
            $table->text('allowed_use')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('title', 500);
            $table->string('language', 10)->default('en');
            $table->date('published_at')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->longText('raw_content')->nullable();
            $table->enum('status', ['pending', 'active', 'changed', 'unavailable'])->default('pending');
            $table->timestamps();
            $table->unique(['knowledge_source_id', 'url'], 'source_documents_source_url_unique');
            $table->index('status');
        });

        foreach (['medical_guidelines', 'orientation_rules', 'diseases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('source_document_id')->nullable()->after('source_url')->constrained()->nullOnDelete();
            });
        }

        Schema::table('symptom_questions', function (Blueprint $table) {
            // Sentence used in the readable case summary, e.g. "Pain intensity: :answer out of 10." (translatable)
            $table->string('summary_template', 500)->nullable()->after('help_text');
        });

        Schema::table('symptom_assessments', function (Blueprint $table) {
            $table->string('questionnaire_version', 20)->nullable()->after('engine_version');
            $table->string('ruleset_version', 20)->nullable()->after('questionnaire_version');
        });

        Schema::create('assessment_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('symptom_assessments')->cascadeOnDelete();
            $table->boolean('consulted')->nullable();
            $table->foreignId('consulted_specialty_id')->nullable()->constrained('specialties')->nullOnDelete();
            $table->foreignId('diagnosis_disease_id')->nullable()->constrained('diseases')->nullOnDelete();
            $table->string('diagnosis_icd11', 20)->nullable();
            $table->boolean('went_to_emergency')->nullable();
            $table->unsignedTinyInteger('helpfulness')->nullable(); // 1..5
            $table->enum('source', ['patient_reported', 'doctor_confirmed'])->default('patient_reported');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_outcomes');
        Schema::table('symptom_assessments', fn (Blueprint $t) => $t->dropColumn(['questionnaire_version', 'ruleset_version']));
        Schema::table('symptom_questions', fn (Blueprint $t) => $t->dropColumn('summary_template'));
        foreach (['medical_guidelines', 'orientation_rules', 'diseases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('source_document_id');
            });
        }
        Schema::dropIfExists('source_documents');
        Schema::dropIfExists('knowledge_sources');
    }
};
