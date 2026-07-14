<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Enforces the Repository Pattern: services must not query Eloquent directly.
 *
 * Services listed in $migratedServices have been refactored to depend on
 * Repository contracts and must not contain direct Eloquent calls. This test
 * guarantees they stay clean going forward.
 *
 * When a new service is migrated to use a repository, add its file to
 * $migratedServices to keep it under guard.
 */
class ServicesArchitectureTest extends TestCase
{
    /**
     * Services that have been fully migrated to the Repository Pattern.
     * Adding a file here is a promise: it must remain free of Eloquent calls.
     *
     * @var array<int, string>
     */
    private array $migratedServices = [
        'app/Services/Ai/Tools/AiAnomalyDetectionService.php',
        'app/Services/Ai/Tools/AiPredictiveAnalysisService.php',
        'app/Services/Ai/Tools/AiTelemetryService.php',
        'app/Services/Tenant/MobilePushService.php',
        'app/Services/Tenant/LandWorkflowService.php',
        'app/Services/Tenant/TerrenoFilterService.php',
        'app/Services/Ai/Tools/AiInsightGeneratorService.php',
        'app/Services/Ai/Tools/AiScoringService.php',
        'app/Services/Ai/Tools/AiEmbeddingService.php',
        'app/Services/TenantStatusService.php',
        'app/Services/TenantPlanService.php',
        'app/Services/TenantAclSyncService.php',
        'app/Services/Tenant/AiMonitorService.php',
        'app/Services/Tenant/ProjetoService.php',
        'app/Services/Tenant/TerrenoService.php',
        'app/Services/Tenant/DocumentIntelligenceService.php',
        'app/Services/Ai/Tools/CreatePdfsTool.php',
        'app/Services/Dashboard/DashboardQueryService.php',
    ];

    /**
     * Static method calls that indicate a direct Eloquent query from a service.
     * These are forbidden — services must use a Repository instead.
     *
     * @var array<int, string>
     */
    private array $forbiddenMethods = [
        'query',
        'create',
        'where',
        'first',
        'find',
        'firstOrCreate',
        'updateOrCreate',
        'findOrFail',
        'firstOrFail',
        'withTrashed',
        'forceFill',
    ];

    public function test_migrated_services_avoid_direct_eloquent_calls(): void
    {
        $basePath = __DIR__.'/../../';

        foreach ($this->migratedServices as $relativePath) {
            $absolutePath = $basePath.$relativePath;
            $this->assertFileExists($absolutePath, "Service not found: {$relativePath}");

            $contents = file_get_contents($absolutePath);
            $this->assertIsString($contents);

            // Strip line comments and block comments so we only inspect executable code.
            $code = $this->stripPhpComments($contents);

            $tokens = token_get_all($code);
            $violations = [
                ...$this->findForbiddenStaticCalls($tokens),
                ...$this->findForbiddenInstancePersistence($code),
            ];

            $this->assertSame(
                [],
                $violations,
                sprintf(
                    "Service '%s' must not use Eloquent directly. Found forbidden calls: %s. ".
                    'Move the queries to a Repository (Contracts/XxxRepositoryInterface + concrete) and inject it.',
                    $relativePath,
                    implode(', ', $violations)
                )
            );
        }
    }

    public function test_services_do_not_depend_on_http_request(): void
    {
        $servicePath = __DIR__.'/../../app/Services';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($servicePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);

            $this->assertStringNotContainsString(
                'Illuminate\Http\Request',
                $contents,
                sprintf(
                    "Service '%s' must not depend on Illuminate\\Http\\Request. ".
                    'Extract the needed values in the Controller and pass them as arguments or a DTO.',
                    $file->getPathname()
                )
            );
        }
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     * @return array<int, string>
     */
    private function findForbiddenStaticCalls(array $tokens): array
    {
        $violations = [];
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $current = $tokens[$i];

            // Look for T_DOUBLE_COLON tokens.
            if (! is_array($current) || $current[0] !== T_DOUBLE_COLON) {
                continue;
            }

            // The next non-whitespace token is the method name.
            $j = $i + 1;
            while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }

            if ($j >= $tokenCount) {
                continue;
            }

            $methodToken = $tokens[$j];

            if (! is_array($methodToken) || $methodToken[0] !== T_STRING) {
                continue;
            }

            $methodName = $methodToken[1];

            if (in_array($methodName, $this->forbiddenMethods, true)) {
                // Reconstruct a simple "Class::method" for the message.
                $className = $this->findClassNameBefore($tokens, $i);
                $violations[] = ($className ?? 'UnknownClass').'::'.$methodName;
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, array{int, string, int}|string>  $tokens
     */
    private function findClassNameBefore(array $tokens, int $doubleColonIndex): ?string
    {
        for ($k = $doubleColonIndex - 1; $k >= 0; $k--) {
            $token = $tokens[$k];

            if (is_array($token)) {
                $tokenId = $token[0];
                $tokenValue = $token[1];

                if ($tokenId === T_WHITESPACE) {
                    continue;
                }

                if (in_array($tokenId, [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    return ltrim($tokenValue, '\\');
                }

                // Stop on any other meaningful token.
                return null;
            }

            // Plain string token (e.g. ';' or '(') — stop searching.
            return null;
        }

        return null;
    }

    /** @return array<int, string> */
    private function findForbiddenInstancePersistence(string $code): array
    {
        $patterns = [
            'object->analyses' => '/->analyses\s*\(\s*\)\s*->/',
            'object->reviews' => '/->reviews\s*\(\s*\)\s*->/',
            'object->versions' => '/->versions\s*\(\s*\)\s*->/',
            'new AiGeneratedReport' => '/new\s+AiGeneratedReport\b/',
            'new DocumentAnalysis' => '/new\s+DocumentAnalysis\b/',
            'new DocumentRequirement' => '/new\s+DocumentRequirement\b/',
        ];

        return array_keys(array_filter(
            $patterns,
            static fn (string $pattern): bool => preg_match($pattern, $code) === 1,
        ));
    }

    private function stripPhpComments(string $code): string
    {
        // Remove single-line comments.
        $code = preg_replace('#//[^\n]*#', '', $code) ?? $code;
        // Remove multi-line comments (non-greedy).
        $code = preg_replace('#/\*.*?\*/#s', '', $code) ?? $code;

        return $code;
    }
}
