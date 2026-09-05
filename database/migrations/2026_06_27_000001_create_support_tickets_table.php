<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            // Submitter — snapshot details so the ticket survives user deletion.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('role')->nullable();
            $table->text('description');
            // Optional attachment, stored on the private local disk.
            $table->string('media_path')->nullable();
            $table->string('media_filename')->nullable();
            $table->string('media_mime')->nullable();
            // Checklist status: open -> resolved.
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
