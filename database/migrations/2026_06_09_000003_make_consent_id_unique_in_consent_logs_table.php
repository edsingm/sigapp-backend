<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateConsentIds = DB::table('consent_logs')
            ->select('consent_id')
            ->groupBy('consent_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('consent_id');

        foreach ($duplicateConsentIds as $consentId) {
            $recordToKeepId = DB::table('consent_logs')
                ->where('consent_id', $consentId)
                ->orderByDesc('consented_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('consent_logs')
                ->where('consent_id', $consentId)
                ->where('id', '!=', $recordToKeepId)
                ->delete();
        }

        Schema::table('consent_logs', function (Blueprint $table) {
            $table->dropIndex('consent_logs_consent_id_index');
            $table->unique('consent_id');
        });
    }

    public function down(): void
    {
        Schema::table('consent_logs', function (Blueprint $table) {
            $table->dropUnique('consent_logs_consent_id_unique');
            $table->index('consent_id');
        });
    }
};
