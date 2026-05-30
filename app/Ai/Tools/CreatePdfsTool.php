<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Stringable;

class CreatePdfsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Gera PDFs para o usuário. Use quando o usuário pedir um relatório, contrato, invoice, resumo, etc. Forneça um nome de arquivo, título e o conteúdo HTML completo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $filename = Str::slug($request['filename']) . '-' . Str::uuid() . '.pdf';
        $path = 'pdfs/' . $filename;

        // HTML completo (a IA pode gerar isso)
        $html = $request['html_content'];

        Pdf::html($html)
            ->meta(title: $request['title'] ?? 'Documento Gerado')
            ->format('A4')
            ->margins(20, 20, 20, 20)
            ->save(storage_path("app/public/{$path}"));

        $url = Storage::url($path);

        return "✅ PDF gerado com sucesso!\n\n" .
               "📄 Nome do arquivo: {$filename}\n" .
               "🔗 Link para download: " . url($url) . "\n\n" .
               "O usuário pode baixar diretamente nesse link.";
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
}
