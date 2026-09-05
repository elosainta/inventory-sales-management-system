<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAttachment extends Model
{
    protected $fillable = [
        'leave_application_id',
        'path',
        'filename',
        'mime',
        'size',
    ];

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }
}
