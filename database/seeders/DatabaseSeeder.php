<?php

namespace Database\Seeders;

use App\Models\Tenant\User;
use Database\Seeders\Tenant\AdminUserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            PlanRolePermissionTemplateSeeder::class,
            AdminsSetupSeeder::class
        ]);


    }
}
