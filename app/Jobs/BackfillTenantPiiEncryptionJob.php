<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Encryption\TenantEncrypter;
use App\Encryption\TenantPiiBlindIndexer;
use App\Models\Central\Tenant;
use App\Services\Billing\BrazilianTaxIdValidator;
use App\Services\Tenant\TenantCacheService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Tries(3)]
#[Timeout(300)]
#[Backoff([30, 120])]
#[Queue('exports')]
class BackfillTenantPiiEncryptionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const VERSION = 2;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $chunkSize = 200,
    ) {}

    public int $uniqueFor = 360;

    public function uniqueId(): string
    {
        return 'pii-backfill:'.$this->tenantId;
    }

    public function handle(TenantEncrypter $encrypter, TenantPiiBlindIndexer $blindIndexer): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant
            || ($tenant->getAttribute('pii_encryption_status') === 'completed'
                && (int) $tenant->getAttribute('pii_encryption_version') >= self::VERSION)) {
            return;
        }

        $tenant->update([
            'pii_encryption_status' => 'running',
            'pii_encryption_last_error' => null,
            'pii_encryption_started_at' => $tenant->getAttribute('pii_encryption_started_at') ?? now(),
        ]);

        try {
            $processed = $tenant->run(function () use ($blindIndexer, $encrypter): int {
                $processed = $this->backfillTable($encrypter, 'terreno_proprietarios', [
                    'rg', 'cpf_cnpj', 'email', 'telefone', 'endereco', 'cep',
                    'conjuge_rg', 'conjuge_cpf_cnpj', 'observacoes',
                ], cpfColumn: 'cpf_cnpj')
                    + $this->backfillTable(
                        $encrypter,
                        'corretores_externos',
                        ['email', 'telefone'],
                        blindIndexer: $blindIndexer,
                    )
                    + $this->backfillTable($encrypter, 'terreno_contatos', ['email', 'telefone', 'observacoes']);

                app(TenantCacheService::class)->flushModules('corretores_externos', 'proprietarios', 'terrenos');

                return $processed;
            });

            $tenant->update([
                'pii_encryption_status' => 'completed',
                'pii_encryption_processed' => $processed,
                'pii_encryption_version' => self::VERSION,
                'pii_encryption_last_error' => null,
                'pii_encrypted_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $tenant->update([
                'pii_encryption_status' => 'failed',
                'pii_encryption_last_error' => Str::limit($exception->getMessage(), 2000, ''),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function backfillTable(
        TenantEncrypter $encrypter,
        string $table,
        array $columns,
        ?string $cpfColumn = null,
        ?TenantPiiBlindIndexer $blindIndexer = null,
    ): int {
        $processed = 0;

        DB::connection('tenant')->table($table)
            ->orderBy('id')
            ->chunkById(max(1, $this->chunkSize), function ($rows) use ($encrypter, $table, $columns, $cpfColumn, $blindIndexer, &$processed): void {
                foreach ($rows as $row) {
                    $updates = [];
                    $plainValues = [];

                    foreach ($columns as $column) {
                        $raw = $row->{$column} ?? null;
                        if (! is_string($raw) || $raw === '') {
                            continue;
                        }

                        $plain = $this->plaintextForBackfill($encrypter, $raw, $table, $column, $row->id);
                        $plainValues[$column] = $plain;
                        $updates[$column] = $encrypter->encrypt($plain);
                    }

                    if ($cpfColumn !== null && isset($plainValues[$cpfColumn])) {
                        $normalized = BrazilianTaxIdValidator::normalizeTaxId($plainValues[$cpfColumn]);
                        $updates['cpf_cnpj_hash'] = $normalized !== '' ? hash('sha256', $normalized) : null;
                    }

                    if ($blindIndexer instanceof TenantPiiBlindIndexer) {
                        if (isset($plainValues['email'])) {
                            $updates['email_hash'] = $blindIndexer->email($plainValues['email']);
                        }
                        if (isset($plainValues['telefone'])) {
                            $updates['telefone_hash'] = $blindIndexer->phone($plainValues['telefone']);
                        }
                    }

                    if ($updates !== []) {
                        DB::connection('tenant')->table($table)->where('id', $row->id)->update($updates);
                    }

                    $processed++;
                }
            });

        return $processed;
    }

    private function plaintextForBackfill(
        TenantEncrypter $encrypter,
        string $raw,
        string $table,
        string $column,
        mixed $id,
    ): string {
        try {
            $plain = $encrypter->decrypt($raw);

            if (! is_string($plain)) {
                throw new \RuntimeException('Payload descriptografado não é string.');
            }

            return $plain;
        } catch (Throwable $exception) {
            if ($encrypter->looksLikeEncryptedPayload($raw)) {
                throw new \RuntimeException(
                    "Ciphertext corrompido em {$table}.{$column}, registro {$id}.",
                    0,
                    $exception,
                );
            }

            return $raw;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BackfillTenantPiiEncryptionJob falhou.', [
            'tenant_id' => $this->tenantId,
            'exception' => $exception::class,
        ]);

        Tenant::query()->whereKey($this->tenantId)->update([
            'pii_encryption_status' => 'failed',
            'pii_encryption_last_error' => Str::limit($exception->getMessage(), 2000, ''),
        ]);
    }
}
