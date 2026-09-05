<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\InvoiceScan;
use App\Support\Bukku;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a reviewed scan into a purchase bill in Bukku.
 *
 * The values written here are the ones a human just confirmed on the review
 * screen — never the raw extraction. `InvoiceScan::$extracted` stays as the
 * record of what the model read, so the two can be compared later; this class
 * reads none of it.
 *
 * Refuses to post twice. A double-tapped Submit or a browser back button would
 * otherwise put the same invoice on the books twice, which is a genuinely
 * expensive mistake to unpick — Bukku bills are voided, not deleted.
 */
class PushInvoiceToBukku
{
    /**
     * Payment terms, from Bukku's own reference list on 2026-08-30. Every one
     * of these is the `in_days` type, so the due date is simply date + value.
     * Hardcoded because reading them costs a POST /v2/lists round trip on
     * every render and they have not changed since the company was set up.
     */
    public const TERMS = [
        1 => ['name' => 'COD',   'days' => 0],
        2 => ['name' => 'NET14', 'days' => 14],
        3 => ['name' => 'NET30', 'days' => 30],
        4 => ['name' => 'NET60', 'days' => 60],
    ];

    public const DEFAULT_TERM_ID = 3;

    public function execute(InvoiceScan $scan, array $data): InvoiceScan
    {
        if ($scan->isPosted()) {
            throw new RuntimeException('This invoice is already in Bukku as ' . $scan->bukku_number . '.');
        }

        $termId  = (int) ($data['term_id'] ?? self::DEFAULT_TERM_ID);
        $date    = Carbon::parse($data['invoice_date']);
        $dueDate = $date->copy()->addDays(self::TERMS[$termId]['days'] ?? 30);

        // A nullable field the reviewer left blank is absent from validated()
        // altogether, not present-and-empty — so this is read once, here, and
        // the payload below never indexes it directly.
        $invoiceNumber = ($data['invoice_number'] ?? null) ?: null;

        $lines = array_values($data['lines'] ?? []);

        if ($lines === []) {
            throw new RuntimeException('A bill needs at least one line.');
        }

        $formItems = [];
        $total     = '0.00';

        foreach ($lines as $index => $line) {
            $quantity  = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];

            // Money in decimal, not float — the same rule InventoryItem
            // follows. 2.65 * 11.70 is a cent out in binary floating point,
            // and this figure is what the supplier gets paid.
            $amount = bcmul((string) $quantity, (string) $unitPrice, 2);
            $total  = bcadd($total, $amount, 2);

            $item = [
                'line'        => $index + 1,
                'account_id'  => (int) $line['account_id'],
                'description' => $line['description'],
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
            ];

            // Mapping a line to a product is what puts it against stock rather
            // than a general expense line. Optional per line.
            if (filled($line['product_id'] ?? null)) {
                $item['product_id'] = (int) $line['product_id'];
                $item = $this->applyProduct($item, (int) $line['product_id']);
            }

            $formItems[] = $item;
        }

        $fileIds = $this->attachPhoto($scan);

        $payload = [
            'contact_id'    => (int) $data['contact_id'],
            'date'          => $date->toDateString(),
            'number2'       => $invoiceNumber,
            'payment_mode'  => 'credit',
            'term_id'       => $termId,
            'currency_code' => 'MYR',
            'exchange_rate' => 1,
            // This company's tax codes are all archived — it is not SST
            // registered, so every line posts with no tax, which is what the
            // bills already on the books do.
            'tax_mode'    => 'exclusive',
            'status'      => 'ready',
            'description' => 'Read from photo on the Inventory, Sales and Management System website (scan #' . $scan->id . ')',
            'form_items'  => $formItems,
            'term_items'  => [[
                'term_id'     => $termId,
                'date'        => $dueDate->toDateString(),
                'payment_due' => '100%',
                'amount'      => (float) $total,
            ]],
        ];

        if ($fileIds !== []) {
            $payload['files'] = array_map(fn ($id) => ['file_id' => $id], $fileIds);
        }

        $transaction = $this->createBill($payload);

        $scan->update([
            'status'               => InvoiceScan::STATUS_POSTED,
            'supplier_name'        => $data['supplier_name'] ?? $scan->supplier_name,
            'invoice_number'       => $invoiceNumber,
            'invoice_date'         => $date->toDateString(),
            'total_amount'         => $total,
            'bukku_transaction_id' => $transaction['id'] ?? null,
            'bukku_number'         => $transaction['number'] ?? null,
            'bukku_short_link'     => $transaction['short_link'] ?? null,
            'posted_at'            => now(),
        ]);

        return $scan->refresh();
    }

    /**
     * Send the bill, and if Bukku refuses the shape, send a plainer one.
     *
     * `product_unit_id` and `location_id` were read off a bill's GET response,
     * which is not proof they are accepted on the way IN — a response shape and
     * a request shape are different things, and Bukku publishes no reference to
     * settle it. Nothing on this repo's side can test that without writing to
     * the real books, so the question is answered where it actually matters: if
     * the payload is refused, the two unproven fields are dropped and the bill
     * goes again without them.
     *
     * Only a 422 is retried. That is Bukku saying the payload is wrong, so
     * nothing was written and a second attempt cannot duplicate a bill; any
     * other failure might have committed and must stay a failure.
     */
    private function createBill(array $payload): array
    {
        try {
            return Bukku::createBill($payload);
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 422) {
                throw $e;
            }

            report($e);
        }

        $payload['form_items'] = array_map(
            fn ($item) => array_diff_key($item, array_flip(['product_unit_id', 'location_id'])),
            $payload['form_items'],
        );

        return Bukku::createBill($payload);
    }

    /**
     * Take the account, the unit and the stock location off the product.
     *
     * A mapped line has to post against the product's own account — Inventory
     * for anything stock-tracked — or the books read as money spent on general
     * expenses with nothing bought, which is exactly what the bills already
     * written by the Telegram bot do not say. The reviewer picks the product;
     * the account is not theirs to get wrong, so whatever the row's account
     * dropdown said is overridden here rather than trusted.
     *
     * A read that fails leaves the submitted account in place. A blunter bill
     * beats no bill when Bukku wobbles for a second, and reads do not retry.
     */
    private function applyProduct(array $item, int $productId): array
    {
        try {
            $product = Bukku::product($productId);
        } catch (\Throwable $e) {
            report($e);

            return $item;
        }

        if ($product === []) {
            return $item;
        }

        $tracked = (bool) ($product['track_inventory'] ?? false);
        $account = $tracked
            ? ($product['inventory_account_id'] ?? null)
            : ($product['purchase_account_id'] ?? null);

        if ($account) {
            $item['account_id'] = (int) $account;
        }

        foreach ($product['units'] ?? [] as $unit) {
            if ($unit['is_purchase_default'] ?? false) {
                $item['product_unit_id'] = (int) $unit['id'];
                break;
            }
        }

        if ($tracked && ($location = Bukku::defaultLocationId())) {
            $item['location_id'] = $location;
        }

        return $item;
    }

    /**
     * Send the photo up to Bukku so the bill carries its own evidence.
     *
     * A failure here is not a failure of the bill: the numbers matter more
     * than the picture, and the picture is still on this server either way.
     *
     * @return array<int,int>
     */
    private function attachPhoto(InvoiceScan $scan): array
    {
        try {
            if (! Storage::exists($scan->file_path)) {
                return [];
            }

            return [Bukku::uploadFile(
                Storage::get($scan->file_path),
                $scan->original_filename,
                Storage::mimeType($scan->file_path) ?: 'application/octet-stream',
            )];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
