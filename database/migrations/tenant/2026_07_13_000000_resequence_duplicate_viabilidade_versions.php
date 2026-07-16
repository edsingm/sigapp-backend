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
        Schema::create('viabilidade_version_repairs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('viabilidade_id')->unique();
            $table->unsignedBigInteger('terreno_id')->index();
            $table->unsignedInteger('previous_version');
            $table->unsignedInteger('repaired_version');
            $table->timestamp('created_at')->useCurrent();
        });

        $duplicateTerrenoIds = DB::table('viabilidades')
            ->select('terreno_id', 'version')
            ->groupBy('terreno_id', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('terreno_id')
            ->unique();

        foreach ($duplicateTerrenoIds as $terrenoId) {
            $viabilidades = DB::table('viabilidades')
                ->where('terreno_id', $terrenoId)
                ->orderByRaw('CASE WHEN created_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'version']);

            foreach ($viabilidades as $index => $viabilidade) {
                $repairedVersion = $index + 1;

                if ((int) $viabilidade->version === $repairedVersion) {
                    continue;
                }

                DB::table('viabilidade_version_repairs')->insert([
                    'viabilidade_id' => $viabilidade->id,
                    'terreno_id' => $terrenoId,
                    'previous_version' => $viabilidade->version,
                    'repaired_version' => $repairedVersion,
                ]);

                DB::table('viabilidades')
                    ->where('id', $viabilidade->id)
                    ->update(['version' => $repairedVersion]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('viabilidade_version_repairs')) {
            return;
        }

        $repairs = DB::table('viabilidade_version_repairs')
            ->orderByDesc('repaired_version')
            ->get(['viabilidade_id', 'previous_version']);

        foreach ($repairs as $repair) {
            DB::table('viabilidades')
                ->where('id', $repair->viabilidade_id)
                ->update(['version' => $repair->previous_version]);
        }

        Schema::drop('viabilidade_version_repairs');
    }
};
