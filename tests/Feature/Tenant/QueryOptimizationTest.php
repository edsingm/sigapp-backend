<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Resources\Tenant\ComiteRevisaoResource;
use App\Http\Resources\Tenant\LegalizacaoResource;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Http\Resources\Tenant\UserResource;
use App\Http\Resources\Tenant\ViabilidadeResource;
use App\Models\Central\Cidade;
use App\Models\Tenant\AiRequestLog;
use App\Models\Tenant\ComiteParecerDepartamento;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\PremissasViabilidade;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoInfos;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Repositories\AiTelemetryRepository;
use App\Repositories\Tenant\LegalizacaoRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Repositories\Tenant\UserRepository;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate([
            'name' => RolesEnum::ADMIN->value,
            'guard_name' => 'web',
        ]);

        $this->admin = User::create([
            'name' => 'Query Optimization Admin',
            'email' => 'query-optimization@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
    }

    public function test_terrain_list_query_count_does_not_grow_per_item(): void
    {
        Terreno::create(['nome' => 'Terreno 1']);

        $oneItemQueries = $this->measureQueries(function (): void {
            $paginator = app(TerrenoRepository::class)->paginate(['per_page' => 100]);
            TerrenoResource::collection($paginator->getCollection())->response()->getData(true);
        });

        foreach (range(2, 5) as $number) {
            Terreno::create(['nome' => "Terreno {$number}"]);
        }

        $fiveItemQueries = $this->measureQueries(function (): void {
            $paginator = app(TerrenoRepository::class)->paginate(['per_page' => 100]);
            TerrenoResource::collection($paginator->getCollection())->response()->getData(true);
        });

        $this->assertSame($oneItemQueries, $fiveItemQueries);
    }

    public function test_legalization_list_query_count_does_not_grow_per_city(): void
    {
        Cidade::create([
            'code' => '3550308',
            'city' => 'São Paulo',
            'state' => 'São Paulo',
            'state_code' => 'SP',
        ]);
        $this->createLegalizacao('Legalização 1');

        $oneItemQueries = $this->measureQueries(function (): void {
            $paginator = app(LegalizacaoRepository::class)->paginate(['per_page' => 100]);
            LegalizacaoResource::collection($paginator->getCollection())->response()->getData(true);
        });

        foreach (range(2, 5) as $number) {
            $this->createLegalizacao("Legalização {$number}");
        }

        $fiveItemQueries = $this->measureQueries(function (): void {
            $paginator = app(LegalizacaoRepository::class)->paginate(['per_page' => 100]);
            LegalizacaoResource::collection($paginator->getCollection())->response()->getData(true);
        });

        $this->assertSame($oneItemQueries, $fiveItemQueries);
    }

    public function test_full_terrain_serialization_executes_no_queries(): void
    {
        PremissasViabilidade::factory()->ativa()->createOne();
        $terreno = Terreno::create(['nome' => 'Terreno completo']);
        TerrenoInfos::create([
            'terreno_id' => $terreno->id,
            'descricao' => 'Nota sem lazy loading',
            'created_by' => $this->admin->id,
            'user_id' => $this->admin->id,
        ]);
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terreno->id,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $revisao = ComiteRevisao::create([
            'terreno_id' => $terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'concluido',
            'final_decision' => 'aprovado_comite',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
        ]);
        ComiteParecerDepartamento::create([
            'comite_revisao_id' => $revisao->id,
            'department_code' => 'juridico',
            'reviewer_user_id' => $this->admin->id,
            'decision' => 'aprovado',
            'checklist_completed' => true,
            'reviewed_at' => now(),
        ]);

        $loaded = app(TerrenoRepository::class)->loadDetailRelations($terreno);
        $this->assertTrue($loaded->relationLoaded('comiteAtual'));
        $this->assertNotNull($loaded->comiteAtual);
        ViabilidadeResource::make($loaded->viabilidadeAtual)->response()->getData(true);

        $committeeQueries = $this->measureQueries(function () use ($loaded): void {
            $committeePayload = ComiteRevisaoResource::make($loaded->comiteAtual)
                ->response()
                ->getData(true)['data'];

            $this->assertSame(
                $this->admin->name,
                $committeePayload['decided_by_user']['name'],
            );
        });
        $this->assertSame(0, $committeeQueries, json_encode(DB::getQueryLog(), JSON_THROW_ON_ERROR));

        $terrainQueries = $this->measureQueries(function () use ($loaded): void {
            $payload = TerrenoResource::make($loaded)
                ->response()
                ->getData(true)['data'];

            $this->assertSame(
                $this->admin->name,
                $payload['informacoes'][0]['user']['name'],
            );
        });
        $this->assertSame(0, $terrainQueries, json_encode(DB::getQueryLog(), JSON_THROW_ON_ERROR));
    }

    public function test_user_list_query_count_does_not_grow_per_item(): void
    {
        $oneItemQueries = $this->measureUserListQueries();

        foreach (range(2, 5) as $number) {
            $user = User::create([
                'name' => "Usuário {$number}",
                'email' => "query-user-{$number}@test.com",
                'password' => Hash::make('password123'),
            ]);
            $user->assignRole(RolesEnum::ADMIN);
        }

        $fiveItemQueries = $this->measureUserListQueries();

        $this->assertSame($oneItemQueries, $fiveItemQueries);
    }

    public function test_current_month_ai_cost_uses_month_boundaries(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        try {
            $this->createAiLog(0.25, '2026-06-30 23:59:59');
            $this->createAiLog(1.25, '2026-07-01 00:00:00');
            $this->createAiLog(2.50, '2026-08-01 00:00:00');

            $this->assertSame(1.25, app(AiTelemetryRepository::class)->getCurrentMonthCost());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_query_indexes_are_present(): void
    {
        $this->assertIndexExists('ai_request_logs', 'ai_request_logs_created_at_idx');
        $this->assertIndexExists('comments', 'comments_related_created_at_idx');
        $this->assertIndexExists('entity_activities', 'entity_activities_terreno_happened_idx');
        $this->assertIndexExists('terrenos', 'terrenos_workflow_created_at_idx');
        $this->assertIndexExists('audit_logs', 'audit_logs_created_at_id_idx');
    }

    public function test_query_index_migrations_are_reversible(): void
    {
        /** @var Migration $tenantMigration */
        $tenantMigration = require database_path(
            'migrations/tenant/2026_07_25_000001_add_query_performance_indexes.php',
        );
        /** @var Migration $centralMigration */
        $centralMigration = require database_path(
            'migrations/2026_07_25_000003_add_audit_log_query_index.php',
        );

        try {
            $this->runMigrationMethod($tenantMigration, 'down');
            $this->runMigrationMethod($centralMigration, 'down');

            $this->assertIndexMissing('ai_request_logs', 'ai_request_logs_created_at_idx');
            $this->assertIndexMissing('comments', 'comments_related_created_at_idx');
            $this->assertIndexMissing('entity_activities', 'entity_activities_terreno_happened_idx');
            $this->assertIndexMissing('terrenos', 'terrenos_workflow_created_at_idx');
            $this->assertIndexMissing('audit_logs', 'audit_logs_created_at_id_idx');
        } finally {
            $this->runMigrationMethod($centralMigration, 'up');
            $this->runMigrationMethod($tenantMigration, 'up');
        }
    }

    private function createLegalizacao(string $name): void
    {
        $terreno = Terreno::create([
            'nome' => "Terreno {$name}",
            'cidade_code' => '3550308',
        ]);

        Legalizacao::create([
            'terreno_id' => $terreno->id,
            'nome' => $name,
            'status' => 'planejado',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function measureUserListQueries(): int
    {
        return $this->measureQueries(function (): void {
            $users = app(UserRepository::class)->queryWithRelations()->get();
            UserResource::collection($users)->response()->getData(true);
        });
    }

    private function createAiLog(float $cost, string $createdAt): void
    {
        $log = AiRequestLog::create([
            'estimated_cost_usd' => $cost,
            'status' => 'success',
        ]);
        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }

    private function assertIndexExists(string $table, string $index): void
    {
        $indexes = collect(Schema::getIndexes($table))->pluck('name');

        $this->assertContains($index, $indexes->all());
    }

    private function assertIndexMissing(string $table, string $index): void
    {
        $indexes = collect(Schema::getIndexes($table))->pluck('name');

        $this->assertNotContains($index, $indexes->all());
    }

    private function runMigrationMethod(Migration $migration, string $method): void
    {
        (new ReflectionMethod($migration, $method))->invoke($migration);
    }

    private function measureQueries(Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
