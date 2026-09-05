<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionActiveDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_section_can_be_created_with_active_days(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->post(route('sections.store'), [
            'name'         => 'Hot Kitchen',
            'active_days'  => ['monday', 'wednesday', 'friday'],
        ])->assertRedirect();

        $this->assertEquals(
            ['monday', 'wednesday', 'friday'],
            Section::firstWhere('name', 'Hot Kitchen')->active_days
        );
    }

    public function test_unchecking_every_day_clears_a_previously_set_selection(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $section = Section::create(['name' => 'Pastry', 'active_days' => ['monday']]);

        // No active_days key at all in the payload - exactly what the browser
        // sends when every day pill is unchecked.
        $this->actingAs($owner)->patch(route('sections.update', $section), [
            'name' => 'Pastry',
        ])->assertRedirect();

        $this->assertSame([], $section->fresh()->active_days);
    }
}
