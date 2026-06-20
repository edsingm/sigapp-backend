<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenant\PortalTerrenoClient;
use App\Services\Tenant\PortalTerrenoCorretoresParser;
use App\Services\Tenant\PortalTerrenoFichaParser;
use App\Services\Tenant\PortalTerrenoFormularioParser;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Enriquece a lista de terrenos (terrenos_portal.json) com dados da ficha
 * completa de cada terreno: endereço/área, formulário (hiperdados com datas e
 * tipologias) e corretores vinculados. Grava o resultado em
 * terrenos_portal_enriquecido.json — pronto para o TerrenosPortalSeeder.
 *
 * Substitui o antigo script avulso database/dados_teste/enriquecer_terrenos_portal.php.
 *
 * Uso:
 *   php artisan portal:enriquecer-terrenos [--limit=N] [--input=...] [--output=...]
 */
class EnriquecerTerrenosPortalCommand extends Command
{
    protected $signature = 'portal:enriquecer-terrenos
        {--limit= : Limita a quantidade de terrenos processados}
        {--input= : Caminho do JSON de entrada (default: database/dados_teste/Hiperdados/terrenos_portal.json)}
        {--output= : Caminho do JSON de saída (default: database/dados_teste/Hiperdados/terrenos_portal_enriquecido.json)}';

    protected $description = 'Enriquece terrenos_portal.json com fichas, formulários e corretores do portal';

    private const BASE_URL = 'https://comproterreno.com.br/login';

    public function handle(): int
    {
        $baseDir = database_path('dados_teste/Hiperdados');
        $inputPath = $this->stringOption('input') ?? "{$baseDir}/terrenos_portal.json";
        $outputPath = $this->stringOption('output') ?? "{$baseDir}/terrenos_portal_enriquecido.json";
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $username = (string) (config('services.portal_terrenos.username') ?? env('PORTAL_TERRENOS_USERNAME', ''));
        $password = (string) (config('services.portal_terrenos.password') ?? env('PORTAL_TERRENOS_PASSWORD', ''));

        if ($password === '') {
            $this->error('Defina PORTAL_TERRENOS_PASSWORD no .env.');

            return self::FAILURE;
        }

        $terrenos = json_decode((string) file_get_contents($inputPath), true);

        if (! is_array($terrenos)) {
            $this->error("Arquivo de entrada inválido: {$inputPath}");

            return self::FAILURE;
        }

        if ($limit !== null) {
            $terrenos = array_slice($terrenos, 0, $limit);
        }

        $client = new PortalTerrenoClient(self::BASE_URL.'/');
        $fichaParser = new PortalTerrenoFichaParser;
        $formularioParser = new PortalTerrenoFormularioParser;
        $corretoresParser = new PortalTerrenoCorretoresParser;

        $this->info('Autenticando no portal...');
        $client->login($username, $password);

        $total = count($terrenos);
        $enriquecidos = [];
        $falhas = [];

        foreach ($terrenos as $index => $terreno) {
            $id = $terreno['id'] ?? null;
            $posicao = $index + 1;

            if ($id === null) {
                continue;
            }

            try {
                // 1. Ficha principal (endereço, área, zoneamento, status, zona)
                $fichaHtml = $client->get(self::BASE_URL."/Terrenos/Visualizar/{$id}", referer: self::BASE_URL.'/Terrenos/Mapa');
                $ficha = $fichaParser->parse($fichaHtml);

                // 2. Formulário (hiperdados: datas, tipologias, VGV)
                $formularioResp = $client->postForm(
                    self::BASE_URL.'/terrenos_terrenos_formulario',
                    ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
                    referer: self::BASE_URL."/Terrenos/Visualizar/{$id}",
                );
                $formularioData = json_decode($formularioResp, true);
                $formulario = ($formularioData['sucesso'] ?? '') === 'true'
                    ? $formularioParser->parse($formularioData['strHtml'] ?? '')
                    : [];

                // 3. Corretores vinculados
                $corretoresResp = $client->postForm(
                    self::BASE_URL.'/terrenos_corretores_consultar',
                    ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
                    referer: self::BASE_URL."/Terrenos/Visualizar/{$id}",
                );
                $corretoresData = json_decode($corretoresResp, true);
                $corretores = ($corretoresData['sucesso'] ?? '') === 'true'
                    ? $corretoresParser->parse($corretoresData['strHtml'] ?? '')
                    : [];

                $enriquecidos[] = array_merge($terreno, [
                    'ficha' => $ficha,
                    'formulario' => $formulario,
                    'corretores' => $corretores,
                ]);

                $this->line(sprintf(
                    '[%d/%d] OK    %s | form=%d campos | corretores=%d',
                    $posicao, $total,
                    $terreno['nome'] ?? $id,
                    count($formulario),
                    count($corretores),
                ));
            } catch (Throwable $e) {
                $falhas[] = ['id' => $id, 'erro' => $e->getMessage()];
                $enriquecidos[] = array_merge($terreno, ['ficha' => null, 'formulario' => [], 'corretores' => []]);
                $this->error(sprintf('[%d/%d] FALHA %s — %s', $posicao, $total, $id, $e->getMessage()));
            }

            // Salva parcial a cada 25 para não perder progresso em execuções longas.
            if ($posicao % 25 === 0) {
                $this->gravarJson($outputPath, $enriquecidos);
            }
        }

        $this->gravarJson($outputPath, $enriquecidos);

        $this->info(sprintf("\nConcluído: %d terrenos, %d falhas.", count($enriquecidos), count($falhas)));
        $this->info("Arquivo: {$outputPath}");

        if ($falhas !== []) {
            $this->warn('IDs com falha: '.implode(', ', array_column($falhas, 'id')));
        }

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private function gravarJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Falha ao serializar JSON.');
        }

        file_put_contents($path, $json);
    }
}
