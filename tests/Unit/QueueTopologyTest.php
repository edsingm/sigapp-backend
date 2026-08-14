<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\AnalyzeDocumentJob;
use App\Jobs\BackfillTenantPiiEncryptionJob;
use App\Jobs\CleanupPendingTenantsJob;
use App\Jobs\CommitHiperdadosImportJob;
use App\Jobs\CommitTerrenoImportJob;
use App\Jobs\CreateFullTenantJob;
use App\Jobs\FetchHiperdadosImportJob;
use App\Jobs\GenerateCommitteeAiDossierJob;
use App\Jobs\GenerateReportRunJob;
use App\Jobs\GenerateSubjectPortabilityJob;
use App\Jobs\GenerateTenantExportJob;
use App\Jobs\GenerateTenantPortabilityJob;
use App\Jobs\GenerateTerrenoAiReportJob;
use App\Jobs\IndexDocumentEmbeddingJob;
use App\Jobs\ParseTerrenoPolygonImportJob;
use App\Jobs\RecalculateAiScoresJob;
use App\Jobs\RefreshTenantStatsJob;
use App\Jobs\ValidateTerrenoImportJob;
use App\Notifications\TenantWelcomeNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue as QueueFacade;
use ReflectionClass;
use Tests\TestCase;

class QueueTopologyTest extends TestCase
{
    public function test_redis_visibility_timeout_exceeds_every_job_timeout(): void
    {
        $retryAfter = config('queue.connections.redis.retry_after');

        $this->assertSame(660, $retryAfter);
        $this->assertSame(5, config('queue.connections.redis.block_for'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));

        foreach ($this->applicationJobClasses() as $jobClass) {
            $reflection = new ReflectionClass($jobClass);
            $timeoutAttribute = $reflection->getAttributes(Timeout::class)[0] ?? null;
            $timeout = $timeoutAttribute?->newInstance()->timeout
                ?? $reflection->getDefaultProperties()['timeout']
                ?? null;

            $this->assertIsInt($timeout, "{$jobClass} precisa declarar timeout.");
            $this->assertLessThan(
                $retryAfter,
                $timeout,
                "{$jobClass} pode ser entregue novamente antes de concluir.",
            );
        }
    }

    public function test_specialized_jobs_are_routed_to_isolated_queues(): void
    {
        $expectedQueues = [
            AnalyzeDocumentJob::class => 'ai',
            CommitHiperdadosImportJob::class => 'exports',
            CommitTerrenoImportJob::class => 'exports',
            CreateFullTenantJob::class => 'tenant-provisioning',
            FetchHiperdadosImportJob::class => 'exports',
            GenerateCommitteeAiDossierJob::class => 'ai',
            GenerateReportRunJob::class => 'exports',
            GenerateSubjectPortabilityJob::class => 'exports',
            GenerateTenantExportJob::class => 'exports',
            GenerateTenantPortabilityJob::class => 'exports',
            BackfillTenantPiiEncryptionJob::class => 'exports',
            GenerateTerrenoAiReportJob::class => 'ai',
            IndexDocumentEmbeddingJob::class => 'ai',
            ParseTerrenoPolygonImportJob::class => 'exports',
            RecalculateAiScoresJob::class => 'ai',
            ValidateTerrenoImportJob::class => 'exports',
        ];

        foreach ($expectedQueues as $jobClass => $expectedQueue) {
            $attribute = (new ReflectionClass($jobClass))->getAttributes(Queue::class)[0] ?? null;

            $this->assertNotNull($attribute, "{$jobClass} precisa declarar sua fila.");
            $this->assertSame($expectedQueue, $attribute->newInstance()->queue);
        }
    }

    public function test_concurrency_sensitive_jobs_are_unique_longer_than_their_timeout(): void
    {
        foreach ([
            CleanupPendingTenantsJob::class,
            CommitHiperdadosImportJob::class,
            CommitTerrenoImportJob::class,
            GenerateCommitteeAiDossierJob::class,
            GenerateReportRunJob::class,
            GenerateSubjectPortabilityJob::class,
            GenerateTenantExportJob::class,
            GenerateTenantPortabilityJob::class,
            BackfillTenantPiiEncryptionJob::class,
            GenerateTerrenoAiReportJob::class,
            IndexDocumentEmbeddingJob::class,
            ParseTerrenoPolygonImportJob::class,
            RecalculateAiScoresJob::class,
            RefreshTenantStatsJob::class,
            ValidateTerrenoImportJob::class,
        ] as $jobClass) {
            $reflection = new ReflectionClass($jobClass);
            $defaults = $reflection->getDefaultProperties();
            $timeoutAttribute = $reflection->getAttributes(Timeout::class)[0] ?? null;
            $timeout = $timeoutAttribute?->newInstance()->timeout ?? $defaults['timeout'] ?? null;
            $uniqueFor = $defaults['uniqueFor'] ?? null;

            $this->assertTrue($reflection->implementsInterface(ShouldBeUnique::class));
            $this->assertIsInt($timeout);
            $this->assertIsInt($uniqueFor);
            $this->assertGreaterThan($timeout, $uniqueFor);
        }
    }

    public function test_every_scheduled_task_has_distributed_and_overlap_locks(): void
    {
        $expectedExpirations = [
            'auth-cleanup-central-login-broker' => 10,
            'privacy-cleanup-consent-logs' => 120,
            'privacy-purge-cancelled-tenants' => 180,
            'privacy-purge-soft-deletes' => 180,
            'tenants-cleanup-pending' => 60,
            'tenant-notify-overdue-legalizacao-etapas' => 60,
            'tenant-check-storage-usage' => 120,
            'tenant-cleanup-terreno-imports' => 120,
            'notifications-send-email-digests-daily' => 120,
            'notifications-send-email-digests-weekly' => 120,
            'ai-recalculate-scores' => 360,
            'refresh-tenant-stats' => 60,
            'cache-prune-stale-redis-tags' => 120,
        ];
        $events = collect(app(Schedule::class)->events())->keyBy('description');

        foreach ($expectedExpirations as $name => $expiration) {
            $event = $events->get($name);

            $this->assertNotNull($event, "Schedule {$name} não encontrado.");
            $this->assertTrue($event->onOneServer, "Schedule {$name} precisa de onOneServer().");
            $this->assertTrue($event->withoutOverlapping, "Schedule {$name} precisa de withoutOverlapping().");
            $this->assertSame($expiration, $event->expiresAt);
        }
    }

    public function test_all_email_notifications_use_the_notifications_queue(): void
    {
        foreach ($this->notificationClasses() as $notificationClass) {
            $reflection = new ReflectionClass($notificationClass);
            $attribute = $reflection->getAttributes(Queue::class)[0] ?? null;

            $this->assertTrue(
                $reflection->implementsInterface(ShouldQueue::class),
                "{$notificationClass} precisa implementar ShouldQueue.",
            );
            $this->assertNotNull($attribute, "{$notificationClass} precisa declarar sua fila.");
            $this->assertSame('notifications', $attribute->newInstance()->queue);
        }
    }

    public function test_sending_an_email_notification_dispatches_to_notifications_queue(): void
    {
        QueueFacade::fake();

        NotificationFacade::route('mail', 'queue-test@example.com')->notify(
            new TenantWelcomeNotification('Tenant Teste', 'https://tenant.example.com'),
        );

        QueueFacade::assertPushedOn('notifications', SendQueuedNotifications::class);
    }

    public function test_supervisor_runs_one_isolated_group_per_queue(): void
    {
        $supervisor = file_get_contents(base_path('.docker/supervisord.conf'));

        $this->assertIsString($supervisor);

        foreach ([
            'tenant-provisioning' => 'QUEUE_TENANT_PROVISIONING_PROCESSES',
            'ai' => 'QUEUE_AI_PROCESSES',
            'exports' => 'QUEUE_EXPORTS_PROCESSES',
            'notifications' => 'QUEUE_NOTIFICATIONS_PROCESSES',
            'default' => 'QUEUE_DEFAULT_PROCESSES',
        ] as $queue => $processVariable) {
            $this->assertStringContainsString("[program:queue-{$queue}]", $supervisor);
            $this->assertStringContainsString("--queue={$queue} ", $supervisor);
            $this->assertStringContainsString("numprocs=%(ENV_{$processVariable})s", $supervisor);
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function applicationJobClasses(): array
    {
        return $this->classesUnder(app_path('Jobs'), 'App\\Jobs\\');
    }

    /**
     * @return array<int, class-string<Notification>>
     */
    private function notificationClasses(): array
    {
        return array_values(array_filter(
            $this->classesUnder(app_path('Notifications'), 'App\\Notifications\\'),
            static fn (string $class): bool => is_subclass_of($class, Notification::class),
        ));
    }

    /**
     * @return array<int, class-string>
     */
    private function classesUnder(string $directory, string $namespace): array
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $classes = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($directory) + 1, -4);
            $class = $namespace.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
