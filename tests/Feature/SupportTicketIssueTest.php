<?php

namespace Tests\Feature;

use App\Mail\SupportReport;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A support ticket is also opened as a GitHub issue (the Owner, 2026-10-11). */
class SupportTicketIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake();
        config(['services.github.token' => 'test-token', 'services.github.repo' => 'owner/repo', 'services.github.assignee' => 'someone']);
    }

    private function chef(): User
    {
        return User::factory()->create(['name' => 'Kai', 'email' => 'kai@example.test', 'role' => User::ROLE_JUNIOR_CHEF]);
    }

    public function test_a_ticket_opens_an_assigned_issue(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 204)]);

        $this->actingAs($this->chef())->post('/support', [
            'description' => "The stock-take   page\nwill not save. Ask @sam",
            'media'       => UploadedFile::fake()->image('bug.jpg'),
        ])->assertSessionHas('success');

        $ticket = SupportTicket::sole();

        // Handed to the open-issue workflow, so the bot opens it and the
        // assignee is notified.
        Http::assertSent(function (Request $r) use ($ticket) {
            $issue = $r['inputs'] ?? [];

            return $r->url() === 'https://api.github.com/repos/owner/repo/actions/workflows/open-issue.yml/dispatches'
                && $issue['title'] === "{$ticket->ticket_number}: The stock-take page will not save. Ask @sam"
                && $issue['label'] === 'support'
                && $issue['assignee'] === 'someone'
                && str_contains($issue['body'], 'Kai (junior chef)')
                && str_contains($issue['body'], route('support-tickets.media', $ticket))
                // Nobody on GitHub called sam gets notified from the body.
                && str_contains($issue['body'], "@\u{200B}sam")
                // Staff addresses stay out of the repo.
                && ! str_contains($issue['body'], 'kai@example.test');
        });
    }

    public function test_github_failing_never_stops_the_ticket(): void
    {
        Http::fake(['api.github.com/*' => Http::response('down', 503)]);

        $this->actingAs($this->chef())
            ->post('/support', ['description' => 'Printer is jammed.'])
            ->assertSessionHas('success');

        $this->assertSame(1, SupportTicket::count());
        Mail::assertSent(SupportReport::class);
    }

    public function test_no_token_opens_nothing(): void
    {
        config(['services.github.token' => null]);
        Http::fake();

        $this->actingAs($this->chef())->post('/support', ['description' => 'Hello']);

        Http::assertNothingSent();
    }
}
