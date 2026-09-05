<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approved R&D trial can become a real recipe. This is the link back.
 *
 * It does two jobs: the R&D page can show that a trial produced a dish and
 * link to it, and the same trial cannot be turned into a second recipe.
 *
 * nullOnDelete, like every other user-facing FK here — deleting a recipe must
 * not delete the record of what was spent developing it. Recipes soft-delete
 * anyway, so this only fires on a hard delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->foreignId('recipe_id')
                ->nullable()
                ->after('inventory_item_id')
                ->constrained('recipes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_id');
        });
    }
};
