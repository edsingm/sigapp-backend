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
        $plans = [
            [
                'name' => 'SIG - Broker',
                'slug' => 'broker',
                'description' => 'Ideal para corretores gerenciarem seus terrenos',
                'stripe_price_id' => 'price_1TArCIGcvhFuedVRPXRKrURP',
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
                'stripe_price_id' => 'price_1SxBAQGcvhFuedVRVBp4uzVP',
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
                'stripe_price_id' => 'price_1SxBDDGcvhFuedVR1FBUXkdr',
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
                'stripe_price_id' => 'price_1SxBEGGcvhFuedVRK8IUp3w6',
                'price' => 947.00,
                'trial_days' => 7,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('✅ 4 planos criados/atualizados com sucesso!');
    }
}
