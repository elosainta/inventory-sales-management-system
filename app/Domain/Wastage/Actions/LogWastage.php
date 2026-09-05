<?php

namespace App\Domain\Wastage\Actions;

use App\Models\InventoryItem;
use App\Models\WastageEntry;
use Illuminate\Support\Facades\DB;

class LogWastage
{
    public function execute(array $data): WastageEntry
    {
        return DB::transaction(function () use ($data) {
            $item = InventoryItem::findOrFail($data['inventory_item_id']);

            $data['cost_lost'] = round(
                $item->unit_cost * $data['quantity_wasted'],
                2
            );

            $entry = WastageEntry::create($data);

            $newQty = max(0, $item->quantity_on_hand - $data['quantity_wasted']);
            $item->quantity_on_hand = $newQty;
            $item->save();

            return $entry;
        });
    }
}