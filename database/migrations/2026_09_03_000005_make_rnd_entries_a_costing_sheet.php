<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An R&D trial is a costing sheet, not a single line.
 *
 * A dish is developed out of a dozen ingredients at once. Keyed in one at a
 * time it read as a list of ingredients with the dish repeated beside each of
 * them, and no line ever added up to what the plate cost — which is the one
 * number the Owner is looking for. This gives the trial the same shape the
 * kitchen's own costing sheets already use, and the same shape Recipes uses:
 * ingredient lines, a subtotal, a miscellaneous percentage on top, a grand
 * total, and the selling price it is being aimed at.
 *
 * The header keeps what belongs to the trial (the dish, the invoice, the date,
 * the decision); the lines carry what it was made with. `item` and `unit` are
 * snapshotted on each line for the same reason every other history row here
 * snapshots them: a renamed or deleted ingredient must still read correctly.
 *
 * The four moved columns are dropped rather than left behind — two places
 * holding "what this trial used" is the drift this repo has already paid for
 * twice with hand-written nav lists. Existing rows are carried over first, so
 * nothing is lost (production holds zero R&D rows as at 2026-09-03; the
 * backfill is here for dev and demo databases, and because a migration that
 * assumes an empty table is a deploy waiting to fail).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_entry_id')->constrained('rnd_entries')->cascadeOnDelete();

            // Nullable + nullOnDelete like every other kitchen record: deleting
            // an ingredient must not delete the history of what was spent on it.
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            $table->string('item');          // the name at the time
            $table->string('unit')->nullable();

            // Four decimals on the quantity: a costing sheet routinely says
            // 0.005 kg of dark soy. Two would round that to nothing.
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 2);

            $table->timestamps();

            $table->index('rnd_entry_id');
        });

        // Every existing single-line entry becomes a one-line sheet.
        foreach (DB::table('rnd_entries')->get() as $entry) {
            DB::table('rnd_entry_lines')->insert([
                'rnd_entry_id'      => $entry->id,
                'inventory_item_id' => $entry->inventory_item_id,
                'item'              => $entry->item,
                'unit'              => null,
                'quantity'          => $entry->quantity,
                'unit_price'        => $entry->unit_price,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        Schema::table('rnd_entries', function (Blueprint $table) {
            // The costing sheet's own three figures. misc_percent defaults to
            // 30 to match the kitchen's sheets and the Recipes page; the
            // selling price is nullable because a trial does not always have
            // one yet.
            $table->unsignedInteger('serving_size')->default(1)->after('menu_name');
            $table->decimal('misc_percent', 5, 2)->default(30)->after('serving_size');
            $table->decimal('selling_price', 12, 2)->nullable()->after('misc_percent');
        });

        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn(['item', 'quantity', 'unit_price']);
        });
    }

    public function down(): void
    {
        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('item')->default('');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
        });

        // Only the first line survives the trip back — the old shape cannot
        // hold more than one. Recorded here rather than discovered later.
        foreach (DB::table('rnd_entry_lines')->orderBy('id')->get()->groupBy('rnd_entry_id') as $entryId => $lines) {
            $first = $lines->first();
            DB::table('rnd_entries')->where('id', $entryId)->update([
                'inventory_item_id' => $first->inventory_item_id,
                'item'              => $first->item,
                'quantity'          => $first->quantity,
                'unit_price'        => $first->unit_price,
            ]);
        }

        Schema::table('rnd_entries', function (Blueprint $table) {
            $table->dropColumn(['serving_size', 'misc_percent', 'selling_price']);
        });

        Schema::dropIfExists('rnd_entry_lines');
    }
};
