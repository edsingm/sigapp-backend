<?php

namespace Database\Seeders\Tenant;

use App\Services\Admin\HiperdadosTerrenoCommitService;
use Illuminate\Database\Seeder;

/**
 * Importa os terrenos enriquecidos do portal para as tabelas do tenant.
 *
 * Gere o JSON antes com:
 *   php artisan portal:enriquecer-terrenos
 *
 * Para rodar em um tenant específico:
 *   php artisan tenants:seed --class='Database\Seeders\Tenant\TerrenosPortalSeeder' --tenants=TENANT_ID
 *
 * Preferir a UI admin (Hiperdados Imports) em produção operacional.
 */
class TerrenosPortalSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('dados_teste/Hiperdados/terrenos_portal_enriquecido.json');

        if (! is_file($path)) {
            $this->command?->error("Arquivo não encontrado: {$path}. Rode 'php artisan portal:enriquecer-terrenos' primeiro.");

            return;
        }

        $terrenos = json_decode((string) file_get_contents($path), true);

        if (! is_array($terrenos)) {
            $this->command?->error('JSON inválido: '.$path);

            return;
        }

        /** @var HiperdadosTerrenoCommitService $commit */
        $commit = app(HiperdadosTerrenoCommitService::class);
        $result = $commit->commit($terrenos);

        $this->command?->info("Terrenos importados/atualizados: {$result['imported']}.");

        if ($result['cidades_nao_resolvidas'] !== []) {
            $this->command?->warn(
                'Cidades não resolvidas (cidade_code nulo): '.implode(', ', $result['cidades_nao_resolvidas'])
            );
        }
    }
}
