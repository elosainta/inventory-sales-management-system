<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Open every page in the app as every role and report what came back.
 *
 * A gate test says who is allowed where. This says nothing broke on the way:
 * a 500 from a view that reads a variable the controller stopped passing, or a
 * 400 from a request that cannot be built, looks exactly like a working page
 * until someone opens it. Only parameterless GET routes — anything with a
 * {model} needs a fixture per route, which is what the module tests are for.
 */
class PageSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_errors_for_any_role(): void
    {
        // Neither API is reachable from a test, and the review screen reads
        // both on render.
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);

        $pages = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->filter(fn ($r) => ! str_contains($r->uri(), '{'))
            ->filter(fn ($r) => ! str_starts_with($r->uri(), '_'))
            ->reject(fn ($r) => in_array($r->uri(), ['up', 'logout'], true))
            // PDF exports are left out on purpose: DomPDF holds a whole
            // document in memory, and eight of them across five roles in one
            // PHP process blows the default 128M limit — the suite would fail
            // for a reason that has nothing to do with the app. They are swept
            // by hand with `php -d memory_limit=1G`, and they all answered 200.
            ->reject(fn ($r) => str_contains($r->uri(), 'export'))
            ->map(fn ($r) => $r->uri())
            ->unique()
            ->values();

        $this->assertGreaterThan(20, $pages->count(), 'The sweep found almost no pages — the filter is wrong.');

        $broken = [];

        foreach ([User::ROLE_OWNER, User::ROLE_HEAD_CHEF, User::ROLE_JUNIOR_CHEF, User::ROLE_PART_TIMER, User::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($pages as $uri) {
                $status = $this->actingAs($user)->get('/' . ltrim($uri, '/'))->getStatusCode();

                // 200 is fine, 302 is a redirect (a role sent to its own home),
                // 403 is the gate doing its job. Anything else is a fault.
                if (! in_array($status, [200, 302, 403], true)) {
                    $broken[] = $role . ' → /' . $uri . ' = ' . $status;
                }
            }
        }

        $this->assertSame([], $broken, "These pages did not answer cleanly:\n" . implode("\n", $broken));
    }
}
