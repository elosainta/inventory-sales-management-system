<?php

namespace Tests\Unit;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use PHPUnit\Framework\TestCase;

class SaleRevenueTest extends TestCase
{
    /**
     * A blank discount field reaches the request as null and `discount` is
     * NOT NULL DEFAULT 0 — this used to 500 on both store and update.
     */
    public function test_a_blank_discount_is_normalised_to_zero(): void
    {
        foreach ([null, ''] as $blank) {
            $request = StoreSaleRequest::create('/sales', 'POST', ['discount' => $blank]);
            (fn () => $this->prepareForValidation())->call($request);
            $this->assertSame(0, $request->input('discount'));
        }
    }

    public function test_discount_comes_off_the_gross(): void
    {
        $this->assertSame(50.00, Sale::revenue(2, 28.00, 6.00));
    }

    public function test_no_discount_is_plain_gross(): void
    {
        $this->assertSame(84.00, Sale::revenue(3, 28.00));
    }

    public function test_a_discount_over_the_gross_never_goes_negative(): void
    {
        $this->assertSame(0.0, Sale::revenue(1, 25.00, 40.00));
    }

    public function test_a_full_discount_staff_meal_is_zero_revenue(): void
    {
        $this->assertSame(0.0, Sale::revenue(1, 25.00, 25.00));
    }
}
