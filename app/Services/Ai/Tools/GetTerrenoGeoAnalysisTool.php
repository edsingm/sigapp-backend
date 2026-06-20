<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Area\PolygonCalculator;
use App\Services\Tenant\Geo\GeoProximityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTerrenoGeoAnalysisTool implements Tool
{
    public function __construct(
        private readonly GeoProximityService $overpass,
        private readonly PolygonCalculator $polygonCalc,
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna análise geográfica completa de um terreno: declividade, APP, área útil, topografia, vias (ruas/avenidas/rodovias) e pontos de apoio próximos (escolas, hospitais, mercados, bancos, postos de gasolina, etc.).';
    }

    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $radiusMetros = max(100, min(5000, (int) ($request['radius_metros'] ?? 1000)));

        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar terrenos.';
        }

        $terreno = Terreno::query()
            ->select([
                'id', 'nome', 'endereco', 'bairro', 'cidade_code', 'estado', 'cep',
                'polygon_coords', 'area_calculada', 'area_calculo_status',
                'area_total', 'area_declividade', 'area_app', 'area_util',
                'percentual_aproveitamento',
                'declividade_classificacao', 'declividade_avaliacao',
                'declividade_impacto_custo',
                'declividade_percentual_maximo', 'declividade_percentual_medio',
                'app_polygons', 'steep_polygons',
                'area_calculada_em',
            ])
            ->find($terrenoId);

        if (! $terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('view', $terreno)) {
            return "Acesso negado: você não tem permissão para visualizar o terreno {$terrenoId}.";
        }

        $polygon = $terreno->polygon_coords ?? [];
        $hasPolygon = count($polygon) >= 3;

        $payload = [
            'terreno_id' => $terreno->id,
            'nome' => $terreno->nome,
            'endereco' => $terreno->endereco,
            'bairro' => $terreno->bairro,
            'cidade_code' => $terreno->cidade_code,
            'estado' => $terreno->estado,
        ];

        $payload['area'] = $this->buildAreaSection($terreno);
        $payload['declividade'] = $this->buildDeclividadeSection($terreno);
        $payload['app'] = $this->buildAppSection($terreno);

        if ($hasPolygon) {
            $center = $this->polygonCalc->centroid($polygon);
            $bbox = $this->polygonCalc->boundingBox($polygon);

            $payload['topografia'] = [
                'centroide' => $center,
                'bounding_box' => $bbox,
                'vertices' => count($polygon),
                'observacao' => 'Elevações processadas pelo CalculateUsableAreaJob no calculo de declividade.',
            ];

            try {
                $payload['vias'] = $this->overpass->getNearbyRoads($polygon, $radiusMetros);
                $payload['pontos_de_apoio'] = $this->overpass->getNearbyAmenities($polygon, $radiusMetros);
            } catch (\Throwable $e) {
                $payload['vias'] = null;
                $payload['pontos_de_apoio'] = null;
                $payload['aviso_geo'] = 'Erro ao consultar API geográfica: '.$e->getMessage();
            }
            $payload['radius_metros_usado'] = $radiusMetros;
        } else {
            $payload['topografia'] = null;
            $payload['vias'] = null;
            $payload['pontos_de_apoio'] = null;
            $payload['aviso'] = 'Polígono do terreno não cadastrado. Cadastre as coordenadas para obter dados de vias e pontos de apoio.';
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar análise geográfica.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
            'radius_metros' => $schema->integer()->description('Raio em metros para busca de vias e pontos de apoio (padrão: 1000, máx: 5000).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAreaSection(Terreno $terreno): array
    {
        return [
            'status_calculo' => $terreno->area_calculo_status,
            'calculado_em' => optional($terreno->area_calculada_em)?->toAtomString(),
            'area_total_m2' => $terreno->area_total,
            'area_declividade_m2' => $terreno->area_declividade,
            'area_app_m2' => $terreno->area_app,
            'area_util_m2' => $terreno->area_util,
            'percentual_aproveitamento' => $terreno->percentual_aproveitamento,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeclividadeSection(Terreno $terreno): array
    {
        return [
            'classificacao' => $terreno->declividade_classificacao,
            'avaliacao' => $terreno->declividade_avaliacao,
            'impacto_custo' => $terreno->declividade_impacto_custo,
            'percentual_maximo' => $terreno->declividade_percentual_maximo,
            'percentual_medio' => $terreno->declividade_percentual_medio,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAppSection(Terreno $terreno): array
    {
        $appPolygons = $terreno->app_polygons ?? [];

        return [
            'area_app_m2' => $terreno->area_app,
            'quantidade_zonas' => count($appPolygons),
        ];
    }
}
