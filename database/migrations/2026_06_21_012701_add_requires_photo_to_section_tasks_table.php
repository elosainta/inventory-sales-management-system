<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('section_tasks', function (Blueprint $table) {
            $table->boolean('requires_photo')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('section_tasks', function (Blueprint $table) {
            $table->dropColumn('requires_photo');
        });
    }
};
