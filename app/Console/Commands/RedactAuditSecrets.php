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
                $fixed += (int) $this->redactAudit($audit, $dryRun);
            }
        });

        $this->info(($dryRun ? '[dry-run] ' : '') . "Redacted hidden-attribute values in {$fixed} audit row(s).");

        return self::SUCCESS;
    }

    /** Whether the row leaked a hidden value; it is only rewritten outside a dry run. */
    private function redactAudit(Audit $audit, bool $dryRun): bool
    {
        $leakedKeys = $this->leakedKeys($audit);

        if (empty($leakedKeys)) {
            return false;
        }

        if (! $dryRun) {
            $audit->forceFill([
                'before' => $this->redact($audit->before ?? [], $leakedKeys),
                'after'  => $this->redact($audit->after ?? [], $leakedKeys),
            ])->saveQuietly();
        }

        return true;
    }

    /** The model's hidden attributes that were written into this audit row. */
    private function leakedKeys(Audit $audit): array
    {
        if (! class_exists($audit->auditable_type)) {
            return [];
        }

        $hidden = array_flip((new $audit->auditable_type)->getHidden());

        return array_keys(array_intersect_key($hidden, ($audit->before ?? []) + ($audit->after ?? [])));
    }

    private function redact(array $values, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
