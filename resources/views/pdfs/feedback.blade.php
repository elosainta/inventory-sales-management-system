<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Feedback Report — {{ $monthLabel }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2a2320; background: #fff; padding: 40px; }
        .header { margin-bottom: 32px; border-bottom: 2px solid #3a2820; padding-bottom: 16px; }
        .brand { font-size: 28px; font-weight: 700; color: #3a2820; letter-spacing: -0.5px; }
        .subtitle { font-size: 11px; color: #6a5a50; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
        .meta { font-size: 11px; color: #8a7060; margin-top: 6px; }
        .period { font-size: 15px; font-weight: 700; color: #2a2320; margin-bottom: 20px; }
        .count-badge { display: inline-block; background: #3a2820; color: #f5f0eb; font-size: 10px; padding: 2px 8px; border-radius: 3px; font-weight: 700; margin-left: 8px; }
        .empty { text-align: center; padding: 48px; color: #9a8878; font-style: italic; }
        h3 { font-size: 13px; margin: 20px 0 8px; color: #3a2820; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6a5a50; background: #faf7f4; padding: 7px 10px; border-bottom: 1px solid #e5ddd5; }
        td { padding: 7px 10px; border-bottom: 1px solid #efe8e0; font-size: 11px; }
        .num { text-align: right; }
        .stars { color: #b85c2e; }
        .muted { color: #9a8878; }
        .comment { font-style: italic; color: #4a423b; }
        .footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid #e5ddd5; font-size: 10px; color: #9a8878; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">isms</div>
        <div class="subtitle">Kitchen OS · Peer Feedback Report</div>
        <div class="meta">Generated {{ now()->format('d M Y, g:i A') }} · Malaysia · Owner copy — includes sender identities</div>
    </div>

    <div class="period">
        {{ $monthLabel }}
        <span class="count-badge">{{ $entries->count() }} entr{{ $entries->count() !== 1 ? 'ies' : 'y' }}</span>
    </div>

    @if($entries->isEmpty())
        <div class="empty">No feedback for this period.</div>
    @else
        <h3>Kitchen scores by question</h3>
        <table>
            <thead><tr><th>Question</th><th class="num">Average (1–5)</th></tr></thead>
            <tbody>
                @foreach(\App\Models\FeedbackEntry::QUESTIONS as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="num">{{ $questionAverages[$key] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3>By team member (feedback received)</h3>
        <table>
            <thead><tr><th>Member</th><th class="num">Reviews</th><th class="num">Average</th></tr></thead>
            <tbody>
                @foreach($perPerson as $person)
                    <tr>
                        <td>{{ $person['name'] }}</td>
                        <td class="num">{{ $person['count'] }}</td>
                        <td class="num">{{ $person['average'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3>All entries</h3>
        <table>
            <thead><tr><th>From → To</th><th>Ratings</th><th class="num">Avg</th><th class="num">Date</th></tr></thead>
            <tbody>
                @foreach($entries as $entry)
                    <tr>
                        <td>
                            <b>{{ $entry->fromUser?->name ?? '—' }}</b> → {{ $entry->toUser?->name ?? '—' }}
                            @if($entry->comment)
                                <div class="comment">“{{ $entry->comment }}”</div>
                            @endif
                            @if($entry->attachments->isNotEmpty())
                                <div class="muted">{{ $entry->attachments->count() }} attachment(s)</div>
                            @endif
                        </td>
                        <td>
                            @foreach(\App\Models\FeedbackEntry::QUESTIONS as $key => $label)
                                <span class="muted">{{ ucfirst($key) }}:</span> {{ $entry->rating($key) }}@if(!$loop->last) · @endif
                            @endforeach
                        </td>
                        <td class="num">{{ $entry->averageRating() }}</td>
                        <td class="num muted">{{ $entry->created_at->format('d M') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Inventory, Sales and Management System — confidential. Sender identities are visible to the Owner only.
    </div>
</body>
</html>
