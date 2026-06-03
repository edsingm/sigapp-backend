<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IndexDocumentEmbeddingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Mockery;
use Monolog\Logger;
use RuntimeException;
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

    public function test_job_tem_timeout(): void
    {
        $job = new IndexDocumentEmbeddingJob(1);

        $refl = new \ReflectionClass($job);
        $timeoutAttr = $refl->getAttributes(Timeout::class);

        $this->assertNotEmpty($timeoutAttr);
        $this->assertSame(120, $timeoutAttr[0]->getArguments()[0]);
    }

    public function test_failed_loga_erro_sem_lancar_excecao(): void
    {
        $captured = null;

        Log::swap(new class($captured) extends \Illuminate\Log\Logger
        {
            public function __construct(mixed &$captured)
            {
                parent::__construct(new Logger('test'));
                $this->captured = &$captured;
            }

            public mixed $captured;

            public function error($message, array $context = []): void
            {
                $this->captured = ['message' => $message, 'context' => $context];
            }
        });

        $job = new IndexDocumentEmbeddingJob(42);
        $job->failed(new RuntimeException('OpenRouter API timeout'));

        $this->assertNotNull($captured);
        $this->assertSame('IndexDocumentEmbeddingJob falhou definitivamente', $captured['message']);
        $this->assertSame(42, $captured['context']['document_id']);
        $this->assertSame('OpenRouter API timeout', $captured['context']['error']);
        $this->assertSame(RuntimeException::class, $captured['context']['exception_class']);
    }
}
