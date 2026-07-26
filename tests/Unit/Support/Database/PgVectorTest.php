<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Database;

use App\Support\Database\PgVector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PgVectorTest extends TestCase
{
    public function test_literal_serializes_a_valid_embedding(): void
    {
        $vector = array_fill(0, PgVector::DIMENSIONS, 0.0);
        $vector[0] = 1.0;

        $literal = PgVector::literal($vector);

        $this->assertStringStartsWith('[1.0,0.0', $literal);
        $this->assertStringEndsWith(']', $literal);
    }

    public function test_rejects_an_embedding_with_the_wrong_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('1536 dimensões');

        PgVector::assertValid([0.1, 0.2]);
    }

    public function test_rejects_a_zero_norm_embedding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('norma zero');

        PgVector::assertValid(array_fill(0, PgVector::DIMENSIONS, 0.0));
    }
}
