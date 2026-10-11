<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The heartbeat the GitHub uptime workflow watches for going quiet. */
class UptimeBeatTest extends TestCase
{
    private function withGithubToken(): void
    {
        config(['services.github.token' => 'test-token', 'services.github.repo' => 'owner/repo']);
    }

    public function test_no_token_means_no_beat(): void
    {
        Http::fake();

        $this->artisan('uptime:beat')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_healthy_site_updates_the_heartbeat_issue(): void
    {
        $this->withGithubToken();
        Http::fake([
            '127.0.0.1/*'                    => Http::response('login page'),
            'api.github.com/*/issues?*'      => Http::response([['number' => 7]]),
            'api.github.com/*/issues/7'      => Http::response(['number' => 7]),
        ]);

        $this->artisan('uptime:beat')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->url() === 'https://api.github.com/repos/owner/repo/issues/7'
            && $r['state'] === 'closed' && str_contains($r['body'], 'Last beat:'));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_the_first_beat_creates_the_heartbeat_issue(): void
    {
        $this->withGithubToken();
        Http::fake([
            '127.0.0.1/*'                    => Http::response('login page'),
            'api.github.com/*/issues?*'      => Http::response([]),
            'api.github.com/repos/owner/repo/issues' => Http::response(['number' => 3], 201),
            'api.github.com/*/issues/3'      => Http::response(['number' => 3]),
        ]);

        $this->artisan('uptime:beat')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['labels'] === ['uptime-heartbeat']);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === 'https://api.github.com/repos/owner/repo/issues/3');
    }

    public function test_a_broken_login_page_means_no_beat(): void
    {
        $this->withGithubToken();
        Http::fake(['127.0.0.1/*' => Http::response('down', 502)]);

        $this->artisan('uptime:beat')->assertFailed();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.github.com'));
    }
}
