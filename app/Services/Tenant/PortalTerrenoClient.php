<?php

namespace App\Services\Tenant;

use DOMDocument;
use RuntimeException;

/**
 * Cliente HTTP autenticado para o portal comproterreno.com.br.
 * Encapsula o login (detecção de formulário + campos ocultos) e o reuso de
 * cookies para baixar páginas internas como a ficha de cada terreno.
 */
class PortalTerrenoClient
{
    private string $cookieJar;

    public function __construct(
        private readonly string $loginUrl = 'https://comproterreno.com.br/login/',
    ) {
        $cookieJar = tempnam(sys_get_temp_dir(), 'portal_terrenos_');

        if ($cookieJar === false) {
            throw new RuntimeException('Não foi possível criar o arquivo temporário de cookies.');
        }

        $this->cookieJar = $cookieJar;
    }

    public function __destruct()
    {
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    public function login(string $username, string $password): void
    {
        $loginPageHtml = $this->request($this->loginUrl);
        $form = $this->detectLoginForm($loginPageHtml);

        $payload = array_merge(
            $this->extractHiddenFields($loginPageHtml),
            [
                ($form['username_field'] ?? 'usuario') => $username,
                ($form['password_field'] ?? 'senha') => $password,
            ]
        );

        $this->request(
            url: $form['action_url'] ?? $this->loginUrl,
            method: $form['method'] ?? 'POST',
            fields: $payload,
            referer: $this->loginUrl,
        );
    }

    public function get(string $url, ?string $referer = null): string
    {
        return $this->request(url: $url, referer: $referer);
    }

    /**
     * @param  array<string, string>  $fields
     */
    public function postForm(string $url, array $fields, ?string $referer = null): string
    {
        return $this->request(url: $url, method: 'POST', fields: $fields, referer: $referer);
    }

    private function request(string $url, string $method = 'GET', array $fields = [], ?string $referer = null): string
    {
        $curl = curl_init();
        $method = strtoupper($method);
        $finalUrl = $url;

        if ($method === 'GET' && $fields !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $finalUrl = $url.$separator.http_build_query($fields);
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $finalUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection: keep-alive',
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            ],
        ]);

        if ($referer !== null) {
            curl_setopt($curl, CURLOPT_REFERER, $referer);
        }

        if ($method !== 'GET') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
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

    private function extractHiddenFields(string $html): array
    {
        $dom = $this->loadDom($html);
        $fields = [];

        if ($dom === null) {
            return $fields;
        }

        foreach ($dom->getElementsByTagName('input') as $input) {
            if (strtolower($input->getAttribute('type')) !== 'hidden') {
                continue;
            }

            $name = trim($input->getAttribute('name'));

            if ($name !== '') {
                $fields[$name] = $input->getAttribute('value');
            }
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'csrf-token' && ! isset($fields['_token'])) {
                $fields['_token'] = $meta->getAttribute('content');
            }
        }

        return $fields;
    }

    private function detectLoginForm(string $html): array
    {
        $dom = $this->loadDom($html);

        if ($dom === null) {
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

            return [
                'action_url' => $this->absoluteUrl(trim($form->getAttribute('action'))),
                'method' => strtoupper(trim($form->getAttribute('method')) ?: 'POST'),
                'username_field' => $usernameField,
                'password_field' => $passwordField,
            ];
        }

        return [];
    }

    private function loadDom(string $html): ?DOMDocument
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        return $loaded ? $dom : null;
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '') {
            return $this->loginUrl;
        }

        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }

        $parts = parse_url($this->loginUrl);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');

        return $origin.$directory.'/'.$url;
    }
}
