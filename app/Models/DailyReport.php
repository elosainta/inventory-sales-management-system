<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    use LogsActivity;

    protected $fillable = ['user_id', 'report_date', 'body'];

    protected $casts = ['report_date' => 'date'];

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
