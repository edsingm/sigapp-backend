<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminsSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
