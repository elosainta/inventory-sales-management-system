<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // An open order is food that isn't on the menu — the person keys the
        // dish in by hand, so there is no recipe to point at and no ingredient
        // deduction. Every ordinary sale still requires recipe_id; the
        // either/or is enforced in StoreSaleRequest.
        Schema::table('sales', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('recipe_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->unsignedBigInteger('recipe_id')->nullable()->change();
            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('item_name'));
    }
};
