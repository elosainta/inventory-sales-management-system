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

            $total = 0;
            foreach ($lines as $line) {
                $total += $line['quantity'] * $line['unit_price'];
            }

            $data['total_amount'] = round($total, 2);
            $purchase = MarketPurchase::create($data);

            $updatedItemIds = [];

            foreach ($lines as $line) {
                $lineTotal = round($line['quantity'] * $line['unit_price'], 2);

                MarketPurchaseLine::create([
                    'market_purchase_id' => $purchase->id,
                    'inventory_item_id'  => $line['inventory_item_id'],
                    'quantity'           => $line['quantity'],
                    'unit_price'         => $line['unit_price'],
                    'line_total'         => $lineTotal,
                ]);

                $item = InventoryItem::find($line['inventory_item_id']);
                if ($item) {
                    $item->quantity_on_hand += $line['quantity'];
                    $item->unit_cost         = $line['unit_price'];
                    $item->last_updated      = now();
                    $item->save();

                    $updatedItemIds[] = $item->id;
                }
            }

            if ($updatedItemIds) {
                Recipe::whereHas('ingredients', fn ($q) => $q->whereIn('inventory_item_id', $updatedItemIds))
                    ->each(fn ($recipe) => $recipe->recalculatePlateCost());
            }

            return $purchase;
        });
    }
}
