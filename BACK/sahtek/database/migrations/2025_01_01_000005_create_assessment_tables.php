<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 10)->default('en');
            $table->enum('sex', ['female', 'male', 'other', 'undisclosed'])->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();
            $table->json('body_part_ids');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->string('engine_version', 20)->nullable();
            $table->boolean('uses_unreviewed_content')->default(false);
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('symptom_assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('symptom_questions')->cascadeOnDelete();
            $table->json('option_codes')->nullable();
            $table->string('value', 255)->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'question_id']);
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('symptom_assessments')->cascadeOnDelete();
            $table->enum('result_type', ['condition', 'specialty', 'guideline']);
            $table->foreignId('disease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guideline_id')->nullable()->constrained('medical_guidelines')->nullOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->json('matched_rule_ids')->nullable();
            $table->timestamps();
            $table->index(['assessment_id', 'result_type']);
        });

        Schema::create('assessment_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('symptom_assessments')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('orientation_rules')->nullOnDelete();
            $table->enum('severity', ['critical', 'urgent']);
            $table->text('message');
            $table->string('emergency_number', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_alerts');
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('symptom_assessments');
    }
};
