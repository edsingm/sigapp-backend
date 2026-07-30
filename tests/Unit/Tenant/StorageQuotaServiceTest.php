<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\UsageMetricsRepositoryInterface;
use App\Services\PlanMatrixService;
use App\Services\Tenant\StorageQuotaService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class StorageQuotaServiceTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        $this->tenant = new Tenant;
        $this->tenant->forceFill(['id' => 'quota-tenant']);
        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    public function test_registration_is_completed_before_the_lock_is_released(): void
    {
        $used = (1024 * 1024 * 1024) - 10;
        $usage = $this->createMock(UsageMetricsRepositoryInterface::class);
        $usage->expects(self::exactly(2))
            ->method('storageUsageForObject')
            ->willReturnCallback(function (string $disk, string $path) use (&$used): array {
                return [
                    'used' => $used,
                    'previous' => 0,
                ];
            });
        $service = $this->service($usage);
        Storage::disk('s3')->put('first.bin', '12345');
        Storage::disk('s3')->put('second.bin', '123456');

        $service->commitFile('s3', 'first.bin', function (int $size) use (&$used): void {
            $used += $size;
        });

        $this->expectException(StorageQuotaExceededException::class);
        $service->commitFile('s3', 'second.bin', static function (): void {});
    }

    public function test_file_is_removed_when_persistence_fails(): void
    {
        $usage = $this->createMock(UsageMetricsRepositoryInterface::class);
        $usage->expects(self::once())
            ->method('storageUsageForObject')
            ->willReturn(['used' => 0, 'previous' => 0]);
        $service = $this->service($usage);
        Storage::disk('s3')->put('failed.bin', 'content');

        try {
            $service->commitFile('s3', 'failed.bin', $this->failPersistence(...));
            self::fail('A persistência deveria falhar.');
        } catch (RuntimeException $exception) {
            self::assertSame('persistence failed', $exception->getMessage());
        }

        self::assertFalse(Storage::disk('s3')->exists('failed.bin'));
    }

    private function service(UsageMetricsRepositoryInterface $usage): StorageQuotaService
    {
        $matrix = $this->createMock(PlanMatrixService::class);
        $matrix->expects(self::atLeastOnce())
            ->method('isUnlimitedLimitForTenant')
            ->with($this->tenant, 'storage_gb')
            ->willReturn(false);
        $matrix->expects(self::atLeastOnce())
            ->method('getLimitForTenant')
            ->with($this->tenant, 'storage_gb')
            ->willReturn(1);

        return new StorageQuotaService($usage, $matrix);
    }

    private function failPersistence(int $size): object
    {
        throw new RuntimeException('persistence failed');
    }
}
