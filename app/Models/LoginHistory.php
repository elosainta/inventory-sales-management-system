<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'ip_address', 'logged_in_at'];

    protected $casts = ['logged_in_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
