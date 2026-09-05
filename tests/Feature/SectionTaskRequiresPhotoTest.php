<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\SectionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Requires photo proof" could be ticked but never unticked.
 *
 * An unticked checkbox posts nothing at all, and both controller methods read
 * `$data['requires_photo'] ?? true` — so absence became "yes". Every save
 * turned the flag back on, and on production all 10 tasks were
 * requires_photo = true with not one false: nobody had ever managed to turn it
 * off. Both forms now pair the checkbox with a hidden "0", the same fix this
 * codebase already uses for is_open_order on sales.
 *
 * These post what the browser posts, which is the only shape that would have
 * caught it — a test that passes requires_photo => false explicitly passes
 * against the broken code too.
 */
class SectionTaskRequiresPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    public function test_unticking_the_box_on_a_new_task_actually_saves(): void
    {
        $section = Section::create(['name' => 'Hot Section']);

        // Exactly what the form sends with the box unticked: the hidden "0"
        // and nothing else.
        $this->actingAs($this->owner())
            ->post(route('sections.tasks.store', $section), [
                'title'          => 'Wipe the pass',
                'requires_photo' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse(
            SectionTask::firstOrFail()->requires_photo,
            'unticking "Requires photo proof" on a new task must save as false',
        );
    }

    public function test_unticking_the_box_on_an_existing_task_actually_saves(): void
    {
        $section = Section::create(['name' => 'Hot Section']);
        $task = SectionTask::create([
            'section_id'     => $section->id,
            'title'          => 'Chiller Check',
            'sort_order'     => 0,
            'requires_photo' => true,
        ]);

        $this->actingAs($this->owner())
            ->patch(route('sections.tasks.update', [$section, $task]), [
                'title'          => 'Chiller Check',
                'requires_photo' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($task->fresh()->requires_photo);
    }

    public function test_ticking_the_box_still_saves_as_true(): void
    {
        $section = Section::create(['name' => 'Hot Section']);

        // Ticked: the browser sends the hidden 0 and then the checkbox 1, and
        // the later value wins. Only the "1" reaches PHP.
        $this->actingAs($this->owner())
            ->post(route('sections.tasks.store', $section), [
                'title'          => 'Photograph the grill',
                'requires_photo' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue(SectionTask::firstOrFail()->requires_photo);
    }

    public function test_a_post_that_omits_the_field_entirely_still_defaults_to_requiring_a_photo(): void
    {
        $section = Section::create(['name' => 'Hot Section']);

        $this->actingAs($this->owner())
            ->post(route('sections.tasks.store', $section), ['title' => 'No field posted'])
            ->assertRedirect();

        $this->assertTrue(SectionTask::firstOrFail()->requires_photo);
    }

    public function test_both_forms_carry_the_hidden_zero(): void
    {
        Section::create(['name' => 'Hot Section']);

        // The fix lives in the markup, so assert it is there: without the
        // hidden field the controller can never see an unticked box, whatever
        // the controller does.
        $html = $this->actingAs($this->owner())
            ->get(route('sections.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            2,
            substr_count($html, '<input type="hidden" name="requires_photo" value="0">'),
            'both the add-task and edit-task forms need the hidden zero',
        );
    }
}
