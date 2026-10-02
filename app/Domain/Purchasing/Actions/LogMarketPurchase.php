<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\InventoryItem;
use App\Models\MarketPurchase;
use App\Models\MarketPurchaseLine;
use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LogMarketPurchase
{
    public function execute(array $data, ?UploadedFile $receipt = null): MarketPurchase
    {
        return DB::transaction(function () use ($data, $receipt) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            if ($receipt) {
                // No disk arg -> the app's default private disk; see LogPurchase.
                $data['receipt_path'] = $receipt->store('market-receipts');
            }

            // The total is the sum of the rounded lines, as on the invoice -
            // rounding a float sum once drifted a sen from the lines beneath it.
            $total = '0.00';
            foreach ($lines as $line) {
                $total = bcadd($total, \App\Support\Money::lineAmount($line['quantity'], $line['unit_price']), 2);
            }

            $data['total_amount'] = $total;
            $purchase = MarketPurchase::create($data);

            $updatedItemIds = [];

            foreach ($lines as $line) {
                $lineTotal = \App\Support\Money::lineAmount($line['quantity'], $line['unit_price']);

                MarketPurchaseLine::create([
                    'market_purchase_id' => $purchase->id,
                    'inventory_item_id'  => $line['inventory_item_id'],
                    'quantity'           => $line['quantity'],
                    'unit_price'         => $line['unit_price'],
                    'line_total'         => $lineTotal,
                ]);

                $item = InventoryItem::find($line['inventory_item_id']);
                if (! $item) continue;

                $item->quantity_on_hand += $line['quantity'];
                $item->unit_cost         = $line['unit_price'];
                $item->last_updated      = now();
                $item->save();

                $updatedItemIds[] = $item->id;
            }

            if ($updatedItemIds) {
                Recipe::whereHas('ingredients', fn ($q) => $q->whereIn('inventory_item_id', $updatedItemIds))
                    ->each(fn ($recipe) => $recipe->recalculatePlateCost());
            }

            return $purchase;
        }, 3); // retried on a write clash; see LogProduction. A retry stores the receipt again, leaving one unused copy.
    }
}
