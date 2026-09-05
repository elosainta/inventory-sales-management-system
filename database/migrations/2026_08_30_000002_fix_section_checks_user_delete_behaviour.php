<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A prep check is a kitchen record, not a personal one — stop deleting it
 * with the person.
 *
 * `section_checks.user_id` has been `cascadeOnDelete` since the table was
 * built, which was right while a section belonged to one junior chef and a
 * check was that chef's own submission. 1.10.49 changed what the row means: a
 * check is now looked up by (task, date), anyone may tick any task, and the
 * name on it is simply whoever last stood in front of it. Under those rules
 * deleting a departed chef silently erases whatever they ticked — including
 * today's — and the Prep Overview then shows work that was done as not done.
 *
 * Both prep views already render `user?->name` with a "a former team member"
 * fallback, so the interface was written for this and could never reach it.
 * This is the rest of that change: nullable + nullOnDelete, matching every
 * other kitchen record (see the deletion rule in CLAUDE.md).
 *
 * The unique index goes the same way. It still names `user_id`, which stopped
 * describing the rule at 1.10.49 — the invariant is one row per task per day,
 * and only the controller was enforcing it.
 *
 * ORDER MATTERS, and only on MariaDB. InnoDB satisfies the `section_task_id`
 * foreign key using the *leftmost* column of that composite unique index, so
 * dropping it first fails with errno 1553 — "needed in a foreign key
 * constraint". SQLite has no such rule and passes either way, which is exactly
 * how the first cut of this migration got written. The new index is therefore
 * created before the old one is dropped: once (section_task_id, checked_date)
 * exists the key has somewhere else to live.
 *
 * Every step is guarded and the migration is safe to re-run. DDL auto-commits
 * on MariaDB, so a failure part-way through leaves the earlier steps applied
 * while the migration itself is still recorded as pending — which is precisely
 * what happened here on the first attempt.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'section_checks_section_task_id_user_id_checked_date_unique';

    private const NEW_INDEX = 'section_checks_section_task_id_checked_date_unique';

    public function up(): void
    {
        if (! $this->userIdIsNullable()) {
            Schema::table('section_checks', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('section_checks', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
            });

            Schema::table('section_checks', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if ($this->hasIndex(self::NEW_INDEX)) {
            return;
        }

        // Rows predating 1.10.49 can legitimately violate the new rule — back
        // then each chef ticked their own copy. Report and leave the old index
        // alone rather than failing a deploy over historical data.
        $duplicates = DB::table('section_checks')
            ->select('section_task_id', 'checked_date')
            ->groupBy('section_task_id', 'checked_date')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            echo "  section_checks: {$duplicates} (task, date) pair(s) hold more than one row — "
                . "keeping the old index. Clean those up, then re-run this migration.\n";

            return;
        }

        Schema::table('section_checks', function (Blueprint $table) {
            $table->unique(['section_task_id', 'checked_date'], self::NEW_INDEX);
        });

        if ($this->hasIndex(self::OLD_INDEX)) {
            Schema::table('section_checks', function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }

        echo "  section_checks: unique index now (section_task_id, checked_date).\n";
    }

    /**
     * Reversible only while no check has been orphaned. Once a user has been
     * deleted their rows carry `user_id = NULL`, which cannot go back into a
     * non-nullable column — delete those rows first if this must be undone,
     * accepting that the prep history goes with them.
     */
    public function down(): void
    {
        if (! $this->hasIndex(self::OLD_INDEX)) {
            Schema::table('section_checks', function (Blueprint $table) {
                $table->unique(['section_task_id', 'user_id', 'checked_date'], self::OLD_INDEX);
            });
        }

        if ($this->hasIndex(self::NEW_INDEX)) {
            Schema::table('section_checks', function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        if ($this->userIdIsNullable()) {
            Schema::table('section_checks', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('section_checks', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
            });

            Schema::table('section_checks', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function userIdIsNullable(): bool
    {
        foreach (Schema::getColumns('section_checks') as $column) {
            if ($column['name'] === 'user_id') {
                return (bool) $column['nullable'];
            }
        }

        return false;
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('section_checks') as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
