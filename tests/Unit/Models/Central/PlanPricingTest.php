<?php

namespace Tests\Unit\Models\Central;

use App\Models\Central\Plan;
use Tests\TestCase;

class PlanPricingTest extends TestCase
{
    public function test_formats_price_as_brl(): void
    {
        $plan = new Plan([
            'price' => 97.00,
        ]);

        self::assertSame('R$ 97,00', $plan->formatted_price);
    }
}
