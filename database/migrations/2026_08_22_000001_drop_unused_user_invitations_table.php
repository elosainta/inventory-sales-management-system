<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The invitation-token flow was never wired up: nothing in the codebase ever
     * wrote a `user_invitations` row, so the two unauthenticated
     * /invite/accept/{token} routes could never match one. Production holds 0
     * rows. Accounts are created directly by InvitationController::store(),
     * which is staying.
     */
    public function up(): void
    {
        Schema::dropIfExists('user_invitations');
    }

    /**
     * Deliberately not restored. The table shape is in git —
     * database/migrations/2026_05_11_072216_create_user_invitations_table.php,
     * before this commit. There is no data to bring back.
     */
    public function down(): void
    {
        //
    }
};
