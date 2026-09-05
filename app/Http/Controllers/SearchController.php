<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Recipe;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\RndEntry;
use App\Models\WastageEntry;
use App\Models\SpecialEvent;
use App\Models\SupportTicket;
use App\Models\User;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('search-global');

        $q = trim($request->get('q', ''));

        $inventory = collect();
        $suppliers  = collect();
        $recipes    = collect();
        $purchases  = collect();
        $sales      = collect();
        $wastage    = collect();
        $rnd        = collect();
        $events     = collect();
        $supportTickets = collect();
        $users          = collect();

        if (strlen($q) >= 2) {
            if (Gate::allows('view-inventory')) {
                $inventory = InventoryItem::where('name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orderBy('name')->limit(10)->get();
            }

            if (Gate::allows('view-suppliers')) {
                $suppliers = Supplier::where('name', 'like', "%{$q}%")
                    ->orderBy('name')->limit(10)->get();
            }

            if (Gate::allows('view-recipes')) {
                $recipes = Recipe::where('name', 'like', "%{$q}%")
                    ->orderBy('name')->limit(10)->get();
            }

            if (Gate::allows('view-purchases')) {
                $purchases = Purchase::with('supplier')
                    ->where(function ($query) use ($q) {
                        $query->where('invoice_number', 'like', "%{$q}%")
                              ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$q}%"));
                    })
                    ->orderByDesc('purchase_date')->limit(10)->get();
            }

            if (Gate::allows('view-sales')) {
                // Open orders are off-menu and carry their own typed-in name,
                // so matching the recipe alone would never find one.
                $sales = Sale::with('recipe')
                    ->where(fn ($query) => $query
                        ->whereHas('recipe', fn ($r) => $r->where('name', 'like', "%{$q}%"))
                        ->orWhere('item_name', 'like', "%{$q}%"))
                    ->orderByDesc('sale_date')->limit(10)->get();
            }

            if (Gate::allows('view-wastage')) {
                $wastage = WastageEntry::with('inventoryItem')
                    ->where(function ($query) use ($q) {
                        $query->whereHas('inventoryItem', fn ($i) => $i->where('name', 'like', "%{$q}%"))
                              ->orWhere('reason', 'like', "%{$q}%");
                    })
                    ->orderByDesc('recorded_date')->limit(10)->get();
            }

            if (Gate::allows('view-rnd')) {
                // Ingredients are matched on the LINE's snapshotted name, not
                // the live inventory one: a trial is findable by what an
                // ingredient was called when it was recorded, which is also
                // what the results list renders.
                $rnd = RndEntry::with(['creator', 'lines'])
                    ->where(function ($query) use ($q) {
                        $query->where('menu_name', 'like', "%{$q}%")
                              ->orWhere('remark', 'like', "%{$q}%")
                              ->orWhereHas('lines', fn ($l) => $l->where('item', 'like', "%{$q}%"));
                    })
                    ->orderByDesc('purchased_on')->limit(10)->get();
            }

            if (Gate::allows('manage-events')) {
                $events = SpecialEvent::where('name', 'like', "%{$q}%")
                    ->orderByDesc('event_date')->limit(10)->get();
            }

            if (Gate::allows('view-support-tickets')) {
                $supportTickets = SupportTicket::where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orderByDesc('created_at')->limit(10)->get();
            }

            if (Gate::allows('view-users')) {
                $users = User::where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                              ->orWhere('email', 'like', "%{$q}%");
                    })
                    // The hidden demo account is only surfaced to Admins.
                    ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('is_demo', false))
                    ->orderBy('name')->limit(10)->get();
            }
        }

        $total = $inventory->count() + $suppliers->count() + $recipes->count()
               + $purchases->count() + $sales->count() + $wastage->count()
               + $events->count() + $rnd->count()
               + $supportTickets->count() + $users->count();

        return view('search.results', compact(
            'q', 'total', 'inventory', 'suppliers', 'recipes',
            'purchases', 'sales', 'wastage', 'events', 'rnd', 'supportTickets', 'users'
        ));
    }
}
