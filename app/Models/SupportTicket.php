<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN     = 'open';
    public const STATUS_RESOLVED = 'resolved';

    // Developer-only triage tags (set on the admin tickets page, not the user form).
    public const TYPE_FEATURE = 'feature';
    public const TYPE_BUG     = 'bug';
    public const TYPE_OTHER   = 'other';

    public const TYPE_LABELS = [
        self::TYPE_FEATURE => 'Feature Request',
        self::TYPE_BUG     => 'Bug Fix',
        self::TYPE_OTHER   => 'Other',
    ];

    protected $fillable = [
        'ticket_number',
        'user_id',
        'name',
        'email',
        'role',
        'description',
        'media_path',
        'media_filename',
        'media_mime',
        'status',
        'type',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
