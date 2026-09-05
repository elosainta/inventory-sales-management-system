<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloatIssuanceRequest;
use App\Models\FloatIssuance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FloatIssuanceController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-float');

        $issuances = FloatIssuance::orderBy('issued_date', 'desc')->get();

        $totalGiven    = $issuances->sum('amount_given');
        $totalSpent    = $issuances->sum('amount_spent');
        $totalReturned = $issuances->sum('amount_returned');
        $netBalance    = $totalGiven - $totalSpent - $totalReturned;

        return view('float.index', compact(
            'issuances',
            'totalGiven', 'totalSpent', 'totalReturned', 'netBalance'
        ));
    }

    public function store(StoreFloatIssuanceRequest $request)
    {
        Gate::authorize('manage-float');

        FloatIssuance::create($request->validated());
        return redirect()->route('float.index')->with('success', 'Float issued.');
    }

    public function destroy(FloatIssuance $floatIssuance)
    {
        Gate::authorize('manage-float');

        $floatIssuance->delete();
        return redirect()->route('float.index')->with('success', 'Entry removed.');
    }
}