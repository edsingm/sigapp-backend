<?php

namespace App\Services\Ai\Tools;

/**
 * Envelope padrão de saída das AI tools do SIG IA.
 *
 * Contrato estável para o LLM e para telemetria:
 * { ok, code, message, data }
 */
final class AiToolResponse
{
    public const OK = 'OK';

    public const EMPTY = 'EMPTY';

    public const DENIED = 'DENIED';

    public const PLAN_DENIED = 'PLAN_DENIED';

    public const VALIDATION = 'VALIDATION';

    public const ERROR = 'ERROR';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data = [], ?string $message = null): string
    {
        return self::encode(true, self::OK, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function empty(string $message, array $data = []): string
    {
        return self::encode(true, self::EMPTY, $message, $data);
    }

    public static function denied(string $message): string
    {
        return self::encode(false, self::DENIED, $message, []);
    }

    public static function planDenied(string $message): string
    {
        return self::encode(false, self::PLAN_DENIED, $message, []);
    }

    public static function validation(string $message): string
    {
        return self::encode(false, self::VALIDATION, $message, []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function error(string $message, array $data = []): string
    {
        return self::encode(false, self::ERROR, $message, $data);
    }

    /**
     * Meta de listagem com total real (pré-limit) e página retornada.
     *
     * @return array{total: int, returned: int, limit: int, has_more: bool}
     */
    public static function listMeta(int $total, int $returned, int $limit): array
    {
        $limit = max(1, $limit);
        $total = max(0, $total);
        $returned = max(0, $returned);

        return [
            'total' => $total,
            'returned' => $returned,
            'limit' => $limit,
            'has_more' => $total > $returned,
        ];
    }

    /**
     * Normaliza limit de listagens (default 10, max 50).
     */
    public static function clampLimit(mixed $limit, int $default = 10, int $max = 50): int
    {
        $value = (int) ($limit ?? $default);

        return max(1, min($value > 0 ? $value : $default, $max));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(bool $ok, string $code, ?string $message, array $data): string
    {
        $payload = [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: '{"ok":false,"code":"ERROR","message":"Falha ao serializar resposta da ferramenta.","data":{}}';
    }
}
