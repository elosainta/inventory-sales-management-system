<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Every figure here came off a live bill that disagreed with this app by a
 * sen, until lines were rounded half up in decimal.
 */
class MoneyLineAmountTest extends TestCase
{
    public function test_lines_round_half_up_like_the_invoice(): void
    {
        $this->assertSame('44.23', Money::lineAmount(3.05, 14.50));   // float gave 44.22 (BL-00048)
        $this->assertSame('146.13', Money::lineAmount(8.35, 17.50));  // bcmul truncated to 146.12 (BL-00040)
        $this->assertSame('31.01', Money::lineAmount('2.65', '11.70')); // the CLAUDE.md float example
        $this->assertSame('236.03', Money::lineAmount(10.978, 21.50));
        $this->assertSame('0.00', Money::lineAmount(0, 9.99));
    }
}
