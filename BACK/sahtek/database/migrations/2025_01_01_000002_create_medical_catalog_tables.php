<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS = ['draft', 'published', 'archived'];
    private const REVIEW = ['draft', 'in_review', 'approved', 'rejected', 'retired'];

    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('icon', 64)->nullable();
            $table->enum('status', self::STATUS)->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('body_parts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('view', ['front', 'back', 'both'])->default('front');
            $table->string('svg_region', 64)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('body_parts')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', self::STATUS)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('symptoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('status', self::STATUS)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('severity', ['low', 'moderate', 'high'])->default('moderate');
            $table->enum('status', self::STATUS)->default('draft')->index();
            $table->enum('review_status', self::REVIEW)->default('draft')->index();
            $table->string('source_name')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('disease_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('symptom_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unique(['disease_id', 'symptom_id']);
        });

        Schema::create('disease_body_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('body_part_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unique(['disease_id', 'body_part_id']);
        });

        Schema::create('medical_guidelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('specialty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 255);
            $table->text('content');
            $table->enum('guideline_type', ['self_care', 'when_to_consult', 'prevention', 'emergency'])->default('when_to_consult');
            $table->string('source_name')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->enum('review_status', self::REVIEW)->default('draft')->index();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_sample')->default(false);
            $table->enum('status', self::STATUS)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_guidelines');
        Schema::dropIfExists('disease_body_parts');
        Schema::dropIfExists('disease_symptoms');
        Schema::dropIfExists('diseases');
        Schema::dropIfExists('symptoms');
        Schema::dropIfExists('body_parts');
        Schema::dropIfExists('specialties');
    }
};
