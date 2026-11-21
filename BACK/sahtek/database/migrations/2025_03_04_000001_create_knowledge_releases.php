<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned exports of the approved knowledge (knowledge:export) and the knowledge version used by each assessment.
 * A release is immutable: one row per version, one JSON file per version, never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();          // "1.0", "1.1", "2.0"…
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->char('content_hash', 64)->index();         // SHA-256 of the canonical approved content
            $table->string('questionnaire_version', 20)->nullable();
            $table->string('ruleset_version', 20)->nullable();
            $table->json('counts');
            $table->string('file_path', 255);
            $table->char('file_sha256', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_via', 100)->nullable();   // e.g. "artisan knowledge:export"
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['major', 'minor']);
        });

        Schema::table('symptom_assessments', function (Blueprint $table) {
            // Release in force when the assessment was created / evaluated; "unreleased" = approved content changed
            // since the last export; "unreviewed" = unreviewed content allowed (development).
            $table->string('knowledge_version', 20)->nullable()->after('ruleset_version')->index();
        });
    }

    public function down(): void
    {
        Schema::table('symptom_assessments', function (Blueprint $table) {
            $table->dropIndex(['knowledge_version']);
            $table->dropColumn('knowledge_version');
        });
        Schema::dropIfExists('knowledge_releases');
    }
};
