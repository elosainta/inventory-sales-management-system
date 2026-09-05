<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_take_id',
        'stock_take_item_id',
        'item_name',
        'unit',
        'current_stock',
        'qty_in',
        'qty_out',
        'balance',
    ];

    /**
     * What the shelf holds once the sheet's movement is applied. Stock never
     * goes negative — an Out bigger than what is on hand means the figure the
     * inventory carried was already wrong, and the count is the correction.
     *
     * The form previews this as you type and the controller writes it, so both
     * go through here and can't drift.
     */
    public static function balance(int|float $current, int|float $in = 0, int|float $out = 0): float
    {
        return max(0, round($current + $in - $out, 2));
    }

    /**
     * How much each inventory item moves in total across the sheet.
     *
     * Two sheet lines can point at the same stock — the catalog holds separate
     * names for things the inventory carries as one item. Netting them up here,
     * before anything is written, is what stops the second line overwriting the
     * first line's movement instead of adding to it.
     *
     * Lines with nothing linked behind them are count-only and drop out.
     *
     * @param  iterable<array{inventory_item_id?: int|null, qty_in?: int|float|string|null, qty_out?: int|float|string|null}>  $lines
     * @return array<int, float>
     */
    public static function netMovement(iterable $lines): array
    {
        $net = [];

        foreach ($lines as $line) {
            if ($id = $line['inventory_item_id'] ?? null) {
                $net[$id] = ($net[$id] ?? 0)
                    + (float) ($line['qty_in'] ?? 0)
                    - (float) ($line['qty_out'] ?? 0);
            }
        }

        return $net;
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }
}
