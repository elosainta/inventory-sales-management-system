<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The private repo's issues: live errors (App\Logging\GithubIssues), the
 * uptime heartbeat (uptime:beat) and support tickets (OpenSupportIssue).
 *
 * GITHUB_ISSUES_TOKEN in the server .env, a fine-grained token for that one
 * repo with Issues read/write and nothing else. Without it every caller does
 * nothing.
 */
class Github
{
    /** The repo's API (`issues`, `issues/7`), or null when no token is set. */
    public static function repo(): ?PendingRequest
    {
        $github = config('services.github');

        if (! $github['token']) {
            return null;
        }

        return Http::withToken($github['token'])->acceptJson()->timeout(10)->baseUrl("https://api.github.com/repos/{$github['repo']}");
    }

    public static function assignee(): string
    {
        return config('services.github.assignee');
    }

    /**
     * Open an issue, assigned, so that its assignee is NOTIFIED.
     *
     * GitHub never notifies anyone of their own activity, and the token
     * belongs to the assignee — an issue it opened arrived in silence, at
     * night included. So the issue is opened by `.github/workflows/
     * open-issue.yml` as github-actions[bot] instead, which needs the token
     * to hold Actions: read and write. Without that the dispatch is refused
     * and the issue is opened directly, as before: still recorded, silent.
     */
    public static function openIssue(PendingRequest $api, string $title, string $body, string $label): void
    {
        $inputs = ['title' => $title, 'body' => $body, 'label' => $label, 'assignee' => self::assignee()];

        if ($api->post('actions/workflows/open-issue.yml/dispatches', ['ref' => 'main', 'inputs' => $inputs])->successful()) {
            return;
        }

        $api->post('issues', ['title' => $title, 'body' => $body, 'labels' => [$label], 'assignees' => [self::assignee()]])->throw();
    }

    /**
     * Text someone else wrote, made safe to put in an issue: an @ would
     * notify whoever that GitHub user is, so a zero-width space breaks it.
     */
    public static function quote(string $text): string
    {
        return Str::replace('@', "@\u{200B}", $text);
    }
}
