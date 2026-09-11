<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Bukku product a shelf item is, where there is one.
 *
 * These have always been two unlinked catalogues — 254 items on the shelf, 77
 * products in Bukku — and that gap is why a scanned invoice line had to be
 * told its Bukku product by hand, and why removing that picker sent every line
 * to General Expense. With the link, a line matched to a shelf item can find
 * its own Bukku product, and the account follows from the product itself.
 *
 * **No foreign key**: the id belongs to another company's system, over HTTP.
 * There is nothing local to constrain against, and a product deleted in Bukku
 * must not be able to fail a write here. A stale id simply stops resolving,
 * which `Bukku::product()` already handles by falling back.
 *
 * Nullable and sparse on purpose. Bukku tracks a fraction of what the kitchen
 * stocks, so most items will never have one, and an unlinked item behaves
 * exactly as it does today.
 *
 * Unique: one Bukku product is one shelf item. Two items pointing at the same
 * product would post the same stock twice on the books. MariaDB and SQLite
 * both allow many NULLs under a unique index, which is what makes this safe
 * while the column is mostly empty.
 *
 * To undo: drop the column. Nothing else depends on it and every line falls
 * back to the configured expense account, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inventory_items', 'bukku_product_id')) {
            echo "  inventory_items.bukku_product_id: already present — skipped.\n";

            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('bukku_product_id')
                ->nullable()
                ->after('unit_cost')
                ->unique();
        });

        echo "  inventory_items.bukku_product_id: added.\n";
    }

    public function down(): void
    {
        if (! Schema::hasColumn('inventory_items', 'bukku_product_id')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['bukku_product_id']);
            $table->dropColumn('bukku_product_id');
        });
    }
};
