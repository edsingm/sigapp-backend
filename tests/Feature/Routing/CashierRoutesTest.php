<?php

namespace Tests\Feature\Routing;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CashierRoutesTest extends TestCase
{
    public function test_cashier_payment_route_exists_and_default_webhook_route_is_disabled(): void
    {
        self::assertTrue(Route::has('cashier.payment'));
        self::assertFalse(Route::has('cashier.webhook'));
        self::assertSame('/stripe/payment/pi_test_123', route('cashier.payment', ['id' => 'pi_test_123'], false));
    }
}
