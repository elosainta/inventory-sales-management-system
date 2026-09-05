<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecialEventRequest;
use App\Models\SpecialEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class SpecialEventController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-events');

        $month = request('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $events = SpecialEvent::whereYear('event_date', $year)
            ->whereMonth('event_date', $mon)
            ->orderBy('event_date', 'desc')
            ->get();

        $totalRevenue = $events->sum('revenue');
        $totalCost    = $events->sum('cost');
        $netProfit    = $totalRevenue - $totalCost;
        $eventCount   = $events->count();

        return view('events.index', compact(
            'events', 'month', 'eventCount', 'totalRevenue', 'totalCost', 'netProfit'
        ));
    }

    public function store(StoreSpecialEventRequest $request)
    {
        Gate::authorize('manage-events');

        SpecialEvent::create($request->validated());

        return redirect()->route('events.index')->with('success', 'Event logged.');
    }

    public function destroy(SpecialEvent $event)
    {
        Gate::authorize('manage-events');

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event removed.');
    }

    public function exportPdf()
    {
        Gate::authorize('manage-events');

        $month = request('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $events = SpecialEvent::whereYear('event_date', $year)
            ->whereMonth('event_date', $mon)
            ->orderBy('event_date', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdfs.events', compact('events', 'month'));

        return $pdf->download('events-' . $month . '.pdf');
    }
}
