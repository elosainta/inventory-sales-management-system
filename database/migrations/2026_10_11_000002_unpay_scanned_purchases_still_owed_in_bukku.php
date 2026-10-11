<?php

use App\Models\Purchase;
use Illuminate\Database\Migrations\Migration;

/**
 * Fifty-one purchases read "Paid" that are not paid.
 *
 * Until 2026-10-11 a sent invoice scan recorded its purchase as completed —
 * which the Purchase log now shows as "Paid" and the purchases PDF counts as
 * paid. Checked against Bukku on 2026-10-11 before writing this: every one of
 * these 51 bills still has its full balance owing, RM 10,716.87 between them,
 * and not one is paid. They go back to pending ("Not paid yet"), so uploading
 * the receipt is what marks them paid, as it now is for every new scan.
 *
 * The ids are listed rather than found by a rule, because the rule (scanned,
 * completed, no receipt) was only checked against Bukku for these. Each is
 * still guarded on that state, so one given a receipt or put back to pending
 * by hand before this runs is left alone, and a second run changes nothing.
 * A fresh install or the test suite has none of these ids in that state.
 */
return new class extends Migration
{
    private const IDS = [
        13, 15, 17, 18, 19, 20, 21, 22, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33,
        34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51,
        52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66,
    ];

    public function up(): void
    {
        $count = $this->move('completed', 'pending');

        echo "  {$count} scanned purchases set to Not paid yet (still owed in Bukku).\n";
    }

    public function down(): void
    {
        $this->move('pending', 'completed');
    }

    /** Through the model, one at a time, so each change lands in the audit log. */
    private function move(string $from, string $to): int
    {
        $purchases = Purchase::whereIn('id', self::IDS)
            ->whereHas('invoiceScan')
            ->where('status', $from)
            ->whereNull('receipt_path')
            ->get();

        $purchases->each->update(['status' => $to]);

        return $purchases->count();
    }
};
