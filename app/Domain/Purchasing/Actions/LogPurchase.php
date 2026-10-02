<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LogPurchase
{
    public function execute(array $data, ?UploadedFile $receipt = null): Purchase
    {
        return DB::transaction(function () use ($data, $receipt) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if ($receipt) {
                // No disk arg -> the app's default private disk. A receipt can
                // show a card number, so it's served through an authorizing
                // route rather than sitting web-readable on the public disk.
                $data['receipt_path'] = $receipt->store('receipts');
            }

            // The total is the sum of the rounded lines, as on the invoice -
            // rounding a float sum once drifted a sen from the lines beneath it.
            $total = '0.00';
            foreach ($lines as $line) {
                $total = bcadd($total, \App\Support\Money::lineAmount($line['quantity'], $line['unit_price']), 2);
            }

            $data['total_amount'] = $total;
            $purchase = Purchase::create($data);

            $updatedItemIds = [];

            foreach ($lines as $line) {
                $lineTotal = \App\Support\Money::lineAmount($line['quantity'], $line['unit_price']);

                PurchaseLine::create([
                    'purchase_id'       => $purchase->id,
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity'          => $line['quantity'],
                    'unit_price'        => $line['unit_price'],
                    'line_total'        => $lineTotal,
                ]);

                $item = InventoryItem::find($line['inventory_item_id']);
                if (! $item) continue;

                $item->quantity_on_hand += $line['quantity'];
                $item->unit_cost         = $line['unit_price'];
                $item->last_updated      = now();
                $item->save();

                $updatedItemIds[] = $item->id;
            }

            // Recalculate plate cost for every recipe that uses a re-priced ingredient
            if ($updatedItemIds) {
                Recipe::whereHas('ingredients', fn ($q) => $q->whereIn('inventory_item_id', $updatedItemIds))
                    ->each(fn ($recipe) => $recipe->recalculatePlateCost());
            }

            return $purchase;
        }, 3); // retried on a write clash; see LogProduction. A retry stores the receipt again, leaving one unused copy.
    }
}