<?php

namespace App\Console\Commands;

use App\Models\Audit;
use Illuminate\Console\Command;

class RedactAuditSecrets extends Command
{
    protected $signature = 'audits:redact-secrets {--dry-run}';

    protected $description = 'One-time cleanup: redact hidden-attribute values (e.g. password hashes) that leaked into existing audit rows before LogsActivity started stripping them.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        // The event itself (a password was changed, by whom, when) stays -
        // only the leaked value is overwritten. Deleting the row would erase
        // real audit history to fix a data-hygiene problem, which is a worse
        // trade than replacing the value with a placeholder.
        Audit::where('action', 'updated')->chunkById(200, function ($audits) use (&$fixed, $dryRun) {
            foreach ($audits as $audit) {
                if (! class_exists($audit->auditable_type)) {
                    continue;
                }

                $hidden = array_flip((new $audit->auditable_type)->getHidden());
                if (empty($hidden)) {
                    continue;
                }

                $before = $audit->before ?? [];
                $after  = $audit->after ?? [];
                $leakedKeys = array_keys(array_intersect_key($hidden, $before + $after));

                if (empty($leakedKeys)) {
                    continue;
                }

                $fixed++;
                if ($dryRun) {
                    continue;
                }

                foreach ($leakedKeys as $key) {
                    if (array_key_exists($key, $before)) {
                        $before[$key] = '[redacted]';
                    }
                    if (array_key_exists($key, $after)) {
                        $after[$key] = '[redacted]';
                    }
                }

                $audit->forceFill(['before' => $before, 'after' => $after])->saveQuietly();
            }
        });

        $this->info(($dryRun ? '[dry-run] ' : '') . "Redacted hidden-attribute values in {$fixed} audit row(s).");

        return self::SUCCESS;
    }
}
