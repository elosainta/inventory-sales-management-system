<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseLine::class);
    }
}