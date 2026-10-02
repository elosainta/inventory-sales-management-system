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

    /**
     * quantity x price to the sen, rounded half up in decimal - the way Bukku
     * and every supplier's invoice do it. Float got it wrong: 3.05 * 14.50 is
     * 44.2249... in binary, so round() gave 44.22 where the bill said 44.23.
     * Both inputs are >= 0 wherever this is used, which is what makes
     * +0.005 then truncate a correct half-up.
     */
    public static function lineAmount(int|float|string $quantity, int|float|string $price): string
    {
        return bcadd(bcmul((string) $quantity, (string) $price, 6), '0.005', 2);
    }
}