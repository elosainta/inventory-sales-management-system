<?php

use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('misc_percent', 5, 2)->default(30.00)->after('selling_price');
        });

        // Backfill: every existing recipe now carries the default 30% overhead,
        // so recompute its stored plate_cost to include miscellaneous.
        Recipe::withTrashed()->get()->each->recalculatePlateCost();
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('misc_percent');
        });
    }
};
