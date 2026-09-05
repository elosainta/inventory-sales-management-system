<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\LoginHistory;
use Illuminate\Console\Command;

class PruneOldRecords extends Command
{
    protected $signature = 'records:prune
        {--login-days=180 : Delete login_histories rows older than this many days}
        {--audit-days= : Delete audits rows older than this many days. Omit to leave the audit trail untouched.}
        {--dry-run : Show what would be deleted without deleting it}';

    protected $description = 'Purge old login history, and optionally old audit-log rows, past a retention window.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->pruneLoginHistory((int) $this->option('login-days'), $dryRun);

        // Unlike login history, the audit trail is the business's own record of
        // who changed what - it's never pruned unless someone explicitly asks
        // for a window, so a scheduled run of this command can never silently
        // erase compliance history nobody chose to discard.
        $auditDays = $this->option('audit-days');
        if ($auditDays === null) {
            $this->info('audits left untouched (pass --audit-days=N to prune it too).');
            return self::SUCCESS;
        }

        $this->pruneAudits((int) $auditDays, $dryRun);

        return self::SUCCESS;
    }

    private function pruneLoginHistory(int $days, bool $dryRun): void
    {
        $query = LoginHistory::where('logged_in_at', '<', now()->subDays($days));
        $count = $query->count();

        $this->info(($dryRun ? 'Would delete ' : 'Deleted ') . "{$count} login_histories row(s) older than {$days} days.");

        if (! $dryRun) {
            $query->delete();
        }
    }

    private function pruneAudits(int $days, bool $dryRun): void
    {
        $query = Audit::where('created_at', '<', now()->subDays($days));
        $count = $query->count();

        $this->info(($dryRun ? 'Would delete ' : 'Deleted ') . "{$count} audits row(s) older than {$days} days.");

        if (! $dryRun) {
            $query->delete();
        }
    }
}
