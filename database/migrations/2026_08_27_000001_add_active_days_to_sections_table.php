<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // Null/empty means "every day" — the section's existing, always-on
            // behavior. A non-empty list is which days it's active.
            $table->json('active_days')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('active_days');
        });
    }
};
