<?php

use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Migrations\Migration;

/**
 * The egg bug, five more times: a pack price sitting on a shelf that counts
 * pieces. Six dishes were selling at a loss because of it, and the Inventory
 * Value tile was carrying RM 13,580 of sausage and cooking oil that does not
 * exist.
 *
 * Every figure below one marked ASSUMED was read off the supplier's own
 * invoice through Invoice Scan, not inferred from the pack size column — which
 * is exactly why they can be trusted. The cooking oil looked like a 17 kg can
 * by arithmetic; Lotus's invoice says 4x5kg, so it is 20 kg and RM 6.18, not
 * RM 7.27. The chicken sausage looked like RM 38.40 by its pack size of 5;
 * Northgate's invoice says 12 packets of 5, so it is RM 3.20.
 *
 * Each block is guarded on the broken figure itself — a price nobody would key
 * by hand — so a second run, or a database already corrected, changes nothing.
 * Quantities on hand are deliberately left alone except where a pack is being
 * split into pieces: stock moves during service, and the Tally is the tool for
 * counting it.
 */
return new class extends Migration
{
    /** Items priced per pack while the shelf already counted pieces. */
    private const REPRICES = [
        // id, name, broken cost, real cost, pieces per pack, evidence
        [181, 'SOURDOUGH', '14.00', '1.75', 8,
            'RM 14 a loaf (COOPERATIVE, "COUNTRY SOURDOUGH LOAF") over 8 slices'],
        [58, 'COOKING OIL', '123.60', '6.18', 20,
            'RM 123.60 a carton (Lotus, "Golden Cooking Oil 4x5kg") over 20 kg'],
        [56, 'CHICKEN SAUSAGE', '192.00', '3.20', 60,
            'RM 192 a carton (Northgate, "PRIME BRAND 12PKT X 400GM" at 80g) over 60 pcs'],
    ];

    /** Items where the shelf counted packs and has to start counting pieces. */
    private const CONVERSIONS = [
        // id, name, broken cost, pieces per pack, new unit (null keeps it), evidence
        [105, 'VICTORIA SLICE CHEESE', '37.80', 84, null,
            'RM 37.80 a pack of 84 slices (the Owner\'s count; no invoice scanned)'],
        [10, 'SAUSAGE', '35.80', 10, 'pcs',
            'RM 35.80 a pack. 10 per pack is ASSUMED — this item has never been '
            . 'bought through the system, so there is no invoice to read. Confirm '
            . 'at the next stock-take.'],
    ];

    /** Recipe lines that only make sense once the items above are per-piece. */
    private const LINES = [
        // recipe id, item id, wrong quantity, right quantity, why
        [18, 219, '0.2000', '0.0200',
            'Shakshuka asked for ten times the shiitake of every other dish'],
        [25, 105, '0.0100', '1.0000',
            'one slice of cheese, now that the item counts slices and not packs'],
    ];

    public function up(): void
    {
        $touched = [];

        foreach (self::REPRICES as [$id, $name, $broken, $real, $pack, $why]) {
            $item = $this->broken($id, $name, $broken);

            if (! $item) {
                continue;
            }

            $item->update(['unit_cost' => $real, 'pack_size' => $pack]);
            $touched[] = $id;

            echo "  {$name}: RM {$broken} -> RM {$real} per {$item->unit} ({$why}),"
                . " stock now worth RM {$item->monetary_value}.\n";
        }

        foreach (self::CONVERSIONS as [$id, $name, $broken, $pack, $unit, $why]) {
            $item = $this->broken($id, $name, $broken);

            if (! $item) {
                continue;
            }

            $packs = $item->quantity_on_hand;

            $item->update([
                'unit' => $unit ?? $item->unit,
                'quantity_on_hand' => bcmul((string) $packs, (string) $pack, 2),
                'unit_cost' => bcdiv($broken, (string) $pack, 2),
                'pack_size' => $pack,
            ]);
            $touched[] = $id;

            echo "  {$name}: {$packs} packs -> {$item->quantity_on_hand} {$item->unit}"
                . " at RM {$item->unit_cost} each ({$why}).\n";
        }

        $this->correctTheLines();
        $this->reprice($touched);
    }

    public function down(): void
    {
        // Nothing to go back to — a pack price on a piece was never a price.
    }

    /** The item, but only while it still holds the figure nobody would key. */
    private function broken(int $id, string $name, string $cost): ?InventoryItem
    {
        $item = InventoryItem::where('id', $id)->where('name', $name)->first();

        if ($item && (string) $item->unit_cost === $cost) {
            return $item;
        }

        echo "  {$name}: not the broken figure (RM {$cost}) — left alone.\n";

        return null;
    }

    private function correctTheLines(): void
    {
        foreach (self::LINES as [$recipe, $item, $wrong, $right, $why]) {
            $line = RecipeIngredient::where('recipe_id', $recipe)
                ->where('inventory_item_id', $item)
                ->first();

            if (! $line || (string) $line->quantity !== $wrong) {
                echo "  recipe {$recipe}: item {$item} is not at {$wrong} — left alone.\n";

                continue;
            }

            $line->update(['quantity' => $right]);

            echo "  recipe {$recipe}: item {$item} {$wrong} -> {$right} ({$why}).\n";
        }
    }

    /** Every dish built on a corrected ingredient, including the untouched ones. */
    private function reprice(array $itemIds): void
    {
        $ids = array_merge($itemIds, array_column(self::LINES, 1));

        if (! $ids) {
            return;
        }

        $recipes = Recipe::whereHas(
            'ingredients',
            fn ($query) => $query->whereIn('inventory_item_id', $ids)
        )->get();

        $recipes->each->recalculatePlateCost();

        $losing = $recipes->filter(fn ($r) => $r->selling_price - $r->plate_cost < 0);

        echo "  {$recipes->count()} recipes repriced; {$losing->count()} still below cost"
            . ($losing->isEmpty() ? ".\n" : ": " . $losing->pluck('name')->implode(', ') . ".\n");
    }
};
