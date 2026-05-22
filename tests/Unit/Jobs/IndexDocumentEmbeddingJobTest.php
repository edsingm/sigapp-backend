<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IndexDocumentEmbeddingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
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

        $refl = new \ReflectionClass($job);
        $triesAttr = $refl->getAttributes(Tries::class);
        $backoffAttr = $refl->getAttributes(Backoff::class);

        $this->assertSame(3, $triesAttr[0]->getArguments()[0]);
        $this->assertSame(30, $backoffAttr[0]->getArguments()[0]);
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

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }
}
