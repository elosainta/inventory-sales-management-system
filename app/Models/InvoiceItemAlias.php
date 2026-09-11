<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The dictionary between a supplier's wording and this kitchen's shelf.
 *
 * Taught one line at a time on the invoice-scan review screen: a reviewer
 * matches "AYAM PEHA 1KG" to Chicken Thigh once, and every later invoice
 * carrying that wording arrives already matched.
 *
 * Audited, because this is a rule rather than a record. A wrong entry silently
 * matches the wrong shelf on every future invoice, so who taught it, and when,
 * is worth being able to look up.
 */
class InvoiceItemAlias extends Model
{
    use LogsActivity;

    protected $fillable = ['normalised', 'supplier_text', 'inventory_item_id'];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * The lookup key.
     *
     * Case, punctuation and spacing are noise on an invoice — the same product
     * arrives as "AYAM PEHA 1KG", "Ayam Peha (1kg)" and "ayam  peha 1kg"
     * across three months of paperwork. Folding them to one key is what makes
     * the dictionary worth having; matching raw text would learn each spelling
     * separately and match none of them next time.
     */
    public static function normalise(string $text): string
    {
        $text = Str::lower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * Resolve many descriptions at once.
     *
     * One query for the whole invoice rather than one per line: a bill of
     * twenty lines should not be twenty round trips to render.
     *
     * @param  iterable<string>  $descriptions
     * @return array<string,int> normalised text => inventory_item_id
     */
    public static function matchAll(iterable $descriptions): array
    {
        $keys = [];

        foreach ($descriptions as $description) {
            if (filled($key = self::normalise((string) $description))) {
                $keys[] = $key;
            }
        }

        if ($keys === []) {
            return [];
        }

        return self::query()
            ->whereIn('normalised', array_unique($keys))
            ->pluck('inventory_item_id', 'normalised')
            ->all();
    }

    /**
     * Learn, or correct, one mapping.
     *
     * `updateOrCreate` on the normalised key, so re-matching a line that was
     * previously matched wrong overwrites the old answer rather than failing
     * on the unique index. A correction has to be able to win — the reviewer
     * is the authority here, not the first person who guessed.
     *
     * ponytail: one dictionary for every supplier. Two suppliers using the
     * same wording for different things would collide; scope on the Bukku
     * contact id if that ever actually happens.
     */
    public static function remember(string $supplierText, int $inventoryItemId): ?self
    {
        $key = self::normalise($supplierText);

        if (blank($key)) {
            return null;
        }

        return self::updateOrCreate(
            ['normalised' => $key],
            ['supplier_text' => $supplierText, 'inventory_item_id' => $inventoryItemId],
        );
    }
}
