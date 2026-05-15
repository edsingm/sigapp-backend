<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IndexDocumentEmbeddingJob;
use App\Services\AiEmbeddingService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class IndexDocumentEmbeddingJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_tem_tries_e_backoff(): void
    {
        $job = new IndexDocumentEmbeddingJob(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->backoff);
    }

    public function test_job_guarda_document_id(): void
    {
        $job = new IndexDocumentEmbeddingJob(42);

        // Usa reflection para acessar property protected
        $reflection = new \ReflectionClass($job);
        $prop = $reflection->getProperty('documentId');
        $this->assertSame(42, $prop->getValue($job));
    }

    public function test_job_implementa_should_queue(): void
    {
        $job = new IndexDocumentEmbeddingJob(1);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
