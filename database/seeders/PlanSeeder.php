<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuredPriceIds = config('cashier.plan_prices', []);

        $plans = [
            [
                'name' => 'SIG - Broker',
                'slug' => 'broker',
                'description' => 'Ideal para corretores gerenciarem seus terrenos',
                'price' => 97.00,
                'trial_days' => 7,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'SIG - Básico',
                'slug' => 'basico',
                'description' => 'Ideal para pequenas equipes que estão começando.',
                'price' => 247.00,
                'trial_days' => 7,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'SIG - Master',
                'slug' => 'master',
                'description' => 'Para equipes em crescimento que precisam de mais recursos.',
                'price' => 597.00,
                'trial_days' => 7,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'SIG - Pro',
                'slug' => 'pro',
                'description' => 'Para grandes organizações com necessidades específicas.',
                'price' => 947.00,
                'trial_days' => 7,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            $configuredPriceId = $configuredPriceIds[$planData['slug']] ?? null;
            if (is_string($configuredPriceId) && $configuredPriceId !== '') {
                $planData['stripe_price_id'] = $configuredPriceId;
            }

            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('✅ 4 planos criados/atualizados com sucesso!');
    }
}
