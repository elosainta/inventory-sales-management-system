<?php

namespace App\Logging;

use App\Support\Github;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Throwable;

/**
 * Errors from the live app opened as GitHub issues on the private repo.
 * Turned on by GITHUB_ISSUES_TOKEN in the server .env (a fine-grained token,
 * that one repo, Issues read and write and nothing else); without it
 * config/logging.php leaves this channel out of the stack.
 *
 * One open issue per error: each carries a fingerprint (exception class and
 * the file and line it was thrown from), and an error whose fingerprint is
 * already on an open issue opens nothing. Close the issue once fixed and the
 * next occurrence opens a new one.
 */
class GithubIssues extends AbstractProcessingHandler
{
    public const LABEL = 'production-error';

    public function __invoke(array $config): Logger
    {
        $handler = new self(Level::Error);

        // The same error within five minutes is checked once, not once per
        // request: an outage would otherwise ask GitHub hundreds of times.
        // Records are held until the request ends, after the page is sent.
        // ponytail: two workers can still both miss the open issue in the same
        // instant and open two; close the spare, or lock if it ever matters.
        $deduped = new DeduplicationHandler($handler, storage_path('logs/github-issues.dedup'), Level::Error, 300);

        // GitHub being down must never turn a logged error into a second one.
        return new Logger('github', [new WhatFailureGroupHandler([$deduped])]);
    }

    protected function write(LogRecord $record): void
    {
        $e = $record->context['exception'] ?? null;
        $e = $e instanceof Throwable ? $e : null;
        $fingerprint = substr(sha1($e ? $e::class . $e->getFile() . $e->getLine() : $record->message), 0, 12);

        $api = Github::repo();

        if (! $api) {
            return;
        }

        $open   = $api->get('issues', ['labels' => self::LABEL, 'state' => 'open', 'per_page' => 100])->throw()->json();
        $known  = collect($open)->contains(fn ($issue) => str_contains($issue['body'] ?? '', "fingerprint: {$fingerprint}"));

        if ($known) {
            return;
        }

        Github::openIssue(
            $api,
            Str::limit(($e ? class_basename($e) . ': ' : '') . $record->message, 120),
            $this->body($record, $e, $fingerprint),
            self::LABEL,
        );
    }

    /** What happened and where — frames without their arguments, which can hold passwords. */
    private function body(LogRecord $record, ?Throwable $e, string $fingerprint): string
    {
        $where = fn (?string $file, ?int $line) => $file ? Str::after($file, base_path() . DIRECTORY_SEPARATOR) . ":{$line}" : '(internal)';

        $lines = [
            '**Message:** ' . $record->message,
            '**When:** ' . $record->datetime->setTimezone(new \DateTimeZone(config('app.timezone')))->format('Y-m-d H:i:s T'),
            '**From:** ' . (app()->runningInConsole() ? 'artisan / scheduler / queue' : request()->method() . ' /' . ltrim(request()->path(), '/')),
            '**User id:** ' . (auth()->id() ?? 'not signed in'),
        ];

        if ($e) {
            $frames = collect($e->getTrace())->take(15)->map(fn ($f) => $where($f['file'] ?? null, $f['line'] ?? null) . '  ' . ($f['class'] ?? '') . ($f['type'] ?? '') . $f['function']);
            $lines[] = '**Thrown at:** `' . $where($e->getFile(), $e->getLine()) . '` (' . $e::class . ')';
            $lines[] = "```\n" . $frames->implode("\n") . "\n```";
        }

        $lines[] = "<!-- fingerprint: {$fingerprint} -->";

        return implode("\n\n", $lines);
    }
}
