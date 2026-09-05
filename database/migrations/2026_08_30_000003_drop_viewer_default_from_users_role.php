<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `users.role` still defaults to 'viewer' — a role that no longer exists.
 *
 * Every path that creates a user supplies a role (the invitation controller,
 * both seeders, the factory), so the default has been dead for a long time.
 * Dead is not harmless here: 'viewer' is not a role any gate knows, so
 * isOwner/isManager/isAdmin all read false — and `view-checklist` is defined
 * as `! isAdmin()`, which means a row inserted without a role would come out
 * holding the prep checklist, photo uploads included. A silent half-privileged
 * account is a worse outcome than a failed insert.
 *
 * So the default goes away rather than changing to a real role: an insert that
 * forgets the role should stop, not guess. Validation (`UpdateUserRequest`,
 * `StoreInvitationRequest`) remains where a role is actually chosen.
 *
 * Existing rows are untouched — none carry 'viewer' on production, and the
 * count is printed so the deploy output proves it rather than assuming it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stragglers = DB::table('users')->where('role', 'viewer')->count();

        echo $stragglers > 0
            ? "  users.role: {$stragglers} row(s) still hold 'viewer' — set them from the Users page.\n"
            : "  users.role: no 'viewer' rows.\n";

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(null)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer')->change();
        });
    }
};
