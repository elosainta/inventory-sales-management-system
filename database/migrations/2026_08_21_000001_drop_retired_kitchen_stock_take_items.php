<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Kitchen stock-take section was retired in 1.10.13 (d3da335): it was
     * never used, and not one sheet was ever recorded against it while Pantry
     * holds both counts on record. Its 64 catalog rows were deliberately left
     * behind at the time, which made them unreachable — no screen lists them,
     * so nobody could delete them by hand either. The Owner asked for them.
     *
     * Safe to delete: stock_take_entries.stock_take_item_id is the only
     * inbound foreign key, it is nullOnDelete, and no entry points at a
     * kitchen row in any case. inventory_items is untouched — that key points
     * the other way.
     */
    public function up(): void
    {
        $deleted = DB::table('stock_take_items')->where('section', 'kitchen')->delete();

        echo "  dropped {$deleted} retired kitchen catalog rows\n";
    }

    /**
     * Deliberately not restored. The names are in git — the $kitchen array in
     * database/seeders/StockTakeItemSeeder.php, before d3da335 — and putting
     * them back would only re-create rows nothing can reach.
     */
    public function down(): void
    {
        //
    }
};
