<?php

namespace App\Jobs;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Area\AreaCalculatorService;
use App\Services\Tenant\Area\IbgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job assíncrono para calcular área útil de um terreno.
 *
 * É disparado automaticamente quando polygon_coords é alterado.
 * Atualiza os campos area_total, area_declividade, area_app,
 * area_util, percentual_aproveitamento e area_calculo_status.
 */
class CalculateUsableAreaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $terrenoId,
    ) {}

    /**
     * O lock é baseado no ID do terreno para evitar cálculos concorrentes.
     */
    public function uniqueId(): string
    {
        return (string) $this->terrenoId;
    }

    public function handle(AreaCalculatorService $calculator, IbgeService $ibge): void
    {
        $terreno = Terreno::find($this->terrenoId);

        if ($terreno === null) {
            Log::warning('CalculateUsableAreaJob: terreno não encontrado', [
                'terreno_id' => $this->terrenoId,
            ]);

            return;
        }

        $terreno->update(['area_calculo_status' => 'calculating']);

        try {
            $result = $calculator->calculate($terreno);

            $ibgeData = $ibge->getFromPolygon($terreno->polygon_coords ?? []);

            $terreno->update([
                'area_total' => $result['area_total'],
                'area_declividade' => $result['area_declividade'],
                'area_app' => $result['area_app'],
                'area_util' => $result['area_util'],
                'percentual_aproveitamento' => $result['percentual_aproveitamento'],
                'declividade_classificacao' => $result['declividade_classificacao'],
                'declividade_avaliacao' => $result['declividade_avaliacao'],
                'declividade_impacto_custo' => $result['declividade_impacto_custo'],
                'declividade_percentual_maximo' => $result['declividade_percentual_maximo'],
                'declividade_percentual_medio' => $result['declividade_percentual_medio'],
                'app_polygons' => $result['app_polygons'],
                'steep_polygons' => $result['steep_polygons'],
                'municipio_ibge_codigo' => $ibgeData['municipio_ibge_codigo'] ?? null,
                'municipio_nome' => $ibgeData['municipio_nome'] ?? null,
                'estado_sigla' => $ibgeData['estado_sigla'] ?? null,
                'estado_nome' => $ibgeData['estado_nome'] ?? null,
                'regiao_nome' => $ibgeData['regiao_nome'] ?? null,
                'mesorregiao_nome' => $ibgeData['mesorregiao_nome'] ?? null,
                'microrregiao_nome' => $ibgeData['microrregiao_nome'] ?? null,
                'area_calculada_em' => now(),
                'area_calculo_status' => 'success',
            ]);

            Log::info('Área útil calculada com sucesso', [
                'terreno_id' => $terreno->id,
                'area_total' => $result['area_total'],
                'area_util' => $result['area_util'],
                'percentual_aproveitamento' => $result['percentual_aproveitamento'],
            ]);

        } catch (Throwable $e) {
            $terreno->update(['area_calculo_status' => 'failed']);

            Log::error('Falha ao calcular área útil', [
                'terreno_id' => $terreno->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $terreno = Terreno::find($this->terrenoId);

        if ($terreno !== null) {
            $terreno->update(['area_calculo_status' => 'failed']);
        }

        Log::error('CalculateUsableAreaJob falhou permanentemente', [
            'terreno_id' => $this->terrenoId,
            'error' => $exception->getMessage(),
        ]);
    }
}
