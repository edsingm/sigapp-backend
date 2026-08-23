<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillTenantPiiEncryptionJob;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class BackfillTenantPiiCommand extends Command
{
    protected $signature = 'privacy:backfill-tenant-pii {--tenant= : ID ou slug específico} {--chunk=200 : Registros por chunk}';

    protected $description = 'Despacha o backfill verificável de PII para tenants ainda não concluídos';

    public function handle(): int
    {
        $identifier = $this->option('tenant');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $query = Tenant::query()->where(function ($query): void {
            $query->whereNull('pii_encryption_version')
                ->orWhere('pii_encryption_version', '<', BackfillTenantPiiEncryptionJob::VERSION)
                ->orWhere('pii_encryption_status', 'failed');
        });

        if (is_string($identifier) && $identifier !== '') {
            $query->where(function ($query) use ($identifier): void {
                $query->whereKey($identifier)->orWhere('slug', $identifier);
            });
        }

        $dispatched = 0;
        $query->select(['id'])->toBase()->chunkById(100, function ($rows) use ($chunkSize, &$dispatched): void {
            foreach ($rows as $row) {
                BackfillTenantPiiEncryptionJob::dispatch((string) $row->id, $chunkSize);
                $dispatched++;
            }
        });

        $this->info("Backfills despachados: {$dispatched}");

        return self::SUCCESS;
    }
}
