<?php

namespace Database\Seeders;

use App\Enums\Common\EntitlementType;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

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
        $entitlementDefs = $this->entitlementDefinitions();

        // 1. Upsert de todos os entitlements
        foreach ($entitlementDefs as $def) {
            Entitlement::updateOrCreate(
                ['key' => $def['key']],
                [
                    'label' => $def['label'],
                    'description' => $def['description'] ?? null,
                    'type' => $def['type']->value,
                    'default_value' => $def['default_value'],
                ]
            );
        }

        $this->command?->info('✅ Entitlements upserted: '.count($entitlementDefs));

        // 2. Sincroniza valores por plano
        foreach ($this->planMatrix() as $slug => $matrix) {
            $plan = Plan::where('slug', $slug)->first();

            if (! $plan) {
                $this->command?->warn("Plano [{$slug}] não encontrado no banco, pulando sync de entitlements.");

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
            $this->command?->info("  ↳ [{$slug}] sincronizado com ".count($pivotData).' entitlements.');
        }
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
                    'home' => true,
                    'dashboard.enabled' => false,
                    'dashboard.overview' => false,
                    'dashboard.units_closed' => false,
                    'dashboard.vgv' => false,
                    'dashboard.funnel' => false,
                    'prospection' => true,
                    'viabilities.enabled' => false,
                    'viabilities.summary' => false,
                    'viabilities.dre' => false,
                    'viabilities.cash_flow' => false,
                    'viabilities.charts' => false,
                    'viabilities.premises' => false,
                    'viabilities.kpis' => false,
                    'committee' => false,
                    'ai' => false,
                    'negotiation' => false,
                    'legalizations' => false,
                    'projects_room' => false,
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
                    'storage_gb' => 0,
                    'ai_budget' => 5,
                ],
            ],
            'basico' => [
                'features' => [
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
                    'viabilities.cash_flow' => false,
                    'viabilities.charts' => false,
                    'viabilities.premises' => false,
                    'viabilities.kpis' => false,
                    'committee' => false,
                    'ai' => false,
                    'negotiation' => false,
                    'legalizations' => false,
                    'projects_room' => false,
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
                    'storage_gb' => 1,
                    'ai_budget' => 10,
                ],
            ],
            'master' => [
                'features' => [
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
                    'viabilities.cash_flow' => true,
                    'viabilities.charts' => false,
                    'viabilities.premises' => false,
                    'viabilities.kpis' => false,
                    'committee' => false,
                    'ai' => true,
                    'negotiation' => false,
                    'legalizations' => false,
                    'projects_room' => false,
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
                    'storage_gb' => 3,
                    'ai_budget' => 25,
                ],
            ],
            'pro' => [
                'features' => [
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
                    'viabilities.cash_flow' => true,
                    'viabilities.charts' => true,
                    'viabilities.premises' => true,
                    'viabilities.kpis' => true,
                    'committee' => true,
                    'ai' => true,
                    'negotiation' => true,
                    'legalizations' => true,
                    'projects_room' => true,
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
                    'storage_gb' => 5,
                    'ai_budget' => 100,
                ],
            ],
        ];
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
            ['key' => 'projects_room',              'label' => 'Sala de Projetos',            'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'product_settings',           'label' => 'Configuração de Produtos',   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'regionals',                  'label' => 'Regionais',                   'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'territorial_base',           'label' => 'Base Territorial',            'type' => EntitlementType::FEATURE, 'default_value' => false],
            ['key' => 'ai',                         'label' => 'Assistente de IA',            'type' => EntitlementType::FEATURE, 'default_value' => false],

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
