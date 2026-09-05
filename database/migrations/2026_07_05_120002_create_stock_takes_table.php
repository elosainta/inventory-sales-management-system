<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One recorded stock-take sheet for a single section, on a given date,
        // by a given person. Purely a manual record — it never changes live
        // inventory.
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('section'); // pantry | kitchen
            $table->date('taken_on');
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['section', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
