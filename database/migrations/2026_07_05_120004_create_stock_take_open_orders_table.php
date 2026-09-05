<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The "Open order" block at the bottom of the sheet — things already on
        // order at the time of the count. Quantity is free text ("4 cans",
        // "2 pkt") to match how it is written on paper.
        Schema::create('stock_take_open_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained('stock_takes')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('quantity')->nullable();
            $table->string('note')->nullable();

            $table->index('stock_take_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_open_orders');
    }
};
