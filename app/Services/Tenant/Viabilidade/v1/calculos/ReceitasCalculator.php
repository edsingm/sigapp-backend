<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeFluxoContext;
use Carbon\Carbon;

class ReceitasCalculator
{
    private const PERCENTUAL_FINANCIAMENTO_CEF = 0.80;

    public function __construct(
        private readonly CurvaService $curvaService,
    ) {}

    public function calcular(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        $ctx ??= new ViabilidadeFluxoContext;

        $ctx->vendasAcumuladas += $ctx->vendasPorMes[$mes] ?? 0.0;

        if (! $ctx->demandaAtingida) {
            $atingiu = $ctx->demandaMinima <= 0 || $ctx->vendasAcumuladas >= $ctx->demandaMinima;
            if ($atingiu) {
                $ctx->demandaAtingida = true;
                $ctx->mesDemandaAtingida = $mes;
            }
        }

        $rp = $ctx->recursosProprios[$mes] ?? [];
        $totalRp = ($rp['sinal'] ?? 0.0) + ($rp['parcelas_obra'] ?? 0.0) + ($rp['parcelas_pos'] ?? 0.0);
        $totalAtrasadas = $ctx->parcelasAtrasadas[$mes] ?? 0.0;

        $rt = $ctx->perfil->isCef()
            ? $this->calcularRecursoTerrenos($mes, $dadosProdutos, $datas, $ctx)
            : ['valor' => 0.0];

        $mo = $ctx->perfil->isCef()
            ? $this->calcularMedicaoObra($mes, $dadosProdutos, $datas, $params, $ctx)
            : ['valor' => 0.0];

        $total = $totalRp + $totalAtrasadas + $rt['valor'] + $mo['valor'];

        return [
            'total' => $total,
            'juros_correcao' => ($rp['juros'] ?? 0.0) + ($rp['correcao'] ?? 0.0) + ($rp['correcao_obra'] ?? 0.0),
            'detalhes' => [
                'Recursos Próprios' => round($totalRp, 2),
                'Recursos Próprios (Atrasados)' => round($totalAtrasadas, 2),
                'Recurso Terrenos' => round($rt['valor'], 2),
                'Medição Obra' => round($mo['valor'], 2),
            ],
        ];
    }

    private function calcularRecursoTerrenos(string $mes, array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): array
    {
        unset($ctx);

        $dataAtual = Carbon::parse($mes.'-01');
        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();

        $quartoMes = $dataLancamento->copy()->addMonths(3);
        if ($dataAtual->startOfMonth() < $quartoMes) {
            return ['valor' => 0];
        }

        $mesNumero = (int) $dataLancamento->diffInMonths($dataAtual->copy()->startOfMonth()) + 1;

        if ($mesNumero === 4) {
            $valorAcumulado = 0.0;
            for ($mesVenda = 1; $mesVenda <= 4; $mesVenda++) {
                $valorAcumulado += $this->calcularRtMesVenda($mesVenda, $dadosProdutos);
            }

            return ['valor' => round($valorAcumulado, 2)];
        }

        $valorRtMes = $this->calcularRtMesVenda($mesNumero, $dadosProdutos);

        return ['valor' => round($valorRtMes, 2)];
    }

    private function calcularRtMesVenda(int $mesVenda, array $dadosProdutos): float
    {
        $valorRtMes = 0.0;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            if (empty($curvaVendas)) {
                continue;
            }

            $indiceMes = $mesVenda - 1;
            $percVendasMes = $curvaVendas[$indiceMes] ?? 0;

            $unidadesProduto = $produto['quantidade_unidades'] ?? 0;
            $permutasProduto = $produto['permutas'] ?? 0;
            $unidadesEfetivas = max(1, $unidadesProduto - $permutasProduto);
            $unidadesVendidasMes = $unidadesEfetivas * ($percVendasMes / 100);

            $avaliacaoCef = $produto['avaliacao_lotesCef'] ?? 0;
            $preco = $produto['preco'] ?? 0;

            if ($avaliacaoCef > 0 && $avaliacaoCef <= 1) {
                $valorPorUnidade = $avaliacaoCef * $preco;
            } else {
                $valorPorUnidade = $avaliacaoCef;
            }

            $valorRtMes += $unidadesVendidasMes * $valorPorUnidade;
        }

        return $valorRtMes;
    }

    private function calcularMedicaoObra(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): array {
        $dataAtual = Carbon::parse($mes.'-01');
        $inicioObra = $datas['inicioObra']->copy()->startOfMonth();
        $fimObra = $datas['fimObra']->copy()->startOfMonth();

        $sextoMes = $inicioObra->copy()->addMonths(5);
        if ($dataAtual->startOfMonth() < $sextoMes || $dataAtual->startOfMonth() > $fimObra) {
            return ['valor' => 0];
        }

        $mesObraAtual = (int) $sextoMes->diffInMonths($dataAtual->startOfMonth()) + 1;

        $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($params['mesesObra']);
        $indice = $mesObraAtual - 1;
        $percObraMes = $curvaObra[$indice] ?? 0.0;

        if ($mesObraAtual > $ctx->mesObraAtual) {
            $ctx->curvaObraAcumulada += ($percObraMes / 100);
            $ctx->mesObraAtual = $mesObraAtual;
        }

        $medicaoTeoricaAcumulada = $ctx->valorMedicaoTotal * $ctx->curvaObraAcumulada;

        $totalUnidades = $dadosProdutos['totalUnidades'];
        $percVendasAcumulado = $totalUnidades > 0 ? $ctx->vendasAcumuladas / $totalUnidades : 0;

        $medicaoVendidaAcumulada = $medicaoTeoricaAcumulada * $percVendasAcumulado;
        $valorReceberMes = max(0, $medicaoVendidaAcumulada - $ctx->medicaoObraAcumulada);

        $ctx->medicaoObraAcumulada += $valorReceberMes;

        return ['valor' => round($valorReceberMes, 2)];
    }

    public function inicializarValorMedicaoTotal(array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        $vgvSemPermuta = $dadosProdutos['vgvSemUnidPermutas'] ?? 0.0;
        $totalRecursoTerrenos = 0.0;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidades = ($produto['quantidade_unidades'] ?? 0) - ($produto['permutas'] ?? 0);
            $unidades = max(0, $unidades);
            $preco = $produto['preco'] ?? 0;
            $avaliacaoCef = $produto['avaliacao_lotesCef'] ?? 0;

            if ($avaliacaoCef > 0 && $avaliacaoCef <= 1) {
                $totalRecursoTerrenos += $avaliacaoCef * $preco * $unidades;
            } else {
                $totalRecursoTerrenos += $avaliacaoCef * $unidades;
            }

            $dataLancamento = $datas['dataLancamento'];
            foreach ($curvaVendas as $mesIndex => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $dataVenda = $dataLancamento->copy()->addMonths($mesIndex);
                $chaveMes = $dataVenda->format('Y-m');
                $unidadesVendidas = $unidades * ($percentualVenda / 100);
                $ctx->vendasPorMes[$chaveMes] = ($ctx->vendasPorMes[$chaveMes] ?? 0) + $unidadesVendidas;
            }
        }

        $ctx->valorMedicaoTotal = max(
            0,
            ($vgvSemPermuta * self::PERCENTUAL_FINANCIAMENTO_CEF) - $totalRecursoTerrenos
        );
    }

    private function agregarCurvaObra(int $mesesObra): array
    {
        return $this->curvaService->getCurvaObraParaPrazo($mesesObra);
    }
}
