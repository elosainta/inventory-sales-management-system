<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // No "remember me" — sessions must strictly expire (30-min inactivity
        // timeout). A persistent remember cookie would silently re-authenticate
        // a user and let the guest middleware bypass the password check.
        if (! Auth::attempt($this->only('email', 'password'))) {
            // Per email + IP: one person getting their own password wrong.
            $this->registerFailure($this->attemptsKey(), $this->lockoutKey(), 5);

            // Per email, address ignored. The key above is defeated outright by
            // an attacker who changes IP: every new address is a fresh five
            // guesses, forever. This second counter is what actually caps the
            // total, and the live cache showed why it is needed — one account
            // accumulated attempts across eighteen separate addresses without
            // ever tripping a lockout.
            //
            // ponytail: an attacker can lock a real user out by burning 20 bad
            // guesses at their address. Accepted — the lockout caps at 15 min,
            // a successful login clears it, and this is a seven-person kitchen.
            // Add a CAPTCHA before raising the cost if that is ever abused.
            $this->registerFailure($this->emailAttemptsKey(), $this->emailLockoutKey(), 20);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        foreach ([$this->attemptsKey(), $this->lockoutKey(), $this->emailAttemptsKey(), $this->emailLockoutKey()] as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Count one failure against a key, and lock out on every Nth.
     */
    protected function registerFailure(string $attemptsKey, string $lockoutKey, int $every): void
    {
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;
        Cache::put($attemptsKey, $attempts, now()->addHour());

        if ($attempts % $every !== 0) {
            return;
        }

        $seconds = $this->lockoutSeconds(intdiv($attempts, $every));
        Cache::put($lockoutKey, time() + $seconds, now()->addSeconds($seconds));
        event(new Lockout($this));

        // The only durable trace of a brute-force attempt. Attempt counters
        // expire after an hour and the nginx access log dies with the
        // container on every deploy, so without this line a lockout that
        // happened last week cannot be shown to have happened at all.
        Log::warning('Login lockout', [
            'email'    => $this->string('email')->toString(),
            'ip'       => $this->ip(),
            'attempts' => $attempts,
            'seconds'  => $seconds,
        ]);
    }

    /**
     * Ensure the login request is not currently locked out.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $until = max(
            (int) Cache::get($this->lockoutKey(), 0),
            (int) Cache::get($this->emailLockoutKey(), 0),
        );

        if ($until <= time()) {
            return;
        }

        $seconds = $until - time();

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Escalating lockout duration in seconds for a given lockout round
     * (1st lockout, 2nd lockout, …): 60s → 120s → 240s → 480s, capped at 15 min.
     */
    protected function lockoutSeconds(int $round): int
    {
        return (int) min(60 * (2 ** ($round - 1)), 900);
    }

    /**
     * Cache key tracking consecutive failed attempts for this email + IP.
     */
    protected function attemptsKey(): string
    {
        return 'login_attempts:'.$this->throttleKey();
    }

    /**
     * Cache key holding the unix timestamp this email + IP is locked out until.
     */
    protected function lockoutKey(): string
    {
        return 'login_lockout:'.$this->throttleKey();
    }

    /** As attemptsKey(), but for the account regardless of where it is tried from. */
    protected function emailAttemptsKey(): string
    {
        return 'login_attempts_email:'.$this->emailKey();
    }

    /** As lockoutKey(), but for the account regardless of where it is tried from. */
    protected function emailLockoutKey(): string
    {
        return 'login_lockout_email:'.$this->emailKey();
    }

    /** The submitted email, normalised so one account is one bucket. */
    protected function emailKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')));
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return $this->emailKey().'|'.$this->ip();
    }
}
