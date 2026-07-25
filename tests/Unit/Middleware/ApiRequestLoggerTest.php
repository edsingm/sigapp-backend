<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiRequestLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\After;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ApiRequestLoggerTest extends TestCase
{
    #[After]
    public function tearDownMockery(): void
    {
        Mockery::close();
    }

    public function test_does_not_log_query_parameter_values(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Requisição de API', Mockery::on(function (array $context): bool {
                self::assertSame('/api/v1/tenants', $context['url']);
                self::assertSame(['page', 'access_token'], $context['query_keys']);
                self::assertStringNotContainsString('top-secret-token', json_encode($context, JSON_THROW_ON_ERROR));

                return true;
            }));

        Log::shouldReceive('channel')
            ->once()
            ->with('tenant')
            ->andReturn($logger);

        $request = Request::create(
            '/api/v1/tenants?page=2&access_token=top-secret-token',
            'GET'
        );

        $response = (new ApiRequestLogger)->handle(
            $request,
            static fn (): Response => new Response(status: 200)
        );

        self::assertSame(200, $response->getStatusCode());
    }
}
