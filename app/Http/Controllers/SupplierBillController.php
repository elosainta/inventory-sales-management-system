<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Support\Bukku;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Every supplier bill on the books, paid and unpaid, by month.
 *
 * The Invoice Scan page already shows what is still owed; this is the whole
 * picture, which is what a month-end review needs. It is READ-ONLY, and the
 * page says so: payments are recorded in Bukku, and a figure here that
 * disagreed with the books would be worse than no figure at all.
 *
 * Bukku's bill list carries the bill's own date and its balance, but no
 * payment date — so "paid" is grouped by the month the bill is DATED, not the
 * month the money left. For a kitchen settling within the month that is the
 * same answer; across a month boundary it is not, and both screens say so
 * rather than implying a precision the data does not have.
 */
class SupplierBillController extends Controller
{
    private const STATUSES = ['unpaid', 'paid', 'all'];

    public function index(Request $request)
    {
        Gate::authorize('view-supplier-bills');

        return view('supplier-bills.index', $this->report($request->query('status')));
    }

    public function exportPdf(Request $request)
    {
        Gate::authorize('export-pdf');

        $report = $this->report($request->query('status'));

        return Pdf::loadView('pdfs.supplier-bills', $report)
            ->download('supplier-bills-' . $report['status'] . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * What both screens render.
     *
     * An unknown status falls back to unpaid rather than to everything: a
     * money page widening on a junk query string is the wrong way to fail.
     *
     * rescue() guards a connection-level exception only. A Bukku read that
     * merely answers badly comes back as an empty list, by design — see
     * Bukku::cached(), which refuses to turn a missing key into a 500 — so
     * "nothing came back" and "there are no bills" are genuinely the same
     * answer here, and the page says the honest one rather than claiming an
     * outage it cannot see.
     */
    private function report(?string $status): array
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'unpaid';

        $all = collect(rescue(fn () => Bukku::bills(), []));
        $bills = $all->filter(fn ($bill) => $this->matches($bill, $status))->sortBy('date')->values();
        $names = Supplier::whereNotNull('bukku_contact_id')->pluck('name', 'bukku_contact_id');

        return [
            'status'     => $status,
            'configured' => Bukku::configured(),
            'totals'     => $this->totals($bills),
            'byMonth'    => $this->byMonth($bills, $names),
        ];
    }

    /** A bill with nothing left on it is paid; part-paid still counts as owed. */
    private function matches(array $bill, string $status): bool
    {
        $owing = (float) ($bill['balance'] ?? 0) > 0;

        return match ($status) {
            'paid'   => ! $owing,
            'unpaid' => $owing,
            default  => true,
        };
    }

    /**
     * By month, oldest first. The label is read off a row's own date and never
     * by re-parsing the Y-m key: Carbon fills a missing day from today, so
     * parsing 2026-02 on the 31st lands in March.
     */
    private function byMonth(Collection $bills, Collection $names): Collection
    {
        return $bills
            ->groupBy(fn ($bill) => Carbon::parse($bill['date'])->format('Y-m'))
            ->sortKeys()
            ->map(fn ($month) => $this->totals($month) + [
                'label'     => Carbon::parse($month->first()['date'])->format('F Y'),
                'suppliers' => $this->bySupplier($month, $names),
            ]);
    }

    /** Dearest first, so the month reads worst-first. */
    private function bySupplier(Collection $bills, Collection $names): Collection
    {
        return $bills
            ->groupBy(fn ($bill) => $names[$bill['contact_id'] ?? 0] ?? ($bill['contact_name'] ?? 'Unknown supplier'))
            ->map(fn ($rows) => $this->totals($rows) + ['bills' => $rows->sortBy('date')->values()])
            ->sortByDesc('billed');
    }

    /** Billed, settled and still owed across any set of bills. */
    private function totals(Collection $bills): array
    {
        return [
            'count'       => $bills->count(),
            'billed'      => $bills->sum(fn ($bill) => (float) ($bill['amount'] ?? 0)),
            'paid'        => $bills->sum(fn ($bill) => (float) ($bill['amount'] ?? 0) - (float) ($bill['balance'] ?? 0)),
            'outstanding' => $bills->sum(fn ($bill) => (float) ($bill['balance'] ?? 0)),
        ];
    }
}
