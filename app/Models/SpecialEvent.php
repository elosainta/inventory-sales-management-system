<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SpecialEvent extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'event_date', 'menu', 'revenue', 'cost'];

    protected $casts = [
        'event_date' => 'date',
        'revenue'    => 'decimal:2',
        'cost'       => 'decimal:2',
    ];
}
