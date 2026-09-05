<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R&D does not carry an invoice number.
 *
 * It was there from when a trial was described as a purchase. It is not: the
 * trial is done with stock the kitchen already bought, and whatever invoice
 * that stock arrived on belongs to the Purchase, not to the experiment. The
 * Owner's call, 2026-09-03.
 *
 * Dropped rather than left as a column nothing writes — two of those on one
 * table is how a form ends up asking for something no page reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('selling_price');
        });
    }
};
