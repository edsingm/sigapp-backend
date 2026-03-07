<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Tenant\RolePermissionSeeder;
use Database\Seeders\Tenant\AdminUserSeeder;
use Database\Seeders\Tenant\AreaStatusSeeder;
use Database\Seeders\Tenant\ProdutoSeeder;
use Database\Seeders\Tenant\CorretorExternoSeeder;
use Database\Seeders\Tenant\RegionalSeeder;
use Database\Seeders\Tenant\TerrenoSeeder;
use Database\Seeders\Tenant\TerrenoProdutoSeeder;
use Database\Seeders\Tenant\LegalizacaoPermissionSeeder;
use App\Models\Tenant\User;
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
        $this->call(AreaStatusSeeder::class);
        $this->call(ProdutoSeeder::class);
        $this->call(CorretorExternoSeeder::class);
        $this->call(RegionalSeeder::class);
        $this->call(TerrenoSeeder::class);
        $this->call(TerrenoProdutoSeeder::class);
        $this->call(LegalizacaoPermissionSeeder::class);

    }
}
