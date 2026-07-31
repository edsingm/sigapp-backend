<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupTerrenoImportsCommand extends Command
{
    protected $signature = 'tenant:cleanup-terreno-imports';

    protected $description = 'Remove importações de terrenos expiradas e seus arquivos temporários';

    public function handle(): int
    {
        $deleted = 0;

        Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->cursor()->each(
            function (Tenant $tenant) use (&$deleted): void {
                try {
                    $tenant->run(function () use (&$deleted): void {
                        $imports = DB::table('terreno_imports')
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now())
                            ->orderBy('id')
                            ->cursor();
                        foreach ($imports as $import) {
                            if (is_string($import->storage_disk) && is_string($import->storage_path)) {
                                Storage::disk($import->storage_disk)->delete($import->storage_path);
                            }
                            DB::table('terreno_imports')->where('id', $import->id)->delete();
                            $deleted++;
                        }
                    });
                } catch (Throwable $exception) {
                    Log::warning('Falha ao limpar importações de terrenos do tenant.', [
                        'tenant_id' => $tenant->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            },
        );

        $this->info("Importações de terrenos removidas: {$deleted}");

        return self::SUCCESS;
    }
}
