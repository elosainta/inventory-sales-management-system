<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}