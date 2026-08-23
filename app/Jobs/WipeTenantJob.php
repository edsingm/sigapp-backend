<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Tenant;
use App\Services\Privacy\TenantLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Tries(5)]
#[Backoff([60, 300, 900, 1800])]
#[Timeout(600)]
#[Queue('tenant-provisioning')]
class WipeTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $tenantId,
        public readonly bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return 'tenant-wipe:'.$this->tenantId;
    }

    public function handle(TenantLifecycleService $lifecycle): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant instanceof Tenant) {
            $lifecycle->wipe($tenant, $this->force);
        }
    }

    public function failed(Throwable $exception): void
    {
        Tenant::query()->whereKey($this->tenantId)->update([
            'wipe_status' => TenantLifecycleService::WIPE_FAILED,
            'wipe_last_error' => Str::limit($exception->getMessage(), 2000, ''),
        ]);

        Log::critical('Wipe de tenant esgotou as tentativas.', [
            'tenant_id' => $this->tenantId,
            'exception' => $exception::class,
        ]);
    }
}
