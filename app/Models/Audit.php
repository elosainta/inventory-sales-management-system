<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
