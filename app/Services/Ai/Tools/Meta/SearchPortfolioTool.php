<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\GetDashboardSummaryTool;
use App\Services\Ai\Tools\GetRankingTool;
use App\Services\Ai\Tools\ListTerrenosTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: listagem de carteira, dashboard e ranking.
 */
class SearchPortfolioTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Portfólio unificado. action=list (filtros de terrenos), dashboard (totais/KPIs), ranking (score). '
            .'Não use para alertas de parados (AnalyzePortfolio monitor) nem detalhe de um terreno (GetTerreno).';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'list');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'list' => $this->call(new ListTerrenosTool, $forward),
            'dashboard' => $this->call(new GetDashboardSummaryTool, $forward),
            'ranking' => $this->call(app(GetRankingTool::class), $forward),
            default => $this->unknownAction($action, ['list', 'dashboard', 'ranking']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('list | dashboard | ranking')
                ->enum(['list', 'dashboard', 'ranking']),
            'search' => $schema->string()->description('list: busca nome/endereço'),
            'workflow_stage' => $schema->string()->description('list: etapa do workflow'),
            'workflow_status_code' => $schema->string()->description('list: status do workflow'),
            'cidade' => $schema->string()->description('list: nome da cidade'),
            'cidade_code' => $schema->string()->description('list: código IBGE'),
            'somente_parados' => $schema->boolean()->description('list: só sem atualização recente'),
            'parados_dias' => $schema->integer()->description('list: janela em dias')->min(1),
            'order_by' => $schema->string()
                ->description('list: updated_at | valor | nome')
                ->enum(['updated_at', 'valor', 'nome']),
            'limit' => $schema->integer()->description('list/ranking: máximo de itens')->min(1),
        ];
    }
}
