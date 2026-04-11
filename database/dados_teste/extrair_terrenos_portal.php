<?php

declare(strict_types=1);

use App\Services\Tenant\PortalTerrenoScraperService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $options = array_merge(defaultOptions(), parseArguments($argv));
    validateRequiredOptions($options, ['login-url', 'target-url', 'username', 'password']);

    $cookieJar = tempnam(sys_get_temp_dir(), 'portal_terrenos_');

    if ($cookieJar === false) {
        throw new RuntimeException('Não foi possível criar o arquivo temporário de cookies.');
    }

    try {
        $loginPageUrl = $options['login-page-url'] ?? $options['login-url'];
        $loginPageHtml = sendHttpRequest(
            url: $loginPageUrl,
            cookieJarPath: $cookieJar,
        );
        $loginForm = detectLoginForm($loginPageHtml, $loginPageUrl);

        $payload = array_merge(
            extractHiddenFields($loginPageHtml),
            extractCustomFields($options),
            [
                $options['username-field'] ?? $loginForm['username_field'] ?? 'usuario' => $options['username'],
                $options['password-field'] ?? $loginForm['password_field'] ?? 'senha' => $options['password'],
            ]
        );

        sendHttpRequest(
            url: $loginForm['action_url'] ?? $options['login-url'],
            method: strtoupper($options['login-method'] ?? $loginForm['method'] ?? 'POST'),
            fields: $payload,
            cookieJarPath: $cookieJar,
            referer: $loginPageUrl,
        );

        $html = sendHttpRequest(
            url: $options['target-url'],
            cookieJarPath: $cookieJar,
            referer: $options['login-url'],
        );

        $terrenos = $app->make(PortalTerrenoScraperService::class)->extractFromHtml($html);
        $outputPath = $options['output'] ?? (__DIR__.'/terrenos_portal.json');
        $outputDirectory = dirname($outputPath);

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException("Não foi possível criar o diretório de saída: {$outputDirectory}");
        }

        $json = json_encode($terrenos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Não foi possível serializar os terrenos em JSON.');
        }

        if (file_put_contents($outputPath, $json) === false) {
            throw new RuntimeException("Não foi possível gravar o arquivo de saída em {$outputPath}");
        }

        fwrite(STDOUT, 'Terrenos extraídos com sucesso: '.count($terrenos).PHP_EOL);
        fwrite(STDOUT, "Arquivo gerado em: {$outputPath}".PHP_EOL);
        exit(0);
    } finally {
        if (is_file($cookieJar)) {
            unlink($cookieJar);
        }
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage().PHP_EOL);
    fwrite(STDERR, PHP_EOL.usage().PHP_EOL);
    exit(1);
}

function parseArguments(array $arguments): array
{
    $options = [];

    foreach (array_slice($arguments, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {

            continue;
        }

        [$key, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, '1');

        if ($key === 'field') {
            $options['field'][] = $value;

            continue;
        }

        $options[$key] = $value;
    }

    return $options;
}

function defaultOptions(): array
{
    return [
        'login-url' => 'https://comproterreno.com.br/login/',
        'target-url' => 'https://comproterreno.com.br/login/Terrenos/Mapa',
        'username' => readConfiguredValue('PORTAL_TERRENOS_USERNAME', 'edson@lrgconstrutora.com.br'),
        'password' => readConfiguredValue('PORTAL_TERRENOS_PASSWORD'),
    ];
}

function readConfiguredValue(string $key, string $default = ''): string
{
    $envValue = env($key);

    if (is_string($envValue) && $envValue !== '') {
        return $envValue;
    }

    $serverValue = $_SERVER[$key] ?? getenv($key);

    if (is_string($serverValue) && $serverValue !== '') {
        return $serverValue;
    }

    return $default;
}

function validateRequiredOptions(array $options, array $requiredKeys): void
{
    $missingKeys = [];

    foreach ($requiredKeys as $requiredKey) {
        if (! array_key_exists($requiredKey, $options) || $options[$requiredKey] === '') {
            $missingKeys[] = $requiredKey;
        }
    }

    if ($missingKeys !== []) {
        throw new RuntimeException('Parâmetros obrigatórios ausentes: '.implode(', ', $missingKeys));
    }
}

function extractHiddenFields(string $html): array
{
    $dom = new DOMDocument;
    $fields = [];

    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html);
    libxml_clear_errors();

    if ($loaded === false) {
        return $fields;
    }

    foreach ($dom->getElementsByTagName('input') as $input) {
        if (strtolower($input->getAttribute('type')) !== 'hidden') {

            continue;
        }

        $name = trim($input->getAttribute('name'));

        if ($name === '') {

            continue;
        }

        $fields[$name] = $input->getAttribute('value');
    }

    foreach ($dom->getElementsByTagName('meta') as $meta) {
        if (strtolower($meta->getAttribute('name')) !== 'csrf-token') {

            continue;
        }

        if (! array_key_exists('_token', $fields)) {
            $fields['_token'] = $meta->getAttribute('content');
        }
    }

    return $fields;
}

function detectLoginForm(string $html, string $baseUrl): array
{
    $dom = new DOMDocument;

    libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($html);
    libxml_clear_errors();

    if ($loaded === false) {
        return [];
    }

    foreach ($dom->getElementsByTagName('form') as $form) {
        $passwordField = null;
        $usernameField = null;

        foreach ($form->getElementsByTagName('input') as $input) {
            $type = strtolower($input->getAttribute('type'));
            $name = trim($input->getAttribute('name'));

            if ($name === '') {
                continue;
            }

            if ($type === 'password' && $passwordField === null) {
                $passwordField = $name;
            }

            if (in_array($type, ['email', 'text'], true) && $usernameField === null) {
                $usernameField = $name;
            }
        }

        if ($passwordField === null || $usernameField === null) {
            continue;
        }

        $action = trim($form->getAttribute('action'));
        $method = strtoupper(trim($form->getAttribute('method')) ?: 'POST');

        return [
            'action_url' => buildAbsoluteUrl($action, $baseUrl),
            'method' => $method,
            'username_field' => $usernameField,
            'password_field' => $passwordField,
        ];
    }

    return [];
}

function extractCustomFields(array $options): array
{
    $fields = [];

    foreach ($options['field'] ?? [] as $field) {
        [$name, $value] = array_pad(explode('=', $field, 2), 2, '');

        if ($name === '') {

            continue;
        }

        $fields[$name] = $value;
    }

    return $fields;
}

function sendHttpRequest(
    string $url,
    string $method = 'GET',
    array $fields = [],
    string $cookieJarPath = '',
    ?string $referer = null,
): string {
    $curl = curl_init();

    if ($curl === false) {
        throw new RuntimeException('Não foi possível iniciar o cURL.');
    }

    $headers = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Connection: keep-alive',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
    ];

    $normalizedMethod = strtoupper($method);
    $normalizedUrl = $url;

    if ($normalizedMethod === 'GET' && $fields !== []) {
        $separator = str_contains($url, '?') ? '&' : '?';
        $normalizedUrl = $url.$separator.http_build_query($fields);
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $normalizedUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookieJarPath,
        CURLOPT_COOKIEFILE => $cookieJarPath,
    ]);

    if ($referer !== null) {
        curl_setopt($curl, CURLOPT_REFERER, $referer);
    }

    if ($normalizedMethod !== 'GET') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $normalizedMethod);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
    }

    $response = curl_exec($curl);

    if ($response === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException("Falha na requisição HTTP: {$message}");
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($statusCode >= 400) {
        throw new RuntimeException("O servidor retornou HTTP {$statusCode} ao acessar {$url}");
    }

    return $response;
}

function buildAbsoluteUrl(string $url, string $baseUrl): string
{
    if ($url === '') {
        return $baseUrl;
    }

    if (preg_match('/^https?:\/\//i', $url) === 1) {
        return $url;
    }

    $baseParts = parse_url($baseUrl);

    if ($baseParts === false || ! isset($baseParts['scheme'], $baseParts['host'])) {
        return $url;
    }

    $origin = $baseParts['scheme'].'://'.$baseParts['host'];

    if (str_starts_with($url, '/')) {
        return $origin.$url;
    }

    $path = $baseParts['path'] ?? '/';
    $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

    return $origin.($directory === '' ? '' : $directory).'/'.$url;
}

function usage(): string
{
    return implode(PHP_EOL, [
        'Uso:',
        'php database/dados_teste/extrair_terrenos_portal.php \\',
        '  [--login-url=https://exemplo.com/login] \\',
        '  [--target-url=https://exemplo.com/mapa/terrenos] \\',
        '  [--username=seu_usuario] \\',
        '  [--password=sua_senha] \\',
        '  [--login-page-url=https://exemplo.com/login] \\',
        '  [--username-field=usuario] \\',
        '  [--password-field=senha] \\',
        '  [--field=empresa=123] \\',
        '  [--output=/caminho/terrenos.json]',
    ]);
}
