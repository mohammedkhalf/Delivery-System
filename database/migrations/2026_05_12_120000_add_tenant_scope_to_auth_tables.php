<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-storefront identity scoping — adds (tenant_id, sub_tenant_id) to
 * `users` and the auth aux tables so the same email/phone can register
 * independently against the host site and each storefront.
 *
 * Existing rows default to (0, 0) — the host scope — so host login flows
 * are unaffected. The host AuthProvider adapter and the HostScope global
 * scope (added in Phase 2) keep host-side code reading host-only rows
 * without further changes.
 *
 * Sentinel `0` (not NULL) is used because MySQL treats NULLs as distinct
 * in UNIQUE indexes — using NULL would silently allow duplicate
 * (email, NULL, NULL) rows on the host scope, defeating the constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ─────────────────────────────────────────────────────
        // users.email is NOT unique today (verified by schema dump), so
        // only phone & ref_code uniques need swapping.
        $this->dropIndexIfExists('users', 'users_phone_unique');
        $this->dropIndexIfExists('users', 'users_ref_code_unique');

        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);

            $t->unique(['email', 'tenant_id', 'sub_tenant_id'], 'users_email_scope_unique');
            $t->unique(['phone', 'tenant_id', 'sub_tenant_id'], 'users_phone_scope_unique');
            $t->unique(['ref_code', 'tenant_id', 'sub_tenant_id'], 'users_ref_code_scope_unique');
            $t->index(['tenant_id', 'sub_tenant_id'], 'users_scope_index');
        });

        // ── phone_verifications ──────────────────────────────────────
        // Has phone_unique today — swap for composite.
        $this->dropIndexIfExists('phone_verifications', 'phone_verifications_phone_unique');

        Schema::table('phone_verifications', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->unique(['phone', 'tenant_id', 'sub_tenant_id'], 'phone_verifications_phone_scope_unique');
            $t->index(['tenant_id', 'sub_tenant_id'], 'phone_verifications_scope_index');
        });

        // ── password_resets ──────────────────────────────────────────
        // No unique constraints today; just add scope columns + index.
        Schema::table('password_resets', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->index(['tenant_id', 'sub_tenant_id'], 'password_resets_scope_index');
        });

        // ── email_verifications ──────────────────────────────────────
        Schema::table('email_verifications', function (Blueprint $t) {
            $t->unsignedBigInteger('tenant_id')->default(0);
            $t->unsignedBigInteger('sub_tenant_id')->default(0);
            $t->index(['tenant_id', 'sub_tenant_id'], 'email_verifications_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique('users_email_scope_unique');
            $t->dropUnique('users_phone_scope_unique');
            $t->dropUnique('users_ref_code_scope_unique');
            $t->dropIndex('users_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
            $t->unique('phone', 'users_phone_unique');
            $t->unique('ref_code', 'users_ref_code_unique');
        });

        Schema::table('phone_verifications', function (Blueprint $t) {
            $t->dropUnique('phone_verifications_phone_scope_unique');
            $t->dropIndex('phone_verifications_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
            $t->unique('phone', 'phone_verifications_phone_unique');
        });

        Schema::table('password_resets', function (Blueprint $t) {
            $t->dropIndex('password_resets_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
        });

        Schema::table('email_verifications', function (Blueprint $t) {
            $t->dropIndex('email_verifications_scope_index');
            $t->dropColumn(['tenant_id', 'sub_tenant_id']);
        });
    }

    /**
     * Drop a named index only if it exists. Survives partial / inconsistent
     * states across dev databases.
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $index],
        );
        if (! empty($rows)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
