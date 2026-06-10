<?php

declare(strict_types=1);

/**
 * Enriquece a lista de terrenos (terrenos_portal.json) com dados da ficha
 * completa de cada terreno: endereço/área, formulário (hiperdados com datas e
 * tipologias) e corretores vinculados. Grava o resultado em
 * terrenos_portal_enriquecido.json — pronto para o TerrenosPortalSeeder.
 *
 * Uso:
 *   php database/dados_teste/enriquecer_terrenos_portal.php [--limit=N] [--input=...] [--output=...]
 */

use App\Services\Tenant\PortalTerrenoClient;
use App\Services\Tenant\PortalTerrenoCorretoresParser;
use App\Services\Tenant\PortalTerrenoFichaParser;
use App\Services\Tenant\PortalTerrenoFormularioParser;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = parseArguments($argv);
$inputPath = $options['input'] ?? __DIR__.'/terrenos_portal.json';
$outputPath = $options['output'] ?? __DIR__.'/terrenos_portal_enriquecido.json';
$limit = isset($options['limit']) ? (int) $options['limit'] : null;
$baseUrl = 'https://comproterreno.com.br/login';

$username = readEnv('PORTAL_TERRENOS_USERNAME', 'edson@lrgconstrutora.com.br');
$password = readEnv('PORTAL_TERRENOS_PASSWORD');

if ($password === '') {
    fwrite(STDERR, "Defina PORTAL_TERRENOS_PASSWORD no .env.\n");
    exit(1);
}

$terrenos = json_decode((string) file_get_contents($inputPath), true);

if (! is_array($terrenos)) {
    fwrite(STDERR, "Arquivo de entrada inválido: {$inputPath}\n");
    exit(1);
}

if ($limit !== null) {
    $terrenos = array_slice($terrenos, 0, $limit);
}

$client = new PortalTerrenoClient($baseUrl.'/');
$fichaParser = new PortalTerrenoFichaParser;
$formularioParser = new PortalTerrenoFormularioParser;
$corretoresParser = new PortalTerrenoCorretoresParser;

fwrite(STDOUT, "Autenticando no portal...\n");
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
        $fichaHtml = $client->get("{$baseUrl}/Terrenos/Visualizar/{$id}", referer: "{$baseUrl}/Terrenos/Mapa");
        $ficha = $fichaParser->parse($fichaHtml);

        // 2. Formulário (hiperdados: datas, tipologias, VGV)
        $formularioResp = $client->postForm(
            "{$baseUrl}/terrenos_terrenos_formulario",
            ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
            referer: "{$baseUrl}/Terrenos/Visualizar/{$id}",
        );
        $formularioData = json_decode($formularioResp, true);
        $formulario = ($formularioData['sucesso'] ?? '') === 'true'
            ? $formularioParser->parse($formularioData['strHtml'] ?? '')
            : [];

        // 3. Corretores vinculados
        $corretoresResp = $client->postForm(
            "{$baseUrl}/terrenos_corretores_consultar",
            ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
            referer: "{$baseUrl}/Terrenos/Visualizar/{$id}",
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

        fwrite(STDOUT, sprintf(
            "[%d/%d] OK    %s | form=%d campos | corretores=%d\n",
            $posicao, $total,
            $terreno['nome'] ?? $id,
            count($formulario),
            count($corretores),
        ));
    } catch (Throwable $e) {
        $falhas[] = ['id' => $id, 'erro' => $e->getMessage()];
        $enriquecidos[] = array_merge($terreno, ['ficha' => null, 'formulario' => [], 'corretores' => []]);
        fwrite(STDERR, sprintf("[%d/%d] FALHA %s — %s\n", $posicao, $total, $id, $e->getMessage()));
    }

    // Salva parcial a cada 25 para não perder progresso em execuções longas.
    if ($posicao % 25 === 0) {
        gravarJson($outputPath, $enriquecidos);
    }
}

gravarJson($outputPath, $enriquecidos);

fwrite(STDOUT, sprintf("\nConcluído: %d terrenos, %d falhas.\n", count($enriquecidos), count($falhas)));
fwrite(STDOUT, "Arquivo: {$outputPath}\n");

if ($falhas !== []) {
    fwrite(STDOUT, 'IDs com falha: '.implode(', ', array_column($falhas, 'id'))."\n");
}

exit(0);

function parseArguments(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (! str_starts_with($arg, '--')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $options[$key] = $value;
    }

    return $options;
}

function readEnv(string $key, string $default = ''): string
{
    $value = env($key);

    if (is_string($value) && $value !== '') {
        return $value;
    }

    $fallback = $_SERVER[$key] ?? getenv($key);

    return is_string($fallback) && $fallback !== '' ? $fallback : $default;
}

function gravarJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Falha ao serializar JSON.');
    }

    file_put_contents($path, $json);
}
