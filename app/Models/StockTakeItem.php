<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    use LogsActivity;

    public const SECTION_PANTRY = 'pantry';

    // Kitchen was retired on 2026-08-21 — it was never used, no sheet was ever
    // recorded against it. Its 64 catalog rows are left in the table rather
    // than deleted; nothing offers them, and sectionLabel() still renders any
    // stray row via its ucfirst() fallback.
    public const SECTIONS = [
        self::SECTION_PANTRY => 'Pantry',
    ];

    protected $fillable = [
        'section',
        'inventory_item_id',
        'name',
        'default_unit',
        'sort_order',
    ];

    /**
     * The live stock this sheet line moves. Null for catalog names the
     * inventory has never carried — those record a count but move nothing.
     */
    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function sectionLabel(): string
    {
        return self::SECTIONS[$this->section] ?? ucfirst($this->section);
    }
}
