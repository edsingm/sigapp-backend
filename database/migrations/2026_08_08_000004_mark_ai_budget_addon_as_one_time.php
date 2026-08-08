<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('billing_addons')
            ->where('slug', 'ai-budget-5')
            ->update([
                'billing_interval' => 'one_time',
                'description' => 'Adiciona US$ 5 em créditos acumulativos de IA, sem expiração mensal.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('billing_addons')
            ->where('slug', 'ai-budget-5')
            ->update([
                'billing_interval' => 'month',
                'description' => 'Adiciona US$ 5 ao orçamento mensal de IA do tenant.',
                'updated_at' => now(),
            ]);
    }
};
