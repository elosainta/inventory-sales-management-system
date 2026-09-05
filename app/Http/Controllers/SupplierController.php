<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index()
    {
        Gate::authorize('view-suppliers');

        $suppliers = Supplier::orderBy('name')->get();
        return view('suppliers.index', compact('suppliers'));
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