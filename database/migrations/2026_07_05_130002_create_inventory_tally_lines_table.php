<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_tally_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_tally_id')->constrained('inventory_tallies')->cascadeOnDelete();
            // Null if the live inventory item is later deleted — the snapshot below preserves history.
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('item_name');
            $table->string('unit')->nullable();
            $table->string('category')->nullable();
            // System quantity at the moment of counting, so the variance stays faithful
            // even if live stock changes afterwards.
            $table->decimal('system_quantity', 12, 2)->nullable();
            $table->decimal('counted_quantity', 12, 2)->nullable();

            $table->index('inventory_tally_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_tally_lines');
    }
};
