<?php

namespace App\Services\Tenant\Area;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Identifica o município brasileiro de um polígono via geocoding reverso.
 *
 * Fluxo: centroide → Nominatim (OSM) → nome da cidade + UF → IBGE Localidades API.
 * Ambas as APIs são gratuitas e sem chave necessária.
 */
class IbgeService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

    private const IBGE_MUNICIPIOS_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    /**
     * Recebe as coordenadas do polígono e retorna os dados do município IBGE.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygonCoords
     * @return array{municipio_ibge_codigo: string, municipio_nome: string, estado_sigla: string, estado_nome: string, regiao_nome: string, mesorregiao_nome: string, microrregiao_nome: string}|null
     */
    public function getFromPolygon(array $polygonCoords): ?array
    {
        if (empty($polygonCoords)) {
            return null;
        }

        [$lat, $lng] = $this->centroid($polygonCoords);

        $cityName = $this->reversGeocode($lat, $lng);
        if ($cityName === null) {
            return null;
        }

        return $this->fetchIbgeData($cityName['city'], $cityName['state_code']);
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $coords
     * @return array{float, float}
     */
    private function centroid(array $coords): array
    {
        $latSum = array_sum(array_column($coords, 'lat'));
        $lngSum = array_sum(array_column($coords, 'lng'));
        $count = count($coords);

        return [$latSum / $count, $lngSum / $count];
    }

    /**
     * @return array{city: string, state_code: string}|null
     */
    private function reversGeocode(float $lat, float $lng): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'SigApp/1.0 (contato@sigapp.com.br)'])
                ->timeout(10)
                ->get(self::NOMINATIM_URL, [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('IbgeService: Nominatim retornou erro', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            $address = $data['address'] ?? [];

            // Nominatim pode retornar city, town, village ou municipality
            $city = $address['city']
                ?? $address['town']
                ?? $address['village']
                ?? $address['municipality']
                ?? null;

            // ISO3166-2-lvl4 ex: "BR-SP" → "SP"
            $isoCode = $address['ISO3166-2-lvl4'] ?? null;
            $stateCode = $isoCode ? substr($isoCode, 3) : null;

            if ($city === null || $stateCode === null) {
                Log::warning('IbgeService: Nominatim não retornou cidade ou estado', ['address' => $address]);

                return null;
            }

            return ['city' => $city, 'state_code' => $stateCode];

        } catch (\Throwable $e) {
            Log::warning('IbgeService: falha no Nominatim', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array{municipio_ibge_codigo: string, municipio_nome: string, estado_sigla: string, estado_nome: string, regiao_nome: string, mesorregiao_nome: string, microrregiao_nome: string}|null
     */
    private function fetchIbgeData(string $cityName, string $stateCode): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::IBGE_MUNICIPIOS_URL, ['nome' => $cityName]);

            if (! $response->successful()) {
                Log::warning('IbgeService: IBGE API retornou erro', ['status' => $response->status()]);

                return null;
            }

            $municipios = $response->json();

            if (! is_array($municipios) || empty($municipios)) {
                return null;
            }

            // Filtra pelo estado para evitar cidades homônimas em UFs diferentes
            $municipio = collect($municipios)->first(function ($m) use ($stateCode) {
                $uf = $m['microrregiao']['mesorregiao']['UF']['sigla']
                    ?? $m['regiao-imediata']['regiao-intermediaria']['UF']['sigla']
                    ?? null;

                return $uf === $stateCode;
            });

            if ($municipio === null) {
                // Fallback: pega o primeiro resultado se não achou pelo estado
                $municipio = $municipios[0];
            }

            $uf = $municipio['microrregiao']['mesorregiao']['UF'] ?? $municipio['regiao-imediata']['regiao-intermediaria']['UF'] ?? [];

            return [
                'municipio_ibge_codigo' => (string) ($municipio['id'] ?? ''),
                'municipio_nome' => $municipio['nome'] ?? $cityName,
                'estado_sigla' => $uf['sigla'] ?? $stateCode,
                'estado_nome' => $uf['nome'] ?? '',
                'regiao_nome' => $uf['regiao']['nome'] ?? '',
                'mesorregiao_nome' => $municipio['microrregiao']['mesorregiao']['nome'] ?? '',
                'microrregiao_nome' => $municipio['microrregiao']['nome'] ?? '',
            ];

        } catch (\Throwable $e) {
            Log::warning('IbgeService: falha na IBGE API', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
