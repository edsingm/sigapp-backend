<?php

declare(strict_types=1);

namespace App\Services\Ai\Document;

/**
 * Resultado normalizado da análise de um PDF.
 */
final readonly class DocumentAnalysisResult
{
    /**
     * @param  array{
     *     summary: string,
     *     key_fields: array{
     *         titulo_ou_tipo: mixed,
     *         partes: list<mixed>,
     *         datas: list<mixed>,
     *         numeros_referencia: list<mixed>,
     *         valores: list<mixed>,
     *         local_ou_cartorio: mixed,
     *         observacoes: mixed
     *     }
     * }  $extractedFields
     * @param  list<string>  $limitations
     */
    public function __construct(
        public array $extractedFields,
        public float $confidence,
        public array $limitations,
        public string $provider,
        public string $model,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
    ) {}

    /**
     * @return array{
     *     summary: string,
     *     key_fields: array{
     *         titulo_ou_tipo: null,
     *         partes: list<never>,
     *         datas: list<never>,
     *         numeros_referencia: list<never>,
     *         valores: list<never>,
     *         local_ou_cartorio: null,
     *         observacoes: null
     *     }
     * }
     */
    public static function emptyExtractedFields(): array
    {
        return [
            'summary' => '',
            'key_fields' => [
                'titulo_ou_tipo' => null,
                'partes' => [],
                'datas' => [],
                'numeros_referencia' => [],
                'valores' => [],
                'local_ou_cartorio' => null,
                'observacoes' => null,
            ],
        ];
    }
}
