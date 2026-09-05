<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_tallies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('counted_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('counted_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_tallies');
    }
};
