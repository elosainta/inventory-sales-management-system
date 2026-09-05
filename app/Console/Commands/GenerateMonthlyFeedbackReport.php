<?php

namespace App\Console\Commands;

use App\Models\FeedbackEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateMonthlyFeedbackReport extends Command
{
    protected $signature   = 'feedback:monthly-report {--month= : Y-m to generate for (defaults to current month)}';
    protected $description = 'Email the monthly peer-feedback report to the owner. A month with no entries still sends a short note.';

    public function handle(): int
    {
        $monthStr = $this->option('month') ?? now()->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            $this->error("Invalid --month '{$monthStr}'. Expected format Y-m (e.g. 2026-07).");
            return self::FAILURE;
        }

        [$entries, $monthLabel, $questionAverages, $perPerson] = FeedbackEntry::monthReport($monthStr);

        // Never the demo account — its @example.test address is unroutable.
        $owner = User::where('role', User::ROLE_OWNER)->where('is_demo', false)->first();

        if (! $owner) {
            $this->error('No owner account found.');
            return self::FAILURE;
        }

        // An empty month used to return here without sending anything, which
        // made "nobody gave feedback" and "the email failed" look identical
        // from the Owner's inbox — and for the first four months of this
        // feature's life every month was empty, so the Owner saw silence and
        // reasonably read it as broken. A short note costs one email a month
        // and makes absence mean something.
        $isEmpty = $entries->isEmpty();

        $subject = $isEmpty
            ? "[ISMS] Peer Feedback — {$monthLabel} (nothing submitted)"
            : "[ISMS] Peer Feedback Report — {$monthLabel}";

        $body = $isEmpty
            ? "<p>Hi {$owner->name},</p>"
                . "<p>No peer feedback was submitted in <strong>{$monthLabel}</strong>, so there is no report to attach.</p>"
                . "<p>This note goes out even on an empty month, so that no email always means something is wrong "
                . "rather than simply that nobody rated anyone.</p>"
                . "<p>— Inventory, Sales and Management System</p>"
            : "<p>Hi {$owner->name},</p>"
                . "<p>Attached is the peer feedback report for <strong>{$monthLabel}</strong> — "
                . "{$entries->count()} rating(s) exchanged this month.</p>"
                . "<p>Sender identities are included; they are visible only to you.</p>"
                . "<p>— Inventory, Sales and Management System</p>";

        $pdfContent = $isEmpty
            ? null
            : Pdf::loadView('pdfs.feedback', compact('entries', 'monthLabel', 'questionAverages', 'perPerson'))->output();

        $filename = 'feedback-' . $monthStr . '.pdf';

        // Retried like every other send in this app. Mail leaves over the
        // Resend HTTPS API (DigitalOcean blocks SMTP), and Resend does wobble
        // — a cURL 52 cost the closing reminder outright on 2026-08-20. This
        // one runs once a month, so a single transient failure would lose the
        // report until the next month rather than the next morning.
        retry(3, fn () => Mail::send([], [], function ($message) use ($owner, $subject, $body, $pdfContent, $filename) {
            $message->to($owner->email, $owner->name)
                ->subject($subject)
                ->html($body);

            if ($pdfContent !== null) {
                $message->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
            }
        }), 2000);

        $this->info($isEmpty
            ? "No feedback for {$monthStr} — empty-month note emailed to {$owner->email}."
            : "Feedback report for {$monthLabel} emailed to {$owner->email} ({$entries->count()} entries).");

        return self::SUCCESS;
    }
}
