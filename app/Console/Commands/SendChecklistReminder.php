<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ChecklistReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendChecklistReminder extends Command
{
    protected $signature   = 'checklist:remind {type=morning : morning or closing}';
    protected $description = 'Send prep checklist reminder to the Head Chef';

    public function handle(): int
    {
        $type = $this->argument('type');
        $failed = 0;

        // Demo accounts have unroutable @example.test addresses — never email them.
        User::where('role', User::ROLE_HEAD_CHEF)->where('is_demo', false)->each(function (User $chef) use ($type, &$failed) {
            try {
                // Resend answered the 20 August closing reminder with an empty
                // reply (cURL 52) and the reminder was lost outright: mail is
                // the first channel, so the in-app copy was never written
                // either. One transient refusal should not cost a reminder.
                retry(3, fn () => $chef->notify(new ChecklistReminder($type)), 2000);
            } catch (\Throwable $e) {
                // Caught per chef, not per run: there are two Head Chefs, and
                // one bad address must not stop the other from being told.
                $failed++;
                Log::error("Checklist reminder ({$type}) failed for {$chef->email}: " . $e->getMessage());
            }
        });

        if ($failed > 0) {
            $this->error("Checklist reminder ({$type}) failed for {$failed} Head Chef(s) — see the log.");

            return self::FAILURE;
        }

        $this->info("Checklist reminder ({$type}) sent to all Head Chefs.");

        return self::SUCCESS;
    }
}
