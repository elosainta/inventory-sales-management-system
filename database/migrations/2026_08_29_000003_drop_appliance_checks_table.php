<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `appliance_checks` along with the feature that wrote to it.
 *
 * Dropping a table is normally the wrong trade — `user_permissions` and
 * `compliance_reports` are both kept empty or retired precisely because a
 * migration that destroys history is worth more scrutiny than one that leaves
 * it. This one is different only because there is provably no history: the
 * table has never held a single row. For four months the appliance list was
 * keyed to section names that did not exist, so every lookup missed silently;
 * once that was fixed in 1.10.50 the list turned out to duplicate prep tasks
 * the kitchen already had, and it was removed a day later having still
 * recorded nothing.
 *
 * The count is checked rather than assumed. If a row has appeared since this
 * was written, the migration stops instead of destroying it — deal with the
 * rows, then decide again.
 *
 * down() recreates the schema exactly as 2026_05_11_180020 built it. The rows
 * are not restored, because there were none.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appliance_checks')) {
            echo "  appliance_checks already gone — nothing to drop\n";

            return;
        }

        $rows = DB::table('appliance_checks')->count();

        if ($rows > 0) {
            throw new RuntimeException(
                "appliance_checks holds {$rows} row(s). This migration was written on the "
                . 'basis that it has never held any, and it will not destroy real records. '
                . 'Export them and decide deliberately before dropping the table.'
            );
        }

        Schema::drop('appliance_checks');

        echo "  dropped appliance_checks (0 rows)\n";
    }

    public function down(): void
    {
        if (Schema::hasTable('appliance_checks')) {
            return;
        }

        Schema::create('appliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('appliance_name');
            $table->date('check_date');
            $table->string('photo_path');
            $table->timestamps();

            $table->unique(['user_id', 'section_id', 'appliance_name', 'check_date'], 'appl_checks_unique');
        });
    }
};
