<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice scan (BETA) — one row per invoice photo uploaded on the website.
 *
 * The row is the bridge between the two halves of the trip: the upload and the
 * read on the way in, the Bukku bill reference on the way back. It exists so a
 * scan survives a page reload — the extraction costs a real API call, and
 * without somewhere to put the result the reviewer would pay for it again
 * every time they refreshed the review screen.
 *
 * `extracted` keeps what the model read, verbatim, even after the reviewer
 * corrects it. That is deliberate: Bukku holds the truth about the bill, and
 * this column holds the truth about what the scan got right, which is the only
 * way to tell whether the feature is good enough to stop being a beta.
 *
 * user_id is nullOnDelete like every other kitchen record — deleting a leaver
 * must not delete the accounting history they scanned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('file_path');
            $table->string('original_filename');

            // scanned  — read, waiting for a human to check it
            // posted   — a bill exists in Bukku
            // failed   — the read itself failed; scan_error says why
            $table->string('status')->default('scanned')->index();
            $table->json('extracted')->nullable();
            $table->text('scan_error')->nullable();

            // Denormalised off `extracted` so the index list and search do not
            // have to open the JSON on every row.
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();

            $table->unsignedBigInteger('bukku_transaction_id')->nullable();
            $table->string('bukku_number')->nullable();
            $table->string('bukku_short_link')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_scans');
    }
};
