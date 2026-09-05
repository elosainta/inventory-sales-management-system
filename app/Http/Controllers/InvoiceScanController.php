<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\Actions\PushInvoiceToBukku;
use App\Domain\Purchasing\Actions\ScanInvoice;
use App\Http\Requests\PushInvoiceScanRequest;
use App\Http\Requests\StoreInvoiceScanRequest;
use App\Models\InvoiceScan;
use App\Support\Bukku;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Invoice scan (BETA) — upload a photo on the website, check what was read,
 * send it to Bukku, get the bill reference back on the website.
 *
 * Everything in and out is the web app. This replaces the round trip that used
 * to run through Telegram: same destination, same photo on the bill, but the
 * review step now happens on a screen wide enough to actually read the lines,
 * and the result is a row anyone with access can find later rather than a
 * message in one person's chat history.
 *
 * Managers only. It writes to the company's books.
 */
class InvoiceScanController extends Controller
{
    public function index()
    {
        Gate::authorize('use-invoice-scan');

        return view('invoice-scan.index', [
            'scans'      => InvoiceScan::with('user')->latest()->paginate(25),
            'configured' => Bukku::configured() && filled(config('services.anthropic.key')),
        ]);
    }

    public function store(StoreInvoiceScanRequest $request, ScanInvoice $action)
    {
        Gate::authorize('use-invoice-scan');

        $scan = $action->execute($request->file('invoice'), auth()->id());

        if ($scan->status === InvoiceScan::STATUS_FAILED) {
            return redirect()
                ->route('invoice-scan.show', $scan)
                ->with('error', 'The invoice could not be read automatically — the details are blank, fill them in by hand.');
        }

        return redirect()
            ->route('invoice-scan.show', $scan)
            ->with('success', 'Invoice read. Check the details before sending it to Bukku.');
    }

    public function show(InvoiceScan $invoiceScan)
    {
        Gate::authorize('use-invoice-scan');

        return view('invoice-scan.show', [
            'scan'           => $invoiceScan,
            'contacts'       => Bukku::contacts(),
            'accounts'       => Bukku::accounts(),
            'products'       => Bukku::products(),
            'terms'          => PushInvoiceToBukku::TERMS,
            'defaultTermId'  => PushInvoiceToBukku::DEFAULT_TERM_ID,
            'defaultAccount' => (int) config('services.bukku.default_account_id'),
        ]);
    }

    public function push(PushInvoiceScanRequest $request, InvoiceScan $invoiceScan, PushInvoiceToBukku $action)
    {
        Gate::authorize('use-invoice-scan');

        try {
            $scan = $action->execute($invoiceScan, $request->validated());
        } catch (\Throwable $e) {
            report($e);

            // Back to the review screen with the reviewer's own edits intact —
            // re-keying twelve lines because Bukku was briefly unreachable is
            // how people stop using a feature.
            return back()->withInput()->with('error', 'Bukku did not accept the bill: ' . $e->getMessage());
        }

        return redirect()
            ->route('invoice-scan.show', $scan)
            ->with('success', 'Sent to Bukku as ' . $scan->bukku_number . '.');
    }

    public function photo(InvoiceScan $invoiceScan)
    {
        Gate::authorize('use-invoice-scan');

        abort_if(! Storage::exists($invoiceScan->file_path), 404);

        return Storage::response($invoiceScan->file_path);
    }

    public function destroy(InvoiceScan $invoiceScan)
    {
        Gate::authorize('delete-entries');

        // Only the local record goes. A bill already in Bukku is the
        // accountant's to void — deleting this row must never look like it
        // undid something on the books.
        Storage::delete($invoiceScan->file_path);
        $invoiceScan->delete();

        return redirect()
            ->route('invoice-scan.index')
            ->with('success', $invoiceScan->isPosted()
                ? 'Scan removed here. The bill stays in Bukku — void it there if it was wrong.'
                : 'Scan removed.');
    }
}
