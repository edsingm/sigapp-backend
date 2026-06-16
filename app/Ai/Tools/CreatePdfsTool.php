<?php

namespace App\Ai\Tools;

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
    public function description(): Stringable|string
    {
        return 'Gera PDFs para o usuário. Use quando o usuário pedir um relatório, contrato, invoice, resumo, etc. Forneça um nome de arquivo, título e o conteúdo HTML completo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = Str::slug($request['filename']).'-'.Str::uuid().'.pdf';
        $path = 'pdfs/'.$filename;

        // HTML completo (a IA pode gerar isso)
        $html = $request['html_content'];

        try {
            Pdf::html($html)
                ->format('A4')
                ->margins(20, 20, 20, 20)
                ->withBrowsershot(function ($browsershot) {
                    $chromePath = $this->resolveChromePath();
                    if ($chromePath && method_exists($browsershot, 'setChromePath')) {
                        $browsershot->setChromePath($chromePath);
                    }

                    if (method_exists($browsershot, 'noSandbox')) {
                        $browsershot->noSandbox();
                    }
                })
                ->disk('public')
                ->save($path);
        } catch (Throwable $e) {
            Log::warning('AI PDF generation failed', [
                'filename' => $filename,
                'title' => $request['title'] ?? 'Documento Gerado',
                'error' => $e->getMessage(),
            ]);

            if (str_contains($e->getMessage(), 'Could not find Chrome')) {
                return 'Nao foi possivel gerar o PDF neste ambiente porque o Chrome/Chromium nao esta instalado no servidor. '
                    .'O chat continua funcionando, mas a infraestrutura de PDF precisa da instalacao do navegador headless '
                    .'ou de um driver alternativo para concluir essa acao.';
            }

            return 'Nao foi possivel gerar o PDF solicitado neste momento. Motivo tecnico: '.$e->getMessage();
        }

        $url = tenant_asset($path);

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
                ->description('Conteúdo completo em HTML do PDF. Pode incluir Tailwind classes se estiver usando Browsershot.'),
        ];
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
