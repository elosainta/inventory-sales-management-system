<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Optional: how many individual pieces/slices are in one purchased
            // pack (e.g. 30 eggs per tray, 10 slices per cheese pack). Lets the
            // head chef price an item per piece by dividing the pack price.
            $table->decimal('pack_size', 12, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('pack_size');
        });
    }
};
