<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\AnalyzeDocumentJob;
use App\Jobs\IndexDocumentEmbeddingJob;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Ai\Document\DocumentAnalysisResult;
use App\Services\Ai\Document\DocumentUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AnalyzeDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
    }

    public function test_persists_completed_analysis_from_understanding_service(): void
    {
        Queue::fake([IndexDocumentEmbeddingJob::class]);

        $user = User::query()->create([
            'name' => 'Analista',
            'email' => 'analista-doc@test.com',
            'password' => Hash::make('password'),
        ]);
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Análise',
            'created_by' => $user->id,
        ]);
        $documento = Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $analysis = DocumentAnalysis::query()->create([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'queued',
            'provider' => 'opencode_go',
            'model' => 'gpt-5.6-luna',
        ]);

        /** @var DocumentUnderstandingService&MockInterface $understanding */
        $understanding = Mockery::mock(DocumentUnderstandingService::class);
        $understanding->shouldReceive('analyze')
            ->once()
            ->andReturn(new DocumentAnalysisResult(
                extractedFields: [
                    'summary' => 'Resumo da matrícula.',
                    'key_fields' => DocumentAnalysisResult::emptyExtractedFields()['key_fields'],
                ],
                confidence: 0.91,
                limitations: [],
                provider: 'opencode_go',
                model: 'gpt-5.6-luna',
                promptTokens: 10,
                completionTokens: 5,
            ));

        $job = new AnalyzeDocumentJob((int) $analysis->id);
        $job->handle($understanding);

        $analysis->refresh();
        $this->assertSame('completed', $analysis->status);
        $this->assertSame('Resumo da matrícula.', $analysis->extracted_fields['summary'] ?? null);
        $this->assertEqualsWithDelta(0.91, (float) $analysis->confidence, 0.001);
        $this->assertNotNull($analysis->completed_at);
        Queue::assertPushed(IndexDocumentEmbeddingJob::class, 1);
    }

    public function test_embedding_reindex_is_best_effort_and_analysis_stays_completed(): void
    {
        Queue::fake([IndexDocumentEmbeddingJob::class]);

        $user = User::query()->create([
            'name' => 'Analista Embed',
            'email' => 'analista-embed@test.com',
            'password' => Hash::make('password'),
        ]);
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Embed',
            'created_by' => $user->id,
        ]);
        $documento = Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $analysis = DocumentAnalysis::query()->create([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'queued',
            'provider' => 'opencode_go',
            'model' => 'gpt-5.6-luna',
        ]);

        $understanding = Mockery::mock(DocumentUnderstandingService::class);
        $understanding->shouldReceive('analyze')->once()->andReturn(new DocumentAnalysisResult(
            extractedFields: [
                'summary' => 'Ok',
                'key_fields' => DocumentAnalysisResult::emptyExtractedFields()['key_fields'],
            ],
            confidence: 0.7,
            limitations: [],
            provider: 'opencode_go',
            model: 'gpt-5.6-luna',
        ));

        $job = new AnalyzeDocumentJob((int) $analysis->id);
        $job->handle($understanding);

        $analysis->refresh();
        $this->assertSame('completed', $analysis->status);
        $this->assertSame('Ok', $analysis->extracted_fields['summary'] ?? null);
        Queue::assertPushed(IndexDocumentEmbeddingJob::class, 1);
    }

    public function test_failed_handler_marks_analysis_as_failed(): void
    {
        $user = User::query()->create([
            'name' => 'Analista 2',
            'email' => 'analista-doc-2@test.com',
            'password' => Hash::make('password'),
        ]);
        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Análise 2',
            'created_by' => $user->id,
        ]);
        $documento = Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Matrícula.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $analysis = DocumentAnalysis::query()->create([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'running',
            'provider' => 'opencode_go',
            'model' => 'gpt-5.6-luna',
        ]);

        $job = new AnalyzeDocumentJob((int) $analysis->id);
        $job->failed(new \RuntimeException('provider down'));

        $analysis->refresh();
        $this->assertSame('failed', $analysis->status);
        $this->assertNotNull($analysis->error_message);
    }
}
