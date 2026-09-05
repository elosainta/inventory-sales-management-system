<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackEntry extends Model
{
    use LogsActivity;

    /**
     * The five fixed survey questions. Keys map to rating_* columns.
     * The recipient sees these anonymously; only the Owner sees senders.
     */
    public const QUESTIONS = [
        'cleanliness'   => 'Cleanliness of their station',
        'safety'        => 'Food & equipment safety',
        'organisation'  => 'Organisation of the kitchen',
        'teamwork'      => 'Teamwork during service',
        'communication' => 'Communication',
    ];

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'rating_cleanliness',
        'rating_safety',
        'rating_organisation',
        'rating_teamwork',
        'rating_communication',
        'comment',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FeedbackAttachment::class);
    }

    public function rating(string $key): int
    {
        return (int) $this->{'rating_' . $key};
    }

    public function averageRating(): float
    {
        return round(collect(array_keys(self::QUESTIONS))->avg(fn ($key) => $this->rating($key)), 1);
    }

    /**
     * Month-filtered entries (with identities) plus the aggregates shared by
     * the Owner page, the PDF export, and the monthly email:
     * [$entries, $monthLabel, $questionAverages, $perPerson].
     */
    public static function monthReport(string $month): array
    {
        [$year, $mon] = explode('-', $month);

        $entries = self::with(['fromUser', 'toUser', 'attachments'])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $mon)
            ->orderByDesc('created_at')
            ->get();

        $monthLabel = \Carbon\Carbon::createFromDate((int) $year, (int) $mon, 1)->format('F Y');

        $questionAverages = collect(self::QUESTIONS)
            ->map(fn ($label, $key) => $entries->isEmpty() ? null : round($entries->avg(fn ($entry) => $entry->rating($key)), 1));

        $perPerson = $entries->groupBy('to_user_id')
            ->map(fn ($group) => [
                'name'    => $group->first()->toUser?->name ?? '—',
                'count'   => $group->count(),
                'average' => round($group->avg(fn ($entry) => $entry->averageRating()), 1),
            ])
            ->sortByDesc('average')
            ->values();

        return [$entries, $monthLabel, $questionAverages, $perPerson];
    }
}
