<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Bukku supplier (contact) a kitchen supplier is.
 *
 * A sent invoice scan used to find its kitchen supplier by exact name, and
 * Bukku's names ("RIVERSIDE FOOD INDUSTRY SDN BHD") are not the
 * kitchen's ("RIVERSIDE") — so every scanned supplier was created a
 * second time on the Suppliers page. With the link, a scan files under the
 * linked supplier whatever either side calls it.
 *
 * Same shape as inventory_items.bukku_product_id: no foreign key (the id is
 * another company's, over HTTP), nullable, unique — one Bukku supplier is one
 * kitchen supplier, or purchases would split between two again.
 *
 * To undo: drop the column. Scans fall back to matching by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('suppliers', 'bukku_contact_id')) {
            echo "  suppliers.bukku_contact_id: already present — skipped.\n";

            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('bukku_contact_id')->nullable()->after('address')->unique();
        });

        echo "  suppliers.bukku_contact_id: added.\n";
    }

    public function down(): void
    {
        if (! Schema::hasColumn('suppliers', 'bukku_contact_id')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['bukku_contact_id']);
            $table->dropColumn('bukku_contact_id');
        });
    }
};
