<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\Actions\LogMarketPurchase;
use App\Http\Requests\StoreMarketPurchaseRequest;
use App\Models\InventoryItem;
use App\Models\MarketPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MarketPurchaseController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-market-purchases');

        $range = $request->query('range');
        $month = $request->query('month', now()->format('Y-m'));

        $query = MarketPurchase::with(['lines', 'user'])->orderByDesc('purchase_date')->orderByDesc('id');

        if ($range === 'today') {
            $query->whereDate('purchase_date', today());
        } elseif ($range === 'week') {
            $query->whereBetween('purchase_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($range === 'month') {
            $query->whereBetween('purchase_date', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($range === 'year') {
            $query->whereBetween('purchase_date', [now()->startOfYear(), now()->endOfYear()]);
        } else {
            [$y, $m] = explode('-', $month);
            $query->whereYear('purchase_date', $y)->whereMonth('purchase_date', $m);
        }

        $purchases  = $query->get();
        $totalSpend = $purchases->sum('total_amount');
        $items      = InventoryItem::orderBy('name')->get();

        // Unfiltered count, so an empty month can say "nothing in this period"
        // instead of claiming there is no data at all.
        $totalOnRecord = \App\Models\MarketPurchase::count();

        return view('market-purchases.index', compact('purchases', 'totalSpend', 'items', 'range', 'month', 'totalOnRecord'));
    }

    public function store(StoreMarketPurchaseRequest $request, LogMarketPurchase $action)
    {
        Gate::authorize('manage-market-purchases');

        $data            = $request->validated();
        $data['user_id'] = auth()->id();

        $action->execute($data, $request->file('receipt'));

        return back()->with('success', 'Market purchase logged.');
    }

    public function receipt(MarketPurchase $marketPurchase)
    {
        Gate::authorize('view-market-purchases');

        abort_if(! $marketPurchase->receipt_path || ! Storage::exists($marketPurchase->receipt_path), 404);

        return Storage::response($marketPurchase->receipt_path);
    }

    public function destroy(MarketPurchase $marketPurchase)
    {
        Gate::authorize('delete-entries');

        if ($marketPurchase->receipt_path) {
            Storage::delete($marketPurchase->receipt_path);
        }

        $marketPurchase->lines()->delete();
        $marketPurchase->delete();

        return back()->with('success', 'Market purchase removed.');
    }
}
