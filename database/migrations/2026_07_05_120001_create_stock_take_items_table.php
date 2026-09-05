<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The editable catalog of things counted during a stock-take, split
        // into two physical sections. The same item may exist in both sections
        // (e.g. hua tiao chiew) — that is simply two rows.
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // pantry | kitchen
            $table->string('name');
            $table->string('default_unit')->nullable(); // kg, bottle, pkt, portion, can…
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['section', 'name']);
            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
