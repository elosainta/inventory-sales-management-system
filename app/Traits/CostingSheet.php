<?php

namespace App\Traits;

/**
 * The figures below a costing sheet's lines - an R&D trial or a staff meal.
 * All derived, none stored; the same arithmetic as
 * Recipe::recalculatePlateCost(). The model supplies lines() and misc_percent.
 */
trait CostingSheet
{
    /** Ingredients only, before the miscellaneous overhead. */
    public function getTotalAttribute(): float
    {
        return round($this->lines->sum(fn ($line) => $line->total), 2);
    }

    public function getMiscAmountAttribute(): float
    {
        return round($this->total * (float) $this->misc_percent / 100, 2);
    }

    /** What the sheet cost. Every other figure on the page reads this. */
    public function getGrandTotalAttribute(): float
    {
        return round($this->total + $this->misc_amount, 2);
    }
}
