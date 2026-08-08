<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Common\BillingAddonType;
use App\Models\Central\BillingAddon;
use Illuminate\Database\Seeder;

class BillingAddonSeeder extends Seeder
{
    public function run(): void
    {
        $configuredPrices = (array) config('cashier.addon_prices', []);

        $addons = [
            [
                'slug' => 'storage-10gb',
                'name' => 'Armazenamento adicional — 10 GB',
                'description' => 'Adiciona 10 GB ao limite mensal de armazenamento do tenant.',
                'type' => BillingAddonType::LIMIT_PACK,
                'definition' => [
                    'grants' => [
                        ['key' => 'storage_gb', 'type' => 'limit', 'unit_value' => 10],
                    ],
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'ai-budget-5',
                'name' => 'Orçamento adicional de IA — US$ 5',
                'description' => 'Adiciona US$ 5 em créditos acumulativos de IA, sem expiração mensal.',
                'type' => BillingAddonType::LIMIT_PACK,
                'definition' => [
                    'grants' => [
                        ['key' => 'ai_budget', 'type' => 'limit', 'unit_value' => 5.0],
                    ],
                ],
                'sort_order' => 2,
                'billing_interval' => 'one_time',
            ],
            [
                'slug' => 'reports-builder',
                'name' => 'Construtor de relatórios',
                'description' => 'Desbloqueia o construtor de relatórios para o tenant.',
                'type' => BillingAddonType::FEATURE_UNLOCK,
                'definition' => [
                    'grants' => [
                        ['key' => 'reports.builder', 'type' => 'feature', 'unit_value' => true],
                    ],
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => 'growth-bundle',
                'name' => 'Bundle Growth',
                'description' => 'Combina armazenamento, orçamento de IA e construtor de relatórios.',
                'type' => BillingAddonType::BUNDLE,
                'definition' => [
                    'grants' => [
                        ['key' => 'storage_gb', 'type' => 'limit', 'unit_value' => 10],
                        ['key' => 'ai_budget', 'type' => 'limit', 'unit_value' => 5.0],
                        ['key' => 'reports.builder', 'type' => 'feature', 'unit_value' => true],
                    ],
                ],
                'sort_order' => 4,
            ],
        ];

        foreach ($addons as $addon) {
            $priceId = $configuredPrices[$addon['slug']] ?? null;
            $values = [
                ...$addon,
                'currency' => (string) config('cashier.currency', 'brl'),
                'billing_interval' => (string) ($addon['billing_interval'] ?? 'month'),
                'is_active' => true,
            ];

            if (is_string($priceId) && $priceId !== '') {
                $values['stripe_price_id'] = $priceId;
            }

            BillingAddon::query()->updateOrCreate(
                ['slug' => $addon['slug']],
                $values,
            );
        }

        $this->command->info('✅ Catálogo de add-ons Stripe sincronizado.');
    }
}
