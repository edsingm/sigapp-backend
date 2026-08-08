<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use PHPUnit\Framework\TestCase;

class BillingAddonSubscriptionStatusTest extends TestCase
{
    public function test_trialing_addon_does_not_grant_access(): void
    {
        $this->assertFalse(BillingAddonSubscriptionStatus::TRIALING->grantsAccess());
        $this->assertTrue(BillingAddonSubscriptionStatus::ACTIVE->grantsAccess());
        $this->assertTrue(BillingAddonSubscriptionStatus::PAST_DUE->grantsAccess());
    }
}
