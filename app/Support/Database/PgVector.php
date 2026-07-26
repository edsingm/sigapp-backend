<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;

final class PgVector
{
    public const DIMENSIONS = 1536;

    public const INDEX_NAME = 'ai_document_embeddings_embedding_hnsw_idx';

    public const DISTANCE_EXPRESSION = '((embedding::text)::vector(1536) <=> CAST(? AS vector(1536)))';

    public const SIMILARITY_EXPRESSION = '1 - ((embedding::text)::vector(1536) <=> CAST(? AS vector(1536)))';

    public static function isPostgreSql(ConnectionInterface $connection): bool
    {
        return $connection instanceof Connection && $connection->getDriverName() === 'pgsql';
    }

    public static function installedVersion(ConnectionInterface $connection): ?string
    {
        if (! self::isPostgreSql($connection)) {
            return null;
        }

        $version = $connection->scalar(
            "SELECT extversion FROM pg_extension WHERE extname = 'vector'"
        );

        return is_string($version) ? $version : null;
    }

    public static function install(ConnectionInterface $connection): void
    {
        if (! self::isPostgreSql($connection)) {
            return;
        }

        $connection->statement('CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA public');
    }

    public static function uninstall(ConnectionInterface $connection): void
    {
        if (! self::isPostgreSql($connection)) {
            return;
        }

        $connection->statement('DROP EXTENSION IF EXISTS vector');
    }

    public static function createEmbeddingIndex(ConnectionInterface $connection): void
    {
        if (! self::isPostgreSql($connection)) {
            return;
        }

        $connection->statement(
            sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON ai_document_embeddings USING hnsw (((embedding::text)::vector(%d)) vector_cosine_ops) WHERE dimensions = %d',
                self::INDEX_NAME,
                self::DIMENSIONS,
                self::DIMENSIONS,
            )
        );
    }

    public static function dropEmbeddingIndex(ConnectionInterface $connection): void
    {
        if (! self::isPostgreSql($connection)) {
            return;
        }

        $connection->statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
    }

    /**
     * @param  array<int, float|int>  $vector
     *
     * @throws JsonException
     */
    public static function literal(array $vector): string
    {
        self::assertValid($vector);

        return json_encode(
            array_values($vector),
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<int, mixed>  $vector
     */
    public static function assertValid(array $vector): void
    {
        if (count($vector) !== self::DIMENSIONS) {
            throw new InvalidArgumentException(
                sprintf('Embedding deve possuir exatamente %d dimensões.', self::DIMENSIONS)
            );
        }

        $squaredNorm = 0.0;

        foreach ($vector as $value) {
            if ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Embedding contém valor não numérico ou não finito.');
            }

            $squaredNorm += (float) $value ** 2;
        }

        if ($squaredNorm === 0.0) {
            throw new InvalidArgumentException('Embedding de norma zero não pode ser indexado.');
        }
    }
}
