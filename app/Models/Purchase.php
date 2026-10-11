<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends Model
{
    use LogsActivity;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'invoice_number',
        'receipt_path',
        'total_amount',
        'status',
        'purchase_date',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseLine, $this> */

    /** @return HasMany<PurchaseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The scan this was filed from, if it came in by photo rather than by hand. */
    /** @return HasOne<InvoiceScan, $this> */
    /** @return HasOne<InvoiceScan, $this> */
    public function invoiceScan(): HasOne
    {
        return $this->hasOne(InvoiceScan::class);
    }
}
