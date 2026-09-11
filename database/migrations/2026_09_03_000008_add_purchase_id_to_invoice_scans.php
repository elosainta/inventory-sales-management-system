<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The purchase a scanned invoice created in this system.
 *
 * Until now a scan filed a bill in Bukku and did nothing to the kitchen's own
 * stock, so the same delivery was keyed in twice — once for the accountant,
 * once for the shelf. This column is the link between the two, and it is what
 * stops one invoice becoming two purchases: a scan that already has one never
 * makes another.
 *
 * nullOnDelete, following the deletion rule: a purchase is a kitchen record
 * and outlives the scan it came from, and a scan whose purchase was deleted
 * should simply lose the link rather than disappear.
 *
 * To undo: drop the column. The purchases it points at are ordinary purchases
 * and stay exactly as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_scans', 'purchase_id')) {
            echo "  invoice_scans.purchase_id: already present — skipped.\n";

            return;
        }

        Schema::table('invoice_scans', function (Blueprint $table) {
            $table->foreignId('purchase_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });

        echo "  invoice_scans.purchase_id: added.\n";
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoice_scans', 'purchase_id')) {
            return;
        }

        Schema::table('invoice_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
        });
    }
};
