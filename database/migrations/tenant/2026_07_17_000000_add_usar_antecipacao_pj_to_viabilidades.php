<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->boolean('usar_antecipacao_pj')
                ->default(false)
                ->after('percentual_antecipacao_pj');
        });

        DB::table('viabilidades')
            ->whereNull('percentual_antecipacao_pj')
            ->update(['usar_antecipacao_pj' => true]);

        DB::table('viabilidades')
            ->where('percentual_antecipacao_pj', '>', 0)
            ->update(['usar_antecipacao_pj' => true]);
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->dropColumn('usar_antecipacao_pj');
        });
    }
};
