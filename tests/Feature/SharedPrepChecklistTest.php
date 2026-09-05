<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\SectionCheck;
use App\Models\SectionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The prep checklist became shared work in 1.10.49. Two rules replaced the
 * old one-section-per-chef model:
 *
 *   1. any account in the kitchen may complete any task, and
 *   2. the account that ticked it is stamped on the row and shown by name.
 *
 * Before this, a section was assigned to one junior chef; nobody else could
 * tick its tasks, and a chef with no section signed in to an empty page. On
 * production that was the live state — six sections, three of them assigned
 * to nobody at all.
 */
class SharedPrepChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function sectionWithTask(string $name = 'Hot Kitchen'): array
    {
        $section = Section::create(['name' => $name]);

        return [$section, SectionTask::create([
            'section_id'     => $section->id,
            'title'          => 'Clean the grill',
            'sort_order'     => 0,
            'requires_photo' => false,
        ])];
    }

    public function test_a_chef_sees_every_section_not_just_an_assigned_one(): void
    {
        Section::create(['name' => 'Pantry']);
        Section::create(['name' => 'Pass Section']);

        // No section is assigned to this chef — under the old rule they saw
        // "You have not been assigned a section yet."
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($chef)
            ->get(route('prep.index'))
            ->assertOk()
            ->assertSee('Pantry')
            ->assertSee('Pass Section');
    }

    public function test_any_account_including_the_support_admin_can_tick_a_task(): void
    {
        [, $task] = $this->sectionWithTask();

        foreach ([User::ROLE_JUNIOR_CHEF, User::ROLE_HEAD_CHEF, User::ROLE_OWNER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->post(route('prep.task-check'), ['task_id' => $task->id])
                ->assertRedirect();

            $this->assertSame(
                $user->id,
                SectionCheck::where('section_task_id', $task->id)->firstOrFail()->user_id,
                "a {$role} should be able to tick a task and be recorded against it",
            );
        }

        // Admin was blocked here until 1.11.2. It is now allowed on purpose:
        // ticking a task is the flow chefs report problems with most often,
        // and support cannot reproduce it without doing it. The admin's name
        // lands on the row like anyone else's, which is honest — the row
        // records who last stood in front of the task.
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)
            ->post(route('prep.task-check'), ['task_id' => $task->id])
            ->assertRedirect();

        $this->assertSame(
            $admin->id,
            SectionCheck::where('section_task_id', $task->id)->firstOrFail()->user_id,
        );
    }

    public function test_the_name_of_whoever_ticked_is_shown_on_the_checklist(): void
    {
        [, $task] = $this->sectionWithTask();

        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'name' => 'Riley Chen']);
        $this->actingAs($chef)->post(route('prep.task-check'), ['task_id' => $task->id]);

        // Shown to a different person: the point is that the kitchen can see
        // who did it, not that you can see your own work.
        $colleague = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($colleague)
            ->get(route('prep.index'))
            ->assertOk()
            ->assertSee('Done by Riley Chen');

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)
            ->get(route('prep.overview'))
            ->assertOk()
            ->assertSee('Riley Chen');
    }

    public function test_re_ticking_a_task_moves_the_name_rather_than_adding_a_second_row(): void
    {
        Storage::fake('local');

        [, $task] = $this->sectionWithTask();

        $first  = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
        $second = User::factory()->create(['role' => User::ROLE_HEAD_CHEF]);

        $this->actingAs($first)->post(route('prep.task-check'), ['task_id' => $task->id]);
        $this->actingAs($second)->post(route('prep.task-check'), [
            'task_id' => $task->id,
            'photo'   => UploadedFile::fake()->image('grill.jpg'),
        ]);

        // One task, one day, one row — the record says who last did it.
        $checks = SectionCheck::where('section_task_id', $task->id)->get();
        $this->assertCount(1, $checks);
        $this->assertSame($second->id, $checks->first()->user_id);
        $this->assertNotNull($checks->first()->photo_path);
    }

    public function test_deleting_the_chef_keeps_the_work_they_ticked(): void
    {
        [, $task] = $this->sectionWithTask();
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($chef)->post(route('prep.task-check'), ['task_id' => $task->id]);

        $check = SectionCheck::firstOrFail();

        // Deleted the way the Users page does it - by a manager, not by the
        // account itself. The audit trail stamps the actor, and an account
        // cannot be the actor for its own removal.
        $manager = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($manager);
        $chef->delete();

        // The row is a kitchen record of whether the task got done, not the
        // chef's personal submission - it outlives the account, the way every
        // other kitchen record does. Until this migration the FK cascaded and
        // deleting a departed chef made today's finished prep read as undone.
        $this->assertModelExists($check);
        $this->assertNull($check->fresh()->user_id);

        // Which is exactly what the view's null fallback was already written
        // for, and could never reach.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_HEAD_CHEF]))
            ->get(route('prep.index'))
            ->assertOk()
            ->assertSee('a former team member');
    }
}
