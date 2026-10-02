<?php

use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off, production data: three purchases a sen out, because LogPurchase
 * did money in float (fixed in 1.32.2, Money::lineAmount).
 *
 *   purchase 10 (hand-keyed)  total 241.05, its lines add to 241.06
 *   purchase 20 (scan #20)    line 37: 3.05 x 14.50 stored 44.22, is 44.23;
 *                             total 98.60 -> 98.61 (Bukku BL-00048 says 98.61)
 *   purchase 27 (scan #38)    total 252.85, its lines add to 252.86 (= Bukku)
 *
 * The fix is "total = the sum of the stored lines", not a recompute from
 * quantity x price: purchase 19's quantity is stored as 10.98 but the line
 * was priced on 10.978, and its stored line (236.03) is the right one.
 *
 * Keyed on id AND the wrong figure; anywhere else, or a second run, changes
 * nothing. Eloquent, so LogsActivity records it. Stock and unit_cost are not
 * touched - a sen on the total moves no shelf.
 *
 * To undo: set the three totals and line 37 back to the figures above.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixed = 0;

        DB::transaction(function () use (&$fixed) {
            $line = PurchaseLine::find(37);

            if ($line && (int) $line->purchase_id === 20
                && number_format((float) $line->line_total, 2, '.', '') === '44.22') {
                $line->update(['line_total' => '44.23']);
            }

            foreach ([10 => '241.05', 20 => '98.60', 27 => '252.85'] as $id => $wrong) {
                $purchase = Purchase::with('lines')->find($id);

                if (! $purchase || number_format((float) $purchase->total_amount, 2, '.', '') !== $wrong) {
                    continue;
                }

                $sum = '0.00';
                foreach ($purchase->lines as $l) {
                    $sum = bcadd($sum, number_format((float) $l->line_total, 2, '.', ''), 2);
                }

                $purchase->update(['total_amount' => $sum]);
                $fixed++;
            }
        });

        echo "  purchase totals: {$fixed} corrected to the sum of their lines.\n";
    }

    public function down(): void
    {
        // See the docblock for the figures to put back.
    }
};
