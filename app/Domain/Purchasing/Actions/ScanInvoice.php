<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\InvoiceScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads an uploaded invoice photo and writes down what it says.
 *
 * This half only reads. It never touches Bukku, never touches inventory, and
 * never decides anything — a human checks the numbers on the review screen
 * before a bill exists anywhere. That split is the whole safety story of the
 * feature: the model is allowed to be wrong here, because nothing downstream
 * happens until someone has looked.
 *
 * Called through Laravel's HTTP client rather than the official Anthropic PHP
 * SDK. Not a preference — this machine has no composer, so a new dependency
 * could not get a matching composer.lock entry, and the Docker build runs
 * `composer install` against that lock. One POST needs no SDK anyway; if the
 * SDK is wanted later, `composer require anthropic-ai/sdk` on a machine that
 * has composer and this class becomes a few lines shorter.
 */
class ScanInvoice
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    /** Images Claude can read, mapped to what the API calls them. */
    private const IMAGE_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    public function execute(UploadedFile $file, ?int $userId): InvoiceScan
    {
        $path = $file->store('invoice-scans');

        $scan = InvoiceScan::create([
            'user_id'           => $userId,
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'status'            => InvoiceScan::STATUS_SCANNED,
        ]);

        try {
            $extracted = $this->read(
                Storage::get($path),
                strtolower($file->getClientOriginalExtension()),
            );

            $scan->update([
                'extracted'      => $extracted,
                'supplier_name'  => $extracted['supplier_name'] ?? null,
                'invoice_number' => $extracted['invoice_number'] ?? null,
                'invoice_date'   => $extracted['invoice_date'] ?? null,
                'total_amount'   => $extracted['total'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // A failed read is a row, not an exception page: the photo is
            // already stored and the reviewer can key the bill in by hand off
            // the same image rather than starting over.
            report($e);

            $scan->update([
                'status'     => InvoiceScan::STATUS_FAILED,
                'scan_error' => $e->getMessage(),
            ]);
        }

        return $scan;
    }

    /** @return array<string,mixed> */
    private function read(string $contents, string $extension): array
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set — see .env.example.');
        }

        // php-fpm's default 30s ceiling is shorter than a careful read of a
        // busy invoice, and the reviewer is watching a spinner meanwhile.
        set_time_limit(180);

        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout(150)
            ->retry(2, 3000, throw: false)
            ->post(self::ENDPOINT, [
                'model'      => config('services.anthropic.model', 'claude-opus-5'),
                'max_tokens' => 16000,
                // Medium, not the default high: this is extraction from one
                // page, and a manager is waiting on the response. Raise it if
                // messy handwritten invoices start coming back wrong.
                'output_config' => [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
                ],
                'system' => 'You read supplier invoices for a restaurant in Malaysia. '
                    . 'Transcribe only what is printed on the page. Never infer, round, or tidy a '
                    . 'figure, and never invent a line that is not there. Amounts are Malaysian '
                    . 'Ringgit unless the invoice says otherwise. Dates are YYYY-MM-DD; if the page '
                    . 'is ambiguous between day-first and month-first, prefer day-first, which is '
                    . 'the Malaysian convention. Use null for anything you genuinely cannot read.',
                'messages' => [[
                    'role'    => 'user',
                    'content' => [
                        $this->documentBlock($contents, $extension),
                        ['type' => 'text', 'text' => 'Extract this invoice.'],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('The scan service returned ' . $response->status() . ': ' . $response->body());
        }

        // A safety decline arrives as a 200 with no usable content, so check
        // the stop reason before reading the blocks.
        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('The scan service declined to read this file.');
        }

        foreach ($response->json('content') ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $decoded = json_decode($block['text'], true);

                if (is_array($decoded)) {
                    return $this->clean($decoded);
                }
            }
        }

        throw new RuntimeException('The scan service returned nothing readable.');
    }

    /** @return array<string,mixed> */
    private function documentBlock(string $contents, string $extension): array
    {
        $data = base64_encode($contents);

        if ($extension === 'pdf') {
            return [
                'type'   => 'document',
                'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data],
            ];
        }

        return [
            'type'   => 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => self::IMAGE_TYPES[$extension] ?? 'image/jpeg',
                'data'       => $data,
            ],
        ];
    }

    /**
     * Drop lines with no substance.
     *
     * A blank row on the page becomes a blank line in the result, and a bill
     * line with no description and no money is noise the reviewer would only
     * have to delete by hand.
     */
    private function clean(array $decoded): array
    {
        $decoded['lines'] = array_values(array_filter(
            $decoded['lines'] ?? [],
            fn ($line) => filled($line['description'] ?? null) || filled($line['amount'] ?? null),
        ));

        return $decoded;
    }

    /** @return array<string,mixed> */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type'       => 'object',
            'properties' => [
                'supplier_name'  => $nullableString,
                'invoice_number' => $nullableString,
                'invoice_date'   => $nullableString,
                'currency'       => $nullableString,
                'lines'          => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'quantity'    => $nullableNumber,
                            'unit_price'  => $nullableNumber,
                            'amount'      => $nullableNumber,
                        ],
                        'required'             => ['description', 'quantity', 'unit_price', 'amount'],
                        'additionalProperties' => false,
                    ],
                ],
                'subtotal' => $nullableNumber,
                'tax'      => $nullableNumber,
                'total'    => $nullableNumber,
            ],
            'required'             => ['supplier_name', 'invoice_number', 'invoice_date', 'currency', 'lines', 'subtotal', 'tax', 'total'],
            'additionalProperties' => false,
        ];
    }
}
