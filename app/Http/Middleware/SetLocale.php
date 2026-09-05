<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales this app will actually switch to.
     *
     * `App::setLocale()` is handed a value that came off the wire, so it is
     * checked against this list first. Laravel encrypts cookies, so tampering
     * is already hard — but a locale string reaches the filesystem when the
     * translation files are resolved, and "hard to tamper with" is not the
     * same as "safe to pass through".
     */
    private const SUPPORTED = ['en', 'id'];

    private const COOKIE = 'app_locale';

    /** A year. This is a UI preference, not a session. */
    private const REMEMBER_MINUTES = 525600;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->preferred_language, self::SUPPORTED, true)) {
            App::setLocale($user->preferred_language);

            // Remember it, because the login page has no user to ask.
            // `SetLocale` runs on every request but nobody is signed in at
            // /login, so before this the sign-in screen was always English —
            // including for Dani, who reads Indonesian and sees that screen
            // first, every day. The cookie is what carries the preference
            // across sign-out.
            return $this->rememberOn($next($request), $user->preferred_language);
        }

        $remembered = $request->cookie(self::COOKIE);

        if (is_string($remembered) && in_array($remembered, self::SUPPORTED, true)) {
            App::setLocale($remembered);
        }

        return $next($request);
    }

    private function rememberOn(Response $response, string $locale): Response
    {
        if ($request = request()) {
            // Nothing to do if it already says the right thing.
            if ($request->cookie(self::COOKIE) === $locale) {
                return $response;
            }
        }

        Cookie::queue(Cookie::make(self::COOKIE, $locale, self::REMEMBER_MINUTES));

        return $response;
    }
}
