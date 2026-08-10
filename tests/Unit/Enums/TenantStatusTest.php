<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TenantStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantStatusTest extends TestCase
{
    #[DataProvider('loginEligibilityProvider')]
    public function test_allows_login_for_regularization_statuses(TenantStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->allowsLogin());
    }

    /**
     * @return array<string, array{0: TenantStatus, 1: bool}>
     */
    public static function loginEligibilityProvider(): array
    {
        return [
            'active' => [TenantStatus::ACTIVE, true],
            'suspended' => [TenantStatus::SUSPENDED, true],
            'under_review' => [TenantStatus::UNDER_REVIEW, true],
            'pending' => [TenantStatus::PENDING, false],
            'cancelled' => [TenantStatus::CANCELLED, false],
            'setup_failed' => [TenantStatus::SETUP_FAILED, false],
        ];
    }

    public function test_login_eligible_values_matches_policy(): void
    {
        $this->assertSame(
            ['active', 'suspended', 'under_review'],
            TenantStatus::loginEligibleValues(),
        );
    }
}
