<?php

namespace Tests\Feature;

use App\Models\FeedbackEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The monthly peer-feedback email.
 *
 * The rule worth protecting is the quiet one: an empty month still sends.
 * Until 2026-09-01 it returned early and sent nothing, so for the first four
 * months of the feature's life the Owner received no email at all — and had no
 * way to tell that apart from a broken mailer. Absence has to mean something.
 */
class MonthlyFeedbackReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The messages the array transport actually collected.
     *
     * Mail::fake() is no use here: the command sends a raw message, and
     * MailFake::send() never invokes the callback, so the subject, recipient
     * and attachment it is supposed to be asserting on are never built. The
     * array transport (already the default in phpunit.xml) records the real
     * thing, and exercises the retry wrapper on the way through.
     *
     * @return array<int, \Symfony\Component\Mime\Email>
     */
    private function sent(): array
    {
        return Mail::getSymfonyTransport()->messages()
            ->map(fn ($message) => $message->getOriginalMessage())
            ->all();
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER, 'is_demo' => false]);
    }

    private function entryIn(string $month): void
    {
        FeedbackEntry::create([
            'from_user_id'         => User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF])->id,
            'to_user_id'           => User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF])->id,
            'rating_cleanliness'   => 4,
            'rating_safety'        => 4,
            'rating_organisation'  => 4,
            'rating_teamwork'      => 4,
            'rating_communication' => 4,
            'comment'              => 'Solid month.',
        ])
            // created_at is not fillable, so create() silently dropped it and
            // stamped now() — which meant this fixture always landed in the
            // CURRENT month. The test passed all through August by coincidence
            // and started failing on 1 September, when "this month" moved on.
            ->forceFill(['created_at' => $month . '-12 09:30:00'])
            ->save();
    }

    public function test_a_month_with_no_feedback_still_emails_the_owner(): void
    {
        $owner = $this->owner();

        $this->artisan('feedback:monthly-report', ['--month' => '2026-07'])
            ->assertSuccessful();

        $sent = $this->sent();

        $this->assertCount(1, $sent);
        $this->assertSame($owner->email, $sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('nothing submitted', $sent[0]->getSubject());
        // Nothing to attach, and an empty PDF would be worse than none.
        $this->assertCount(0, $sent[0]->getAttachments());
    }

    public function test_a_month_with_feedback_sends_the_report(): void
    {
        $owner = $this->owner();
        $this->entryIn('2026-08');

        $this->artisan('feedback:monthly-report', ['--month' => '2026-08'])
            ->assertSuccessful();

        $sent = $this->sent();

        $this->assertCount(1, $sent);
        $this->assertSame($owner->email, $sent[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Peer Feedback Report', $sent[0]->getSubject());
        $this->assertStringNotContainsString('nothing submitted', $sent[0]->getSubject());
        $this->assertCount(1, $sent[0]->getAttachments());
    }

    public function test_the_demo_owner_is_never_the_recipient(): void
    {
        User::factory()->create(['role' => User::ROLE_OWNER, 'is_demo' => true]);

        // Only a demo owner exists, and its @example.test address is
        // unroutable — better to fail loudly than to send into a black hole.
        $this->artisan('feedback:monthly-report', ['--month' => '2026-07'])
            ->assertFailed();

        $this->assertSame([], $this->sent());
    }

    public function test_a_malformed_month_sends_nothing(): void
    {
        $this->owner();

        $this->artisan('feedback:monthly-report', ['--month' => 'August'])
            ->assertFailed();

        $this->assertSame([], $this->sent());
    }
}
