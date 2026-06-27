<?php

namespace App\Services\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class GetCityIbgeProfileTool implements Tool
{
    public function __construct(
        protected AiIbgeCityProfileService $cityProfileService
    ) {}

    public function description(): Stringable|string
    {
        return 'Busca dados oficiais do IBGE sobre um município, incluindo panorama, histórico, PIB, trabalho, renda e habitação.';
    }

    public function handle(Request $request): Stringable|string
    {
        $codigoMunicipio = trim((string) ($request['codigo_municipio'] ?? '')) ?: null;
        $cidade = trim((string) ($request['cidade'] ?? '')) ?: null;
        $uf = trim((string) ($request['uf'] ?? '')) ?: null;

        if ($codigoMunicipio === null && ($cidade === null || $uf === null)) {
            return 'Informe um codigo_municipio válido ou a combinação cidade + uf.';
        }

        try {
            $result = $this->cityProfileService->getCityProfile(
                $codigoMunicipio,
                $cidade,
                $uf
            );
        } catch (Throwable $exception) {
            return 'Falha ao consultar dados do IBGE: '.$exception->getMessage();
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar perfil da cidade.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo_municipio' => $schema->string(),
            'cidade' => $schema->string(),
            'uf' => $schema->string(),
        ];
    }
}
