<?php

namespace App\Domain\Support\Actions;

use App\Models\SupportTicket;
use App\Support\Github;
use Illuminate\Support\Str;
use Throwable;

/**
 * A support ticket also opened as a GitHub issue on the private repo,
 * labelled `support` and assigned to the developer (the Owner, 2026-10-11).
 * The ticket and its email are untouched: this is a copy where the developer
 * works, and GitHub failing is reported and never stops the ticket.
 *
 * Who sent it is by name and role, not email — staff addresses are kept out
 * of the repo. The attachment stays in the app; the issue links to it.
 */
class OpenSupportIssue
{
    public const LABEL = 'support';

    public function execute(SupportTicket $ticket): void
    {
        $api = Github::repo();

        if (! $api) {
            return;
        }

        try {
            Github::openIssue(
                $api,
                $ticket->ticket_number . ': ' . Str::limit(Str::squish($ticket->description), 80),
                $this->body($ticket),
                self::LABEL,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function body(SupportTicket $ticket): string
    {
        $lines = [
            "**Ticket:** {$ticket->ticket_number} — [open in the app](" . route('support-tickets.index') . ')',
            '**From:** ' . Github::quote($ticket->name) . ' (' . str_replace('_', ' ', $ticket->role) . ')',
            '**Sent:** ' . $ticket->created_at->format('Y-m-d H:i T'),
        ];

        if ($ticket->media_path) {
            $lines[] = '**Attachment:** [' . Github::quote($ticket->media_filename) . '](' . route('support-tickets.media', $ticket) . ') (sign in to open)';
        }

        $lines[] = "---\n\n" . Github::quote($ticket->description);

        return implode("\n\n", $lines);
    }
}
