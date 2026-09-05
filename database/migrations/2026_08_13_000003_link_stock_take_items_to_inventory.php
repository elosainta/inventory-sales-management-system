<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A recorded stock-take now takes its Out column off live stock, so each
     * catalog item needs to say which inventory item it is. Nullable: the
     * catalog carries names the 185-item inventory has never heard of, and an
     * unlinked item simply moves no stock.
     */
    public function up(): void
    {
        Schema::table('stock_take_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('section')
                ->constrained('inventory_items')->nullOnDelete();
        });

        // Adopt the names that already match, so the link starts mostly filled
        // in rather than as 118 blanks nobody will work through.
        // Same reason as the audits backfill: UPDATE..JOIN is MySQL-only. LOWER
        // and TRIM exist on both drivers, so the matching rule is unchanged.
        foreach (DB::table('inventory_items')->select('id', 'name')->get() as $item) {
            DB::table('stock_take_items')
                ->whereNull('inventory_item_id')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($item->name))])
                ->update(['inventory_item_id' => $item->id]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_take_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn('inventory_item_id');
        });
    }
};
