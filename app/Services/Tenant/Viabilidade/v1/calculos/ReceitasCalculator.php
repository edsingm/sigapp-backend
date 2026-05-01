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
        if (! $ctx->demandaAtingida || $ctx->mesDemandaAtingida === null) {
            return ['valor' => 0.0];
        }

        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();
        $dataDemandaAtingida = Carbon::parse($ctx->mesDemandaAtingida.'-01')->startOfMonth();
        $valorRtMes = 0.0;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $valorRtMes += $this->calcularRtMesProduto(
                $mes,
                $produto,
                $dataLancamento,
                $dataDemandaAtingida,
            );
        }

        return ['valor' => round($valorRtMes, 2)];
    }

    private function calcularRtMesProduto(
        string $mes,
        array $produto,
        Carbon $dataLancamento,
        Carbon $dataDemandaAtingida
    ): float {
        $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
        $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

        if ($curvaVendas === []) {
            return 0.0;
        }

        $defasagem = max(0, (int) round((float) ($produto['defasagem_pgtoTerreno'] ?? 0)));
        $valorRtMes = 0.0;

        foreach ($curvaVendas as $mesIndex => $percVendasMes) {
            if ($percVendasMes <= 0) {
                continue;
            }

            $dataVenda = $dataLancamento->copy()->addMonths($mesIndex)->startOfMonth();
            $dataLiberacao = $dataVenda->lessThanOrEqualTo($dataDemandaAtingida)
                ? $dataDemandaAtingida->copy()
                : $dataVenda->copy();
            $dataRecebimento = $dataLiberacao->copy()->addMonths($defasagem);

            if ($dataRecebimento->format('Y-m') !== $mes) {
                continue;
            }

            $valorRtMes += $this->calcularRtValorVendaProduto($produto, (float) $percVendasMes);
        }

        return $valorRtMes;
    }

    private function calcularRtValorVendaProduto(array $produto, float $percVendasMes): float
    {
        $unidadesProduto = $produto['quantidade_unidades'] ?? 0;
        $permutasProduto = $produto['permutas'] ?? 0;
        $unidadesEfetivas = max(1, $unidadesProduto - $permutasProduto);
        $unidadesVendidasMes = $unidadesEfetivas * ($percVendasMes / 100);

        $avaliacaoCef = $produto['avaliacao_lotesCef'] ?? 0;
        $preco = $produto['preco'] ?? 0;

        $valorPorUnidade = ($avaliacaoCef > 0 && $avaliacaoCef <= 1)
            ? $avaliacaoCef * $preco
            : $avaliacaoCef;

        return $unidadesVendidasMes * $valorPorUnidade;
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
        $fimMedicao = $fimObra->copy()->addMonths(5);

        if ($dataAtual->startOfMonth() < $inicioObra || $dataAtual->startOfMonth() > $fimMedicao) {
            return ['valor' => 0];
        }

        $mesObraAtual = (int) $inicioObra->diffInMonths($dataAtual->startOfMonth()) + 1;

        $percentualAteLancamento = max(0.0, min(1.0, (float) ($params['obraAteLancamento'] ?? 0.0)));
        $curvaObra = $dadosProdutos['curvaFinanceiraMedicaoAgregada']
            ?? $this->agregarCurvaFinanceiraMedicao((int) ($params['mesesObra'] ?? 0), $percentualAteLancamento);
        $indice = $mesObraAtual - 1;
        $percObraMes = $curvaObra[$indice] ?? 0.0;

        if ($mesObraAtual > $ctx->mesObraAtual) {
            $ctx->curvaObraAcumulada += ($percObraMes / 100);
            $ctx->mesObraAtual = $mesObraAtual;
        }

        $medicaoTeoricaAcumulada = $ctx->valorMedicaoTotal * $ctx->curvaObraAcumulada;

        $totalUnidadesConstrutora = max(1, (int) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1));
        $percVendasAcumulado = min(1.0, $ctx->vendasAcumuladas / $totalUnidadesConstrutora);

        $medicaoVendidaAcumulada = $medicaoTeoricaAcumulada * $percVendasAcumulado;
        $valorReceberMes = max(0, $medicaoVendidaAcumulada - $ctx->medicaoObraAcumulada);

        $ctx->medicaoObraAcumulada += $valorReceberMes;

        return ['valor' => round($valorReceberMes, 2)];
    }

    public function inicializarValorMedicaoTotal(array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        $vgvSemPermuta = $dadosProdutos['vgvSemUnidPermutas'] ?? 0.0;
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'] ?? $vgvSemPermuta;
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

        $valorRecursoProprio = $vgvSemPermuta * (1 - self::PERCENTUAL_FINANCIAMENTO_CEF);
        $valorFinanciamento = max(0, $vgvSemTerrenista - $valorRecursoProprio);

        $ctx->valorMedicaoTotal = max(0, $valorFinanciamento - $totalRecursoTerrenos);
    }

    private function agregarCurvaFinanceiraMedicao(int $mesesObra, float $obraAteLancamento): array
    {
        return $this->curvaService->getCurvaFinanceiraMedicaoParaPrazo($mesesObra, $obraAteLancamento);
    }
}
