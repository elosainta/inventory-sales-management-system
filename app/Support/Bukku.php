<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Bukku accounting API.
 *
 * There is no official PHP SDK for Bukku and no public API reference, so this
 * is Laravel's HTTP client and nothing more — the shapes below were read off
 * live bills the Telegram bot had already created, not guessed from docs.
 *
 * The base URL, the subdomain header and the fallback account are all config,
 * because the one thing that cannot be verified from here is the credential:
 * the token lives only in the server .env. `php artisan bukku:ping` is the way
 * to confirm the three of them agree on the server — run it after setting the
 * token, before trusting the feature.
 *
 * Reference data (suppliers, accounts, products) is cached for an hour. It
 * changes rarely and the review screen needs all three on every render; an
 * hour is short enough that a supplier added in Bukku shows up the same
 * morning, and `bukku:ping` flushes it if someone is waiting.
 */
class Bukku
{
    private const CACHE_TTL = 3600;

    private const CACHE_KEYS = ['bukku.contacts', 'bukku.accounts', 'bukku.products', 'bukku.location'];

    public static function configured(): bool
    {
        return filled(config('services.bukku.token'));
    }

    public static function http(): PendingRequest
    {
        $token = config('services.bukku.token');

        if (blank($token)) {
            throw new RuntimeException('BUKKU_API_TOKEN is not set — see .env.example.');
        }

        $request = Http::baseUrl(rtrim((string) config('services.bukku.base_url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);

        // Only sent when configured. Whether the company is selected by this
        // header or carried inside the token is the one thing that could not
        // be established without the credential — bukku:ping settles it.
        if (filled($subdomain = config('services.bukku.subdomain'))) {
            $request = $request->withHeaders(['Company-Subdomain' => $subdomain]);
        }

        return $request;
    }

    /**
     * The write path, which is allowed to be patient.
     *
     * Reads deliberately do not retry: three endpoints x three tries x a two
     * second pause is eighteen seconds of a manager watching a blank review
     * screen during a Bukku wobble, and an empty picker already says so
     * honestly. A half-sent bill is the expensive failure, not a slow page.
     */
    private static function write(): PendingRequest
    {
        // A 4xx is Bukku saying the request itself is wrong, and an identical
        // second attempt cannot fix it — retrying one only triples the log
        // noise and keeps the reviewer waiting. Wobbles (5xx, a dropped
        // connection) are what the three attempts are for.
        return self::http()->retry(
            3,
            2000,
            fn ($e) => ! ($e instanceof RequestException)
                || ! $e->response->clientError(),
            throw: false,
        );
    }

    /** Suppliers, for the review screen's contact picker. */
    public static function contacts(): array
    {
        return Cache::remember('bukku.contacts', self::CACHE_TTL, function () {
            $response = self::http()->get('/contacts', ['page_size' => 200, 'type' => 'supplier']);

            return $response->successful() ? ($response->json('contacts') ?? []) : [];
        });
    }

    /** Chart of accounts, for the fallback expense account picker. */
    public static function accounts(): array
    {
        return Cache::remember('bukku.accounts', self::CACHE_TTL, function () {
            $response = self::http()->get('/accounts', ['is_archived' => false]);

            return $response->successful() ? ($response->json('accounts') ?? []) : [];
        });
    }

    /**
     * Products, so a reviewer can map a line onto stock.
     *
     * This is what keeps the books right: a line mapped to a product posts
     * against that product's own account (Inventory, on this company's chart),
     * exactly as the Telegram bot's bills did. An unmapped line falls back to
     * the general expense account instead, which is a real bill but a blunter
     * one — see the note on the review screen.
     */
    public static function products(): array
    {
        return Cache::remember('bukku.products', self::CACHE_TTL, function () {
            $response = self::http()->get('/products', ['page_size' => 500]);

            return $response->successful() ? ($response->json('products') ?? []) : [];
        });
    }

    /**
     * One product, in full.
     *
     * The product LIST carries no accounts — only the detail does — which is
     * why a mapped line costs a GET at Submit time instead of the review
     * screen fetching all 77 up front. Deliberately not cached: the call is
     * made a handful of times on one submit, and a per-id cache would need a
     * per-id invalidation nobody would remember to run.
     */
    public static function product(int $id): array
    {
        $response = self::http()->get('/products/' . $id);

        return $response->successful() ? ($response->json('product') ?? []) : [];
    }

    /**
     * The stock location a product line is received into.
     *
     * This company has exactly one ("HQ"), and every bill the Telegram bot
     * wrote carries it on its stock lines, so it is read rather than assumed.
     */
    public static function defaultLocationId(): ?int
    {
        return Cache::remember('bukku.location', self::CACHE_TTL, function () {
            $response = self::http()->get('/locations');

            foreach ($response->successful() ? ($response->json('locations') ?? []) : [] as $location) {
                if (! ($location['is_archived'] ?? false)) {
                    return (int) $location['id'];
                }
            }

            return null;
        });
    }

    public static function forgetReferenceData(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Upload the invoice photo to Bukku and return its file id.
     *
     * The bill carries the photo so the Owner can open the original from
     * inside the accounts, which is what the Telegram bot did — every bill it
     * wrote has file_count 1.
     */
    public static function uploadFile(string $contents, string $filename, string $mime): int
    {
        $response = self::write()
            ->asMultipart()
            ->attach('file', $contents, $filename, ['Content-Type' => $mime])
            ->post('/files');

        if (! $response->successful()) {
            throw new RuntimeException('Bukku rejected the file upload: ' . $response->body());
        }

        $id = $response->json('file.id') ?? $response->json('id');

        if (! $id) {
            throw new RuntimeException('Bukku accepted the file but returned no id: ' . $response->body());
        }

        return (int) $id;
    }

    /** Create a purchase bill. Returns the created transaction. */
    public static function createBill(array $payload): array
    {
        $response = self::write()->post('/purchases/bills', $payload);

        // The status travels as the exception code: a 422 is Bukku refusing the
        // shape of the payload, which the caller can answer by sending a
        // simpler one. Every other failure is not worth a second attempt.
        if (! $response->successful()) {
            throw new RuntimeException('Bukku rejected the bill: ' . $response->body(), $response->status());
        }

        return $response->json('transaction') ?? $response->json() ?? [];
    }
}
