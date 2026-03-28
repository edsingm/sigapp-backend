<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('CENTRAL_ADMIN_EMAIL', ''));
        $password = (string) env('CENTRAL_ADMIN_PASSWORD', '');
        $name = trim((string) env('CENTRAL_ADMIN_NAME', 'Admin Central'));

        if ($email === '' || $password === '') {
            $this->command?->warn('CentralAdminSeeder ignorado: defina CENTRAL_ADMIN_EMAIL e CENTRAL_ADMIN_PASSWORD.');
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Administrador central criado/atualizado com sucesso.');
    }
}