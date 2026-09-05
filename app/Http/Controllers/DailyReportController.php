<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyReportRequest;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyReportController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-daily-report');

        $month = $request->query('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);

        $reports = DailyReport::with('user')
            ->whereYear('report_date', $y)
            ->whereMonth('report_date', $m)
            ->orderByDesc('report_date')
            ->get();

        $today = DailyReport::with('user')->whereDate('report_date', today())->first();

        return view('daily-reports.index', compact('reports', 'today', 'month'));
    }

    public function store(StoreDailyReportRequest $request)
    {
        Gate::authorize('write-daily-report');

        // The kitchen has more than one Head Chef, and the day only needs one
        // report. Whoever gets there first owns it — the other is told it's
        // already done rather than silently writing over it. The author can
        // still come back and correct their own.
        $today = DailyReport::with('user')->whereDate('report_date', today())->first();

        if ($today && $today->user_id !== auth()->id()) {
            return back()->with('error', "Today's report is already done — {$today->user?->name} wrote it.");
        }

        DailyReport::updateOrCreate(
            ['report_date' => today()->toDateString()],
            ['user_id' => auth()->id(), 'body' => $request->validated('body')]
        );

        return redirect()->route('daily-report.index')->with('success', 'Daily report saved.');
    }
}
