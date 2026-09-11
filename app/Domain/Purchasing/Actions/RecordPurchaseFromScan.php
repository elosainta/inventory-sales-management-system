<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\InvoiceScan;
use App\Models\Purchase;
use App\Models\Supplier;

/**
 * Turn a filed invoice into this kitchen's own purchase.
 *
 * Until this existed a scan sent a bill to Bukku and did nothing to the shelf,
 * so every delivery was keyed in twice — once for the accountant, once for the
 * kitchen. The dictionary is what made this possible: a line now knows which
 * inventory item it is, so it can move stock without anyone retyping it.
 *
 * **Only matched lines become a purchase.** A line the reviewer did not match
 * to an inventory item is not a mystery to be guessed at — it is a delivery
 * fee, or something the kitchen does not stock — and inventing a shelf entry
 * for it would put fictional stock in front of the Owner. Unmatched lines are
 * on the Bukku bill and nowhere else, which is correct.
 *
 * Runs once. `invoice_scans.purchase_id` is the guard, and pushing an
 * already-posted scan is refused upstream anyway — two purchases from one
 * invoice would double the stock on every shelf it touched.
 */
class RecordPurchaseFromScan
{
    public function __construct(private LogPurchase $logPurchase)
    {
    }

    /**
     * @param  array<string,mixed>  $data  the reviewed bill, already validated
     */
    public function execute(InvoiceScan $scan, array $data, ?string $supplierName): ?Purchase
    {
        if ($scan->purchase_id) {
            return null;
        }

        $lines = [];

        foreach ($data['lines'] ?? [] as $line) {
            if (blank($line['inventory_item_id'] ?? null)) {
                continue;
            }

            $lines[] = [
                'inventory_item_id' => (int) $line['inventory_item_id'],
                'quantity'          => (float) $line['quantity'],
                'unit_price'        => (float) $line['unit_price'],
            ];
        }

        if ($lines === []) {
            return null;
        }

        $purchase = $this->logPurchase->execute([
            'supplier_id'    => $this->supplier($supplierName)->id,
            'user_id'        => auth()->id(),
            'invoice_number' => $data['invoice_number'] ?? null,
            'status'         => 'completed',
            'purchase_date'  => $data['invoice_date'],
            'lines'          => $lines,
        ]);

        $scan->update(['purchase_id' => $purchase->id]);

        return $purchase;
    }

    /**
     * The kitchen's own supplier record for whoever this bill is from.
     *
     * Bukku's contacts and this system's suppliers are separate lists that
     * happen to hold the same companies — HILLSIDE AGROFARM is in both — so
     * the name is the only join available. Matched case-insensitively because
     * the two lists were typed by different people at different times.
     *
     * Created when it is genuinely new: `purchases.supplier_id` is NOT NULL,
     * so the alternative to creating one is refusing to record the purchase at
     * all, which would be a worse answer to "we bought from someone new".
     */
    private function supplier(?string $name): Supplier
    {
        $name = trim((string) $name) ?: 'Unknown supplier';

        // contact, email and address are NOT NULL on this table, and an invoice
        // carries none of them. Empty is the honest value — the Owner fills
        // them in on the Suppliers page — and it beats refusing to record the
        // delivery because nobody typed a phone number.
        return Supplier::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Supplier::create([
                'name'    => $name,
                'contact' => '',
                'email'   => '',
                'address' => '',
            ]);
    }
}
