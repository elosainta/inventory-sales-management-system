<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_entries', function (Blueprint $table) {
            $table->id();
            $table->string('item');
            $table->string('invoice_number')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->text('remark')->nullable();
            $table->date('purchased_on');

            $table->string('status')->default('pending');

            // Deleting an account never deletes the kitchen's records — the
            // audit trail keeps the name at write time, and both views render
            // user?->name with a fallback.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('purchased_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_entries');
    }
};
