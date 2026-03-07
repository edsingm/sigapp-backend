<?php

namespace Database\Seeders;

use App\Models\Tenant\User;
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
        ]);

        User::firstOrCreate(
            ['email' => 'admin@sigapp.com.br'],
            [
                'name' => 'Edson G. Maldonado',
                'password' => bcrypt('Mrt74dla@'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
