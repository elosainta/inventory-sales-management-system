<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff meals no longer record how many people ate.
 *
 * The headcount existed to produce a cost per head, and counting the team
 * every day was work for a figure nobody asked for (the Owner's call,
 * 2026-09-09). What the page reports now is what each meal cost and what
 * feeding the team costs per month — the per-person split goes with it.
 *
 * Guarded rather than assumed: the column is dropped only if it is still
 * there, so re-running against a database that has already lost it is a
 * no-op instead of a failed deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('staff_meals', 'pax')) {
            return;
        }

        Schema::table('staff_meals', function (Blueprint $table) {
            $table->dropColumn('pax');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_meals', 'pax')) {
            return;
        }

        // Back to the original shape. The headcounts themselves are gone —
        // dropping a column does not keep them — so existing rows come back
        // as 1, which is what the column defaulted to.
        Schema::table('staff_meals', function (Blueprint $table) {
            $table->unsignedInteger('pax')->default(1)->after('dish');
        });
    }
};
