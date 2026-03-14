<?php

namespace Tests\Unit\Models\Central;

use App\Models\Central\Plan;
use Tests\TestCase;

class PlanPricingTest extends TestCase
{
    public function test_formats_price_from_cents(): void
    {
        $plan = new Plan([
            'price' => 9700,
        ]);

        self::assertSame('R$ 97,00', $plan->formatted_price);
    }
}
