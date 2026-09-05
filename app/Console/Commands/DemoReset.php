<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Rebuild the demo sandbox database as a fresh, isolated clone of the live database.';

    /**
     * Tables whose SCHEMA is cloned but whose ROWS are not.
     *
     * The sandbox exists to be handed to someone for training, so it must not
     * carry anything that is a credential, a personal record, or a pointer at a
     * real uploaded file. Everything operational — inventory, recipes, sales,
     * purchases, stock takes — is still cloned, which is what makes the demo
     * realistic. A new table is cloned in full unless it is named here.
     */
    private const SKIP_DATA = [
        // Credentials and request state.
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
        // Personal records. Demo users create their own; cloning real ones puts
        // staff data in an account that is deliberately given away.
        'leave_applications', 'leave_attachments', 'feedback_entries',
        'feedback_attachments', 'compliance_reports', 'login_histories',
        'notifications', 'audits',
        // Rows pointing at real uploads on the shared storage volume — a demo
        // user with view-sales would otherwise pull up real receipt photos.
        'sale_attachments',
    ];

    /**
     * Copies the live schema + data into the demo connection table by table.
     * Demo/training logins are swapped onto this database at runtime, so this
     * gives them a realistic, fully-working copy of the kitchen to play with —
     * and running it again wipes whatever they changed and starts them over.
     *
     * The empty demo database must already exist and the app DB user must be
     * granted on it (a one-time root step — see the note printed on failure).
     */
    public function handle(): int
    {
        $src  = DB::connection('mariadb');
        $dst  = DB::connection('mariadb_demo');
        $name = $dst->getDatabaseName();

        try {
            $dst->getPdo();
        } catch (Throwable $e) {
            $this->error("Cannot reach the demo database \"{$name}\".");
            $this->newLine();
            $this->warn('Provision it once as a DB admin/root, then re-run this command:');
            $this->line("  CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->line("  GRANT ALL PRIVILEGES ON `{$name}`.* TO '".config('database.connections.mariadb.username')."'@'%';");
            $this->line('  FLUSH PRIVILEGES;');

            return self::FAILURE;
        }

        if (! $this->confirm("This ERASES everything in \"{$name}\" and re-clones it from live. Continue?", true)) {
            return self::SUCCESS;
        }

        $tables = array_map(
            fn ($row) => array_values((array) $row)[0],
            $src->select('SHOW TABLES')
        );

        $dst->statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop whatever is currently in the demo database.
        foreach (array_map(fn ($row) => array_values((array) $row)[0], $dst->select('SHOW TABLES')) as $existing) {
            $dst->statement("DROP TABLE IF EXISTS `{$existing}`");
        }

        $this->withProgressBar($tables, function (string $table) use ($src, $dst) {
            // Recreate the table from the live definition.
            $createSql = (array) $src->select("SHOW CREATE TABLE `{$table}`")[0];
            $dst->statement($createSql['Create Table']);

            // Schema only for these — the table must exist so the app works,
            // but its rows must not follow live data into the sandbox.
            if (in_array($table, self::SKIP_DATA, true)) {
                return;
            }

            // Stream rows across in batches so large tables (e.g. sales) don't
            // load entirely into memory.
            $buffer = [];
            foreach ($src->table($table)->cursor() as $row) {
                $buffer[] = (array) $row;
                if (count($buffer) >= 500) {
                    $dst->table($table)->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                $dst->table($table)->insert($buffer);
            }
        });

        // Real staff rows are kept so names still render on cloned records, but
        // their password hashes must not sit in a database that a handed-out
        // login queries. Replaced with an unguessable hash nobody holds the
        // plaintext for; the seeded demo logins keep their own password.
        $scrubbed = $dst->table('users')->where('is_demo', false)->update([
            'password'       => Hash::make(Str::random(64)),
            'remember_token' => null,
        ]);

        $dst->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine(2);
        $this->info("Demo sandbox \"{$name}\" rebuilt from live — ".count($tables).' tables cloned, '
            .count(self::SKIP_DATA).' emptied of data, '."{$scrubbed} live password hashes scrubbed.");

        return self::SUCCESS;
    }
}
