<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\AiGeneratedReport;
use App\Repositories\Tenant\AiGeneratedReportRepository;
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
    private ?AiGeneratedReport $lastGeneratedReport = null;

    public function __construct(
        private readonly PlanMatrixService $planMatrix,
        private readonly UsageMetricsService $usageService,
        private readonly AiGeneratedReportRepository $reportRepository,
    ) {}

    public function description(): Stringable|string
    {
        return 'Gera PDFs a partir de HTML fornecido (relatório customizado, resumo, documento). Para relatório completo de um terreno, prefira CreateTerrenoReportTool. Retorna data.url com o link de download.';
    }

    public function handle(Request $request): Stringable|string
    {
        // The same tool instance may be reused by the container during a request.
        // Clear the previous result before starting a new generation so callers
        // can never mistake a stale report for the current one.
        $this->lastGeneratedReport = null;

        $auth = app(AiToolAuth::class);
        $authChecked = filter_var($request['_auth_checked'] ?? false, FILTER_VALIDATE_BOOL);
        $skipRateLimit = filter_var($request['_skip_rate_limit'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $authChecked) {
            if ($deny = $auth->ensureAuthenticated(
                'Acesso negado: autenticação necessária para gerar PDFs.'
            )) {
                return $deny;
            }
        }

        if (! $skipRateLimit) {
            if ($deny = $auth->ensureRateLimit(
                'ai-tool-pdf',
                (int) config('ai.pdf_rate_limit_per_hour', 10),
                3600,
                'Limite de geração de PDF atingido para este período.'
            )) {
                return $deny;
            }
        }

        $filenameInput = trim((string) ($request['filename'] ?? ''));
        $title = trim((string) ($request['title'] ?? ''));
        $htmlContent = (string) ($request['html_content'] ?? '');
        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($filenameInput === '' || mb_strlen($filenameInput) < 3) {
            return AiToolResponse::validation('Informe um filename com pelo menos 3 caracteres.');
        }

        if ($title === '') {
            return AiToolResponse::validation('Informe o título do PDF.');
        }

        if (trim($htmlContent) === '') {
            return AiToolResponse::validation('Informe o html_content do PDF.');
        }

        $maxHtml = max(1000, (int) config('ai.pdf_max_html_chars', 150000));
        if (mb_strlen($htmlContent) > $maxHtml) {
            return AiToolResponse::validation(
                "html_content excede o limite de {$maxHtml} caracteres. Reduza o conteúdo ou use o relatório por terreno."
            );
        }

        if ($terrenoId > 0 && ! $authChecked) {
            $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }

        $filename = Str::slug($filenameInput).'-'.Str::uuid().'.pdf';
        $path = 'pdfs/'.$filename;

        try {
            Pdf::view('exports.ai-pdf', [
                'title' => $title,
                'content' => $this->sanitizeHtml($htmlContent),
            ])
                ->format('A4')
                ->margins(14, 16, 24, 16)
                ->footerView('exports.ai-pdf-footer', [
                    'title' => $title,
                ])
                ->withBrowsershot(function ($browsershot) {
                    $chromePath = $this->resolveChromePath();
                    if ($chromePath && method_exists($browsershot, 'setChromePath')) {
                        $browsershot->setChromePath($chromePath);
                    }

                    if (method_exists($browsershot, 'disableJavascript')) {
                        $browsershot->disableJavascript();
                    }

                    if (config('services.browsershot.no_sandbox') && method_exists($browsershot, 'noSandbox')) {
                        $browsershot->noSandbox();
                    }
                })
                ->disk('s3')
                ->save($path);
        } catch (Throwable $e) {
            Log::warning('AI PDF generation failed', [
                'filename' => $filename,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'Could not find Chrome')) {
                return AiToolResponse::error(
                    'Nao foi possivel gerar o PDF neste ambiente porque o Chrome/Chromium nao esta instalado no servidor. '
                    .'O chat continua funcionando, mas a infraestrutura de PDF precisa da instalacao do navegador headless '
                    .'ou de um driver alternativo para concluir essa acao.'
                );
            }

            return AiToolResponse::error(
                'Nao foi possivel gerar o PDF solicitado neste momento. Motivo tecnico: '.$e->getMessage()
            );
        }

        $tamanho = (int) Storage::disk('s3')->size($path);
        $tenant = tenancy()->tenant;

        if ($tenant && ! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
            $maxBytes = $this->planMatrix->getLimitForTenant($tenant, 'storage_gb') * 1024 * 1024 * 1024;

            if (($this->usageService->getStorageUsedBytes() + $tamanho) > $maxBytes) {
                Storage::disk('s3')->delete($path);

                return AiToolResponse::planDenied(
                    'Não foi possível salvar o PDF: o limite de armazenamento do plano foi atingido. '
                    .'Faça upgrade do plano ou libere espaço para continuar gerando PDFs.'
                );
            }
        }

        $report = $this->reportRepository->create([
            'terreno_id' => $terrenoId > 0 ? $terrenoId : null,
            'nome' => $title,
            'file_path' => $path,
            'tamanho' => $tamanho,
            'created_by' => auth()->id(),
        ]);

        $this->lastGeneratedReport = $report;

        $url = route('ai.reports.download', ['id' => $report->getKey()]);

        return AiToolResponse::ok([
            'filename' => $filename,
            'report_id' => $report->getKey(),
            'url' => $url,
            'bytes' => $tamanho,
            'terreno_id' => $terrenoId > 0 ? $terrenoId : null,
        ], 'PDF gerado com sucesso. Envie o link de download ao usuário (campo data.url).');
    }

    public function lastGeneratedReport(): ?AiGeneratedReport
    {
        return $this->lastGeneratedReport;
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

        // Imagens remotas fariam o Chromium requisitar URLs arbitrárias a partir do servidor.
        $config->set('URI.AllowedSchemes', [
            'data' => true,
        ]);

        // Desabilita cache em disco durante a geração
        $config->set('Cache.DefinitionImpl', null);

        return (new \HTMLPurifier($config))->purify($html);
    }

    private function resolveChromePath(): ?string
    {
        $candidates = array_filter([
            config('services.browsershot.chrome_path'),
            config('services.browsershot.puppeteer_executable_path'),
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
