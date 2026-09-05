<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOldRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_login_history_past_the_window_and_keeps_recent_rows(): void
    {
        $user = User::factory()->create();

        $old = LoginHistory::create(['user_id' => $user->id, 'ip_address' => '1.1.1.1', 'logged_in_at' => now()->subDays(200)]);
        $recent = LoginHistory::create(['user_id' => $user->id, 'ip_address' => '1.1.1.1', 'logged_in_at' => now()->subDays(5)]);

        $this->artisan('records:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_audits_are_left_alone_unless_audit_days_is_passed(): void
    {
        $user = User::factory()->create();
        $old = Audit::create(['user_id' => $user->id, 'user_name' => $user->name, 'action' => 'created', 'auditable_type' => User::class, 'auditable_id' => $user->id]);
        $old->forceFill(['created_at' => now()->subYears(5)])->save();

        $this->artisan('records:prune')->assertSuccessful();
        $this->assertModelExists($old);

        $this->artisan('records:prune --audit-days=730')->assertSuccessful();
        $this->assertModelMissing($old);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $user = User::factory()->create();
        $old = LoginHistory::create(['user_id' => $user->id, 'ip_address' => '1.1.1.1', 'logged_in_at' => now()->subDays(200)]);

        $this->artisan('records:prune --dry-run')->assertSuccessful();

        $this->assertModelExists($old);
    }
}
