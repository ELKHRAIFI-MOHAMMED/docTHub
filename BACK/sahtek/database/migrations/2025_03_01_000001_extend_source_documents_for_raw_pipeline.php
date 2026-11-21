<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raw layer of the knowledge pipeline (knowledge:fetch).
 *
 * - knowledge_sources gets a stable code (icd11, medlineplus) and a licence status. A source can only be fetched
 *   once a human has confirmed its licence (license_status = confirmed).
 * - source_documents now keeps ONE ROW PER FINGERPRINT: the raw response is stored untouched on disk
 *   (raw_path = raw/{source}/{sha256}.{ext}); when the content of a known URL changes, a new row is created and the
 *   previous one becomes "superseded" instead of being overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique()->after('id');
            $table->enum('license_status', ['unverified', 'confirmed', 'unclear'])->default('unverified')->after('license_url');
            $table->date('license_checked_at')->nullable()->after('license_status');
            $table->string('attribution', 500)->nullable()->after('allowed_use');
        });

        // The new identity index must exist first: MariaDB/MySQL uses the old unique index for the knowledge_source_id FK.
        Schema::table('source_documents', function (Blueprint $table) {
            $table->index(['knowledge_source_id', 'url', 'language'], 'source_documents_identity_index');
        });
        Schema::table('source_documents', function (Blueprint $table) {
            $table->dropUnique('source_documents_source_url_unique');
        });

        DB::statement("ALTER TABLE source_documents MODIFY status ENUM('pending','active','changed','superseded','unavailable') NOT NULL DEFAULT 'pending'");

        Schema::table('source_documents', function (Blueprint $table) {
            $table->foreignId('previous_document_id')->nullable()->after('knowledge_source_id')->constrained('source_documents')->nullOnDelete();
            $table->string('raw_path', 255)->nullable()->after('raw_content');
            $table->string('raw_format', 10)->nullable()->after('raw_path');
            $table->unsignedInteger('raw_bytes')->nullable()->after('raw_format');
            $table->unsignedSmallInteger('http_status')->nullable()->after('raw_bytes');
            $table->json('metadata')->nullable()->after('http_status');
            $table->unique(['url', 'language', 'content_hash'], 'source_documents_url_lang_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('source_documents', function (Blueprint $table) {
            $table->dropUnique('source_documents_url_lang_hash_unique');
            $table->dropConstrainedForeignId('previous_document_id');
            $table->dropColumn(['raw_path', 'raw_format', 'raw_bytes', 'http_status', 'metadata']);
        });

        DB::table('source_documents')->where('status', 'superseded')->delete();
        DB::statement("ALTER TABLE source_documents MODIFY status ENUM('pending','active','changed','unavailable') NOT NULL DEFAULT 'pending'");

        Schema::table('source_documents', function (Blueprint $table) {
            $table->unique(['knowledge_source_id', 'url'], 'source_documents_source_url_unique');
        });
        Schema::table('source_documents', fn (Blueprint $table) => $table->dropIndex('source_documents_identity_index'));

        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'license_status', 'license_checked_at', 'attribution']);
        });
    }
};
