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

    /**
     * Bukku's hard maximum. 101 is a 422 — "The page size may not be greater
     * than 100." This was found the expensive way: the code asked for 200
     * contacts and 500 products, both were refused, both empty lists were
     * cached for an hour, and the review screen told the reviewer no suppliers
     * existed. Page, never ask for one big page.
     */
    private const PAGE_SIZE = 100;

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

    /**
     * Walk every page of a list endpoint.
     *
     * Returns null when a read fails, which the caller needs to tell apart
     * from "there genuinely are none" — an empty picker and a broken picker
     * look identical on screen and must not be cached the same way.
     */
    private static function paged(string $path, string $key, array $params = []): ?array
    {
        $items = [];

        // Bounded rather than while(true): a list endpoint that stops
        // reporting a sane total must not spin against Bukku forever.
        for ($page = 1; $page <= 50; $page++) {
            $response = self::http()->get($path, $params + [
                'page_size' => self::PAGE_SIZE,
                'page'      => $page,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $batch = $response->json($key) ?? [];
            $items = array_merge($items, $batch);
            $total = $response->json('paging.total');

            if ($batch === [] || $total === null || count($items) >= (int) $total) {
                break;
            }
        }

        return $items;
    }

    /**
     * Cache a reference list, but only once it has actually been read.
     *
     * `Cache::remember` stores whatever the closure returns, so a single failed
     * request used to put an empty list in front of the reviewer for a full
     * hour — and the review screen refuses to send when the supplier list is
     * empty. A failure now degrades for one request, not for an hour.
     */
    private static function cached(string $key, callable $fetch): array
    {
        // No token is not an error on a read. The review screen is built to
        // show "nothing came back from Bukku" and refuse to send; throwing
        // instead turned that whole page into a 500, which is what production
        // served for every day the key was missing. A *write* with no token
        // still throws — silently not filing a bill would be far worse.
        if (! self::configured()) {
            return [];
        }

        if (is_array($hit = Cache::get($key))) {
            return $hit;
        }

        $fresh = $fetch();

        if ($fresh === null) {
            return [];
        }

        Cache::put($key, $fresh, self::CACHE_TTL);

        return $fresh;
    }

    /** Suppliers, for the review screen's contact picker. */
    public static function contacts(): array
    {
        return self::cached(
            'bukku.contacts',
            fn () => self::paged('/contacts', 'contacts', ['type' => 'supplier']),
        );
    }

    /**
     * Chart of accounts, for the fallback expense account picker.
     *
     * Not paged: this endpoint returns no `paging` block and hands back the
     * whole chart in one response.
     */
    public static function accounts(): array
    {
        return self::cached('bukku.accounts', function () {
            $response = self::http()->get('/accounts', ['is_archived' => false]);

            return $response->successful() ? ($response->json('accounts') ?? []) : null;
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
        return self::cached('bukku.products', fn () => self::paged('/products', 'products'));
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

    /**
     * Find a bill this app already wrote, by the marker in its description.
     *
     * Bukku has no idempotency key, so the marker IS the key: every bill this
     * app creates carries a unique "(scan #N)" in its description. After a
     * write that failed in a way that might still have committed, this answers
     * the only question that matters — did the bill land?
     *
     * `search` is a SUBSTRING match ("invoice #7" returns #71 and #72), so a
     * hit is confirmed against the whole description before it is believed.
     * Guessing wrong here would either duplicate a bill or adopt someone
     * else's.
     */
    public static function findBillByDescription(string $description): ?array
    {
        $response = self::http()->get('/purchases/bills', [
            'search'    => $description,
            'page_size' => 20,
        ]);

        if (! $response->successful()) {
            return null;
        }

        foreach ($response->json('transactions') ?? [] as $bill) {
            if (($bill['description'] ?? null) === $description) {
                return $bill;
            }
        }

        return null;
    }

    /**
     * Create a purchase bill. Returns the created transaction.
     *
     * Deliberately does NOT use the retrying client. A blind retry of a POST
     * that may already have committed is how one invoice becomes two bills,
     * and a Bukku bill is voided rather than deleted. The caller retries only
     * after `findBillByDescription()` has confirmed nothing landed.
     */
    public static function createBill(array $payload): array
    {
        $response = self::http()->post('/purchases/bills', $payload);

        // The status travels as the exception code: a 422 is Bukku refusing the
        // shape of the payload, which the caller can answer by sending a
        // simpler one. Every other failure is not worth a second attempt.
        if (! $response->successful()) {
            throw new RuntimeException('Bukku rejected the bill: ' . $response->body(), $response->status());
        }

        return $response->json('transaction') ?? $response->json() ?? [];
    }
}
