<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\Ai\Tools\CreateTerrenoReportTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: PDF no chat (relatório de terreno ou HTML livre).
 */
class ExportPdfTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Exporta PDF. action=terreno_report (preferido para terreno_id — HTML montado no backend) '
            .'ou custom (filename+title+html_content). Sempre devolva data.url ao usuário; nunca invente link.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'terreno_report');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'terreno_report' => $this->call(app(CreateTerrenoReportTool::class), $forward),
            'custom' => $this->call(app(CreatePdfsTool::class), $forward),
            default => $this->unknownAction($action, ['terreno_report', 'custom']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('terreno_report | custom')
                ->enum(['terreno_report', 'custom']),
            'terreno_id' => $schema->integer()
                ->description('terreno_report: obrigatório; custom: opcional se relacionado a um terreno'),
            'filename' => $schema->string()->description('custom: nome do arquivo sem extensão'),
            'title' => $schema->string()->description('custom: título do PDF'),
            'html_content' => $schema->string()
                ->description('custom: HTML do corpo (sem html/head/body)'),
        ];
    }
}
