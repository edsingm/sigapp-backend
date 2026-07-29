<?php

namespace App\Services\Ai\Tools;

use App\Services\Tenant\TerrenoAiReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Gera PDF de relatório de um terreno no backend (HTML montado com dados reais).
 * Preferir esta tool a CreatePdfsTool quando o usuário pedir relatório de um terreno.
 */
class CreateTerrenoReportTool implements Tool
{
    public function __construct(
        private readonly TerrenoAiReportService $reportService,
        private readonly CreatePdfsTool $pdfTool,
    ) {}

    public function description(): Stringable|string
    {
        return 'Gera o relatório SIG IA em PDF de um terreno específico (dados + narrativa + mapa quando houver). Use quando o usuário pedir relatório/PDF de um terreno pelo ID. Retorna link de download em data.url.';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);

        if ($deny = $auth->ensureAuthenticated(
            'Acesso negado: autenticação necessária para gerar relatórios.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
        if (is_string($terrenoOrDeny)) {
            return $terrenoOrDeny;
        }

        if ($deny = $auth->ensureRateLimit(
            'ai-tool-pdf',
            (int) config('ai.pdf_rate_limit_per_hour', 10),
            3600,
            'Limite de geração de PDF atingido para este período.'
        )) {
            return $deny;
        }

        try {
            $report = $this->reportService->build($terrenoOrDeny);
        } catch (Throwable $e) {
            return AiToolResponse::error(
                'Não foi possível montar o relatório do terreno: '.$e->getMessage()
            );
        }

        return $this->pdfTool->handle(new Request([
            'filename' => $report['filename'],
            'title' => $report['title'],
            'html_content' => $report['html_content'],
            'terreno_id' => $terrenoOrDeny->id,
            // evita consumir o rate limit de novo no CreatePdfsTool
            '_skip_rate_limit' => true,
            '_auth_checked' => true,
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()
                ->required()
                ->description('ID do terreno para o qual gerar o relatório PDF.'),
        ];
    }
}
