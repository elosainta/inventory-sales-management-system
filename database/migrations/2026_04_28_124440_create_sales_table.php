<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes');
            $table->foreignId('staff_id')->nullable();
            $table->integer('qty_sold')->default(1);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamp('sale_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};