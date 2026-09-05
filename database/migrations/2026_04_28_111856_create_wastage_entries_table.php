<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wastage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('staff_id')->nullable();
            $table->decimal('quantity_wasted', 12, 2)->default(0);
            $table->decimal('cost_lost', 12, 2)->default(0);
            $table->string('reason');
            $table->timestamp('recorded_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastage_entries');
    }
};