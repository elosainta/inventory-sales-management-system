<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('float_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable();
            $table->decimal('amount_given', 12, 2)->default(0);
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->decimal('amount_returned', 12, 2)->default(0);
            $table->string('status')->default('open');
            $table->timestamp('issued_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('float_issuances');
    }
};