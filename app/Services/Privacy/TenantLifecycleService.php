<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Notifications\TenantWipeUpcomingNotification;
use App\Services\Tenant\TenantCacheService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Throwable;

class TenantLifecycleService
{
    public const WIPE_SCHEDULED = 'scheduled';

    public const WIPE_RUNNING = 'running';

    public const WIPE_FAILED = 'failed';

    public const WIPE_COMPLETED = 'completed';

    public function __construct(
        private readonly TenantCacheService $cache,
    ) {}

    public function scheduleOffboard(Tenant $tenant): Tenant
    {
        if ($tenant->getAttribute('wiped_at') !== null) {
            return $tenant;
        }

        return $tenant->cancel();
    }

    public function wipe(Tenant $tenant, bool $force = false): Tenant
    {
        if ($tenant->getAttribute('wiped_at') !== null) {
            return $tenant;
        }

        if (! $force && ! (bool) config('privacy.auto_wipe_enabled', false)) {
            return $tenant;
        }

        $tenant->update([
            'wipe_status' => self::WIPE_RUNNING,
            'wipe_attempts' => (int) $tenant->getAttribute('wipe_attempts') + 1,
            'wipe_last_error' => null,
            'wipe_started_at' => $tenant->getAttribute('wipe_started_at') ?? now(),
        ]);

        try {
            $this->deleteAndVerifyTenantSchema($tenant);
            $this->deleteAndVerifyTenantStorage($tenant);

            $tenant->update(['wipe_step' => 'central_anonymizing']);
            $this->cache->flushEntireTenant((string) $tenant->getKey());

            TenantUserDirectory::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->delete();

            $tenant->update([
                'status' => TenantStatus::CANCELLED->value,
                'database_created' => false,
                'encryption_key' => null,
                'admin_name' => 'anonimizado',
                'admin_email' => 'wiped-'.$tenant->getKey().'@privacy.invalid',
                'admin_password' => null,
                'billing_email' => null,
                'billing_phone' => null,
                'billing_street' => null,
                'billing_number' => null,
                'billing_complement' => null,
                'billing_neighborhood' => null,
                'wipe_status' => self::WIPE_COMPLETED,
                'wipe_step' => 'completed',
                'wipe_last_error' => null,
                'wiped_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $tenant->update([
                'wipe_status' => self::WIPE_FAILED,
                'wipe_last_error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw $exception;
        }

        return $tenant->refresh();
    }

    /** @param callable(Tenant): void $callback */
    public function eachDueForWipe(callable $callback): void
    {
        Tenant::query()
            ->whereNull('wiped_at')
            ->whereNotNull('wipe_scheduled_at')
            ->where('wipe_scheduled_at', '<=', now())
            ->select(['id'])
            ->toBase()
            ->chunkById(100, function ($rows) use ($callback): void {
                foreach ($rows as $row) {
                    $callback(Tenant::query()->findOrFail((string) $row->id));
                }
            });
    }

    public function sendDueWipeNotices(): int
    {
        $sent = 0;

        foreach ($this->dueForWipeNotice(
            (int) config('privacy.wipe_first_notice_days_before', 30),
            'wipe_notice_d60_sent_at',
        ) as $tenant) {
            $this->notifyWipeUpcoming($tenant);
            $tenant->update(['wipe_notice_d60_sent_at' => now()]);
            $sent++;
        }

        foreach ($this->dueForWipeNotice(
            (int) config('privacy.wipe_final_notice_days_before', 7),
            'wipe_notice_d83_sent_at',
        ) as $tenant) {
            $this->notifyWipeUpcoming($tenant);
            $tenant->update(['wipe_notice_d83_sent_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    public function acceptAiDocumentTransfer(Tenant $tenant): Tenant
    {
        if ($tenant->getAttribute('ai_document_transfer_accepted_at') === null) {
            $tenant->update(['ai_document_transfer_accepted_at' => now()]);
        }

        return $tenant->refresh();
    }

    /**
     * @return array<int, Tenant>
     */
    private function dueForWipeNotice(int $daysBefore, string $sentAtColumn): array
    {
        $daysBefore = max(1, $daysBefore);

        $tenants = [];
        $rows = Tenant::query()
            ->whereNull('wiped_at')
            ->whereNull($sentAtColumn)
            ->whereNotNull('wipe_scheduled_at')
            ->where('wipe_scheduled_at', '<=', now()->addDays($daysBefore))
            ->select(['id'])
            ->limit(500)
            ->toBase()
            ->get()
            ->values()
            ->all();

        foreach ($rows as $row) {
            $tenants[] = Tenant::query()->findOrFail((string) $row->id);
        }

        return $tenants;
    }

    private function notifyWipeUpcoming(Tenant $tenant): void
    {
        if ($tenant->routeNotificationForMail() === null) {
            return;
        }

        $scheduled = $tenant->getAttribute('wipe_scheduled_at');
        if (! $scheduled instanceof CarbonInterface) {
            return;
        }

        $daysRemaining = max(0, (int) now()->diffInDays($scheduled, false));

        $tenant->notify(new TenantWipeUpcomingNotification(
            (string) $tenant->getAttribute('name'),
            $scheduled,
            $daysRemaining,
        ));
    }

    private function deleteAndVerifyTenantSchema(Tenant $tenant): void
    {
        $tenant->update(['wipe_step' => 'schema_deleting']);
        if (! (bool) $tenant->getAttribute('database_created')
            && DB::connection()->getDriverName() !== 'pgsql') {
            $tenant->update(['wipe_step' => 'schema_deleted']);

            return;
        }

        $manager = $tenant->database()->manager();
        $databaseName = (string) $tenant->database()->getName();

        if ($manager->databaseExists($databaseName)) {
            (new DeleteDatabase($tenant))->handle();
        }

        if ($manager->databaseExists($databaseName)) {
            throw new \RuntimeException('O schema do tenant ainda existe após a exclusão.');
        }

        $tenant->update(['wipe_step' => 'schema_deleted']);
    }

    private function deleteAndVerifyTenantStorage(Tenant $tenant): void
    {
        $tenant->update(['wipe_step' => 'storage_deleting']);
        $prefix = 'tenants/'.$tenant->getKey();
        $disk = Storage::disk('s3');
        $disk->deleteDirectory($prefix);

        if ($disk->allFiles($prefix) !== []) {
            throw new \RuntimeException('O prefixo S3 do tenant ainda contém objetos após a exclusão.');
        }

        $tenant->update(['wipe_step' => 'storage_deleted']);
    }
}
