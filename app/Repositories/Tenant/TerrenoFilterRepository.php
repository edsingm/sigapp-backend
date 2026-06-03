<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Repositories\Contracts\TerrenoFilterRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TerrenoFilterRepository implements TerrenoFilterRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Terreno>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->with([
                'responsavel',
                'regional',
                'cidade',
            ])
            ->withSum('terrenoProdutos as total_unidades', 'unidades')
            ->addSelect([
                'vgv_total' => TerrenoProduto::select(DB::raw('COALESCE(SUM(COALESCE(valor, 0) * COALESCE(unidades, 0)), 0)'))
                    ->whereColumn('terreno_id', 'terrenos.id'),
            ]);

        $nome = $filters['nome'] ?? null;
        if ($nome !== null && $nome !== '') {
            $query->whereRaw('LOWER(nome) LIKE ?', [Str::lower($nome).'%']);
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

        $dateField = $filters['date_field'] ?? 'created_at';
        if (! is_string($dateField) || trim($dateField) === '') {
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

        $sortBy = $filters['sort_by'] ?? 'created_at';
        if (! is_string($sortBy) || trim($sortBy) === '') {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
