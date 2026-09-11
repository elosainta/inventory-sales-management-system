<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One invoice photo uploaded on the website, on its way to Bukku.
 *
 * Audited: this ends in a bill on the company's books, so who scanned what and
 * who pushed it has to survive the account being deleted.
 */
class InvoiceScan extends Model
{
    use LogsActivity;

    public const STATUS_SCANNED = 'scanned';

    public const STATUS_POSTED = 'posted';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'purchase_id',
        'file_path',
        'original_filename',
        'status',
        'extracted',
        'scan_error',
        'supplier_name',
        'invoice_number',
        'invoice_date',
        'total_amount',
        'bukku_transaction_id',
        'bukku_number',
        'bukku_short_link',
        'posted_at',
    ];

    protected $casts = [
        'extracted'    => 'array',
        'invoice_date' => 'date',
        'posted_at'    => 'datetime',
    ];

    /** The purchase this invoice created in this system, once it was filed. */
    public function purchase()
    {
        return $this->belongsTo(\App\Models\Purchase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /** Line items as read, each [description, quantity, unit_price]. */
    public function lines(): array
    {
        return $this->extracted['lines'] ?? [];
    }

    /**
     * Other scans that look like this same paper invoice, each with the reason.
     *
     * The push guard stops one SCAN becoming two bills. It cannot stop the same
     * invoice being photographed twice - by two people, or once today and once
     * after it turned up again in a pile - and each of those is its own scan
     * with its own Send button. Bukku bills are voided, never deleted, so the
     * second bill is the expensive half. This is a flag, not a lock: a manager
     * reads it and decides.
     *
     * Two signals, because the invoice number is read by a model and a misread
     * digit would slip past a number-only check:
     *   - same invoice number, ignoring case, spaces and punctuation
     *   - same supplier, same date, same total
     *
     * @return Collection<int, array{scan: self, reason: string}>
     */
    public function possibleDuplicates(): Collection
    {
        $number  = self::numberKey($this->invoice_number);
        $content = self::contentKey($this);

        if ($number === null && $content === null) {
            return collect();
        }

        // ponytail: compares every scan in PHP - fine at hundreds (7 live as at
        // 2026-09-11); store both keys as indexed columns if it reaches thousands.
        return self::with('user')->whereKeyNot($this->getKey())->latest()->get()
            ->map(fn (self $other) => match (true) {
                $number !== null && self::numberKey($other->invoice_number) === $number
                    => ['scan' => $other, 'reason' => 'same invoice number'],
                $content !== null && self::contentKey($other) === $content
                    => ['scan' => $other, 'reason' => 'same supplier, date and total'],
                default => null,
            })
            ->filter()
            ->values();
    }

    /**
     * Ids of every scan that shares an invoice with at least one other, for the
     * flag on the list page. One pass over the table rather than a query per row.
     *
     * @return array<int, true>
     */
    public static function duplicateIds(): array
    {
        $byKey = [];
        foreach (self::get(['id', 'supplier_name', 'invoice_number', 'invoice_date', 'total_amount']) as $scan) {
            foreach (array_filter(['n:' . self::numberKey($scan->invoice_number), 'c:' . self::contentKey($scan)], fn ($k) => strlen($k) > 2) as $key) {
                $byKey[$key][] = $scan->id;
            }
        }

        $ids = [];
        foreach ($byKey as $group) {
            if (count($group) > 1) {
                foreach ($group as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }

    /** "INV-20001", "inv 20001" and "INV20001" are one number. */
    private static function numberKey(?string $number): ?string
    {
        $key = str_replace(' ', '', InvoiceItemAlias::normalise((string) $number));

        return $key === '' ? null : $key;
    }

    /** Supplier + date + total; null unless all three were read. */
    private static function contentKey(self $scan): ?string
    {
        $supplier = InvoiceItemAlias::normalise((string) $scan->supplier_name);

        if ($supplier === '' || ! $scan->invoice_date || $scan->total_amount === null) {
            return null;
        }

        return $supplier . '|' . $scan->invoice_date->toDateString() . '|' . number_format((float) $scan->total_amount, 2, '.', '');
    }
}
