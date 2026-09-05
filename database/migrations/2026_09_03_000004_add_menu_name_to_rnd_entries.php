<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the trial was FOR — the menu item being developed.
 *
 * The entry already records the ingredient it used; this is the other half,
 * and without it a list of R&D reads as a list of ingredients with no way to
 * tell which experiment each one belonged to.
 *
 * Nullable in the table, required by StoreRndEntryRequest: the column has to
 * tolerate rows written before it existed, while nothing new gets in without
 * it. (There are none on production today — the feature is hours old — but a
 * NOT NULL that depends on that staying true is a deploy waiting to fail.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->string('menu_name')->nullable()->after('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropColumn('menu_name');
        });
    }
};
