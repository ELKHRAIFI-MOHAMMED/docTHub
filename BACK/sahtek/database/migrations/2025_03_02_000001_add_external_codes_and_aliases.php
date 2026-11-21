<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structuring layer of the knowledge pipeline (knowledge:import).
 *
 * - external_system / external_code / external_uri: identity of the item in its source (icd11 + code + URI, which the
 *   ICD-11 terms require to keep; medlineplus + topic id + page URL).
 * - source_document_id + version on symptoms (diseases already had source_document_id).
 * - needs_translation: locales for which the source provides no text (left empty, never machine-translated).
 * - diseases.severity becomes nullable with no default: an imported condition must not receive an invented severity.
 * - knowledge_aliases: synonyms pointing to ONE canonical item (deduplicated per locale).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['symptoms', 'diseases'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('external_system', 20)->nullable()->after('code');
                $t->string('external_code', 40)->nullable()->after('external_system');
                $t->string('external_uri', 255)->nullable()->after('external_code');
                $t->json('needs_translation')->nullable()->after('description');
                $t->unique(['external_system', 'external_code'], "{$table}_external_unique");
                if ($table === 'symptoms') {
                    $t->foreignId('source_document_id')->nullable()->after('status')->constrained()->nullOnDelete();
                }
                $t->unsignedInteger('version')->default(1)->after('status');
            });
        }

        DB::statement("ALTER TABLE diseases MODIFY severity ENUM('low','moderate','high') NULL DEFAULT NULL");

        Schema::create('knowledge_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('aliasable_type', 40);
            $table->unsignedBigInteger('aliasable_id');
            $table->string('locale', 10);
            $table->string('alias', 255);
            $table->string('normalized', 255);
            $table->foreignId('source_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['aliasable_type', 'locale', 'normalized'], 'knowledge_aliases_unique');
            $table->index(['aliasable_type', 'aliasable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_aliases');
        DB::table('diseases')->whereNull('severity')->update(['severity' => 'moderate']);
        DB::statement("ALTER TABLE diseases MODIFY severity ENUM('low','moderate','high') NOT NULL DEFAULT 'moderate'");

        foreach (['symptoms', 'diseases'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique("{$table}_external_unique");
                if ($table === 'symptoms') {
                    $t->dropConstrainedForeignId('source_document_id');
                }
                $t->dropColumn(['external_system', 'external_code', 'external_uri', 'needs_translation', 'version']);
            });
        }
    }
};
