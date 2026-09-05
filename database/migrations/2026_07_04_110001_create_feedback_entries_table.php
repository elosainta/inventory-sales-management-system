<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feedback_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating_cleanliness');
            $table->unsignedTinyInteger('rating_safety');
            $table->unsignedTinyInteger('rating_organisation');
            $table->unsignedTinyInteger('rating_teamwork');
            $table->unsignedTinyInteger('rating_communication');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_entries');
    }
};
