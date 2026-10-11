<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/** Live errors become GitHub issues, one open issue per error. */
class GithubIssuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.token' => 'test-token', 'services.github.repo' => 'owner/repo', 'services.github.assignee' => 'someone']);
        $this->forget();
    }

    protected function tearDown(): void
    {
        $this->forget();
        parent::tearDown();
    }

    /** Log through a fresh channel and flush it, as the end of a request does. */
    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel('github')->{$level}($message, $context);
        Log::channel('github')->getLogger()->close();
        $this->forget();
    }

    private function forget(): void
    {
        Log::forgetChannel('github');
        @unlink(storage_path('logs/github-issues.dedup'));
    }

    public function test_no_token_means_no_github_in_the_stack(): void
    {
        $this->assertNotContains('github', config('logging.channels.stack.channels'));
    }

    public function test_an_error_opens_an_issue_and_a_repeat_does_not(): void
    {
        // The open-issue workflow, as GitHub would run it: what it was handed
        // becomes an open issue the next lookup sees.
        $opened = [];
        Http::fake(function (Request $request) use (&$opened) {
            if (str_ends_with($request->url(), '/actions/workflows/open-issue.yml/dispatches')) {
                $opened[] = $request['inputs'];

                return Http::response(null, 204);
            }

            return Http::response($opened);
        });

        $boom = new RuntimeException('Bukku said no');
        $this->log('error', 'Bukku said no', ['exception' => $boom]);
        $this->log('error', 'Bukku said no', ['exception' => $boom]);
        $this->log('warning', 'Only a warning');

        $this->assertCount(1, $opened);
        $this->assertSame('RuntimeException: Bukku said no', $opened[0]['title']);
        $this->assertSame('production-error', $opened[0]['label']);
        $this->assertSame('someone', $opened[0]['assignee']);
        $this->assertStringContainsString('GithubIssuesTest.php:', $opened[0]['body']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer test-token')
            && $r->url() === 'https://api.github.com/repos/owner/repo/actions/workflows/open-issue.yml/dispatches'
            && $r['ref'] === 'main');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/issues'));
    }

    public function test_a_token_that_cannot_run_workflows_still_opens_the_issue(): void
    {
        // Before the token holds Actions: write, GitHub refuses the dispatch.
        Http::fake([
            'api.github.com/*/dispatches' => Http::response(['message' => 'Resource not accessible'], 403),
            'api.github.com/*/issues?*'   => Http::response([]),
            'api.github.com/*/issues'     => Http::response(['number' => 5], 201),
        ]);

        $this->log('error', 'Something broke');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://api.github.com/repos/owner/repo/issues'
            && $r['labels'] === ['production-error'] && $r['assignees'] === ['someone']);
    }

    public function test_github_being_down_does_not_break_logging(): void
    {
        Http::fake(fn () => Http::response('down', 503));

        $this->log('error', 'Something broke');

        Http::assertSentCount(1);
    }
}
