<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackAttachment extends Model
{
    protected $fillable = [
        'feedback_entry_id',
        'path',
        'filename',
        'mime',
        'size',
    ];

    /** @return BelongsTo<FeedbackEntry, $this> */

    /** @return BelongsTo<FeedbackEntry, $this> */
    public function feedbackEntry(): BelongsTo
    {
        return $this->belongsTo(FeedbackEntry::class);
    }
}
