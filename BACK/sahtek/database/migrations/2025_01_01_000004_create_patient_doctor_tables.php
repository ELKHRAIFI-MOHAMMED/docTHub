<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32)->nullable();
            $table->text('date_of_birth')->nullable(); // encrypted cast
            $table->enum('sex', ['female', 'male', 'other', 'undisclosed'])->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language_code', 10)->default('en');
            $table->boolean('share_location')->default(false);
            $table->boolean('email_notifications')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('consent_type', ['terms', 'privacy', 'health_data']);
            $table->string('version', 20);
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'consent_type']);
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 20)->default('Dr.');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('slug', 160)->unique();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->text('qualifications')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->char('fee_currency', 3)->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'verification_status']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('doctor_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unique(['doctor_id', 'specialty_id']);
            $table->index('specialty_id');
        });

        Schema::create('doctor_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->unique(['doctor_id', 'language_id']);
        });

        Schema::create('doctor_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('label', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->foreignId('country_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->index(['country_id', 'city_id']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('doctor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['license', 'diploma', 'identity', 'other']);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('doctor_documents');
        Schema::dropIfExists('doctor_locations');
        Schema::dropIfExists('doctor_languages');
        Schema::dropIfExists('doctor_specialties');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patient_preferences');
        Schema::dropIfExists('patients');
    }
};
