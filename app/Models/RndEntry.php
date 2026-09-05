<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A research-and-development trial, recorded as a costing sheet: the dish being
 * developed, every ingredient it was made with, and what the plate came to.
 *
 * The arithmetic is the kitchen's own, and the same as Recipes: ingredient
 * lines add up to `total`, `misc_percent` of that is the miscellaneous
 * overhead (gas, condiments, small consumables), the two together are the
 * `grand_total`, and the selling price less the grand total is the profit.
 *
 * Recording one DEDUCTS every line's quantity from live stock straight away
 * (see RndEntryController::store) — the chef has taken it, so the shelf should
 * say so, without waiting on a review. Approval is about the money, not the
 * stock. Like every other deduction in this app it is not reversed by an edit,
 * a rejection or a delete; the shelf is corrected by a Tally.
 *
 * Plate costs are untouched: unit_cost never changes here, so no recipe is
 * repriced by an experiment.
 */
class RndEntry extends Model
{
    use LogsActivity;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'recipe_id',
        // The menu item being developed — what the trial was FOR. What it was
        // made WITH lives on the lines.
        'menu_name',
        'serving_size',
        'misc_percent',
        'selling_price',
        'remark',
        'purchased_on',
        'status',
        'created_by',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'serving_size'  => 'integer',
        'misc_percent'  => 'decimal:2',
        'selling_price' => 'decimal:2',
        'purchased_on'  => 'date',
        'decided_at'    => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RndEntryLine::class);
    }

    /**
     * The dish this trial turned into, once the Owner approved it and someone
     * wrote it up — see RndEntryController::createRecipe. Null until then, and
     * set is what stops the same trial becoming two recipes.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // ---------- the costing sheet. All derived, none stored. ----------

    /** Ingredients only, before the miscellaneous overhead. */
    public function getTotalAttribute(): float
    {
        return round($this->lines->sum(fn (RndEntryLine $line) => $line->total), 2);
    }

    public function getMiscAmountAttribute(): float
    {
        return round($this->total * (float) $this->misc_percent / 100, 2);
    }

    /** What the plate actually cost — the figure the Owner is approving. */
    public function getGrandTotalAttribute(): float
    {
        return round($this->total + $this->misc_amount, 2);
    }

    /** Null until a selling price is set: a trial does not always have one. */
    public function getProfitAttribute(): ?float
    {
        return $this->selling_price === null
            ? null
            : round((float) $this->selling_price - $this->grand_total, 2);
    }

    /** Approved, not yet written up, and made of something still on the shelf. */
    public function canBecomeRecipe(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && ! $this->recipe_id
            && $this->lines->whereNotNull('inventory_item_id')->isNotEmpty();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * An approved entry is frozen: the figures the Owner signed off cannot be
     * changed underneath the approval. A rejected one stays editable — the
     * point of a rejection is that it can be corrected — and editing sends it
     * back to pending for another look (see RndEntryController::update).
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
