<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Draft the missing Bahasa Indonesia strings with a local model.
 *
 * WHY LOCAL, AND WHY ONLY THIS. Translation upkeep is the one recurring job in
 * this project that genuinely suits a small local model: every feature adds
 * English strings, Dani is the only Indonesian reader, a wrong draft costs
 * nothing because a person reads it before it ships, and it runs offline for
 * free. Invoice reading deliberately stays on the Anthropic API — that one
 * writes to the company's books and accuracy is worth paying for.
 *
 * THIS IS A DEVELOPER COMMAND. It runs on a machine with Ollama, never in a
 * request, and never on the droplet (which has no GPU and no Ollama — there it
 * simply reports that it cannot reach the host and exits).
 *
 * IT NEVER WRITES lang/id.json. Output goes to lang/id.draft.json for a human
 * to read, correct and merge. A machine translation landing straight in front
 * of a chef, unread, is how you end up with a prep instruction that means
 * something else — and the whole reason this is safe to automate is that
 * somebody checks first. Ideally Dani.
 *
 *     ollama pull aya-expanse:8b
 *     php artisan lang:draft
 */
class DraftTranslations extends Command
{
    protected $signature = 'lang:draft
        {--locale=id : Target locale file in lang/}
        {--model=aya-expanse:8b : Ollama model tag}
        {--host=http://127.0.0.1:11434 : Ollama host}
        {--batch=20 : Strings per request}';

    protected $description = 'Draft missing translations with a local Ollama model into lang/<locale>.draft.json (never the live file)';

    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        $live   = base_path("lang/{$locale}.json");

        if (! is_file($live)) {
            $this->error("No lang/{$locale}.json to compare against.");

            return self::FAILURE;
        }

        $existing = json_decode((string) file_get_contents($live), true) ?: [];
        $missing  = array_values(array_diff($this->stringsInViews(), array_keys($existing)));

        if ($missing === []) {
            $this->info("Nothing to draft — every __() string already has a {$locale} translation.");

            return self::SUCCESS;
        }

        $this->line(sprintf('  %d untranslated string(s). Drafting with %s…', count($missing), $this->option('model')));

        $drafts = [];

        foreach (array_chunk($missing, (int) $this->option('batch')) as $i => $chunk) {
            $this->line(sprintf('  batch %d (%d strings)', $i + 1, count($chunk)));

            try {
                $drafts += $this->translate($chunk, $locale);
            } catch (\Throwable $e) {
                $this->error('  ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $out = base_path("lang/{$locale}.draft.json");
        file_put_contents($out, json_encode($drafts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        $this->newLine();
        $this->info(sprintf('  %d draft(s) written to lang/%s.draft.json', count($drafts), $locale));
        $this->line('  Read them before merging into ' . "lang/{$locale}.json" . ' — ideally with Dani.');

        return self::SUCCESS;
    }

    /** Every distinct `__('…')` string used in a Blade view. */
    private function stringsInViews(): array
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // Single-quoted only: that is what this codebase uses, and it keeps
            // the pattern from swallowing an apostrophe inside a sentence.
            preg_match_all("/__\(\s*'([^']*)'/", (string) file_get_contents($file->getPathname()), $m);
            $found = array_merge($found, $m[1]);
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  array<int, string>  $strings
     * @return array<string, string>
     */
    private function translate(array $strings, string $locale): array
    {
        $language = $locale === 'id' ? 'Bahasa Indonesia' : $locale;

        $response = Http::timeout(300)
            ->acceptJson()
            ->post(rtrim((string) $this->option('host'), '/') . '/api/chat', [
                'model'  => $this->option('model'),
                'stream' => false,
                // Ollama's JSON mode. Without it a small model tends to wrap the
                // object in prose and the decode below fails.
                'format' => 'json',
                'options' => ['temperature' => 0.2],
                'messages' => [[
                    'role'    => 'system',
                    'content' => "You translate restaurant kitchen software UI strings from English into {$language}. "
                        . 'Reply with a JSON object mapping each English string, verbatim, to its translation. '
                        . 'Keep placeholders like :name and :count exactly as they are. '
                        . 'Keep it short — these are buttons and labels, not prose. '
                        . 'Do not translate the product name "Inventory, Sales and Management System".',
                ], [
                    'role'    => 'user',
                    'content' => json_encode($strings, JSON_UNESCAPED_UNICODE),
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Ollama returned HTTP ' . $response->status() . '. Is it running? Try: ollama serve'
            );
        }

        $decoded = json_decode((string) $response->json('message.content'), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Could not parse the model reply as JSON — try a smaller --batch.');
        }

        // Only keep keys we actually asked about. A small model will sometimes
        // invent extra entries, and those must not reach the draft file.
        return array_intersect_key($decoded, array_flip($strings));
    }
}
