<?php

use App\Models\InvoiceScan;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off, production data: four sent scans stored a total 1-2 sen under
 * their Bukku bill, because PushInvoiceToBukku truncated each line instead of
 * rounding it half up (fixed in 1.31.2). The Bukku figure, read off each bill
 * on 2026-09-19, is the true one. Keyed on id, bill number AND the wrong
 * total, so anywhere else — or a second run — changes nothing.
 *
 * Only the local figure changes. Bukku had already set each bill's payment
 * term to its own (correct) total, so nothing is owed there.
 *
 * To undo: set each total back to the "from" figure below.
 */
return new class extends Migration
{
    /** scan id => [bill number, stored (wrong), Bukku (right)] */
    private const FIX = [
        1  => ['BL-00040', '242.54', '242.56'],
        14 => ['BL-00047', '496.02', '496.03'],
        20 => ['BL-00048', '98.59', '98.61'],
        38 => ['BL-00056', '252.84', '252.86'],
    ];

    public function up(): void
    {
        $fixed = 0;

        foreach (self::FIX as $id => [$bill, $from, $to]) {
            $scan = InvoiceScan::find($id);

            if (! $scan || $scan->status !== InvoiceScan::STATUS_POSTED
                || ($scan->bukku_number !== $bill)
                || number_format((float) $scan->total_amount, 2, '.', '') !== $from) {
                continue;
            }

            $scan->update(['total_amount' => $to]);
            $fixed++;
        }

        echo "  invoice scan totals: {$fixed} corrected to the Bukku bill.\n";
    }

    public function down(): void
    {
        // See the docblock — the "from" figures are the way back.
    }
};
