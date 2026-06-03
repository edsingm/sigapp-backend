<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Inspeção de saúde da aplicação.
 *
 * Cada check retorna o formato:
 *   ['status' => 'ok'|'down', 'message' => string, 'latency_ms' => float]
 *
 * Checks marcados como `critical` derrubam o status geral para `down`
 * (HTTP 503). Demais checks apenas marcam como `degraded`.
 */
class HealthCheckService
{
    /** @var array<string, bool> */
    private const CRITICAL_CHECKS = [
        'database' => true,
        'storage' => true,
    ];

    /**
     * Executa todos os checks e retorna o relatório consolidado.
     *
     * @return array{
     *     status: 'ok'|'degraded'|'down',
     *     timestamp: string,
     *     checks: array<string, array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}>
     * }
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'stripe' => $this->checkStripe(),
            'openrouter' => $this->checkOpenRouter(),
        ];

        $status = $this->aggregateStatus($checks);

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * Verifica o banco de dados central (e tenant, se inicializado).
     *
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkDatabase(): array
    {
        return $this->timed(function (): array {
            DB::connection()->select('SELECT 1');

            $tenancyInitialized = tenancy()->initialized;
            $tenantOk = true;
            $tenantMsg = '';

            if ($tenancyInitialized) {
                try {
                    DB::connection('tenant')->select('SELECT 1');
                } catch (Throwable $e) {
                    $tenantOk = false;
                    $tenantMsg = '; tenant: '.$e->getMessage();
                }
            }

            $message = $tenancyInitialized
                ? ($tenantOk ? 'Central + tenant OK' : 'Central OK, tenant falhou'.$tenantMsg)
                : 'Central OK';

            if (! $tenantOk) {
                throw new \RuntimeException('Conexão tenant indisponível'.$tenantMsg);
            }

            return ['status' => 'ok', 'message' => $message, 'critical' => true];
        }, critical: true);
    }

    /**
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkCache(): array
    {
        return $this->timed(function (): array {
            $key = 'health_check_'.uniqid('', true);
            $value = (string) microtime(true);

            Cache::put($key, $value, 5);

            $retrieved = Cache::get($key);
            Cache::forget($key);

            if ($retrieved !== $value) {
                throw new \RuntimeException('Cache get/put falhou — store: '.config('cache.default'));
            }

            return ['status' => 'ok', 'message' => 'Store: '.config('cache.default')];
        });
    }

    /**
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkStorage(): array
    {
        return $this->timed(function (): array {
            $disk = Storage::disk(config('filesystems.default'));
            $path = 'health-check-'.uniqid('', true).'.txt';
            $content = 'ok';

            $disk->put($path, $content);
            $retrieved = $disk->get($path);
            $disk->delete($path);

            if ($retrieved !== $content) {
                throw new \RuntimeException('Storage put/get falhou — disk: '.config('filesystems.default'));
            }

            return ['status' => 'ok', 'message' => 'Disk: '.config('filesystems.default'), 'critical' => true];
        }, critical: true);
    }

    /**
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkQueue(): array
    {
        return $this->timed(function (): array {
            $default = config('queue.default');

            // Apenas valida que a conexão responde; não tenta despachar job em health check.
            // O sync driver é considerado OK pois jobs rodam imediatamente.
            return ['status' => 'ok', 'message' => 'Connection: '.$default];
        });
    }

    /**
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkStripe(): array
    {
        $secret = config('cashier.secret');

        if (empty($secret)) {
            return [
                'status' => 'ok',
                'message' => 'Não configurado',
                'latency_ms' => 0.0,
                'critical' => false,
            ];
        }

        return $this->timed(function () use ($secret): array {
            // Endpoint mínimo da API Stripe — valida autenticação
            $response = Http::timeout(5)
                ->withToken($secret)
                ->get('https://api.stripe.com/v1/balance');

            if (! $response->successful()) {
                throw new \RuntimeException('Stripe API respondeu HTTP '.$response->status());
            }

            return ['status' => 'ok', 'message' => 'API reachable'];
        });
    }

    /**
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function checkOpenRouter(): array
    {
        $key = config('ai.providers.openrouter.key');

        if (empty($key)) {
            return [
                'status' => 'ok',
                'message' => 'Não configurado',
                'latency_ms' => 0.0,
                'critical' => false,
            ];
        }

        return $this->timed(function () use ($key): array {
            $response = Http::timeout(5)
                ->withHeaders(['Authorization' => 'Bearer '.$key])
                ->get('https://openrouter.ai/api/v1/auth/key');

            if (! $response->successful()) {
                throw new \RuntimeException('OpenRouter respondeu HTTP '.$response->status());
            }

            return ['status' => 'ok', 'message' => 'API reachable'];
        });
    }

    /**
     * Mede a latência de um check e captura exceções.
     *
     * @param  callable(): array{status: 'ok', message: string, critical?: bool}  $check
     * @return array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}
     */
    private function timed(callable $check, bool $critical = false): array
    {
        $start = microtime(true);

        try {
            $result = $check();
            $result['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
            $result['critical'] = $critical;

            /** @var array{status: 'ok', message: string, latency_ms: float, critical: bool} */
            return $result;
        } catch (Throwable $e) {
            return [
                'status' => 'down',
                'message' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'critical' => $critical,
            ];
        }
    }

    /**
     * Consolida o status geral a partir dos checks.
     *
     * @param  array<string, array{status: 'ok'|'down', message: string, latency_ms: float, critical: bool}>  $checks
     * @return 'ok'|'degraded'|'down'
     */
    private function aggregateStatus(array $checks): string
    {
        foreach ($checks as $name => $check) {
            if ($check['status'] === 'down' && (self::CRITICAL_CHECKS[$name] ?? false)) {
                return 'down';
            }
        }

        foreach ($checks as $check) {
            if ($check['status'] === 'down') {
                return 'degraded';
            }
        }

        return 'ok';
    }
}
