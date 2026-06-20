<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Garante as fronteiras de camada definidas no AGENTS.md:
 *  - Controllers não executam queries Eloquent diretamente (delegam a Service/Repository).
 *  - Todo Job define um handler failed().
 */
class LayerBoundariesTest extends TestCase
{
    private const ROOT_PATH = __DIR__.'/../../';

    /**
     * Métodos estáticos que indicam uma query Eloquent direta.
     *
     * @var array<int, string>
     */
    private array $forbiddenMethods = [
        'query', 'where', 'create', 'find', 'first', 'firstOrCreate',
        'updateOrCreate', 'findOrFail', 'firstOrFail', 'withTrashed', 'all', 'count',
    ];

    /**
     * Facades/helpers cujos nomes estáticos colidem com os métodos proibidos
     * mas não são Eloquent.
     *
     * @var array<int, string>
     */
    private array $allowedClasses = [
        'ApiResponseService', 'Cache', 'Gate', 'Log', 'DB', 'Auth', 'Password',
        'Hash', 'Str', 'RateLimiter', 'Excel', 'Pdf', 'self', 'static', 'Carbon',
        'CarbonImmutable', 'Response', 'Validator',
    ];

    /**
     * Controllers excluídos por serem integração de framework de terceiros.
     * WebhookController estende o controller do Laravel Cashier e manipula
     * o ciclo de vida de webhooks do Stripe diretamente.
     *
     * @var array<int, string>
     */
    private array $excludedControllers = [
        'WebhookController.php',
    ];

    public function test_controllers_avoid_direct_eloquent_queries(): void
    {
        $files = $this->phpFilesIn(self::ROOT_PATH.'app/Http/Controllers');

        foreach ($files as $file) {
            foreach ($this->excludedControllers as $excluded) {
                if (str_ends_with($file, $excluded)) {
                    continue 2;
                }
            }

            $contents = file_get_contents($file);
            $this->assertIsString($contents);

            $violations = $this->findForbiddenStaticCalls($contents);

            $this->assertSame(
                [],
                $violations,
                sprintf(
                    "Controller '%s' não deve usar Eloquent diretamente. Encontrado: %s. ".
                    'Delegue a um Service/Repository.',
                    $file,
                    implode(', ', $violations)
                )
            );
        }
    }

    public function test_all_jobs_define_failed_handler(): void
    {
        $files = $this->phpFilesIn(self::ROOT_PATH.'app/Jobs');

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);

            $this->assertStringContainsString(
                'public function failed(',
                $contents,
                "Job '{$file}' deve implementar failed() para tratar falhas definitivas."
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function findForbiddenStaticCalls(string $contents): array
    {
        $methods = implode('|', $this->forbiddenMethods);
        $allowed = $this->allowedClasses;
        $violations = [];

        if (preg_match_all('/([A-Z][A-Za-z0-9_]*)::('.$methods.')\(/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (! in_array($match[1], $allowed, true)) {
                    $violations[] = $match[1].'::'.$match[2];
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesIn(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
