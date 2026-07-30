<?php

declare(strict_types=1);

namespace Tests\Unit\Plans;

use App\Enums\Common\EntitlementScope;
use App\Enums\Common\EntitlementType;
use App\Services\EntitlementValueService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntitlementValueServiceTest extends TestCase
{
    private EntitlementValueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntitlementValueService;
    }

    #[DataProvider('validValues')]
    public function test_it_normalizes_valid_values(
        EntitlementType $type,
        string $key,
        bool|int|float $value,
    ): void {
        self::assertSame($value, $this->service->normalize($type, $key, $value));
    }

    /** @return iterable<string, array{EntitlementType, string, bool|int|float}> */
    public static function validValues(): iterable
    {
        yield 'feature true' => [EntitlementType::FEATURE, 'feature', true];
        yield 'feature false' => [EntitlementType::FEATURE, 'feature', false];
        yield 'zero limit' => [EntitlementType::LIMIT, 'users', 0];
        yield 'unlimited' => [EntitlementType::LIMIT, 'users', -1];
        yield 'ai decimal budget' => [EntitlementType::LIMIT, 'ai_budget', 12.5];
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_incompatible_values(
        EntitlementType $type,
        string $key,
        mixed $value,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalize($type, $key, $value);
    }

    /** @return iterable<string, array{EntitlementType, string, mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'truthy feature' => [EntitlementType::FEATURE, 'feature', 1];
        yield 'string feature' => [EntitlementType::FEATURE, 'feature', 'true'];
        yield 'decimal regular limit' => [EntitlementType::LIMIT, 'users', 1.5];
        yield 'negative regular limit' => [EntitlementType::LIMIT, 'users', -2];
        yield 'negative budget' => [EntitlementType::LIMIT, 'ai_budget', -0.01];
        yield 'numeric string budget' => [EntitlementType::LIMIT, 'ai_budget', '10'];
    }

    public function test_limit_scope_is_always_internal(): void
    {
        self::assertSame(
            EntitlementScope::INTERNAL,
            $this->service->validateScope(EntitlementType::LIMIT, null),
        );
    }
}
