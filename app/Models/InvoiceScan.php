<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
