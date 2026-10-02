<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a scan is a supplier invoice or a delivery order (DO).
 *
 * Some suppliers hand over a delivery order with the goods and no invoice, and
 * the kitchen bills it exactly as it would an invoice — same review, same
 * Bukku bill, same stock movement. The column records which paper it was, so
 * the list, the review screen and the bill in Bukku can all say so.
 *
 * A plain string with a default rather than an enum: MariaDB and the SQLite
 * test suite disagree about enums, and every row that already exists was an
 * invoice, which the default says without a backfill.
 *
 * To undo: drop the column. Nothing else reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_scans', 'document_type')) {
            echo "  invoice_scans.document_type: already present — skipped.\n";

            return;
        }

        Schema::table('invoice_scans', function (Blueprint $table) {
            $table->string('document_type', 20)->default('invoice')->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_scans', 'document_type')) {
            Schema::table('invoice_scans', function (Blueprint $table) {
                $table->dropColumn('document_type');
            });
        }
    }
};
