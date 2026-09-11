<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a supplier calls a thing, and what this kitchen calls it.
 *
 * A supplier writes "AYAM PEHA 1KG" on the invoice; the shelf says "Chicken
 * Thigh". The reviewer matches them once on the invoice-scan screen and this
 * table remembers, so the next invoice carrying that wording arrives already
 * matched. It is a dictionary the kitchen teaches by using the system.
 *
 * `normalised` is the lookup key — lowercased, punctuation stripped, spaces
 * collapsed — so "AYAM PEHA 1KG", "Ayam Peha 1kg" and "ayam  peha  1kg" are
 * one entry rather than three. `supplier_text` keeps the wording exactly as it
 * arrived, because that is what a person recognises when reviewing the list.
 *
 * cascadeOnDelete, deliberately against the usual nullOnDelete habit: an alias
 * whose inventory item is gone maps to nothing and would only ever produce a
 * silently wrong match. The deletion rule protects kitchen *records*; this is
 * a lookup rule, not a record of anything that happened.
 *
 * To undo: drop the table. Nothing else reads it, and losing it costs only the
 * learned matches — every invoice can still be matched by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_item_aliases')) {
            echo "  invoice_item_aliases: already present — skipped.\n";

            return;
        }

        Schema::create('invoice_item_aliases', function (Blueprint $table) {
            $table->id();

            // 191, not the 255 default: this column carries a unique index, and
            // 191 is the length that is safe on every utf8mb4 configuration
            // this app might meet. No supplier writes a 191-character item
            // name, and the untruncated wording lives in supplier_text anyway.
            $table->string('normalised', 191)->unique();
            $table->string('supplier_text');

            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });

        echo "  invoice_item_aliases: created.\n";
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_aliases');
    }
};
