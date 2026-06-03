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
        'app/Services/AiAnomalyDetectionService.php',
        'app/Services/AiPredictiveAnalysisService.php',
        'app/Services/AiTelemetryService.php',
        'app/Services/Tenant/MobilePushService.php',
        'app/Services/Tenant/LandWorkflowService.php',
        'app/Services/Tenant/TerrenoFilterService.php',
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
            $violations = $this->findForbiddenStaticCalls($tokens);

            $this->assertSame(
                [],
                $violations,
                sprintf(
                    "Service '%s' must not use Eloquent directly. Found forbidden static calls: %s. ".
                    'Move the queries to a Repository (Contracts/XxxRepositoryInterface + concrete) and inject it.',
                    $relativePath,
                    implode(', ', $violations)
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

    private function stripPhpComments(string $code): string
    {
        // Remove single-line comments.
        $code = preg_replace('#//[^\n]*#', '', $code) ?? $code;
        // Remove multi-line comments (non-greedy).
        $code = preg_replace('#/\*.*?\*/#s', '', $code) ?? $code;

        return $code;
    }
}
