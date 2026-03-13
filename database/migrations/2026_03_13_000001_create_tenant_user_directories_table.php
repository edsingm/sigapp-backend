<?php

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_directories', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('tenant_user_id');
            $table->string('email_normalized');
            $table->string('user_name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'tenant_user_id']);
            $table->index(['email_normalized', 'active']);
            $table->index('tenant_id');
        });

        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            try {
                $rows = $tenant->run(function () use ($tenant) {
                    return User::query()
                        ->get(['id', 'name', 'email'])
                        ->filter(fn (User $user) => filled($user->email))
                        ->map(fn (User $user) => [
                            'tenant_id' => (string) $tenant->getKey(),
                            'tenant_user_id' => (string) $user->getKey(),
                            'email_normalized' => mb_strtolower(trim((string) $user->email)),
                            'user_name' => (string) $user->name,
                            'active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])
                        ->values()
                        ->all();
                });
            } catch (\Throwable $exception) {
                Log::warning('Tenant user directory migration skipped tenant', [
                    'tenant_id' => (string) $tenant->getKey(),
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($rows === []) {
                continue;
            }

            DB::table('tenant_user_directories')->upsert(
                $rows,
                ['tenant_id', 'tenant_user_id'],
                ['email_normalized', 'user_name', 'active', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_directories');
    }
};
