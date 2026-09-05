<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedactAuditSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_redacts_a_leaked_password_but_keeps_the_rest_of_the_diff(): void
    {
        $user = User::factory()->create();

        // Simulate a row written before the LogsActivity fix existed.
        $audit = Audit::create([
            'user_id' => $user->id, 'user_name' => $user->name, 'action' => 'updated',
            'auditable_type' => User::class, 'auditable_id' => $user->id,
            'before' => ['password' => '$2y$old-hash', 'name' => 'Old Name'],
            'after'  => ['password' => '$2y$new-hash', 'name' => 'New Name'],
        ]);

        $this->artisan('audits:redact-secrets')->assertSuccessful();

        $audit->refresh();
        $this->assertSame('[redacted]', $audit->before['password']);
        $this->assertSame('[redacted]', $audit->after['password']);
        // The rest of the diff - the actual point of the audit row - survives.
        $this->assertSame('Old Name', $audit->before['name']);
        $this->assertSame('New Name', $audit->after['name']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create();
        $audit = Audit::create([
            'user_id' => $user->id, 'user_name' => $user->name, 'action' => 'updated',
            'auditable_type' => User::class, 'auditable_id' => $user->id,
            'before' => ['password' => '$2y$old-hash'],
            'after'  => ['password' => '$2y$new-hash'],
        ]);

        $this->artisan('audits:redact-secrets --dry-run')->assertSuccessful();

        $audit->refresh();
        $this->assertSame('$2y$old-hash', $audit->before['password']);
    }

    public function test_a_row_with_nothing_hidden_is_left_untouched(): void
    {
        $user = User::factory()->create();
        $audit = Audit::create([
            'user_id' => $user->id, 'user_name' => $user->name, 'action' => 'updated',
            'auditable_type' => User::class, 'auditable_id' => $user->id,
            'before' => ['name' => 'Old Name'],
            'after'  => ['name' => 'New Name'],
        ]);

        $this->artisan('audits:redact-secrets')->assertSuccessful();

        $audit->refresh();
        $this->assertSame('Old Name', $audit->before['name']);
        $this->assertSame('New Name', $audit->after['name']);
    }
}
