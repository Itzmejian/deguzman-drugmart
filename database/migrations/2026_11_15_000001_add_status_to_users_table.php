<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff account requests: ang staff ang nagre-request ng account (pangalan + email lang),
 * may access code na ipinapadala sa email (iyon ang password), at si Admin ang nag-a-approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role')->index(); // active | pending
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('approved_at')->nullable()->after('must_change_password');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'must_change_password', 'approved_at']);
        });
    }
};
