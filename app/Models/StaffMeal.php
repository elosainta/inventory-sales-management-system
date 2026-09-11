<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\CostingSheet;

/**
 * One staff meal, recorded as a costing sheet: the day, the dish, and
 * everything it was made with.
 *
 * The arithmetic is the kitchen's own and identical to R&D and Recipes:
 * ingredient lines add up to `total`, `misc_percent` of that is the
 * miscellaneous overhead (gas, condiments, small consumables), the two together
 * are the `grand_total`, which is what the meal cost the kitchen.
 *
 * It does NOT record how many people ate. The headcount and the cost per head
 * it produced were dropped on 2026-09-09 — counting the team every day was
 * work for a figure nobody asked for.
 *
 * There is no selling price and no profit: staff meals are not sold.
 *
 * Recording one DEDUCTS every line from live stock straight away (see
 * StaffMealController::store) — the team has eaten it, so the shelf should say
 * so. Like every other deduction in this app it is not reversed by an edit or
 * a delete, and not repeated; the shelf is corrected with a Tally.
 *
 * Plate costs are untouched: unit_cost never changes here, so no dish on the
 * menu is repriced by what the team ate.
 *
 * Nothing here is approved — a meal is a fact, not a request.
 */
class StaffMeal extends Model
{
    use LogsActivity, CostingSheet;

    protected $fillable = [
        'meal_date',
        // What was cooked, typed. Not a recipe_id: staff eat something
        // different every day and a catalogue of one-offs helps nobody.
        'dish',
        'misc_percent',
        'remark',
        'created_by',
    ];

    protected $casts = [
        'meal_date'    => 'date',
        'misc_percent' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(StaffMealLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------- the costing sheet. All derived, none stored. ----------

    /**
     * The point of the report.
     *
     * pax is unsigned with min:1 on the request, so the guard below is belt and
     * braces for a row written straight to the database.
     */
    public function getCostPerHeadAttribute(): float
    {
        return $this->pax > 0 ? round($this->grand_total / $this->pax, 2) : 0.0;
    }
}
