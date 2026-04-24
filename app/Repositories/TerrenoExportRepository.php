<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\TerrenoExportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TerrenoExportRepository implements TerrenoExportRepositoryInterface
{
    public function queryForExport(array $filters): Collection
    {
        $query = Terreno::query()
            ->with([
                'status',
                'responsavel',
                'regional',
                'cidade',
            ])
            ->withSum('terrenoProdutos as total_unidades', 'unidades');

        $this->applyFilters($query, $filters);

        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function findForSingleExport(int $id): ?Terreno
    {
        return Terreno::with([
            'status',
            'responsavel',
            'regional',
            'cidade',
            'terrenoProdutos.produto',
            'informacoes.createdBy',
            'proprietarios',
        ])
            ->withSum('terrenoProdutos as total_unidades', 'unidades')
            ->find($id);
    }

    public function findForChecklist(int $id): ?Terreno
    {
        return Terreno::with([
            'responsavel',
            'regional',
            'cidade',
            'terrenoProdutos.produto',
            'proprietarios',
            'corretorExterno',
        ])
            ->find($id);
    }

    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        $nome = $filters['nome'] ?? null;
        if ($nome !== null && $nome !== '') {
            $query->whereRaw('LOWER(nome) LIKE ?', [Str::lower($nome) . '%']);
        }

        $workflowStatuses = $filters['workflow_statuses'] ?? null;
        if (is_array($workflowStatuses) && count($workflowStatuses)) {
            $query->whereIn('workflow_status_code', $workflowStatuses);
        }

        $ufs = $filters['ufs'] ?? null;
        if (is_array($ufs) && count($ufs)) {
            $query->whereIn('estado', $ufs);
        }

        $cidades = $filters['cidades'] ?? null;
        if (is_array($cidades) && count($cidades)) {
            $query->whereIn('cidade_code', $cidades);
        }

        $gestores = $filters['gestor_ids'] ?? null;
        if (is_array($gestores) && count($gestores)) {
            $query->whereIn('responsavel_id', $gestores);
        }

        $corretores = $filters['corretor_ids'] ?? null;
        if (is_array($corretores) && count($corretores)) {
            $query->whereIn('corretor_id', $corretores);
        }

        $regionais = $filters['regional_ids'] ?? null;
        if (is_array($regionais) && count($regionais)) {
            $query->whereIn('regional_id', $regionais);
        }

        $dateField = $filters['date_field'] ?? null;
        if (empty($dateField)) {
            $dateField = 'created_at';
        }

        $dataInicio = $filters['data_inicio'] ?? null;
        $dataFim = $filters['data_fim'] ?? null;
        if ($dataInicio && $dataFim) {
            $query->whereBetween($dateField, [$dataInicio, $dataFim]);
        }

        $ano = $filters['ano'] ?? null;
        if ($ano) {
            $query->whereYear($dateField, (int) $ano);
        }
    }
}
