<?php

declare(strict_types=1);

// Script TEMPORARIO de exploracao: loga no portal e baixa o HTML do mapa
// para descobrir rotas de detalhe/edicao de terreno. Apenas GET (leitura).

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

require __DIR__.'/_portal_lib.php'; // funcoes compartilhadas extraidas

$options = defaultOptions();
$cookieJar = tempnam(sys_get_temp_dir(), 'portal_explore_');

$loginPageUrl = $options['login-url'];
$loginPageHtml = sendHttpRequest(url: $loginPageUrl, cookieJarPath: $cookieJar);
$loginForm = detectLoginForm($loginPageHtml, $loginPageUrl);

$payload = array_merge(
    extractHiddenFields($loginPageHtml),
    [
        ($loginForm['username_field'] ?? 'usuario') => $options['username'],
        ($loginForm['password_field'] ?? 'senha') => $options['password'],
    ]
);

sendHttpRequest(
    url: $loginForm['action_url'] ?? $options['login-url'],
    method: strtoupper($loginForm['method'] ?? 'POST'),
    fields: $payload,
    cookieJarPath: $cookieJar,
    referer: $loginPageUrl,
);

$html = sendHttpRequest(url: $options['target-url'], cookieJarPath: $cookieJar, referer: $options['login-url']);
file_put_contents(__DIR__.'/_mapa.html', $html);
fwrite(STDOUT, 'HTML do mapa salvo ('.strlen($html)." bytes)\n");

// Procurar padroes de URL candidatas
$patterns = [
    'hrefs' => '/href\s*=\s*["\']([^"\']*[Tt]erreno[^"\']*)["\']/',
    'ajax_urls' => '/url\s*:\s*["\']([^"\']+)["\']/',
    'window_open' => '/(?:window\.open|location\.href)\s*[=(]\s*["\']([^"\']+)["\']/',
    'terreno_paths' => '#(?:/login)?/Terrenos?/[A-Za-z]+(?:/\d+|\?[^"\'\s]+)?#',
    'idterreno_ctx' => '/.{60}idterreno.{60}/s',
];

foreach ($patterns as $label => $regex) {
    preg_match_all($regex, $html, $m);
    $vals = array_values(array_unique($m[1] ?? $m[0] ?? []));
    fwrite(STDOUT, "\n=== {$label} (".count($vals).") ===\n");
    foreach (array_slice($vals, 0, 25) as $v) {
        fwrite(STDOUT, '  '.trim(preg_replace('/\s+/', ' ', $v))."\n");
    }
}

if (is_file($cookieJar)) {
    unlink($cookieJar);
}
