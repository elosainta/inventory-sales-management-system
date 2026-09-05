<?php

namespace App\Domain\Production\Actions;

use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchLine;
use App\Models\Recipe;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The middle of Inventory -> Production -> Sales.
 *
 * The chef says which dish and how many they made. The recipe is the formula:
 * it decides what came off the shelf, so nothing is keyed in ingredient by
 * ingredient. Raw stock goes down, the finished dish goes up.
 */
class LogProduction
{
    public function execute(array $data): ProductionBatch
    {
        return DB::transaction(function () use ($data) {
            $recipe = Recipe::with('ingredients.inventoryItem', 'outputInventoryItem')
                ->findOrFail($data['recipe_id']);

            $quantity = (float) $data['quantity_produced'];

            // What the batch cost to make. plate_cost is the recipe's ingredient
            // cost with the misc overhead already applied, so it is the right
            // per-serving figure and it keeps production costed the same way the
            // dashboard costs a sale.
            $data['total_value'] = round((float) $recipe->plate_cost * $quantity, 2);

            $batch = ProductionBatch::create($data);

            // ---- Inventory -> : the formula comes off the shelf ----
            $consumed = $recipe->consumptionFor($quantity);
            $items    = InventoryItem::whereIn('id', array_keys($consumed))->get();

            $lowStockItems = [];

            foreach ($items as $item) {
                $used = $consumed[$item->id];

                ProductionBatchLine::create([
                    'production_batch_id' => $batch->id,
                    'inventory_item_id'   => $item->id,
                    'quantity'            => $used,
                    'unit_cost'           => $item->unit_cost,
                    'line_total'          => round($used * (float) $item->unit_cost, 2),
                ]);

                // Only the quantity is assigned — monetary_value is derived in
                // InventoryItem::booted(). unit_cost is left alone: cooking with
                // an ingredient does not change what it cost to buy.
                $item->quantity_on_hand = max(0, (float) $item->quantity_on_hand - $used);
                $item->last_updated     = now();
                $item->save();

                if ($item->isLowStock()) {
                    $lowStockItems[] = $item;
                }
            }

            // ---- -> Production : the finished dish becomes stock ----
            // Costed at what it took to make, so a sale of it reports a truthful
            // margin. A recipe with no output item still consumes its
            // ingredients; it simply has nowhere to put the result yet, and its
            // sales go on deducting raw ingredients (see LogSale).
            if ($output = $recipe->outputInventoryItem) {
                $output->quantity_on_hand = (float) $output->quantity_on_hand + $quantity;
                $output->unit_cost        = $recipe->plate_cost;
                $output->last_updated     = now();
                $output->save();

                // The finished good's unit cost just moved, so any recipe using
                // it as an ingredient — a dish built on a prepped component —
                // is now costed on a stale figure.
                Recipe::whereHas('ingredients', fn ($q) => $q->where('inventory_item_id', $output->id))
                    ->each(fn ($r) => $r->recalculatePlateCost());
            }

            // Notify head chefs of any item still at/below its low-stock level.
            // Keep the alert inside the actor's own world: a demo user's action
            // notifies only the demo head chef (in the sandbox DB), a real user's
            // action only real head chefs. Scheduled/CLI runs have no actor and
            // fall through to the real head chefs.
            if ($lowStockItems) {
                $actorIsDemo = (bool) auth()->user()?->is_demo;
                $headChefs = User::where('role', User::ROLE_HEAD_CHEF)
                    ->where('is_demo', $actorIsDemo)
                    ->get();
                foreach ($lowStockItems as $item) {
                    foreach ($headChefs as $chef) {
                        try {
                            $chef->notify(new LowStockAlert($item));
                        } catch (\Throwable $e) {
                            // This runs inside the transaction that just saved
                            // the batch. Resend can refuse a send outright (an
                            // empty reply cost the 20 August reminder), and an
                            // uncaught throw here would roll the whole batch
                            // back — the chef would lose work they had already
                            // logged because an email failed. The batch is the
                            // record that matters; the alert is not.
                            Log::error("Low stock alert failed for {$chef->email}: " . $e->getMessage());
                        }
                    }
                }
            }

            return $batch;
        });
    }
}
