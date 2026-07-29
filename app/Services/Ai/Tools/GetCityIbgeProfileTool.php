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
        // LLM costuma mandar "estado" em vez de "uf"
        $uf = trim((string) ($request['uf'] ?? $request['estado'] ?? '')) ?: null;

        // "Londrina - PR" às vezes vem só em cidade
        if (($uf === null || $uf === '') && $cidade !== null && preg_match('/\b([A-Za-z]{2})\s*$/', $cidade, $m) === 1) {
            $uf = strtoupper($m[1]);
            $cidade = trim((string) preg_replace('/[\s\-\/]*[A-Za-z]{2}\s*$/', '', $cidade));
        }

        if ($codigoMunicipio === null && ($cidade === null || $uf === null)) {
            return AiToolResponse::validation(
                'Informe um codigo_municipio válido (7 dígitos) ou a combinação cidade + uf (ex.: Londrina e PR).'
            );
        }

        try {
            $result = $this->cityProfileService->getCityProfile(
                $codigoMunicipio,
                $cidade,
                $uf
            );
        } catch (Throwable $exception) {
            return AiToolResponse::error('Falha ao consultar dados do IBGE: '.$exception->getMessage());
        }

        return AiToolResponse::ok(is_array($result) ? $result : ['profile' => $result]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'codigo_municipio' => $schema->string()
                ->description('Código IBGE do município (7 dígitos). Alternativa a cidade+uf.'),
            'cidade' => $schema->string()
                ->description('Nome da cidade (usar com uf). Ex.: Londrina'),
            'uf' => $schema->string()
                ->description('Sigla do estado (ex.: PR). Obrigatório se não houver codigo_municipio.'),
            'estado' => $schema->string()
                ->description('Alias de uf (ex.: PR). Aceito se uf não for enviado.'),
        ];
    }
}
