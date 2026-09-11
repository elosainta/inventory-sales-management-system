<?php

namespace App\Domain\Production\Actions;

use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use Illuminate\Support\Facades\DB;

/**
 * Removing a production entry puts back what it took (the Owner, 2026-09-11).
 *
 * Until then a delete only removed the record: the ingredients stayed off the
 * shelf and the finished dishes stayed on it, and the confirm box said so.
 * With the multi-dish pop-up gone, delete is how a wrong entry is corrected,
 * and a correction that leaves the stock wrong is not one.
 *
 * What goes back is read off the batch's own lines - what was ACTUALLY taken,
 * including amounts the chef typed on the dish page - never off the recipe,
 * which may have been edited since. The finished dish comes off by how many
 * were made, never below zero: if some were sold since, those sales already
 * took them, and a negative shelf is not a truth. unit_cost is left alone
 * either way, as it is when the batch is made.
 */
class UndoProduction
{
    public function execute(ProductionBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch->load('lines');

            $giveBack = [];
            foreach ($batch->lines as $line) {
                $giveBack[$line->inventory_item_id] = ($giveBack[$line->inventory_item_id] ?? 0) + (float) $line->quantity;
            }

            foreach (InventoryItem::whereIn('id', array_keys($giveBack))->get() as $item) {
                $item->quantity_on_hand = (float) $item->quantity_on_hand + $giveBack[$item->id];
                $item->last_updated     = now();
                $item->save();
            }

            // withTrashed: a recipe deleted since still made these dishes.
            $output = $batch->recipe()->withTrashed()->with('outputInventoryItem')->first()?->outputInventoryItem;
            if ($output && $batch->quantity_produced) {
                $output->quantity_on_hand = max(0, (float) $output->quantity_on_hand - (float) $batch->quantity_produced);
                $output->last_updated     = now();
                $output->save();
            }

            $batch->lines()->delete();
            $batch->delete();
        });
    }
}
