<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tenant;

use App\Models\Tenant\Documento;
use App\Services\Tenant\DocumentAnalysisEligibility;
use PHPUnit\Framework\TestCase;

class DocumentAnalysisEligibilityTest extends TestCase
{
    private DocumentAnalysisEligibility $eligibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibility = new DocumentAnalysisEligibility;
    }

    public function test_detects_pdf_paths_and_documentos(): void
    {
        $this->assertTrue($this->eligibility->isPdfPath('documentos/foo.PDF'));
        $this->assertFalse($this->eligibility->isPdfPath('documentos/foo.png'));

        $doc = new Documento;
        $doc->file_path = 'documentos/matricula.pdf';
        $this->assertTrue($this->eligibility->isPdfDocumento($doc));
    }

    public function test_auto_allowlist_includes_juridicos_and_laudos(): void
    {
        foreach (['matricula', 'rg_cpf', 'viabilidade', 'laudo_ambiental'] as $tipo) {
            $this->assertTrue($this->eligibility->isAutoAnalyzableTipo($tipo), $tipo);
        }

        $this->assertFalse($this->eligibility->isAutoAnalyzableTipo('outros'));
        $this->assertFalse($this->eligibility->isAutoAnalyzableTipo('planta'));
    }

    public function test_should_auto_analyze_requires_pdf_and_allowlist_tipo(): void
    {
        $ok = new Documento;
        $ok->file_path = 'documentos/m.pdf';
        $ok->tipo = 'matricula';
        $this->assertTrue($this->eligibility->shouldAutoAnalyze($ok));

        $wrongTipo = new Documento;
        $wrongTipo->file_path = 'documentos/m.pdf';
        $wrongTipo->tipo = 'outros';
        $this->assertFalse($this->eligibility->shouldAutoAnalyze($wrongTipo));

        $notPdf = new Documento;
        $notPdf->file_path = 'documentos/m.png';
        $notPdf->tipo = 'matricula';
        $this->assertFalse($this->eligibility->shouldAutoAnalyze($notPdf));
    }

    public function test_on_demand_accepts_any_pdf(): void
    {
        $doc = new Documento;
        $doc->file_path = 'documentos/x.pdf';
        $doc->tipo = 'outros';
        $this->assertTrue($this->eligibility->canAnalyzeOnDemand($doc));
    }
}
