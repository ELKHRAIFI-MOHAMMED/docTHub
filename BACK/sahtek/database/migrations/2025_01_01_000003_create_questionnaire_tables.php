<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OPERATORS = ['equals', 'not_equals', 'in', 'not_in', 'gte', 'lte', 'answered', 'contains'];

    public function up(): void
    {
        Schema::create('symptom_questions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('body_part_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->text('help_text')->nullable();
            $table->enum('type', ['single_choice', 'multi_choice', 'scale', 'number', 'boolean']);
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->string('unit', 32)->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('symptom_questions')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label', 255);
            $table->integer('score')->nullable();
            $table->foreignId('symptom_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['question_id', 'code']);
        });

        Schema::create('question_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('symptom_questions')->cascadeOnDelete();
            $table->foreignId('depends_on_question_id')->constrained('symptom_questions')->cascadeOnDelete();
            $table->enum('operator', self::OPERATORS);
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('orientation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('body_part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('symptom_questions')->nullOnDelete();
            $table->enum('operator', self::OPERATORS)->nullable();
            $table->json('expected_value')->nullable();
            $table->json('conditions')->nullable();
            $table->integer('priority')->default(0);
            $table->enum('result_type', ['emergency', 'disease', 'specialty', 'advice']);
            $table->foreignId('disease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guideline_id')->nullable()->constrained('medical_guidelines')->nullOnDelete();
            $table->enum('emergency_severity', ['critical', 'urgent'])->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->text('message')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->enum('validation_status', ['draft', 'in_review', 'approved', 'rejected', 'retired'])->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['result_type', 'validation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientation_rules');
        Schema::dropIfExists('question_conditions');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('symptom_questions');
    }
};
