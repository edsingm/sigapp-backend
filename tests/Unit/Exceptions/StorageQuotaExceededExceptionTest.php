<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\DomainException;
use App\Exceptions\StorageQuotaExceededException;
use Tests\TestCase;

class StorageQuotaExceededExceptionTest extends TestCase
{
    public function test_maps_to_plan_limit_exceeded_api_payload(): void
    {
        $exception = new StorageQuotaExceededException;

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertSame(403, $exception->statusCode());
        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'PLAN_LIMIT_EXCEEDED',
                'message' => 'O arquivo excede o limite de armazenamento do plano.',
                'details' => [
                    'resource' => 'storage_gb',
                ],
            ],
        ], $exception->toResponsePayload());
    }
}
