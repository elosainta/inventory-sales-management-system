<?php

namespace App\Http\Controllers;

use App\Domain\Production\Actions\LogProduction;
use App\Domain\Production\Actions\UndoProduction;
use App\Http\Requests\StoreDishProductionRequest;
use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-production');

        $range = $request->query('range');
        $month = $request->query('month', now()->format('Y-m'));

        $query = ProductionBatch::with(['lines', 'user'])->orderByDesc('production_date')->orderByDesc('id');

        Period::filter($query, $range, $month, 'production_date');

        $batches    = $query->with('recipe')->get();
        $totalValue = $batches->sum('total_value');

        // Every recipe, for the dish cards at the top of the page.
        $recipes = Recipe::with('ingredients.inventoryItem')
            ->orderBy('name')
            ->get();

        // Unfiltered count, so an empty month can say "nothing in this period"
        // instead of claiming there is no data at all.
        $totalOnRecord = \App\Models\ProductionBatch::count();

        return view('production.index', compact('batches', 'totalValue', 'recipes', 'range', 'month', 'totalOnRecord'));
    }

    /**
     * A chef records everything they cooked in one go, so the form submits a
     * quantity per dish. Each dish becomes its own batch — that keeps a batch
     * costed at one recipe's plate cost, and lets a single dish be removed
     * later without unpicking the others.
     *
     * One transaction wraps the lot: if the fourth dish fails, the first three
     * are not left written to inventory.
     */
    /**
     * One dish, laid out like its recipe, with a quantity box per ingredient
     * prefilled from the recipe. The chef corrects what they actually used and
     * that is what comes off the shelf. Reached by tapping a dish card.
     */
    public function dish(Recipe $recipe)
    {
        Gate::authorize('manage-production');

        $recipe->load('ingredients.inventoryItem');

        // One row per shelf item, merged: a recipe may list the same one twice,
        // and two boxes for one item would both post to the same key.
        $perDish = $recipe->consumptionFor(1);
        $items   = InventoryItem::whereIn('id', array_keys($perDish))->orderBy('name')->get();

        return view('production.dish', compact('recipe', 'perDish', 'items'));
    }

    public function storeDish(StoreDishProductionRequest $request, Recipe $recipe, LogProduction $action)
    {
        Gate::authorize('manage-production');

        $data = $request->validated();

        $action->execute([
            'user_id'           => auth()->id(),
            'recipe_id'         => $recipe->id,
            'quantity_produced' => $data['quantity_produced'],
            'produced_by'       => $data['produced_by'],
            'production_date'   => $data['production_date'],
            'notes'             => $data['notes'] ?? null,
            'used'              => $data['used'],
        ]);

        return redirect()->route('production.index')->with('success', 'Production logged.');
    }

    public function destroy(ProductionBatch $productionBatch, UndoProduction $undo)
    {
        Gate::authorize('delete-entries');

        $undo->execute($productionBatch);

        return back()->with('success', 'Production entry removed. What it took off the shelf is back.');
    }
}
