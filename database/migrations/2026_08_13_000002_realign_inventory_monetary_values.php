<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * One-off realignment of the values that had already drifted before
     * InventoryItem::booted() started deriving monetary_value on every save.
     */
    public function up(): void
    {
        DB::statement('UPDATE inventory_items SET monetary_value = ROUND(quantity_on_hand * unit_cost, 2)');
    }

    public function down(): void
    {
        // Nothing to undo — the corrected figures are the right ones.
    }
};
