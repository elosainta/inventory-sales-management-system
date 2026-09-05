<?php

namespace App\Support;

class Money
{
    /**
     * Format a numeric value as Malaysian Ringgit.
     * Always shows 2 decimal places. Always uses thousands separators.
     *
     * Money::format(1234.5)  → "RM 1,234.50"
     * Money::format(0)       → "RM 0.00"
     * Money::format(-50.25)  → "-RM 50.25"
     */
    public static function format(int|float|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        // Sign outside the currency symbol: number_format() alone would give
        // "RM -50.25", which reads as a price rather than a deduction.
        return ($value < 0 ? '-' : '') . 'RM ' . number_format(abs($value), 2);
    }
}