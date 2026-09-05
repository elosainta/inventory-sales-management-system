<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Without a no-store header the browser restores /login from its cache on a
 * Back press instead of asking the server, so a signed-in user pressing Back
 * lands on a stale login form rather than being redirected onward. If this
 * test fails, that bug is back.
 */
class LoginCacheHeadersTest extends TestCase
{
    public function test_login_page_is_not_stored_in_the_browser_cache(): void
    {
        // The frontend is only built inside Docker, so there is no local Vite
        // manifest — the header is what matters here, not the asset tags.
        $this->withoutVite();

        $this->get('/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
