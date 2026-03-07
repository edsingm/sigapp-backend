<?php

namespace App\Services\Tenant\Viabilidade;

use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\Terreno;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ViabilidadeService
{
    protected ViabilidadeUnificadoService $unificadoService;

    public function __construct(
        ViabilidadeUnificadoService $unificadoService
    ) {
        $this->unificadoService = $unificadoService;
    }

    /**
     * Listar viabilidades por terreno
     */
    public function listarViabilidadesPorTerreno(int $terrenoId)
    {
        return Viabilidade::with(['terreno', 'createdBy', 'updatedBy'])
            ->where('terreno_id', $terrenoId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Buscar viabilidade atual (mais recente) de um terreno
     */
    public function buscarViabilidadeAtual(int $terrenoId)
    {
        return Viabilidade::with(['terreno', 'createdBy', 'updatedBy'])
            ->where('terreno_id', $terrenoId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Comparar duas viabilidades
     */
    public function compararViabilidades(int $id1, int $id2): array
    {
        $v1 = $this->buscarViabilidadeComDre($id1);
        $v2 = $this->buscarViabilidadeComDre($id2);

        return [
            'viabilidade_1' => $v1,
            'viabilidade_2' => $v2,
        ];
    }


    /**
     * Criar nova viabilidade e gerar DRE automaticamente
     */
    public function criarViabilidadeComDre(array $dados): array
    {
        return DB::transaction(function () use ($dados) {
            $this->validarDados($dados);

            $viabilidade = Viabilidade::create([
                ...collect($dados)->except(['produtos'])->toArray(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $dados['terreno_id'],
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade->update([
                'resultados_dre' => $dreResultados
            ]);

            return [
                'viabilidade' => $viabilidade->load(['terreno', 'createdBy', 'updatedBy']),
            ];
        });
    }

    /**
     * Atualizar viabilidade e recalcular DRE
     */
    public function atualizarViabilidadeComDre(Viabilidade $viabilidade, array $dados): array
    {
        return DB::transaction(function () use ($viabilidade, $dados) {
            // Se houver validação específica de update, chamar aqui
            // $this->validarDados($dados); // Opcional, dependendo da regra

            $viabilidade->update([
                ...collect($dados)->except(['produtos'])->toArray(),
                'updated_by' => Auth::id(),
            ]);

            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id,
                $dados['produtos'] ?? null
            );

            $viabilidade->update([
                'resultados_dre' => $dreResultados
            ]);

            return [
                'viabilidade' => $viabilidade->fresh(['terreno', 'createdBy', 'updatedBy']),
                'dre_resultados' => $dreResultados
            ];
        });
    }

    /**
     * Buscar viabilidade com DRE por ID
     */
    public function buscarViabilidadeComDre(int $viabilidadeId): array
    {
        try {
            $viabilidade = Viabilidade::with(['terreno.cidade', 'area.cidade', 'createdBy', 'updatedBy'])
                ->findOrFail($viabilidadeId);

            $dreResultados = $viabilidade->resultados_dre;

            // Verifica se precisa recalcular (estrutura antiga ou vazio)
            if ($this->precisaRecalcularDre($dreResultados)) {
                $dreResultados = $this->recalcularDre($viabilidade)['dre_resultados'];
            }

            return [
                'viabilidade' => $viabilidade,
                'dre_resultados' => $dreResultados
            ];

        } catch (Exception $e) {
            Log::error('Erro ao buscar viabilidade com DRE', [
                'viabilidade_id' => $viabilidadeId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Erro ao buscar viabilidade: ' . $e->getMessage());
        }
    }

    private function precisaRecalcularDre($dreResultados): bool
    {
        if (empty($dreResultados))
            return true;

        // Verifica se chaves principais da nova estrutura existem
        if (!isset($dreResultados['indicadores']) || !isset($dreResultados['totais'])) {
            return true;
        }

        $fluxo = $dreResultados['fluxo_mensal'] ?? [];
        $primeiroMes = !empty($fluxo) ? reset($fluxo) : null;

        // Verifica se a estrutura mudou (ex: se não tem chave 'receitas' detalhada)
        return $primeiroMes && !isset($primeiroMes['receitas']);
    }

    /**
     * Listar todas as viabilidades com paginação e filtros
     */
    public function listarTodasViabilidades(array $filtros = [])
    {
        $query = Viabilidade::with(['terreno', 'createdBy', 'updatedBy']);

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->whereHas('terreno', function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%");
            });
        }

        if (!empty($filtros['terreno_id'])) {
            $query->where('terreno_id', $filtros['terreno_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filtros['per_page'] ?? 10);
    }

    /**
     * Validar dados de viabilidade
     */
    public function validarDados(array $dados): array
    {
        if (empty($dados['terreno_id'])) {
            throw new Exception('ID do terreno é obrigatório');
        }

        if (!Terreno::find($dados['terreno_id'])) {
            throw new Exception('Terreno não encontrado');
        }

        // Validação de numéricos pode ser simplificada com filter_var ou validator do Laravel
        // Mantendo lógica original mas simplificada e centralizada
        $camposNumericos = Viabilidade::CAMPOS_FINANCEIROS;

        foreach ($camposNumericos as $campo) {
            if (isset($dados[$campo]) && !is_numeric($dados[$campo])) {
                throw new Exception("Campo {$campo} deve ser numérico");
            }
        }

        if (isset($dados['prazo_obra'])) {
            $prazosValidos = ['18', '24', '36', '48', '60'];
            if (!in_array((string) $dados['prazo_obra'], $prazosValidos)) {
                throw new Exception('Prazo de obra deve ser: 18, 24, 36, 48 ou 60 meses');
            }
        }

        return $dados;
    }

    /**
     * Duplicar viabilidade (para criar nova versão)
     */
    public function duplicarViabilidade(int $viabilidadeId): Viabilidade
    {
        $viabilidadeOriginal = Viabilidade::findOrFail($viabilidadeId);

        $dadosNova = $viabilidadeOriginal->toArray();
        $dadosNova['created_by'] = Auth::id();
        $dadosNova['updated_by'] = Auth::id();
        $dadosNova['resultados_dre'] = null;

        // Remove campos gerados automaticamente
        unset($dadosNova['id'], $dadosNova['created_at'], $dadosNova['updated_at'], $dadosNova['deleted_at']);

        return Viabilidade::create($dadosNova);
    }

    /**
     * Excluir viabilidade (soft delete)
     */
    public function excluirViabilidade(int $viabilidadeId): bool
    {
        $viabilidade = Viabilidade::findOrFail($viabilidadeId);
        return $viabilidade->delete();
    }

    /**
     * Recalcular DRE de uma viabilidade existente
     */
    public function recalcularDre(Viabilidade $viabilidade): array
    {
        return DB::transaction(function () use ($viabilidade) {
            $dreResultados = $this->unificadoService->gerarFluxoMensal(
                $viabilidade->terreno_id,
                $viabilidade->id
            );

            $viabilidade->update([
                'resultados_dre' => $dreResultados,
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);

            return [
                'viabilidade' => $viabilidade->fresh(['terreno', 'createdBy', 'updatedBy']),
                'dre_resultados' => $dreResultados
            ];
        });
    }
}
