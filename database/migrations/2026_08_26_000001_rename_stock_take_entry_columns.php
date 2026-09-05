<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The sheet stopped being a paper record. `opening` was the previous
     * sheet's closing figure carried forward by hand; it is now the live
     * inventory figure read at the moment of counting. `closing` was typed;
     * it is now the stock left after In and Out are applied.
     *
     * Renamed rather than left alone because both names now describe the
     * opposite of what the column holds. Old rows keep their figures — an
     * opening was still the stock at the start and a closing still the stock
     * at the end, so the rename reads correctly against history too.
     */
    public function up(): void
    {
        Schema::table('stock_take_entries', function (Blueprint $table) {
            $table->renameColumn('opening', 'current_stock');
            $table->renameColumn('closing', 'balance');
        });
    }

    public function down(): void
    {
        Schema::table('stock_take_entries', function (Blueprint $table) {
            $table->renameColumn('current_stock', 'opening');
            $table->renameColumn('balance', 'closing');
        });
    }
};
