<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\SectionCheck;
use App\Models\SectionTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The prep record, open to the whole kitchen.
 *
 * Prep Overview is the manager's read of today. History is the other half and
 * deliberately not gated the same way: anyone signed in can look up who did
 * what on a given day, which is the point of stamping a name on a check.
 */
class PrepHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function checkOn(string $date, User $user): SectionCheck
    {
        $section = Section::create(['name' => 'Pantry']);
        $task    = SectionTask::create(['section_id' => $section->id, 'title' => 'Wipe the shelves']);

        return SectionCheck::create([
            'section_task_id' => $task->id,
            'user_id'         => $user->id,
            'checked_date'    => $date,
        ]);
    }

    public function test_every_role_can_read_the_history(): void
    {
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'name' => 'Dani']);
        $this->checkOn('2026-09-14', $chef);

        foreach ([User::ROLE_OWNER, User::ROLE_HEAD_CHEF, User::ROLE_ADMIN, User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('prep.history', ['date' => '2026-09-14']))
                ->assertOk()
                ->assertSee('Dani');
        }
    }

    public function test_it_shows_the_day_asked_for_and_not_another(): void
    {
        $chef = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF, 'name' => 'Dani']);
        $this->checkOn('2026-09-14', $chef);

        $this->actingAs($this->owner())
            ->get(route('prep.history', ['date' => '2026-09-13']))
            ->assertOk()
            ->assertDontSee('Dani')
            ->assertSee('Nothing was ticked on this day.');
    }

    /**
     * The date is a query string, so it takes whatever anyone types. Rubbish
     * reads as today rather than a 500, and a future date is clamped — there
     * is nothing after today to show.
     *
     * Today is the kitchen's, not the app clock's. Tests run on UTC, and 18:49
     * UTC on the 1st is 02:49 on the 2nd in Malaysia — asserting today() here
     * failed every night between midnight and 8am, kitchen time.
     */
    public function test_a_rubbish_or_future_date_falls_back_to_today(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01 18:49:00', 'UTC'));

        foreach (['not-a-date', '9999-99-99', now()->addYear()->toDateString()] as $bad) {
            $this->actingAs($this->owner())
                ->get(route('prep.history', ['date' => $bad]))
                ->assertOk()
                ->assertSee('Friday, 02 Oct 2026');
        }
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }
}
