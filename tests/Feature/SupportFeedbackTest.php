<?php

namespace Tests\Feature;

use App\Mail\SupportReport;
use App\Models\FeedbackEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_JUNIOR_CHEF): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** A complete, valid set of the five 1–5 ratings. */
    private function ratings(int $value = 4): array
    {
        return [
            'rating_cleanliness'   => $value,
            'rating_safety'        => $value,
            'rating_organisation'  => $value,
            'rating_teamwork'      => $value,
            'rating_communication' => $value,
        ];
    }

    // ---------- SUPPORT FORM ----------

    public function test_guest_cannot_access_support(): void
    {
        $this->get('/support')->assertRedirect('/login');
    }

    public function test_user_can_view_support_form(): void
    {
        $this->actingAs($this->user())->get('/support')->assertOk();
    }

    public function test_support_report_sends_email(): void
    {
        Mail::fake();

        // The destination is SUPPORT_EMAIL from the environment. This asserted
        // one developer's personal address, so it passed only on the machine
        // whose .env happened to hold it. Pin the config instead: what matters
        // is that the report goes wherever support is configured to go.
        config(['services.support.email' => 'support@example.test']);

        $this->actingAs($this->user())
            ->post('/support', ['description' => 'The dashboard chart is broken.'])
            ->assertRedirect(route('support.index'))
            ->assertSessionHas('success');

        Mail::assertSent(SupportReport::class, fn ($mail) => $mail->hasTo('support@example.test'));
    }

    public function test_support_report_accepts_valid_attachment(): void
    {
        Mail::fake();

        $this->actingAs($this->user())
            ->post('/support', [
                'description' => 'See screenshot.',
                'media'       => UploadedFile::fake()->image('bug.jpg'),
            ])
            ->assertSessionHas('success');

        Mail::assertSent(SupportReport::class);
    }

    public function test_support_requires_description(): void
    {
        Mail::fake();

        $this->actingAs($this->user())
            ->post('/support', ['description' => ''])
            ->assertSessionHasErrors('description');

        Mail::assertNothingSent();
    }

    public function test_support_rejects_disallowed_file_type(): void
    {
        Mail::fake();

        $this->actingAs($this->user())
            ->post('/support', [
                'description' => 'malware',
                'media'       => UploadedFile::fake()->create('evil.exe', 10),
            ])
            ->assertSessionHasErrors('media');

        Mail::assertNothingSent();
    }

    // ---------- PEER FEEDBACK ----------

    public function test_guest_cannot_access_feedback(): void
    {
        $this->get('/feedback')->assertRedirect('/login');
    }

    public function test_part_timers_cannot_access_feedback(): void
    {
        // Admin was the role asserted here until 2026-09-03, when the Owner
        // widened it to every feature but the dashboard. Peer feedback stays
        // anonymous to everyone either way — only the Owner sees who sent
        // what (export-feedback-pdf and the owner view), and that is unchanged.
        $this->actingAs($this->user(User::ROLE_PART_TIMER))
            ->get('/feedback')
            ->assertForbidden();
    }

    public function test_staff_can_submit_feedback(): void
    {
        $from = $this->user();
        $to   = $this->user();

        $this->actingAs($from)
            ->post('/feedback', array_merge([
                'to_user_id' => $to->id,
                'comment'    => 'Great work keeping the line clean.',
            ], $this->ratings(5)))
            ->assertRedirect(route('feedback.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('feedback_entries', [
            'from_user_id'       => $from->id,
            'to_user_id'         => $to->id,
            'rating_cleanliness' => 5,
            'comment'            => 'Great work keeping the line clean.',
        ]);
    }

    public function test_cannot_give_feedback_about_self(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/feedback', array_merge(['to_user_id' => $user->id], $this->ratings()))
            ->assertSessionHasErrors('to_user_id');

        $this->assertDatabaseCount('feedback_entries', 0);
    }

    public function test_feedback_requires_all_five_ratings(): void
    {
        $to = $this->user();

        $this->actingAs($this->user())
            ->post('/feedback', ['to_user_id' => $to->id, 'rating_cleanliness' => 4])
            ->assertSessionHasErrors([
                'rating_safety',
                'rating_organisation',
                'rating_teamwork',
                'rating_communication',
            ]);

        $this->assertDatabaseCount('feedback_entries', 0);
    }

    public function test_ratings_must_be_between_one_and_five(): void
    {
        $to = $this->user();

        $this->actingAs($this->user())
            ->post('/feedback', array_merge(['to_user_id' => $to->id], $this->ratings(), ['rating_cleanliness' => 6]))
            ->assertSessionHasErrors('rating_cleanliness');
    }

    public function test_owner_cannot_submit_feedback(): void
    {
        $owner = $this->user(User::ROLE_OWNER);
        $to    = $this->user();

        $this->actingAs($owner)
            ->post('/feedback', array_merge(['to_user_id' => $to->id], $this->ratings()))
            ->assertForbidden();
    }

    public function test_recipient_does_not_see_sender_identity(): void
    {
        $sender    = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'name' => 'Zorro The Sender']);
        $recipient = $this->user();

        FeedbackEntry::create(array_merge([
            'from_user_id' => $sender->id,
            'to_user_id'   => $recipient->id,
            'comment'      => 'Nicely organised prep.',
        ], $this->ratings()));

        // assertDontSee was too blunt: the page also carries the "Choose a
        // teammate" dropdown, which lists every colleague by name, so the
        // sender appears there legitimately and the test failed on its own
        // rating form. What must not leak is the name against the feedback —
        // that block renders "From a teammate" and nothing else.
        $html = $this->actingAs($recipient)
            ->get('/feedback')
            ->assertOk()
            ->assertSee('Nicely organised prep.')
            ->assertSee('From a teammate')
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'Zorro The Sender'),
            'the sender may appear once in the teammate picker and nowhere else',
        );
    }

    public function test_owner_sees_sender_identity(): void
    {
        $sender    = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'name' => 'Zorro The Sender']);
        $recipient = $this->user();

        FeedbackEntry::create(array_merge([
            'from_user_id' => $sender->id,
            'to_user_id'   => $recipient->id,
            'comment'      => 'Nicely organised prep.',
        ], $this->ratings()));

        $this->actingAs($this->user(User::ROLE_OWNER))
            ->get('/feedback')
            ->assertOk()
            ->assertSee('Zorro The Sender');
    }

    public function test_non_owner_cannot_export_feedback_pdf(): void
    {
        $this->actingAs($this->user())
            ->get('/feedback/export/pdf?month=' . now()->format('Y-m'))
            ->assertForbidden();
    }

    public function test_owner_can_export_feedback_pdf(): void
    {
        $owner = $this->user(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->get('/feedback/export/pdf?month=' . now()->format('Y-m'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_pdf_rejects_malformed_month_without_crashing(): void
    {
        $owner = $this->user(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->get('/feedback/export/pdf?month=garbage')
            ->assertSessionHasErrors('month');
    }
}
