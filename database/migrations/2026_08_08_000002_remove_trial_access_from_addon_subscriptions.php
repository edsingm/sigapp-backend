<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenant_addon_subscriptions')
            ->where('status', 'trialing')
            ->update([
                'status' => 'incomplete',
                'quantity' => 0,
                'last_synced_at' => now(),
            ]);
    }

    public function down(): void
    {
        // A normalização é intencionalmente irreversível: não há como
        // distinguir dados legados de um trial real após a migração.
    }
};
