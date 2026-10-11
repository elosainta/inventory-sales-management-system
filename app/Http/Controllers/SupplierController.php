<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use App\Support\Bukku;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index()
    {
        Gate::authorize('view-suppliers');

        $suppliers = Supplier::orderBy('name')->get();

        // id => name, for the link picker and the "In Bukku" line on each card.
        $bukkuContacts = Gate::allows('manage-suppliers')
            ? collect(Bukku::contacts())->mapWithKeys(fn ($c) => [(int) $c['id'] => Bukku::contactName($c)])->sort()->all()
            : [];

        return view('suppliers.index', compact('suppliers', 'bukkuContacts'));
    }

    public function store(StoreSupplierRequest $request)
    {
        Gate::authorize('manage-suppliers');

        Supplier::create($request->validated());

        return redirect()->route('suppliers.index')->with('success', 'Supplier added.');
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier)
    {
        Gate::authorize('manage-suppliers');

        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        Gate::authorize('manage-suppliers');

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier removed.');
    }
}
