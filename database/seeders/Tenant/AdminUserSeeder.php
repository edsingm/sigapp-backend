<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get current tenant
        $tenant = tenancy()->tenant;

        $email = $tenant->admin_email;
        $name = $tenant->admin_name;

        if (!$email) {
            Log::error('Admin email ausente ao criar usuário do tenant', [
                'tenant_id' => $tenant->id,
            ]);
            // We don't throw exception here to avoid breaking the entire seeding process if something is weird, 
            // but in the original job it did throw. Let's keep it safe but log error.
            return;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $adminPassword = $tenant->admin_password;

            if (!$adminPassword) {
                Log::error('Admin password ausente ao criar usuário do tenant', [
                    'tenant_id' => $tenant->id,
                ]);
                return;
            }

            if (Hash::needsRehash($adminPassword)) {
                $adminPassword = Hash::make($adminPassword);
                
                // Update central tenant password if needed (using central context)
                tenancy()->central(function () use ($tenant, $adminPassword) {
                    /** @var \App\Models\Central\Tenant $tenant */
                    $tenant->forceFill(['admin_password' => $adminPassword])->save();
                });
            }

            $user = User::create([
                'name' => $name ?? 'Administrador',
                'email' => $email,
                'password' => $adminPassword,
                'email_verified_at' => now(),
            ]);
        }

        if (!$user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        Log::info('Usuário admin criado/verificado via Seeder', [
            'tenant_id' => $tenant->id,
            'user_email' => $user->email,
        ]);
    }
}
