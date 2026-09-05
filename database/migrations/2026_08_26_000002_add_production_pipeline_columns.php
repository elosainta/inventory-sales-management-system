<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Inventory -> Production -> Sales, with the recipe as the formula.
     *
     * A recipe already says what one serving is made of. What it could not say
     * is what one serving *becomes*, so production had no finished good to put
     * anything into and a sale had no choice but to deduct raw ingredients.
     *
     * `output_inventory_item_id` closes that: it names the stock a batch of this
     * recipe creates. It is **nullable on purpose** — that is what makes the
     * pipeline opt-in. A recipe with no output item behaves exactly as it always
     * has (the sale deducts its raw ingredients), so nothing changes on the day
     * this ships and recipes can be wired up one at a time.
     *
     * `recipe_id` + `quantity_produced` move the batch from "a list of items I
     * made" to "N servings of this dish", which is the whole point: the chef
     * enters how many they cooked and the formula works out the rest.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('output_inventory_item_id')->nullable()->after('name')
                ->constrained('inventory_items')->nullOnDelete();
        });

        Schema::table('production_batches', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->after('user_id')
                ->constrained('recipes')->nullOnDelete();
            $table->decimal('quantity_produced', 10, 2)->nullable()->after('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropColumn(['recipe_id', 'quantity_produced']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropForeign(['output_inventory_item_id']);
            $table->dropColumn('output_inventory_item_id');
        });
    }
};
