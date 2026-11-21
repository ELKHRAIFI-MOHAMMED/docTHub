<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewer on conditions and rules (guidelines already had it). knowledge:validate requires a reviewer for every
 * approved item and for every active emergency rule. Filled by a person in the admin, never by code or seeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diseases', function (Blueprint $table) {
            $table->string('reviewed_by_name')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_name');
        });
        Schema::table('orientation_rules', function (Blueprint $table) {
            $table->string('reviewed_by_name')->nullable()->after('validation_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('diseases', fn (Blueprint $table) => $table->dropColumn(['reviewed_by_name', 'reviewed_at']));
        Schema::table('orientation_rules', fn (Blueprint $table) => $table->dropColumn(['reviewed_by_name', 'reviewed_at']));
    }
};
