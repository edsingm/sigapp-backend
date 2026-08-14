<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Encryption\TenantEncrypter;
use App\Models\Central\Tenant;
use App\Models\Tenant\Proprietario;
use App\Services\Billing\BrazilianTaxIdValidator;
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
use Throwable;

#[Tries(3)]
#[Timeout(300)]
#[Backoff([30, 120])]
#[Queue('exports')]
class BackfillTenantPiiEncryptionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    public int $uniqueFor = 360;

    public function uniqueId(): string
    {
        return 'pii-backfill:'.$this->tenantId;
    }

    public function handle(TenantEncrypter $encrypter): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant || $tenant->getAttribute('pii_encrypted_at') !== null) {
            return;
        }

        $tenant->run(function () use ($encrypter): void {
            if (! $encrypter->isConfigured()) {
                return;
            }

            $ids = Proprietario::query()->orderBy('id')->pluck('id');
            foreach ($ids as $id) {
                /** @var Proprietario|null $proprietario */
                $proprietario = Proprietario::query()->find($id);
                if ($proprietario === null) {
                    continue;
                }

                $raw = (string) ($proprietario->getAttributes()['cpf_cnpj'] ?? '');
                $plain = $proprietario->cpf_cnpj;
                $hashSource = is_string($plain) && $plain !== '' ? $plain : $raw;
                $normalized = $hashSource !== '' ? BrazilianTaxIdValidator::normalizeTaxId($hashSource) : '';

                $proprietario->forceFill([
                    'cpf_cnpj_hash' => $normalized !== '' ? hash('sha256', $normalized) : null,
                ])->save();
            }
        });

        $tenant->update(['pii_encrypted_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BackfillTenantPiiEncryptionJob falhou.', [
            'tenant_id' => $this->tenantId,
            'exception' => $exception::class,
        ]);
    }
}
