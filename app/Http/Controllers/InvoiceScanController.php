<?php

namespace App\Http\Controllers;

use App\Domain\Purchasing\Actions\PushInvoiceToBukku;
use App\Domain\Purchasing\Actions\RecordPurchaseFromScan;
use App\Domain\Purchasing\Actions\ScanInvoice;
use App\Http\Requests\PushInvoiceScanRequest;
use App\Http\Requests\StoreInvoiceScanRequest;
use App\Models\InventoryItem;
use App\Models\InvoiceItemAlias;
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
 * Two gates, deliberately. `use-invoice-scan` is the screen — upload, read,
 * review, clear a row — and every signed-in account holds it, because whoever
 * takes the delivery is who has the paper in their hand. `send-invoice-scan`
 * is the button that writes to the company's books, and that is a manager's.
 */
class InvoiceScanController extends Controller
{
    public function index()
    {
        Gate::authorize('use-invoice-scan');

        return view('invoice-scan.index', [
            'scans'        => InvoiceScan::with('user')->latest()->paginate(25),
            'duplicateIds' => InvoiceScan::duplicateIds(),
            'configured'   => Bukku::configured() && filled(config('services.anthropic.key')),
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

        $lines = $invoiceScan->lines();

        return view('invoice-scan.show', [
            'scan'           => $invoiceScan,
            'duplicates'     => $invoiceScan->possibleDuplicates(),
            'contacts'       => Bukku::contacts(),
            'accounts'       => Bukku::accounts(),
            'products'       => Bukku::products(),
            'terms'          => PushInvoiceToBukku::TERMS,
            'defaultTermId'  => PushInvoiceToBukku::DEFAULT_TERM_ID,
            'defaultAccount' => (int) config('services.bukku.default_account_id'),

            // The searchable inventory list behind each row's match box, and
            // what this kitchen has already been taught about these suppliers'
            // wording. Resolved here, in one query for the whole invoice.
            'pickerItems'    => InventoryItem::orderBy('name')->get(['id', 'name', 'unit', 'unit_cost']),
            'aliasMatches'   => InvoiceItemAlias::matchAll(array_column($lines, 'description')),
        ]);
    }

    public function push(
        PushInvoiceScanRequest $request,
        InvoiceScan $invoiceScan,
        PushInvoiceToBukku $action,
        RecordPurchaseFromScan $recordPurchase,
    ) {
        // The one method on this controller that spends money.
        Gate::authorize('send-invoice-scan');

        $data = $request->validated();

        // Learn before sending, not after. What a supplier's wording means is
        // the reviewer's judgement about naming; it is true whether or not
        // Bukku accepts the bill a moment later, and re-matching twenty lines
        // because of someone else's outage is how a feature stops being used.
        foreach ($data['lines'] as $line) {
            if (filled($line['inventory_item_id'] ?? null)) {
                InvoiceItemAlias::remember($line['description'], (int) $line['inventory_item_id']);
            }
        }

        try {
            $scan = $action->execute($invoiceScan, $data);
        } catch (\Throwable $e) {
            report($e);

            // Back to the review screen with the reviewer's own edits intact —
            // re-keying twelve lines because Bukku was briefly unreachable is
            // how people stop using a feature.
            return back()->withInput()->with('error', 'Bukku did not accept the bill: ' . $e->getMessage());
        }

        // The bill is filed. Now put the same delivery on this kitchen's own
        // shelf, which used to be a second round of typing. Only the lines the
        // reviewer matched can move stock; the rest are on the bill and
        // nowhere else, which is right for a delivery fee.
        //
        // Deliberately after the send and deliberately not fatal: the bill is
        // already on the books and re-pushing is refused, so a failure here
        // must report itself and leave the numbers alone rather than throw
        // away a successful filing.
        $note = '';

        try {
            $supplierName = collect(Bukku::contacts())
                ->firstWhere('id', (int) $request->validated()['contact_id'])['name']
                ?? $data['supplier_name']
                ?? $scan->supplier_name;

            $purchase = $recordPurchase->execute($scan, $data, $supplierName);

            $note = $purchase
                ? ' Stock updated — recorded as purchase #' . $purchase->id . '.'
                : ' No lines were matched to your inventory, so nothing was added to stock.';
        } catch (\Throwable $e) {
            report($e);

            $note = ' The bill is filed, but stock could NOT be updated — add this delivery under Purchases by hand.';
        }

        return redirect()
            ->route('invoice-scan.show', $scan)
            ->with('success', 'Sent to Bukku as ' . $scan->bukku_number . '.' . $note);
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
