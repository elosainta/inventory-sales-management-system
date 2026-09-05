<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\WastageEntry;
use App\Models\Sale;
use App\Models\FloatIssuance;
use App\Models\SpecialEvent;
use App\Models\Recipe;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index()
    {
        // /dashboard is the landing route as well as the financial page — the
        // sidebar logo points here from every page and so does the bare domain
        // — so anyone whose home is elsewhere is sent there rather than shown a
        // refusal. This used to name the roles, and every role added after it
        // was written fell through to the 403 below. See User::homeRoute().
        $user = auth()->user();

        if ($user->homeRoute() !== 'dashboard') {
            return redirect()->route($user->homeRoute());
        }

        Gate::authorize('view-dashboard');

        $month = request('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        // Always current — not month-filtered
        $inventoryValue   = InventoryItem::sum('monetary_value');
        $pettyCashBalance = FloatIssuance::sum('amount_given') - FloatIssuance::sum('amount_spent') - FloatIssuance::sum('amount_returned');
        $lowStockCount    = InventoryItem::whereRaw('quantity_on_hand <= reorder_threshold * ?', [InventoryItem::LOW_STOCK_FACTOR])->count();

        // Month-filtered KPIs
        $salesThisMonth = Sale::whereYear('sale_date', $year)->whereMonth('sale_date', $mon)->sum('total_revenue');
        $purchaseSpend  = Purchase::whereYear('purchase_date', $year)->whereMonth('purchase_date', $mon)->sum('total_amount');
        $wasteCost      = WastageEntry::whereYear('recorded_date', $year)->whereMonth('recorded_date', $mon)->sum('cost_lost');
        $wastageRate    = $purchaseSpend > 0 ? round(($wasteCost / $purchaseSpend) * 100, 1) : 0;

        // Cost of goods actually sold this month = Σ(recipe plate cost × qty sold).
        // This is the true cost base for gross margin — not purchase spend, which
        // reflects buying activity (stock coming in), not the cost of what was sold.
        $cogs = Sale::whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
            ->join('recipes', 'sales.recipe_id', '=', 'recipes.id')
            ->sum(DB::raw('recipes.plate_cost * sales.qty_sold'));

        $grossMargin    = $salesThisMonth > 0 ? round((($salesThisMonth - $cogs) / $salesThisMonth) * 100, 1) : 0;

        // Charts — month-filtered
        $salesTrend = Sale::select(DB::raw('DATE(sale_date) as date'), DB::raw('SUM(total_revenue) as revenue'))
            ->whereYear('sale_date', $year)->whereMonth('sale_date', $mon)
            ->groupBy('date')->orderBy('date')->get();

        $purchaseTrend = Purchase::select(DB::raw('DATE(purchase_date) as date'), DB::raw('SUM(total_amount) as total'))
            ->whereYear('purchase_date', $year)->whereMonth('purchase_date', $mon)
            ->groupBy('date')->orderBy('date')->get();

        $wastageByReason = WastageEntry::select('reason', DB::raw('SUM(cost_lost) as total'))
            ->whereYear('recorded_date', $year)->whereMonth('recorded_date', $mon)
            ->groupBy('reason')->get();

        // Inventory by category is always current stock value
        $inventoryByCategory = InventoryItem::select('category', DB::raw('SUM(monetary_value) as total'))
            ->groupBy('category')->get();

        $wastageTrend = WastageEntry::select(DB::raw('DATE(recorded_date) as date'), DB::raw('SUM(cost_lost) as total'))
            ->whereYear('recorded_date', $year)->whereMonth('recorded_date', $mon)
            ->groupBy('date')->orderBy('date')->get();

        // Bottom lists — low stock is always current; top wasted & spend by supplier respect month
        $lowStockItems = InventoryItem::whereRaw('quantity_on_hand <= reorder_threshold * ?', [InventoryItem::LOW_STOCK_FACTOR])
            ->orderBy('quantity_on_hand')->take(5)->get();

        $topWasted = WastageEntry::select('inventory_item_id', DB::raw('SUM(cost_lost) as total'))
            ->with('inventoryItem')
            ->whereYear('recorded_date', $year)->whereMonth('recorded_date', $mon)
            ->groupBy('inventory_item_id')->orderByDesc('total')->take(5)->get();

        $spendBySupplier = Purchase::select('supplier_id', DB::raw('SUM(total_amount) as total'))
            ->with('supplier')
            ->whereYear('purchase_date', $year)->whereMonth('purchase_date', $mon)
            ->groupBy('supplier_id')->orderByDesc('total')->take(5)->get();

        $ownerData = null;
        if (auth()->user()?->role === 'owner') {
            $ownerData = [
                'inventory'  => InventoryItem::orderBy('name')->get(),
                'sales'      => Sale::with('recipe')->orderBy('sale_date', 'desc')->take(7)->get(),
                'purchases'  => Purchase::with('supplier')->orderBy('purchase_date', 'desc')->take(7)->get(),
                'wastage'    => WastageEntry::with('inventoryItem')->orderBy('recorded_date', 'desc')->take(7)->get(),
                'events'     => SpecialEvent::orderBy('event_date', 'desc')->take(7)->get(),
                'float'      => FloatIssuance::orderBy('issued_date', 'desc')->take(7)->get(),
                'recipes'    => Recipe::orderBy('name')->get(),
                'suppliers'  => Supplier::orderBy('name')->get(),
            ];
        }

        // Demo accounts now run against an isolated sandbox database (see the
        // UseDemoDatabase middleware), so every figure here is already demo-only
        // and safe to show in full.
        $hideSales = false;

        return view('dashboard', compact(
            'month', 'hideSales',
            'inventoryValue', 'salesThisMonth', 'purchaseSpend', 'wasteCost', 'cogs',
            'wastageRate', 'grossMargin', 'pettyCashBalance', 'lowStockCount',
            'salesTrend', 'purchaseTrend', 'wastageByReason', 'inventoryByCategory',
            'wastageTrend', 'lowStockItems', 'topWasted', 'spendBySupplier',
            'ownerData'
        ));
    }
}