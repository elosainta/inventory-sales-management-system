<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class FloatIssuance extends Model
{
    use LogsActivity;

   protected $fillable = [
    'amount_given',
    'amount_spent',
    'amount_returned',
    'status',
    'issued_date',
];

    protected $casts = [
        'issued_date' => 'datetime',
    ];

}