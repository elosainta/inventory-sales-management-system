<?php

use Illuminate\Support\Facades\Schedule;

// Where a scheduled run says what it did.
//
// Laravel sends a scheduled command's output to /dev/null unless told
// otherwise, so a command that finishes cleanly leaves no trace of *which*
// branch it took. On 2026-08-31 that meant the monthly feedback run could be
// proven to have exited 0, but not whether it had emailed a report or skipped
// an empty month — the answer had to be reconstructed from a row count.
// Appending costs a few lines a day and makes the scheduler self-explaining.
$log = storage_path('logs/schedule.log');

Schedule::command('checklist:remind morning')->dailyAt('09:00')->timezone('Asia/the site')->appendOutputTo($log);
Schedule::command('checklist:remind closing')->dailyAt('17:00')->timezone('Asia/the site')->appendOutputTo($log);
Schedule::command('feedback:monthly-report')->lastDayOfMonth('23:30')->timezone('Asia/the site')->appendOutputTo($log);

// No --audit-days here on purpose: the scheduled run only ever prunes the
// low-stakes login history. Pruning the audit trail is a deliberate, manual
// call the Owner makes with `php artisan records:prune --audit-days=N`.
Schedule::command('records:prune')->weeklyOn(0, '03:00')->timezone('Asia/the site')->appendOutputTo($log);
