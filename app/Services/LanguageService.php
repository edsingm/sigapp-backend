<?php

namespace App\Services;

use Illuminate\Support\Arr;
use App\Models\Tenant\User as TenantUser;

class LanguageService
{
    public const SUPPORTED_LOCALES = ['pt-br', 'en-us'];

    private array $translations = [];

    public function __construct(private readonly string $locale)
    {
        $this->translations = $this->load($this->normalize($locale));
    }

    /**
     * Atualiza o locale da aplicação para a requisição atual e persiste na sessão.
     *
     * @throws \InvalidArgumentException se o locale não for suportado
     */
    public static function setLocale(string $locale): void
    {
        $normalized = strtolower(str_replace('_', '-', $locale));

        if (!in_array($normalized, self::SUPPORTED_LOCALES, strict: true)) {
            throw new \InvalidArgumentException(
                "Locale '{$locale}' não suportado. Locales disponíveis: " . implode(', ', self::SUPPORTED_LOCALES)
            );
        }

        app()->setLocale($normalized);
        session(['locale' => $normalized]);

        // Persiste na preferência do usuário autenticado (tenant)
        $user = request()->user();
        if ($user instanceof TenantUser) {
            $user->fill(['locale' => $normalized])->save();
        }
    }

    /**
     * Resolve o locale do usuário autenticado com fallback para pt-br.
     * Deve ser chamado no boot da aplicação (ex: middleware).
     */
    public static function resolveFromUser(): void
    {
        $user = request()->user();
        $locale = $user?->locale ?? self::SUPPORTED_LOCALES[0];

        app()->setLocale(
            in_array($locale, self::SUPPORTED_LOCALES, strict: true) ? $locale : self::SUPPORTED_LOCALES[0]
        );
    }

    /**
     * Retorna a tradução para a chave informada.
     * Suporta notação de ponto para chaves aninhadas (ex: "auth.login").
     * Suporta substituição de placeholders (ex: "validation.required", [':field' => 'nome']).
     *
     * @param string $key
     * @param array<string, string> $replace
     * @return string
     */
    public function t(string $key, array $replace = []): string
    {
        $value = Arr::get($this->translations, $key, $key);

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . ltrim($placeholder, ':'), $replacement, $value);
        }

        return $value;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    private function load(string $locale): array
    {
        $basePath = method_exists(app(), 'resourcePath')
            ? app()->resourcePath("lang/{$locale}.json")
            : dirname(__DIR__, 2) . "/resources/lang/{$locale}.json";

        if (!file_exists($basePath)) {
            return [];
        }

        $contents = file_get_contents($basePath);
        $decoded  = json_decode($contents, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Normaliza "pt_BR" → "pt-br", "en_US" → "en-us", etc. */
    private function normalize(string $locale): string
    {
        return strtolower(str_replace('_', '-', $locale));
    }
}
