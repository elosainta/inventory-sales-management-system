<?php

namespace App\Http\Controllers;

use App\Domain\Production\Actions\LogProduction;
use App\Http\Requests\StoreProductionBatchRequest;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-production');

        $range = $request->query('range');
        $month = $request->query('month', now()->format('Y-m'));

        $query = ProductionBatch::with(['lines', 'user'])->orderByDesc('production_date')->orderByDesc('id');

        if ($range === 'today') {
            $query->whereDate('production_date', today());
        } elseif ($range === 'week') {
            $query->whereBetween('production_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($range === 'month') {
            $query->whereBetween('production_date', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($range === 'year') {
            $query->whereBetween('production_date', [now()->startOfYear(), now()->endOfYear()]);
        } else {
            [$y, $m] = explode('-', $month);
            $query->whereYear('production_date', $y)->whereMonth('production_date', $m);
        }

        $batches    = $query->with('recipe')->get();
        $totalValue = $batches->sum('total_value');

        // Every recipe with its formula, so the Log Production form can show
        // what a batch will take off the shelf before it is saved. The figures
        // are a preview only — LogProduction reads the recipe again on save.
        $recipes = Recipe::with(['ingredients.inventoryItem', 'outputInventoryItem'])
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
    public function store(StoreProductionBatchRequest $request, LogProduction $action)
    {
        Gate::authorize('manage-production');

        $data = $request->validated();

        $shared = [
            'user_id'         => auth()->id(),
            'produced_by'     => $data['produced_by'],
            'production_date' => $data['production_date'],
            'notes'           => $data['notes'] ?? null,
        ];

        DB::transaction(function () use ($action, $data, $shared) {
            foreach ($data['quantities'] as $recipeId => $quantity) {
                $action->execute($shared + [
                    'recipe_id'         => (int) $recipeId,
                    'quantity_produced' => $quantity,
                ]);
            }
        });

        $made = count($data['quantities']);

        return back()->with('success', $made === 1
            ? 'Production logged.'
            : "Production logged for {$made} dishes.");
    }

    public function destroy(ProductionBatch $productionBatch)
    {
        Gate::authorize('delete-entries');

        $productionBatch->lines()->delete();
        $productionBatch->delete();

        return back()->with('success', 'Production entry removed.');
    }
}
