<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\AiGeneratedReport;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Stringable;
use Throwable;

class CreatePdfsTool implements Tool
{
    public function __construct(
        private readonly PlanMatrixService $planMatrix,
        private readonly UsageMetricsService $usageService,
    ) {}

    public function description(): Stringable|string
    {
        return 'Gera PDFs para o usuário. Use quando o usuário pedir um relatório, contrato, invoice, resumo, etc. Forneça um nome de arquivo, título e o conteúdo HTML completo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = Str::slug($request['filename']).'.pdf';
        $path = 'pdfs/'.$filename;

        try {
            Pdf::view('exports.ai-pdf', [
                'title' => $request['title'],
                'content' => $this->sanitizeHtml($request['html_content']),
            ])
                ->format('A4')
                ->margins(14, 16, 24, 16)
                ->footerView('exports.ai-pdf-footer', [
                    'title' => $request['title'],
                ])
                ->withBrowsershot(function ($browsershot) {
                    $chromePath = $this->resolveChromePath();
                    if ($chromePath && method_exists($browsershot, 'setChromePath')) {
                        $browsershot->setChromePath($chromePath);
                    }

                    if (method_exists($browsershot, 'disableJavascript')) {
                        $browsershot->disableJavascript();
                    }

                    if (env('BROWSERSHOT_NO_SANDBOX') && method_exists($browsershot, 'noSandbox')) {
                        $browsershot->noSandbox();
                    }
                })
                ->disk('s3')
                ->save($path);
        } catch (Throwable $e) {
            Log::warning('AI PDF generation failed', [
                'filename' => $filename,
                'title' => $request['title'],
                'error' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'Could not find Chrome')) {
                return 'Nao foi possivel gerar o PDF neste ambiente porque o Chrome/Chromium nao esta instalado no servidor. '
                    .'O chat continua funcionando, mas a infraestrutura de PDF precisa da instalacao do navegador headless '
                    .'ou de um driver alternativo para concluir essa acao.';
            }

            return 'Nao foi possivel gerar o PDF solicitado neste momento. Motivo tecnico: '.$e->getMessage();
        }

        $tamanho = (int) Storage::disk('s3')->size($path);
        $tenant = tenancy()->tenant;

        if ($tenant && ! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
            $maxBytes = $this->planMatrix->getLimitForTenant($tenant, 'storage_gb') * 1024 * 1024 * 1024;

            if (($this->usageService->getStorageUsedBytes() + $tamanho) > $maxBytes) {
                Storage::disk('s3')->delete($path);

                return 'Não foi possível salvar o PDF: o limite de armazenamento do plano foi atingido. '
                    .'Faça upgrade do plano ou libere espaço para continuar gerando PDFs.';
            }
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        $report = AiGeneratedReport::create([
            'terreno_id' => $terrenoId > 0 ? $terrenoId : null,
            'nome' => (string) $request['title'],
            'file_path' => $path,
            'tamanho' => $tamanho,
            'created_by' => auth()->id(),
        ]);

        $url = route('ai.reports.download', ['id' => $report->id]);

        return "✅ PDF gerado com sucesso!\n\n".
               "📄 Nome do arquivo: {$filename}\n".
               '🔗 Link para download: '.$url."\n\n".
               'O usuário pode baixar diretamente nesse link.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()
                ->min(3)
                ->required()
                ->description('Nome do arquivo sem extensão (ex: relatorio-vendas)'),

            'title' => $schema->string()
                ->required()
                ->description('Título que aparecerá no PDF'),

            'html_content' => $schema->string()
                ->required()
                ->description('Conteúdo HTML do corpo do PDF (sem as tags html/head/body). Use h1-h6, p, ul, ol, table. Estilos inline são permitidos.'),

            'terreno_id' => $schema->integer()
                ->description('ID do terreno relacionado a este PDF, se houver.'),
        ];
    }

    private function sanitizeHtml(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed',
            'h1,h2,h3,h4,h5,h6,p[class|style],'.
            'ul[class|style],ol[class|style],li[class|style],'.
            'table[class|style],thead,tbody,tfoot,'.
            'tr[class|style],th[class|style|colspan|rowspan],td[class|style|colspan|rowspan],'.
            'strong,em,b,i,hr,br,'.
            'blockquote[class|style],div[class|style],span[class|style],'.
            'code[class],pre[class],img[src|alt|class|style|width|height]'
        );

        // Permite apenas imagens HTTP/HTTPS/data usadas pelo relatório.
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'data' => true,
        ]);

        // Desabilita cache em disco durante a geração
        $config->set('Cache.DefinitionImpl', null);

        return (new \HTMLPurifier($config))->purify($html);
    }

    private function resolveChromePath(): ?string
    {
        $candidates = array_filter([
            env('BROWSERSHOT_CHROME_PATH'),
            env('PUPPETEER_EXECUTABLE_PATH'),
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
