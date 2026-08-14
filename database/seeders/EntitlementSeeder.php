<?php

namespace Database\Seeders;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Support\EntitlementCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula a tabela entitlements com todos os recursos definidos em config/plans.php
 * e sincroniza os valores por plano na tabela plan_entitlements.
 *
 * Execute após o PlanSeeder.
 */
class EntitlementSeeder extends Seeder
{
    public function run(): void
    {
        $planIds = DB::transaction(fn (): array => $this->seedCatalogAndMatrix());

        foreach ($planIds as $planId) {
            app(PlanRepositoryInterface::class)->invalidateMatrixCache($planId);
        }
    }

    /** @return list<int> */
    private function seedCatalogAndMatrix(): array
    {
        $entitlementDefs = $this->entitlementDefinitions();
        $planIds = [];

        // 1. Upsert de todos os entitlements
        foreach ($entitlementDefs as $def) {
            Entitlement::query()->updateOrCreate(
                ['key' => $def['key']],
                [
                    'label' => $def['label'],
                    'description' => $def['description'] ?? null,
                    'type' => $def['type']->value,
                    'scope' => $def['type'] === EntitlementType::LIMIT
                        ? 'internal'
                        : EntitlementCatalog::scopeForFeature($def['key'])->value,
                    'default_value' => $def['default_value'],
                ]
            );
        }

        $this->command->info('✅ Entitlements upserted: '.count($entitlementDefs));

        // 2. Sincroniza valores por plano
        foreach ($this->planMatrix() as $slug => $matrix) {
            $plan = Plan::where('slug', $slug)->first();

            if (! $plan) {
                $this->command->warn("Plano [{$slug}] não encontrado no banco, pulando sync de entitlements.");

                continue;
            }

            $pivotData = [];

            foreach ($matrix['features'] as $key => $value) {
                $entitlement = Entitlement::where('key', $key)->first();

                if ($entitlement) {
                    $pivotData[$entitlement->id] = ['value' => json_encode((bool) $value)];
                }
            }

            foreach ($matrix['limits'] as $key => $value) {
                $entitlement = Entitlement::where('key', $key)->first();

                if ($entitlement) {
                    $pivotData[$entitlement->id] = ['value' => json_encode((int) $value)];
                }
            }

            $plan->entitlements()->sync($pivotData);
            $planIds[] = (int) $plan->id;
            $this->command->info("  ↳ [{$slug}] sincronizado com ".count($pivotData).' entitlements.');
        }

        return $planIds;
    }

    /**
     * Matriz de valores iniciais por plano (espelha o config/plans.php original).
     *
     * @return array<string, array{features: array<string, bool>, limits: array<string, int>}>
     */
    private function planMatrix(): array
    {
        return [
            'broker' => [
                'features' => [
                    ...$this->roadmapFeatureMatrix(planSlug: 'broker'),
                    'home' => true,
                    'dashboard.enabled' => true,
                    'dashboard.overview' => false,
                    'dashboard.units_closed' => false,
                    'dashboard.vgv' => false,
                    'dashboard.funnel' => false,
                    'prospection' => true,
                    'viabilities.enabled' => false,
                    'viabilities.summary' => false,
                    'viabilities.dre' => false,
                    'viabilities.comercial' => false,
                    'viabilities.cash_flow' => false,
                    'viabilities.charts' => false,
                    'viabilities.premises' => false,
                    'viabilities.kpis' => false,
                    'committee' => false,
                    'ai' => false,
                    'negotiation' => false,
                    'legalizations' => false,
                    'projects.enabled' => false,
                    'projects.planning' => false,
                    'product_settings' => true,
                    'regionals' => true,
                    'territorial_base' => true,
                    'exports.excel' => true,
                    'exports.pdf' => false,
                ],
                'limits' => [
                    'users' => 1,
                    'terrenos' => 50,
                    'products' => 1,
                    'storage_gb' => 1,
                    'ai_budget' => 0,
                ],
            ],
            'basico' => [
                'features' => [
                    ...$this->roadmapFeatureMatrix(planSlug: 'basico'),
                    'home' => true,
                    'dashboard.enabled' => true,
                    'dashboard.overview' => true,
                    'dashboard.units_closed' => false,
                    'dashboard.vgv' => false,
                    'dashboard.funnel' => false,
                    'prospection' => true,
                    'viabilities.enabled' => true,
                    'viabilities.summary' => true,
                    'viabilities.dre' => true,
                    'viabilities.comercial' => false,
                    'viabilities.cash_flow' => false,
                    'viabilities.charts' => false,
                    'viabilities.premises' => true,
                    'viabilities.kpis' => true,
                    'committee' => false,
                    'ai' => false,
                    'negotiation' => false,
                    'legalizations' => false,
                    'projects.enabled' => false,
                    'projects.planning' => false,
                    'product_settings' => true,
                    'regionals' => true,
                    'territorial_base' => true,
                    'exports.excel' => true,
                    'exports.pdf' => true,
                ],
                'limits' => [
                    'users' => 3,
                    'terrenos' => 100,
                    'products' => 2,
                    'storage_gb' => 5,
                    'ai_budget' => 0,
                ],
            ],
            'master' => [
                'features' => [
                    ...$this->roadmapFeatureMatrix(planSlug: 'master'),
                    'home' => true,
                    'dashboard.enabled' => true,
                    'dashboard.overview' => true,
                    'dashboard.units_closed' => true,
                    'dashboard.vgv' => true,
                    'dashboard.funnel' => true,
                    'prospection' => true,
                    'viabilities.enabled' => true,
                    'viabilities.summary' => true,
                    'viabilities.dre' => true,
                    'viabilities.comercial' => true,
                    'viabilities.cash_flow' => true,
                    'viabilities.charts' => true,
                    'viabilities.premises' => true,
                    'viabilities.kpis' => true,
                    'committee' => true,
                    'ai' => true,
                    'negotiation' => true,
                    'legalizations' => false,
                    'projects.enabled' => false,
                    'projects.planning' => false,
                    'product_settings' => true,
                    'regionals' => true,
                    'territorial_base' => true,
                    'exports.excel' => true,
                    'exports.pdf' => true,
                ],
                'limits' => [
                    'users' => 10,
                    'terrenos' => 200,
                    'products' => 3,
                    'storage_gb' => 10,
                    'ai_budget' => 20,
                ],
            ],
            'pro' => [
                'features' => [
                    ...$this->roadmapFeatureMatrix(planSlug: 'pro'),
                    'home' => true,
                    'dashboard.enabled' => true,
                    'dashboard.overview' => true,
                    'dashboard.units_closed' => true,
                    'dashboard.vgv' => true,
                    'dashboard.funnel' => true,
                    'prospection' => true,
                    'viabilities.enabled' => true,
                    'viabilities.summary' => true,
                    'viabilities.dre' => true,
                    'viabilities.comercial' => true,
                    'viabilities.cash_flow' => true,
                    'viabilities.charts' => true,
                    'viabilities.premises' => true,
                    'viabilities.kpis' => true,
                    'committee' => true,
                    'ai' => true,
                    'negotiation' => true,
                    'legalizations' => true,
                    'projects.enabled' => true,
                    'projects.planning' => true,
                    'product_settings' => true,
                    'regionals' => true,
                    'territorial_base' => true,
                    'exports.excel' => true,
                    'exports.pdf' => true,
                ],
                'limits' => [
                    'users' => -1,
                    'terrenos' => -1,
                    'products' => -1,
                    'storage_gb' => 20,
                    'ai_budget' => 50,
                ],
            ],
        ];
    }

    /**
     * Features do recorte A, pelo fluxo do terreno.
     *
     * Broker: captação. Básico: análise usável. Master: decisão e fechamento
     * (comitê + negociação/contrato) com IA só de chat. Pro: operação completa
     * (deal room, legalização, projetos, documentos, IA avançada/contextual).
     * `onboarding.profile` e `experience.accessibility` ficam em todos os
     * planos. As chaves `projects_room` e `projects.room` são aliases
     * temporários resolvidos em runtime, fora do catálogo comercial.
     *
     * @return array<string, bool>
     */
    private function roadmapFeatureMatrix(string $planSlug): array
    {
        $features = [
            'prospection.terrain_cockpit' => false,
            'prospection.pipeline_board' => false,
            'collaboration.tasks' => false,
            'collaboration.inbox' => false,
            'prospection.comparison' => false,
            'viabilities.scenarios' => false,
            'dashboard.executive' => false,
            'dashboard.goals' => false,
            'dashboard.management' => false,
            'projects.planning' => false,
            'committee.meeting' => false,
            'committee.meeting_mode' => false,
            'negotiation.deal_room' => false,
            'legalization.control_center' => false,
            'search.global' => false,
            'workspace.saved_views' => false,
            'workspace.personalization' => false,
            'reports.builder' => false,
            'territorial.map_comparison' => false,
            'documents.intelligence' => false,
            'ai.contextual' => false,
            'ai.advanced' => false,
            'mobile.capture' => false,
            'onboarding.profile' => false,
            'dashboard.personalization' => false,
            'experience.accessibility' => false,
        ];

        $broker = [
            'prospection.terrain_cockpit',
            'prospection.pipeline_board',
            'collaboration.tasks',
            'collaboration.inbox',
            'workspace.saved_views',
            'mobile.capture',
            'onboarding.profile',
            'experience.accessibility',
        ];

        $basico = [
            ...$broker,
            'prospection.comparison',
            'search.global',
            'workspace.personalization',
        ];

        $master = [
            ...$basico,
            'viabilities.scenarios',
            'dashboard.executive',
            'dashboard.goals',
            'dashboard.management',
            'committee.meeting',
            'committee.meeting_mode',
            'reports.builder',
            'dashboard.personalization',
        ];

        $pro = [
            ...$master,
            'projects.planning',
            'negotiation.deal_room',
            'legalization.control_center',
            'territorial.map_comparison',
            'documents.intelligence',
            'ai.advanced',
            'ai.contextual',
        ];

        $enabledFeatures = match ($planSlug) {
            'broker' => $broker,
            'basico' => $basico,
            'master' => $master,
            'pro' => $pro,
            default => [],
        };

        foreach ($enabledFeatures as $feature) {
            $features[$feature] = true;
        }

        return $features;
    }

    /**
     * Definições canônicas de todos os entitlements do sistema.
     *
     * @return array<int, array{key: string, label: string, type: EntitlementType, default_value: mixed, description?: string}>
     */
    private function entitlementDefinitions(): array
    {
        return [
            // ── Features simples ──────────────────────────────────────────────
            ['key' => 'home',                       'label' => 'Home',                        'type' => EntitlementType::FEATURE, 'default_value' => true],
            ['key' => 'prospection',                'label' => 'Prospecção',                  'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'committee',                  'label' => 'Comitê de Revisão',           'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'negotiation',                'label' => 'Negociações',                 'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'legalizations',              'label' => 'Legalizações',                'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'projects.enabled',           'label' => 'Projetos — CRUD',             'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'product_settings',           'label' => 'Configuração de Produtos',   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'regionals',                  'label' => 'Regionais',                   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'territorial_base',           'label' => 'Base Territorial',            'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'ai',                         'label' => 'Assistente de IA',            'type' => EntitlementType::FEATURE, 'default_value' => false],

            // ── Features do recorte comercial ────────────────────────────────
            ['key' => 'prospection.terrain_cockpit',       'label' => 'Cockpit do terreno',                    'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'prospection.pipeline_board',        'label' => 'Board do pipeline',                     'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'collaboration.tasks',               'label' => 'Tarefas colaborativas',                 'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'collaboration.inbox',               'label' => 'Inbox operacional',                     'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'prospection.comparison',            'label' => 'Comparação de oportunidades',           'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.scenarios',             'label' => 'Cenários de viabilidade',               'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.executive',               'label' => 'Cockpit executivo',                     'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.goals',                   'label' => 'Metas gerenciais',                      'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.management',              'label' => 'Capacidade gerencial',                  'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'projects.planning',                 'label' => 'Projetos — Planejamento',               'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'committee.meeting',                 'label' => 'Reuniões de comitê',                    'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'committee.meeting_mode',            'label' => 'Modo reunião do comitê',                'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'negotiation.deal_room',             'label' => 'Deal room',                             'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'legalization.control_center',       'label' => 'Central de legalização',                'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'search.global',                     'label' => 'Busca global',                          'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'workspace.saved_views',             'label' => 'Visões salvas',                         'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'workspace.personalization',        'label' => 'Personalização do workspace',           'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'reports.builder',                   'label' => 'Construtor de relatórios',              'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'territorial.map_comparison',        'label' => 'Comparação territorial',                'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'documents.intelligence',             'label' => 'Documentos inteligentes',               'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'ai.advanced',                       'label' => 'IA avançada',                           'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'ai.contextual',                     'label' => 'IA contextual',                         'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'mobile.capture',                    'label' => 'Captura mobile',                        'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'onboarding.profile',                'label' => 'Onboarding por perfil',                 'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.personalization',        'label' => 'Dashboard personalizável',              'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'experience.accessibility',          'label' => 'Qualidade e acessibilidade',            'type' => EntitlementType::FEATURE, 'default_value' => false],

            // ── Features aninhadas: dashboard ────────────────────────────────
            ['key' => 'dashboard.enabled',          'label' => 'Dashboard',                   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.overview',         'label' => 'Dashboard — Visão Geral',    'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.units_closed',     'label' => 'Dashboard — Units Fechadas', 'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.vgv',              'label' => 'Dashboard — VGV',            'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'dashboard.funnel',           'label' => 'Dashboard — Funil',          'type' => EntitlementType::FEATURE, 'default_value' => false],

            // ── Features aninhadas: viabilities ──────────────────────────────
            ['key' => 'viabilities.enabled',        'label' => 'Viabilidades',                'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.summary',        'label' => 'Viabilidades — Resumo',      'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.dre',            'label' => 'Viabilidades — DRE',         'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.comercial',      'label' => 'Viabilidades — Comercial',   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.cash_flow',      'label' => 'Viabilidades — Fluxo Caixa', 'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.charts',         'label' => 'Viabilidades — Gráficos',    'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.premises',       'label' => 'Viabilidades — Premissas',   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'viabilities.kpis',           'label' => 'Viabilidades — KPIs',        'type' => EntitlementType::FEATURE, 'default_value' => false],

            // ── Features aninhadas: exports ───────────────────────────────────
            ['key' => 'exports.excel',              'label' => 'Exportação Excel',            'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'exports.pdf',                'label' => 'Exportação PDF',              'type' => EntitlementType::FEATURE, 'default_value' => false],

            // ── Limits ────────────────────────────────────────────────────────
            ['key' => 'users',                      'label' => 'Limite de usuários',          'type' => EntitlementType::LIMIT, 'default_value' => 1,  'description' => 'Use -1 para ilimitado'],
            ['key' => 'terrenos',                   'label' => 'Limite de terrenos',          'type' => EntitlementType::LIMIT, 'default_value' => 50, 'description' => 'Use -1 para ilimitado'],
            ['key' => 'products',                   'label' => 'Limite de produtos',          'type' => EntitlementType::LIMIT, 'default_value' => 1,  'description' => 'Use -1 para ilimitado'],
            ['key' => 'storage_gb',                 'label' => 'Armazenamento (GB)',          'type' => EntitlementType::LIMIT, 'default_value' => 0,  'description' => 'Use -1 para ilimitado'],
            ['key' => 'ai_budget',                  'label' => 'Orçamento mensal de IA (USD)', 'type' => EntitlementType::LIMIT, 'default_value' => 10, 'description' => 'Budget mensal de IA por tenant em USD'],
        ];
    }
}
