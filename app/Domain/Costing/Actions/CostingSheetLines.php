<?php

namespace App\Domain\Costing\Actions;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Model;

/**
 * The ingredient lines of a costing sheet - an R&D trial or a staff meal.
 * Both pages wrote and deducted them with the same two methods, copied.
 */
final class CostingSheetLines
{
    /**
     * Write the lines onto the sheet.
     *
     * The name and unit are read from the database, never from the form, for
     * the same reason every other history row here snapshots them: a later
     * rename or deletion leaves the old sheet still readable, and a line cannot
     * read as one ingredient while having been costed as another.
     */
    public static function write(Model $sheet, array $lines): void
    {
        $items = InventoryItem::findMany(array_column($lines, 'inventory_item_id'))->keyBy('id');

        foreach ($lines as $line) {
            $item = $items[$line['inventory_item_id']];

            $sheet->lines()->create([
                'inventory_item_id' => $item->id,
                'item'              => $item->name,
                'unit'              => $item->unit,
                'quantity'          => $line['quantity'],
                'unit_price'        => $line['unit_price'],
            ]);
        }

        $sheet->load('lines');
    }

    /**
     * Take the sheet off live stock.
     *
     * Summed per item first: one sheet legitimately lists the same ingredient
     * twice (oil in two steps), and deducting those one at a time would lose
     * the first. Same merge Recipe::consumptionFor() does.
     *
     * Setting the quantity is enough - InventoryItem::booted() recomputes
     * monetary_value. unit_cost is untouched, so neither a trial nor feeding
     * the team reprices a single dish on the menu.
     */
    public static function deduct(array $lines): void
    {
        $used = [];
        foreach ($lines as $line) {
            $id = (int) $line['inventory_item_id'];
            $used[$id] = ($used[$id] ?? 0) + (float) $line['quantity'];
        }

        foreach (InventoryItem::findMany(array_keys($used)) as $item) {
            $item->quantity_on_hand = max(0, (float) $item->quantity_on_hand - $used[$item->id]);
            $item->last_updated     = now();
            $item->save();
        }
    }
}
