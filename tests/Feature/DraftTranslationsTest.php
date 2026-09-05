<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The translation drafter.
 *
 * The rule worth protecting is that it never writes lang/id.json. A machine
 * translation reaching a chef unread is the failure this design exists to
 * prevent — drafts go to a separate file that a person merges by hand.
 *
 * The gap tests run against a scratch locale rather than `id`, because `id` is
 * currently complete: every __() string in every view already resolves, so the
 * real command short-circuits before it would ever call Ollama.
 */
class DraftTranslationsTest extends TestCase
{
    private string $locale = 'zz-test';

    protected function setUp(): void
    {
        parent::setUp();

        // Ollama is a developer's local process — absent in CI, absent on the
        // droplet. An unmatched request must be an error, never a real call.
        Http::preventStrayRequests();

        // Empty locale: every string in every view counts as missing.
        file_put_contents(base_path("lang/{$this->locale}.json"), "{}\n");
    }

    protected function tearDown(): void
    {
        foreach ([".json", ".draft.json"] as $suffix) {
            $path = base_path("lang/{$this->locale}{$suffix}");
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_never_writes_the_live_translation_file(): void
    {
        $live   = base_path('lang/id.json');
        $before = file_get_contents($live);

        Http::fake(['*/api/chat' => Http::response([
            'message' => ['content' => json_encode(['Save' => 'Simpan'])],
        ])]);

        $this->artisan('lang:draft', ['--locale' => $this->locale])->assertSuccessful();

        $this->assertSame($before, file_get_contents($live), 'lang/id.json must never be written by this command');
        $this->assertFileExists(base_path("lang/{$this->locale}.draft.json"));
    }

    public function test_it_discards_entries_the_model_invented(): void
    {
        // Small models add keys nobody asked about. Those must not reach the
        // draft file, where a reviewer might take them for real strings.
        Http::fake(['*/api/chat' => Http::response([
            'message' => ['content' => json_encode([
                'Save'                 => 'Simpan',
                'A string nobody used' => 'Halusinasi',
            ])],
        ])]);

        $this->artisan('lang:draft', ['--locale' => $this->locale])->assertSuccessful();

        $drafts = json_decode(file_get_contents(base_path("lang/{$this->locale}.draft.json")), true);

        $this->assertArrayNotHasKey('A string nobody used', $drafts);
    }

    public function test_it_reports_cleanly_when_ollama_is_not_running(): void
    {
        // The common case on a machine that has not started Ollama, and the
        // permanent case on the droplet. It must say so, not stack-trace.
        Http::fake(['*/api/chat' => Http::response('connection refused', 500)]);

        $this->artisan('lang:draft', ['--locale' => $this->locale])
            ->expectsOutputToContain('Is it running?')
            ->assertFailed();
    }

    public function test_a_fully_translated_locale_does_no_work(): void
    {
        // True of `id` in this repo today — it should say so and send nothing
        // rather than spin up a GPU for no reason.
        $this->artisan('lang:draft')->assertSuccessful();

        Http::assertNothingSent();
    }
}
