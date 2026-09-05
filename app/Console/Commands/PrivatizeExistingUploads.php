<?php

namespace App\Console\Commands;

use App\Models\MarketPurchase;
use App\Models\Purchase;
use App\Models\SectionCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeExistingUploads extends Command
{
    protected $signature = 'uploads:privatize {--dry-run}';

    protected $description = 'One-time migration: move receipts and check photos uploaded before the private-disk fix from the public disk onto the private disk.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->migrate(Purchase::class, 'receipt_path', $dryRun);
        $this->migrate(MarketPurchase::class, 'receipt_path', $dryRun);
        $this->migrate(SectionCheck::class, 'photo_path', $dryRun);

        return self::SUCCESS;
    }

    // Both disks store the same relative path (receipts/xxx.jpg), so moving
    // the physical file is enough - no column ever needs rewriting, which is
    // what keeps this safe to run more than once or interrupt partway.
    private function migrate(string $modelClass, string $column, bool $dryRun): void
    {
        $rows = $modelClass::whereNotNull($column)->get();
        $moved = 0;
        $alreadyPrivate = 0;
        $missing = 0;

        foreach ($rows as $row) {
            $path = $row->{$column};

            if (Storage::disk('local')->exists($path)) {
                $alreadyPrivate++;
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $missing++;
                continue;
            }

            if (! $dryRun) {
                Storage::disk('local')->put($path, Storage::disk('public')->get($path));
                Storage::disk('public')->delete($path);
            }

            $moved++;
        }

        $label = class_basename($modelClass) . '.' . $column;
        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}{$label}: moved {$moved}, already private {$alreadyPrivate}, missing {$missing} (of {$rows->count()} rows)");
    }
}
