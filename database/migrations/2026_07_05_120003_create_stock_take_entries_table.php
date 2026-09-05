<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One line of the sheet, mirroring the paper columns:
        // ITEM | Prep date | Closing stock (opening) | In | Out | Closing stock.
        // item_name is snapshotted so the record stays stable even if the
        // catalog item is later renamed or removed.
        Schema::create('stock_take_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->foreignId('stock_take_item_id')->nullable()->constrained('stock_take_items')->nullOnDelete();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->date('prep_date')->nullable();
            $table->decimal('opening', 10, 2)->nullable();
            $table->decimal('qty_in', 10, 2)->nullable();
            $table->decimal('qty_out', 10, 2)->nullable();
            $table->decimal('closing', 10, 2)->nullable();

            $table->index('stock_take_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_entries');
    }
};
