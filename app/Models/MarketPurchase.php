<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketPurchase extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'signed_by',
        'receipt_path',
        'purchase_date',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MarketPurchaseLine::class);
    }
}
