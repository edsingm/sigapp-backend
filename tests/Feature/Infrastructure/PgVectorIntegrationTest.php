<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\Central\Tenant;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Repositories\Tenant\AiEmbeddingRepository;
use App\Support\Database\PgVector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PgVectorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Este teste requer PostgreSQL com pgvector.');
        }
    }

    public function test_tenant_migration_creates_hnsw_index_and_native_search_orders_by_cosine_similarity(): void
    {
        $tenant = $this->makeTenant('ci-vector-'.strtolower(Str::random(8)));

        try {
            $manager = $tenant->database()->manager();
            $manager->createDatabase($tenant);
            tenancy()->initialize($tenant);

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);

            $connection = DB::connection('tenant');
            $this->assertNotNull(PgVector::installedVersion($connection));
            $this->assertTrue((bool) $connection->scalar(
                'SELECT EXISTS (
                    SELECT 1
                    FROM pg_indexes
                    WHERE schemaname = current_schema()
                      AND indexname = ?
                )',
                [PgVector::INDEX_NAME],
            ));

            $now = now();
            $terrenoId = $connection->table('terrenos')->insertGetId([
                'nome' => 'Terreno vetorial',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $documentId = $connection->table('terreno_documentos')->insertGetId([
                'terreno_id' => $terrenoId,
                'nome' => 'Matrícula',
                'tipo' => 'matricula',
                'categoria' => 'juridico',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $firstChunkId = $connection->table('ai_document_chunks')->insertGetId([
                'document_id' => $documentId,
                'terreno_id' => $terrenoId,
                'chunk_index' => 0,
                'content' => 'Vetor mais próximo',
                'metadata' => json_encode(['pagina' => 1], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $secondChunkId = $connection->table('ai_document_chunks')->insertGetId([
                'document_id' => $documentId,
                'terreno_id' => $terrenoId,
                'chunk_index' => 1,
                'content' => 'Vetor ortogonal',
                'metadata' => json_encode(['pagina' => 2], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $queryVector = array_fill(0, PgVector::DIMENSIONS, 0.0);
            $queryVector[0] = 1.0;
            $orthogonalVector = array_fill(0, PgVector::DIMENSIONS, 0.0);
            $orthogonalVector[1] = 1.0;

            $connection->table('ai_document_embeddings')->insert([
                [
                    'chunk_id' => $firstChunkId,
                    'embedding' => PgVector::literal($queryVector),
                    'provider' => 'test',
                    'model' => 'embedding-test',
                    'dimensions' => PgVector::DIMENSIONS,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'chunk_id' => $secondChunkId,
                    'embedding' => PgVector::literal($orthogonalVector),
                    'provider' => 'test',
                    'model' => 'embedding-test',
                    'dimensions' => PgVector::DIMENSIONS,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $results = app(AiEmbeddingRepository::class)->searchSimilarByVector(
                $queryVector,
                'embedding-test',
                $terrenoId,
                2,
            );

            $this->assertNotNull($results);
            $this->assertCount(2, $results);
            $firstResult = $results->first();
            $this->assertInstanceOf(AiDocumentEmbedding::class, $firstResult);
            $this->assertSame($firstChunkId, $firstResult->chunk_id);
            $this->assertEqualsWithDelta(1.0, (float) $firstResult->getAttribute('similarity'), 0.0001);
            $this->assertTrue($firstResult->relationLoaded('chunk'));
        } finally {
            tenancy()->end();

            $manager = $tenant->database()->manager();
            if ($manager->databaseExists((string) $tenant->database()->getName())) {
                $manager->deleteDatabase($tenant);
            }
        }
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'CI Admin',
            'admin_email' => "{$slug}@example.test",
            'admin_password' => 'not-used',
        ]);
    }
}
