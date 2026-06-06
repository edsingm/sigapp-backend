<?php

declare(strict_types=1);

// Funcoes de auth/HTTP compartilhadas (copiadas de extrair_terrenos_portal.php) - TEMPORARIO

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

function sendHttpRequest(
    string $url,
    string $method = 'GET',
    array $fields = [],
    string $cookieJarPath = '',
    ?string $referer = null,
): string {
    $curl = curl_init();
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
    curl_close($curl);

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
