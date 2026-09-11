<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\InvoiceItemAlias;
use App\Support\Bukku;
use Illuminate\Console\Command;

/**
 * Link shelf items to their Bukku products, without clicking 254 times.
 *
 * Only **exact** matches are applied, on the same normalised form the invoice
 * dictionary uses — case, punctuation and spacing folded away. Anything less
 * certain is printed as a suggestion for a human to confirm on the Inventory
 * page, and never written.
 *
 * That conservatism is the whole design. A wrong link is silent: it posts a
 * stock purchase against another product's account on every future invoice,
 * and nobody notices until the accounts are read. Leaving an item unlinked
 * costs a fallback to General Expense, which is merely blunt. The two failures
 * are not comparable, so the guessing is left to the person who knows.
 *
 *     php artisan bukku:link-products                  # show what it would do
 *     php artisan bukku:link-products --threshold=65   # widen the suggestions
 *     php artisan bukku:link-products --apply          # write the exact matches
 */
class BukkuLinkProducts extends Command
{
    protected $signature = 'bukku:link-products
        {--apply : Write the links. Without this, nothing is changed.}
        {--threshold=70 : How alike two names must be to be worth suggesting, 0-100.}';

    protected $description = 'Match inventory items to Bukku products by name and link the certain ones';

    /**
     * Default likeness below which a pair is not worth showing.
     *
     * 70, the Owner's call on 2026-09-03 after reading passes at 82 and 65.
     *
     * It is a filter for a human's eyes and nothing more. **Likeness does not
     * track correctness here**, and the live data says so plainly: TUMERIC
     * POWDER matched Cumin Powder at 76% and CASTOR SUGAR matched Coarse Sugar
     * at 75% — both wrong, and both still above this line — while KERISIK matched Rasaku
     * Kerisik at 66% and was right. That is why no threshold, at any value,
     * makes a suggestion safe to apply automatically.
     */
    private const SUGGEST_THRESHOLD = 70.0;

    public function handle(): int
    {
        if (! Bukku::configured()) {
            $this->error('BUKKU_API_TOKEN is not set — nothing to match against.');

            return self::FAILURE;
        }

        $products = Bukku::products();

        if ($products === []) {
            $this->error('Bukku returned no products. Run bukku:ping to see why.');

            return self::FAILURE;
        }

        $apply     = (bool) $this->option('apply');
        $threshold = max(0.0, min(100.0, (float) $this->option('threshold')));

        // Products already spoken for, so two shelf items can never claim one.
        $taken = InventoryItem::whereNotNull('bukku_product_id')
            ->pluck('bukku_product_id')
            ->all();

        $byName = [];
        foreach ($products as $product) {
            $key = InvoiceItemAlias::normalise((string) ($product['name'] ?? ''));

            // A duplicate name in Bukku is genuinely ambiguous — refuse both
            // rather than silently picking whichever came back first.
            $byName[$key] = array_key_exists($key, $byName) ? null : $product;
        }

        $linked = 0;
        $suggestions = [];

        foreach (InventoryItem::whereNull('bukku_product_id')->orderBy('name')->get() as $item) {
            $key     = InvoiceItemAlias::normalise($item->name);
            $product = $byName[$key] ?? null;

            if ($product && ! in_array((int) $product['id'], $taken, true)) {
                $this->line(sprintf('  <fg=green>link</>  %-42s → %s', $this->trim($item->name), $product['name']));

                if ($apply) {
                    $item->update(['bukku_product_id' => (int) $product['id']]);
                }

                $taken[] = (int) $product['id'];
                $linked++;

                continue;
            }

            if ($near = $this->nearest($item->name, $products, $taken, $threshold)) {
                $suggestions[] = [$item->name, $near['name'], $near['score']];
            }
        }

        $this->line('');

        foreach ($suggestions as [$itemName, $productName, $score]) {
            $this->line(sprintf('  <fg=yellow>maybe</> %-42s ≈ %s  (%d%%)', $this->trim($itemName), $productName, $score));
        }

        $this->line('');
        $this->line(sprintf(
            '  %s %d exact match(es). %d suggestion(s) at %d%% or better, left for a human.',
            $apply ? 'Linked' : 'Would link',
            $linked,
            count($suggestions),
            (int) $threshold,
        ));

        $this->line(sprintf(
            '  %d shelf item(s) still unlinked — those post to the fallback expense account.',
            InventoryItem::whereNull('bukku_product_id')->count() - ($apply ? 0 : $linked),
        ));

        if ($suggestions === []) {
            $this->line('  Nothing close enough to suggest. Try --threshold=60 to widen the net.');
        }

        if (! $apply && $linked > 0) {
            $this->line('  Nothing was written. Re-run with <options=bold>--apply</> to write these links.');
        }

        if ($suggestions !== []) {
            $this->line('  Suggestions are never applied — confirm them on the Inventory page.');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /** @return array{name:string,score:float}|null */
    private function nearest(string $name, array $products, array $taken, float $threshold = self::SUGGEST_THRESHOLD): ?array
    {
        $best = null;

        foreach ($products as $product) {
            if (in_array((int) ($product['id'] ?? 0), $taken, true)) {
                continue;
            }

            similar_text(
                InvoiceItemAlias::normalise($name),
                InvoiceItemAlias::normalise((string) ($product['name'] ?? '')),
                $percent,
            );

            if ($percent >= $threshold && (! $best || $percent > $best['score'])) {
                $best = ['name' => (string) $product['name'], 'score' => $percent];
            }
        }

        return $best;
    }

    private function trim(string $text): string
    {
        return mb_strlen($text) > 42 ? mb_substr($text, 0, 39) . '…' : $text;
    }
}
