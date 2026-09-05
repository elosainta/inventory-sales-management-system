<?php

namespace Tests\Unit;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogsActivityHidesSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_password_does_not_write_it_into_the_audit_log(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);

        $user->update(['password' => Hash::make('a-brand-new-password')]);

        $audit = Audit::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('password', $audit->before ?? []);
        $this->assertArrayNotHasKey('password', $audit->after ?? []);
    }
}
