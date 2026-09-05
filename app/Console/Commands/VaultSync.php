<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Regenerates the machine-derived half of the Obsidian vault (docs/vault/_generated).
 *
 * Everything under _generated/ is overwritten on every run and must never be
 * hand-edited — write explanation into the curated notes instead, and link to
 * the generated tables from there.
 *
 * Run by .githooks/pre-commit so the vault ships inside the same commit as the
 * code it describes. Safe to run by hand: `php artisan vault:sync`.
 */
class VaultSync extends Command
{
    protected $signature = 'vault:sync';

    protected $description = 'Regenerate the auto-derived notes in the Obsidian vault';

    private string $out;

    public function handle(): int
    {
        $this->out = base_path('docs/vault/_generated');

        if (! is_dir($this->out)) {
            mkdir($this->out, 0775, true);
        }

        $this->write('Routes.md', $this->routes());
        $this->write('Gates matrix.md', $this->gates());
        $this->write('Models index.md', $this->models());
        $this->write('Migrations index.md', $this->migrations());
        $this->write('Domain actions index.md', $this->actions());
        $this->write('Repo snapshot.md', $this->snapshot());

        $this->info('Vault synced → docs/vault/_generated');

        return self::SUCCESS;
    }

    private function write(string $file, string $body): void
    {
        $header = "---\ngenerated: true\n---\n\n"
            . "> [!warning] Auto-generated — do not edit\n"
            . "> Rewritten by `php artisan vault:sync` on every commit. Put explanation in the curated notes and link here.\n"
            . "> Source: `app/Console/Commands/VaultSync.php` · Back to [[Home]] · [[Vault automation]]\n\n";

        file_put_contents($this->out . '/' . $file, $header . $body);
    }

    /** Every registered web route with its controller, name and middleware. */
    private function routes(): string
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $action = str_replace('App\\Http\\Controllers\\', '', $action);

            $middleware = collect($route->gatherMiddleware())
                ->map(fn ($m) => is_string($m) ? class_basename($m) : 'Closure')
                ->reject(fn ($m) => $m === 'web')
                ->implode(', ');

            $rows[] = sprintf(
                '| `%s` | `%s` | %s | `%s` | %s |',
                implode('/', array_diff($route->methods(), ['HEAD'])),
                '/' . ltrim($route->uri(), '/'),
                $route->getName() ? '`' . $route->getName() . '`' : '—',
                $action === 'Closure' ? 'Closure' : $action,
                $middleware ?: '—'
            );
        }

        sort($rows);

        return "# Routes\n\n"
            . 'All ' . count($rows) . " registered routes, read straight from Laravel's router.\n"
            . "Every controller method behind these is required to open with `Gate::authorize()` — see [[Authorization gates]].\n\n"
            . "| Method | URI | Name | Action | Middleware |\n|---|---|---|---|---|\n"
            . implode("\n", $rows) . "\n";
    }

    /**
     * Every Gate, evaluated against one synthetic user per role, so the matrix
     * is the real answer rather than a hand-kept table that drifts.
     *
     * The synthetic users are never saved and no gate touches the database,
     * so this runs with MariaDB down — which the pre-commit hook relies on.
     */
    private function gates(): string
    {
        $roles = [
            'Owner'       => User::ROLE_OWNER,
            'Head Chef'   => User::ROLE_HEAD_CHEF,
            'Junior Chef' => User::ROLE_JUNIOR_CHEF,
            'Admin'       => User::ROLE_ADMIN,
        ];

        $users = [];
        foreach ($roles as $label => $role) {
            $users[$label] = new User(['role' => $role, 'is_demo' => false]);
        }

        $rows = [];
        foreach (array_keys(Gate::abilities()) as $ability) {
            $cells = [];
            foreach ($users as $user) {
                try {
                    $cells[] = Gate::forUser($user)->allows($ability) ? '✅' : '—';
                } catch (\Throwable) {
                    $cells[] = '?';
                }
            }
            $rows[] = "| `{$ability}` | " . implode(' | ', $cells) . ' |';
        }

        sort($rows);

        return "# Gates matrix\n\n"
            . 'All ' . count($rows) . " gates from `AppServiceProvider::boot()`, evaluated live against a synthetic user per role.\n"
            . "`?` means the gate needs state a synthetic user does not have. Explained in [[Authorization gates]].\n\n"
            . '| Gate | ' . implode(' | ', array_keys($roles)) . " |\n|---|" . str_repeat('---|', count($roles)) . "\n"
            . implode("\n", $rows) . "\n";
    }

    /**
     * Model inventory: table, audit status, relations.
     *
     * Relations are read out of the source with a regex rather than by calling
     * the methods — invoking arbitrary zero-arg model methods would fire real
     * side effects (`Recipe::recalculatePlateCost()` writes to the database).
     */
    private function models(): string
    {
        $rows = [];

        foreach (glob(app_path('Models/*.php')) as $path) {
            $source = file_get_contents($path);
            $class  = 'App\\Models\\' . basename($path, '.php');

            preg_match_all(
                '/public function (\w+)\(\).*?\{\s*return \$this->(hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphTo|hasManyThrough)\(/s',
                $source,
                $matches
            );

            $relations = collect($matches[1])
                ->map(fn ($name, $i) => "`{$name}` " . $matches[2][$i])
                ->implode(', ');

            $table = class_exists($class) ? (new $class)->getTable() : '—';

            $rows[] = sprintf(
                '| `%s` | `%s` | %s | %s |',
                basename($path, '.php'),
                $table,
                Str::contains($source, 'use LogsActivity;') ? '✅' : '—',
                $relations ?: '—'
            );
        }

        return "# Models index\n\n"
            . count($rows) . " Eloquent models. \"Audited\" means the model carries the `LogsActivity` trait — see [[Audit trail]].\n"
            . "Model names are plain code, not wiki-links: there is no note per model, and 37 dead links would drown the graph.\n"
            . "The models that carry real behaviour are explained in [[Code paths index]] and [[Formulas index]].\n\n"
            . "| Model | Table | Audited | Relations |\n|---|---|---|---|\n"
            . implode("\n", $rows) . "\n";
    }

    /** Migration timeline with the tables each one touches. */
    private function migrations(): string
    {
        $rows = [];

        foreach (glob(base_path('database/migrations/*.php')) as $path) {
            $file = basename($path, '.php');
            preg_match_all("/Schema::(create|table|drop|dropIfExists)\('(\w+)'/", file_get_contents($path), $m);

            $tables = collect($m[2])->unique()->map(fn ($t) => "`{$t}`")->implode(', ');

            // 2026_07_05_120001_create_stock_takes_table → date + readable name
            $date = implode('-', array_slice(explode('_', $file), 0, 3));
            $name = str_replace('_', ' ', implode('_', array_slice(explode('_', $file), 4)));

            $rows[] = "| {$date} | {$name} | " . ($tables ?: '—') . ' |';
        }

        return "# Migrations index\n\n"
            . count($rows) . " migrations, oldest first. The schema they build is described in [[Database overview]].\n\n"
            . "| Date | Migration | Tables touched |\n|---|---|---|\n"
            . implode("\n", $rows) . "\n";
    }

    /** Business-logic actions, the only place writes are allowed to originate. */
    private function actions(): string
    {
        // Link each action to the curated note that walks it end to end, rather
        // than to a [[LogSale]] note that does not and will not exist.
        $notes = [
            'LogSale'           => 'Path — Logging a sale',
            'LogPurchase'       => 'Path — Logging a purchase',
            'LogMarketPurchase' => 'Path — Logging a purchase',
            'LogWastage'        => 'Path — Logging wastage',
            'LogProduction'     => 'Path — Logging production',
            'SaveRecipe'        => 'Path — Saving a recipe',
            'IssueFloat'        => 'Petty cash balance',
        ];

        $rows = [];

        foreach (glob(app_path('Domain/*/Actions/*.php')) as $path) {
            $rel     = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
            $name    = basename($path, '.php');
            $context = basename(dirname($path, 2));
            $source  = file_get_contents($path);

            // First line of the class docblock, if there is one.
            preg_match('/\/\*\*\s*\n\s*\* ([^\n@]+)/', $source, $doc);

            $rows[] = sprintf(
                '| `%s` | %s | `%s` | %s | %s | %s |',
                $name,
                $context,
                $rel,
                Str::contains($source, 'DB::transaction') ? '✅' : '—',
                isset($notes[$name]) ? "[[{$notes[$name]}]]" : '—',
                trim($doc[1] ?? '—')
            );
        }

        return "# Domain actions index\n\n"
            . count($rows) . " actions under `app/Domain/<Context>/Actions/`. Controllers stay thin and delegate here — see [[Layering rules]].\n"
            . "\"Transactional\" means the whole write is wrapped in `DB::transaction()`.\n\n"
            . "> [!note] Two features write stock from a controller instead\n"
            . "> Tally reconciliation and stock-take recording have no action class. Both are still transactional. See [[Layering rules]].\n\n"
            . "| Action | Context | File | Transactional | Walkthrough | Purpose |\n|---|---|---|---|---|---|\n"
            . implode("\n", $rows) . "\n";
    }

    /** Size and git position of the codebase at generation time. */
    private function snapshot(): string
    {
        $git = fn (string $cmd) => trim((string) @shell_exec("git -C " . escapeshellarg(base_path()) . " {$cmd} 2>&1"));

        $count = fn (string $pattern) => count(glob(base_path($pattern), GLOB_BRACE));

        // glob() has no recursive mode, and `**` only spans one level — count
        // deep trees (views, tests) by walking them instead of guessing depth.
        $countDeep = function (string $dir, string $suffix): int {
            if (! is_dir(base_path($dir))) {
                return 0;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));

            return count(array_filter(
                iterator_to_array($files),
                fn ($f) => str_ends_with($f->getFilename(), $suffix)
            ));
        };

        // Tab-separated and unquoted on purpose: this runs through cmd.exe on
        // Windows and sh elsewhere, and neither `%x09` nor the rest of the
        // format string carries a character either shell wants to interpret.
        $log = collect(explode("\n", $git('log -15 --date=short --pretty=format:%ad%x09%h%x09%s')))
            ->filter()
            ->map(function (string $line) {
                [$date, $hash, $subject] = array_pad(explode("\t", $line, 3), 3, '');

                return "| {$date} | `{$hash}` | " . str_replace('|', '\\|', $subject) . ' |';
            })
            ->implode("\n");

        return "# Repo snapshot\n\n"
            . 'Taken ' . now()->toDayDateTimeString() . ".\n\n"
            . "> [!note] Git position is one commit behind after a hooked run\n"
            . "> The pre-commit hook generates this **before** the commit exists, so during a commit the\n"
            . "> figures below describe the previous one. Run `php artisan vault:sync` by hand to see HEAD.\n"
            . "> Everything outside this section reads the working tree and is always current.\n\n"
            . "## Position (as of the last commit)\n\n"
            . "| | |\n|---|---|\n"
            . '| Commits | ' . ($git('rev-list --count HEAD') ?: '—') . " |\n"
            . '| HEAD | `' . ($git('rev-parse --short HEAD') ?: '—') . "` |\n"
            . '| Branch | `' . ($git('rev-parse --abbrev-ref HEAD') ?: '—') . "` |\n"
            . '| Released version | `' . \App\Support\ReleaseNotes::CURRENT_VERSION . "` |\n"
            . "\n## Size\n\n"
            . "| Layer | Files |\n|---|---|\n"
            . '| Models | ' . $count('app/Models/*.php') . " |\n"
            . '| Controllers | ' . $count('app/Http/Controllers/{,Auth/}*.php') . " |\n"
            . '| Form Requests | ' . $count('app/Http/Requests/{,Auth/}*.php') . " |\n"
            . '| Domain actions | ' . $count('app/Domain/*/Actions/*.php') . " |\n"
            . '| Middleware | ' . $count('app/Http/Middleware/*.php') . " |\n"
            . '| Console commands | ' . $count('app/Console/Commands/*.php') . " |\n"
            . '| Migrations | ' . $count('database/migrations/*.php') . " |\n"
            . '| Blade views | ' . $countDeep('resources/views', '.blade.php') . " |\n"
            . '| Tests | ' . $countDeep('tests', 'Test.php') . " |\n"
            . "\n## Last 15 commits\n\n"
            . "| Date | Hash | Subject |\n|---|---|---|\n"
            . ($log ?: '| — | — | git unavailable |') . "\n";
    }
}
