<?php

namespace App\Console\Commands;

use App\Support\Github;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The server saying "still up", every 10 minutes, to a closed GitHub issue
 * labelled uptime-heartbeat. `.github/workflows/uptime.yml` opens a
 * site-down issue when that goes quiet.
 *
 * Inside out because nothing outside can reach the site to ask: Cloudflare's
 * Bot Fight Mode challenges GitHub's runners, and the droplet's firewall only
 * lets Cloudflare in. Going quiet also covers the scheduler itself dying,
 * which no error would ever report.
 *
 * Uses the GitHub error-issues token (GITHUB_ISSUES_TOKEN); without one it
 * does nothing.
 */
class UptimeBeat extends Command
{
    public const LABEL = 'uptime-heartbeat';

    protected $signature = 'uptime:beat';

    protected $description = 'Tell GitHub the site is up, after checking the login page from inside the server';

    public function handle(): int
    {
        $api = Github::repo();

        if (! $api) {
            return self::SUCCESS;
        }

        // The login page needs nginx, PHP and the session table, so a 200
        // proves all three. Through nginx locally, not through Cloudflare.
        $page = Http::withHeaders(['Host' => parse_url(config('app.url'), PHP_URL_HOST)])->timeout(15)->get('http://127.0.0.1/login');

        if (! $page->ok()) {
            Log::error("Uptime check: the login page answered HTTP {$page->status()} from inside the server");

            return self::FAILURE;
        }

        $beat = $api->get('issues', ['labels' => self::LABEL, 'state' => 'all', 'per_page' => 1])->throw()->json('0');
        $body = 'Last beat: ' . now()->format('Y-m-d H:i:s T') . "\n\nUpdated every 10 minutes by `php artisan uptime:beat` on the server; `.github/workflows/uptime.yml` opens a site-down issue when it goes quiet. Leave this issue closed.";

        $number = $beat['number'] ?? $api->post('issues', ['title' => 'Uptime heartbeat', 'body' => $body, 'labels' => [self::LABEL]])->throw()->json('number');

        $api->patch("issues/{$number}", ['body' => $body, 'state' => 'closed'])->throw();

        return self::SUCCESS;
    }
}
