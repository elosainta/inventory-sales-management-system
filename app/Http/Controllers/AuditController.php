<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Support\Facades\Gate;

class AuditController extends Controller
{
    public function index()
    {
        Gate::authorize('view-audit-log');

        $audits = Audit::with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('audits.index', compact('audits'));
    }
}