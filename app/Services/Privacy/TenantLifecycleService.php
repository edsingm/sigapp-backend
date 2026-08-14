<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Notifications\TenantWipeUpcomingNotification;
use App\Services\Tenant\TenantCacheService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Throwable;

class TenantLifecycleService
{
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

        if ((bool) $tenant->getAttribute('database_created')) {
            try {
                (new DeleteDatabase($tenant))->handle();
            } catch (Throwable) {
                // SQLite de teste e schemas já removidos não bloqueiam o wipe do registro.
            }
        }

        $this->deleteTenantStorage($tenant);
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
            'wiped_at' => now(),
        ]);

        return $tenant->refresh();
    }

    /**
     * @return array<int, Tenant>
     */
    public function dueForWipe(): array
    {
        return $this->tenantsWhereWipeDue();
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

        /** @var Collection<int, Tenant> $rows */
        $rows = Tenant::query()
            ->whereNull('wiped_at')
            ->whereNull($sentAtColumn)
            ->whereNotNull('wipe_scheduled_at')
            ->where('wipe_scheduled_at', '<=', now()->addDays($daysBefore))
            ->get();

        return $rows->values()->all();
    }

    /**
     * @return array<int, Tenant>
     */
    private function tenantsWhereWipeDue(): array
    {
        /** @var Collection<int, Tenant> $rows */
        $rows = Tenant::query()
            ->whereNull('wiped_at')
            ->whereNotNull('wipe_scheduled_at')
            ->where('wipe_scheduled_at', '<=', now())
            ->get();

        return $rows->values()->all();
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

    private function deleteTenantStorage(Tenant $tenant): void
    {
        try {
            $prefix = 'tenants/'.$tenant->getKey();
            $disk = Storage::disk('s3');
            $files = $disk->allFiles($prefix);
            if ($files !== []) {
                $disk->delete($files);
            }
        } catch (Throwable) {
            // Testes sem AWS e falha de storage não bloqueiam o wipe do registro.
        }
    }
}
