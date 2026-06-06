<?php

declare(strict_types=1);

// TEMPORARIO: loga e baixa a ficha de um terreno (Terrenos/Visualizar/{id})

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/_portal_lib.php';

$id = $argv[1] ?? '33975';
$path = $argv[2] ?? 'Visualizar';

$options = defaultOptions();
$cookieJar = tempnam(sys_get_temp_dir(), 'portal_ficha_');

$loginPageHtml = sendHttpRequest(url: $options['login-url'], cookieJarPath: $cookieJar);
$loginForm = detectLoginForm($loginPageHtml, $options['login-url']);
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
    referer: $options['login-url'],
);

$url = "https://comproterreno.com.br/login/Terrenos/{$path}/{$id}";
$html = sendHttpRequest(url: $url, cookieJarPath: $cookieJar, referer: $options['target-url']);
$out = __DIR__."/_ficha_{$path}_{$id}.html";
file_put_contents($out, $html);
fwrite(STDOUT, "URL: {$url}\n".strlen($html)." bytes -> {$out}\n");

if (is_file($cookieJar)) {
    unlink($cookieJar);
}
