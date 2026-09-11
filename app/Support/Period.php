<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The Today / This Week / This Month / This Year / Custom-month filter that
 * Purchases, Sales, Wastage, Market purchases and Production all share - the
 * bar itself is partials/period-filter. It was written out five times, one per
 * controller, differing only in the date column.
 *
 * No range means the month picked in the Custom box, defaulting to this one.
 */
final class Period
{
    public static function filter(Builder $query, ?string $range, string $month, string $column): Builder
    {
        if ($range === 'today') {
            return $query->whereDate($column, today());
        }
        if ($range === 'week') {
            return $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]);
        }
        if ($range === 'month') {
            return $query->whereYear($column, now()->year)->whereMonth($column, now()->month);
        }
        if ($range === 'year') {
            return $query->whereYear($column, now()->year);
        }

        [$year, $mon] = explode('-', $month);

        return $query->whereYear($column, $year)->whereMonth($column, $mon);
    }
}
