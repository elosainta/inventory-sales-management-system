<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the Owner-level training account (demo@example.test, id 14).
     *
     * It could not be removed from the Users page by anyone: the Owner does not
     * see demo accounts (the list filters `is_demo` for non-Admins), and an
     * Admin who does see it is stopped by the privilege-escalation guard in
     * UserController::destroy, because the account carries the `owner` role. A
     * migration is the only route, and it leaves the reason in git.
     *
     * Its 8 login_histories rows cascade away with it. Its 5 audits rows stay —
     * they are nullOnDelete and carry a snapshotted user_name, so the history of
     * what that account did is preserved and still attributed.
     *
     * The demo sandbox database is untouched: it is a stale clone, `demo:reset`
     * rebuilds it from live, and authentication has always resolved from the
     * live connection — so with this row gone the account cannot sign in.
     *
     * demochef@example.test and demojunior@example.test are deliberately left in
     * place; only the Owner-level account was asked for.
     */
    public function up(): void
    {
        $deleted = DB::table('users')
            ->where('email', 'demo@example.test')
            ->where('is_demo', true)
            ->delete();

        echo "  deleted {$deleted} demo owner account\n";
    }

    /**
     * Deliberately not restored. It was a seeded training account on the
     * published demo password, not real data; the definition is in git —
     * database/seeders/DemoUserSeeder.php, before this commit.
     */
    public function down(): void
    {
        //
    }
};
