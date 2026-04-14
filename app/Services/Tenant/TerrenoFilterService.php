<?php

namespace App\Services\Tenant;

use App\Http\Requests\Tenant\FilterTerrenosRequest;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TerrenoFilterService
{
    /**
     * Aplica filtros avançados na consulta de terrenos e retorna os resultados paginados.
     */
    public function filter(FilterTerrenosRequest $request)
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

        $nome = $request->input('nome');
        if ($nome !== null && $nome !== '') {
            $query->whereRaw('LOWER(nome) LIKE ?', [Str::lower($nome).'%']);
        }

        $workflowStatuses = $request->input('workflow_statuses');
        if (is_array($workflowStatuses) && count($workflowStatuses)) {
            $query->whereIn('workflow_status_code', $workflowStatuses);
        }

        $ufs = $request->input('ufs');
        if (is_array($ufs) && count($ufs)) {
            $query->whereIn('estado', $ufs);
        }

        $cidades = $request->input('cidades');
        if (is_array($cidades) && count($cidades)) {
            $query->whereIn('cidade_code', $cidades);
        }

        $gestores = $request->input('gestor_ids');
        if (is_array($gestores) && count($gestores)) {
            $query->whereIn('responsavel_id', $gestores);
        }

        $corretores = $request->input('corretor_ids');
        if (is_array($corretores) && count($corretores)) {
            $query->whereIn('corretor_id', $corretores);
        }

        $regionais = $request->input('regional_ids');
        if (is_array($regionais) && count($regionais)) {
            $query->whereIn('regional_id', $regionais);
        }

        $dateField = $request->input('date_field');
        if (empty($dateField) || ! is_string($dateField) || trim($dateField) === '') {
            $dateField = 'created_at';
        }
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
        if ($dataInicio && $dataFim) {
            $query->whereBetween($dateField, [$dataInicio, $dataFim]);
        }

        $ano = $request->input('ano');
        if ($ano) {
            $query->whereYear($dateField, (int) $ano);
        }

        $sortBy = $request->input('sort_by') ?? 'created_at';
        if (empty($sortBy) || ! is_string($sortBy) || trim($sortBy) === '') {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower((string) ($request->input('sort_dir') ?? 'desc'));
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) ($request->input('per_page') ?? 20);
        $page = (int) ($request->input('page') ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
