<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(AdminUserSeeder::class);

        // TODO: remover em prod
        if (! app()->environment('prod')) {
            $this->call(ProdutoSeeder::class);
            $this->call(CorretorExternoSeeder::class);
            $this->call(RegionalSeeder::class);
        }

    }
}
