<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R&D is done with the kitchen's own stock, so an entry names an inventory
 * item rather than being typed free-hand.
 *
 * Nullable and nullOnDelete for the same reason every other kitchen record is:
 * deleting an ingredient must not delete the history of what was spent on it.
 * `item` stays as the snapshot of the name at the time, so a renamed or
 * deleted ingredient still reads correctly on an old row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->after('id')
                ->constrained('inventory_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
