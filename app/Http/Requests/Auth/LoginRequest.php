<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            $attempts = (int) Cache::get($this->attemptsKey(), 0) + 1;
            Cache::put($this->attemptsKey(), $attempts, now()->addHour());

            // Every 5th wrong password triggers a lockout that grows each round.
            if ($attempts % 5 === 0) {
                $seconds = $this->lockoutSeconds(intdiv($attempts, 5));
                Cache::put($this->lockoutKey(), time() + $seconds, now()->addSeconds($seconds));
                event(new Lockout($this));
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Cache::forget($this->attemptsKey());
        Cache::forget($this->lockoutKey());
    }

    /**
     * Ensure the login request is not currently locked out.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $until = (int) Cache::get($this->lockoutKey(), 0);

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

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
