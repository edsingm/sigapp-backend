<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Documento;

/**
 * Regras de elegibilidade para análise de conteúdo de documentos (MVP: somente PDF).
 */
final class DocumentAnalysisEligibility
{
    /**
     * Tipos que disparam análise automática no upload (somente se o arquivo for PDF).
     *
     * @var list<string>
     */
    public const AUTO_ANALYZE_TIPOS = [
        'matricula',
        'escritura',
        'certidao_negativa',
        'iptu',
        'contrato',
        'procuracao',
        'rg_cpf',
        'laudo_ambiental',
        'levantamento_topografico',
        'viabilidade',
    ];

    public function isPdfDocumento(Documento $documento): bool
    {
        $path = (string) ($documento->file_path ?? '');
        if ($path === '') {
            return false;
        }

        return $this->isPdfPath($path);
    }

    public function isPdfPath(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isAutoAnalyzableTipo(?string $tipo): bool
    {
        if ($tipo === null || $tipo === '') {
            return false;
        }

        return in_array($tipo, self::AUTO_ANALYZE_TIPOS, true);
    }

    /**
     * Análise automática no upload: PDF + tipo na allowlist.
     * A feature de plano é verificada pelo caller.
     */
    public function shouldAutoAnalyze(Documento $documento): bool
    {
        return $this->isPdfDocumento($documento)
            && $this->isAutoAnalyzableTipo($documento->tipo ?? null);
    }

    /**
     * Sob demanda (API ou tool): qualquer PDF.
     * A feature de plano e auth são verificadas pelo caller.
     */
    public function canAnalyzeOnDemand(Documento $documento): bool
    {
        return $this->isPdfDocumento($documento);
    }
}
