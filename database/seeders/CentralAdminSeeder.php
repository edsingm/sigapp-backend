<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('app.central_admin.email'));
        $password = (string) config('app.central_admin.password');
        $name = trim((string) config('app.central_admin.name'));

        if ($email === '' || $password === '') {
            $this->command?->warn('CentralAdminSeeder ignorado: defina CENTRAL_ADMIN_EMAIL e CENTRAL_ADMIN_PASSWORD.');

            return;
        }

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => $name,
            'password' => $password,
        ]);
        $admin->forceFill([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
        $admin->save();

        $this->command?->info('Administrador central criado/atualizado com sucesso.');
    }
}
