<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ReleaseNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The page rendered three section keys while the notes used five: 41
     * releases written under 'changed' and 8 under 'fixed' showed only their
     * summary (found 2026-10-02). A key the page does not render fails here.
     */
    public function test_every_release_note_is_on_the_page(): void
    {
        $page = $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('about.index'))
            ->assertOk();

        $notes = collect(ReleaseNotes::all())
            ->flatMap(fn (array $release) => Arr::flatten(Arr::except($release, ['version', 'date', 'summary', 'commits'])));

        foreach ($notes as $note) {
            $page->assertSeeText($note);
        }
    }
}
