<?php

namespace App\Http\Controllers;

use App\Domain\Recipes\Actions\SaveRecipe;
use App\Http\Requests\StoreRecipeRequest;
use App\Models\InventoryItem;
use App\Models\Recipe;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class RecipeController extends Controller
{
    public function index()
    {
        Gate::authorize('view-recipes');

        $recipes = Recipe::with('ingredients.inventoryItem')->orderBy('name')->get();
        $trashed = Recipe::onlyTrashed()->with('ingredients.inventoryItem')->orderBy('name')->get();
        $items   = InventoryItem::orderBy('name')->get();

        return view('recipes.index', compact('recipes', 'trashed', 'items'));
    }

    public function show(Recipe $recipe)
    {
        Gate::authorize('view-recipes');

        $recipe->load('ingredients.inventoryItem');
        $items = InventoryItem::orderBy('name')->get();
        return view('recipes.show', compact('recipe', 'items'));
    }

    public function store(StoreRecipeRequest $request, SaveRecipe $action)
    {
        Gate::authorize('manage-recipes');

        $action->execute($request->validated());

        return redirect()->route('recipes.index')->with('success', 'Recipe added.');
    }

    public function update(StoreRecipeRequest $request, Recipe $recipe, SaveRecipe $action)
    {
        Gate::authorize('manage-recipes');

        $action->execute($request->validated(), $recipe);

        return redirect()->route('recipes.show', $recipe)->with('success', 'Recipe updated.');
    }

    public function destroy(Recipe $recipe)
    {
        Gate::authorize('manage-recipes');

        $recipe->delete();
        return redirect()->route('recipes.index')->with('success', 'Recipe removed. You can restore it from the deleted list.');
    }

    public function restore(int $id)
    {
        Gate::authorize('manage-recipes');

        $recipe = Recipe::onlyTrashed()->findOrFail($id);
        $recipe->restore();

        return redirect()->route('recipes.index')->with('success', 'Recipe restored.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        $recipes = Recipe::with('ingredients.inventoryItem')->orderBy('name')->get();

        $pdf = Pdf::loadView('pdfs.recipes', compact('recipes'));
        return $pdf->download('recipes-' . now()->format('Y-m-d') . '.pdf');
    }
}