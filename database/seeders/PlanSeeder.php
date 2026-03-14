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
                'max_users' => 1,
                'max_storage_gb' => 0,
                'max_terrenos' => 50,
                'entitlements' => [
                    'users' => ['max' => 1],
                    'terrenos' => ['max' => 50],
                    'storage' => ['max_gb' => 0],
                    'viabilidade' => ['enabled' => false, 'tier' => 'none'],
                    'reports' => ['tier' => 'basic', 'export_pdf' => true],
                    'dashboard' => ['tier' => 'simple'],
                    'dre' => ['tier' => 'none'],
                    'cash_flow' => ['enabled' => false],
                    'analytics' => [
                        'kpis' => ['enabled' => false],
                        'indicators' => ['enabled' => false],
                    ],
                    'api_access' => ['enabled' => false],
                    'sso' => ['enabled' => false],
                    'integrations' => ['full' => false],
                    'support' => ['priority' => false, 'channel' => 'email'],
                    'acl' => [
                        'custom_roles' => ['enabled' => false],
                        'permission_management' => ['enabled' => false],
                    ],
                ],
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
                'max_users' => 3,
                'max_storage_gb' => 1,
                'max_terrenos' => 100,
                'entitlements' => [
                    'users' => ['max' => 3],
                    'terrenos' => ['max' => 100],
                    'storage' => ['max_gb' => 1],
                    'viabilidade' => ['enabled' => true, 'tier' => 'simple'],
                    'reports' => ['tier' => 'basic', 'export_pdf' => false],
                    'dashboard' => ['tier' => 'simple'],
                    'dre' => ['tier' => 'simple'],
                    'cash_flow' => ['enabled' => false],
                    'analytics' => [
                        'kpis' => ['enabled' => false],
                        'indicators' => ['enabled' => false],
                    ],
                    'api_access' => ['enabled' => false],
                    'sso' => ['enabled' => false],
                    'integrations' => ['full' => false],
                    'support' => ['priority' => false, 'channel' => 'email'],
                    'acl' => [
                        'custom_roles' => ['enabled' => false],
                        'permission_management' => ['enabled' => false],
                    ],
                ],
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
                'max_users' => 10,
                'max_storage_gb' => 3,
                'max_terrenos' => 200,
                'entitlements' => [
                    'users' => ['max' => 10],
                    'terrenos' => ['max' => 200],
                    'storage' => ['max_gb' => 3],
                    'viabilidade' => ['enabled' => true, 'tier' => 'simple'],
                    'reports' => ['tier' => 'advanced', 'export_pdf' => true],
                    'dashboard' => ['tier' => 'simple'],
                    'dre' => ['tier' => 'full'],
                    'cash_flow' => ['enabled' => false],
                    'analytics' => [
                        'kpis' => ['enabled' => false],
                        'indicators' => ['enabled' => false],
                    ],
                    'api_access' => ['enabled' => false],
                    'sso' => ['enabled' => false],
                    'integrations' => ['full' => false],
                    'support' => ['priority' => false, 'channel' => 'email'],
                    'acl' => [
                        'custom_roles' => ['enabled' => true],
                        'permission_management' => ['enabled' => false],
                    ],
                ],
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
                'max_users' => -1, // Ilimitado
                'max_storage_gb' => 10,
                'max_terrenos' => -1, // Ilimitado
                'entitlements' => [
                    'users' => ['max' => -1],
                    'terrenos' => ['max' => -1],
                    'storage' => ['max_gb' => 10],
                    'viabilidade' => ['enabled' => true, 'tier' => 'full'],
                    'reports' => ['tier' => 'advanced', 'export_pdf' => true],
                    'dashboard' => ['tier' => 'full'],
                    'dre' => ['tier' => 'full'],
                    'cash_flow' => ['enabled' => true],
                    'analytics' => [
                        'kpis' => ['enabled' => true],
                        'indicators' => ['enabled' => true],
                    ],
                    'api_access' => ['enabled' => true],
                    'sso' => ['enabled' => false],
                    'integrations' => ['full' => true],
                    'support' => ['priority' => true, 'channel' => 'priority'],
                    'acl' => [
                        'custom_roles' => ['enabled' => true],
                        'permission_management' => ['enabled' => true],
                    ],
                ],
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
