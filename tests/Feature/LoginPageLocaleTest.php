<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sign-in screen in the reader's own language.
 *
 * `SetLocale` reads `preferred_language` off the signed-in user, and nobody is
 * signed in at /login — so the sign-in screen was always English, including
 * for Dani, who reads Indonesian and sees that screen first, every day. A
 * remembered cookie is what carries the preference across sign-out.
 *
 * The tampering test is the one that matters: the locale comes off the wire
 * and ends up resolving translation files on disk.
 */
class LoginPageLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_is_english_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to your kitchen');
    }

    public function test_the_login_page_is_indonesian_when_that_is_remembered(): void
    {
        $this->withUnencryptedCookie('app_locale', 'id')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk ke dapur Anda')
            ->assertSee('Kata Sandi')
            ->assertDontSee('Sign in to your kitchen');
    }

    public function test_a_tampered_locale_cookie_is_ignored(): void
    {
        // Never hand an unchecked value to App::setLocale() — it is used to
        // resolve files. Anything off the allowlist falls back to English
        // rather than being tried.
        foreach (['../../etc/passwd', 'fr', '', 'id/../en'] as $bad) {
            $this->withUnencryptedCookie('app_locale', $bad)
                ->get(route('login'))
                ->assertOk()
                ->assertSee('Sign in to your kitchen');
        }
    }

    public function test_signing_in_remembers_the_language_for_next_time(): void
    {
        $dani = User::factory()->create([
            'role'               => User::ROLE_JUNIOR_CHEF,
            'preferred_language' => 'id',
        ]);

        $this->actingAs($dani)
            ->get(route('prep.index'))
            ->assertOk()
            ->assertPlainCookie('app_locale', 'id');
    }

    public function test_an_english_speaker_is_not_given_a_stale_indonesian_screen(): void
    {
        // The cookie belongs to the device, not the person. A second account
        // signing in on the same phone must overwrite it, not inherit it.
        $paul = User::factory()->create([
            'role'               => User::ROLE_HEAD_CHEF,
            'preferred_language' => 'en',
        ]);

        $this->withUnencryptedCookie('app_locale', 'id')
            ->actingAs($paul)
            ->get(route('prep.index'))
            ->assertOk()
            ->assertPlainCookie('app_locale', 'en');
    }

    public function test_login_errors_are_translated(): void
    {
        // auth.failed comes from Laravel's own lang files, not from __(), so a
        // wrapped view would still have shown this line in English.
        $this->withUnencryptedCookie('app_locale', 'id')
            ->from(route('login'))
            ->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi yang Anda masukkan salah.']);
    }

    public function test_a_missing_field_is_reported_in_indonesian(): void
    {
        $this->withUnencryptedCookie('app_locale', 'id')
            ->from(route('login'))
            ->post(route('login'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.']);
    }

    public function test_the_lockout_countdown_survives_translation(): void
    {
        // The page reads the remaining seconds out of the throttle message with
        // a regex. It used to be /in (\d+) second/i, which matches nothing in
        // "…dalam 60 detik" — so the countdown would have quietly stopped
        // working for the one person reading Indonesian. Both languages must
        // yield a number.
        foreach (['en' => 'seconds', 'id' => 'detik'] as $locale => $unit) {
            $message = trans('auth.throttle', ['seconds' => 60, 'minutes' => 1], $locale);

            $this->assertStringContainsString($unit, $message, "auth.throttle missing for {$locale}");
            $this->assertMatchesRegularExpression('/(\d+)/', $message);
            preg_match('/(\d+)/', $message, $m);
            $this->assertSame('60', $m[1], "the first number in the {$locale} throttle line must be the seconds");
        }
    }
}
