<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('appliance_name');
            $table->date('check_date');
            $table->string('photo_path');
            $table->timestamps();

            $table->unique(['user_id', 'section_id', 'appliance_name', 'check_date'], 'appl_checks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliance_checks');
    }
};
