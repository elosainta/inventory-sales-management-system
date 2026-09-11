<?php

namespace App\Http\Controllers;

use App\Domain\Sales\Actions\LogSale;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\StoreSalesSheetRequest;
use App\Models\Sale;
use App\Models\SaleAttachment;
use App\Models\Recipe;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    public function index()
    {
        Gate::authorize('view-sales');

        $range = request('range');
        $month = request('month', now()->format('Y-m'));

        $query = Sale::with(['recipe', 'attachments'])->orderBy('sale_date', 'desc');

        Period::filter($query, $range, $month, 'sale_date');

        $sales        = $query->get();
        $recipes      = Recipe::orderBy('name')->get();
        $totalRevenue = $sales->sum('total_revenue');

        // Unfiltered count, so an empty month can say "nothing in this period"
        // instead of claiming there is no data at all.
        $totalOnRecord = \App\Models\Sale::count();

        return view('sales.index', compact('sales', 'recipes', 'month', 'totalRevenue', 'range', 'totalOnRecord'));
    }

    /**
     * The Log sales sheet: every dish sold today, in one go. Each becomes its
     * own sale through LogSale - exactly what the pop-up does for one - inside
     * one transaction, so a failure on the fifth dish leaves none of them
     * written and none of their stock moved.
     */
    public function storeSheet(StoreSalesSheetRequest $request, LogSale $action)
    {
        Gate::authorize('manage-sales');

        $data = $request->validated();

        DB::transaction(function () use ($action, $data) {
            foreach ($data['qty'] as $recipeId => $qty) {
                $action->execute([
                    'recipe_id'     => (int) $recipeId,
                    'item_name'     => null,
                    'qty_sold'      => (int) $qty,
                    'selling_price' => $data['price'][$recipeId],
                    'sale_date'     => $data['sale_date'],
                    'is_open_order' => false,
                    'discount'      => 0,
                ]);
            }
        });

        $count = count($data['qty']);

        return back()->with('success', $count === 1 ? 'Sale logged.' : "{$count} sales logged.");
    }

    public function store(StoreSaleRequest $request, LogSale $action)
    {
        Gate::authorize('manage-sales');

        $sale = $action->execute($request->safe()->except('photos'));

        // Stream any photos straight to the private disk — never load into memory.
        foreach ($request->file('photos', []) as $file) {
            $sale->attachments()->create([
                'path'     => $file->store('sale-attachments'),
                'filename' => $file->getClientOriginalName(),
                'mime'     => $file->getClientMimeType(),
                'size'     => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Sale logged.');
    }

    public function attachment(SaleAttachment $saleAttachment)
    {
        Gate::authorize('view-sales');

        abort_if(! Storage::exists($saleAttachment->path), 404);

        // Images open inline in a new tab; anything else downloads.
        return $saleAttachment->isImage()
            ? Storage::response($saleAttachment->path, $saleAttachment->filename)
            : Storage::download($saleAttachment->path, $saleAttachment->filename);
    }

    public function update(StoreSaleRequest $request, Sale $sale)
    {
        Gate::authorize('manage-sales');

        $data = $request->safe()->except('photos');
        $data['total_revenue'] = Sale::revenue($data['qty_sold'], $data['selling_price'], (float) ($data['discount'] ?? 0));

        $sale->update($data);

        return back()->with('success', 'Sale updated.');
    }

    public function destroy(Sale $sale)
    {
        Gate::authorize('delete-entries');

        $sale->delete();
        return back()->with('success', 'Sale removed.');
    }

    public function restore(Sale $sale)
    {
        Gate::authorize('delete-entries');

        DB::transaction(function () use ($sale) {
            $recipe = Recipe::with('ingredients.inventoryItem')->find($sale->recipe_id);

            if ($recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $item = $ingredient->inventoryItem;
                    if (!$item) continue;

                    $item->quantity_on_hand += $ingredient->quantity * $sale->qty_sold;
                    $item->save();
                }
            }

            $sale->delete();
        });

        return back()->with('success', 'Sale undone and stock restored.');
    }

    public function exportPdf()
    {
        Gate::authorize('export-pdf');

        $sales = Sale::with(['recipe'])
            ->orderBy('sale_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdfs.sales', compact('sales'));
        return $pdf->download('sales-' . now()->format('Y-m-d') . '.pdf');
    }
}