<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Resolve a extensão segura para arquivos enviados.
 *
 * Preferimos a extensão do nome original (já validada pelo FormRequest via mimes/
 * extensions) porque guessExtension() depende do MIME detectado e falha para
 * formatos baseados em ZIP/XML (kmz, xlsx, docx, kml → zip/xml).
 */
final class SafeUploadExtension
{
    /**
     * Extensões aceitas em documentos de terreno e versões documentais.
     *
     * @var list<string>
     */
    public const DOCUMENT_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'kml',
        'kmz',
        'dwg',
    ];

    /**
     * Extensões que o detector de conteúdo costuma reportar no lugar da real.
     *
     * @var array<string, list<string>>
     */
    private const CONTENT_ALIASES = [
        'kmz' => ['kmz', 'zip'],
        'kml' => ['kml', 'xml'],
        'xlsx' => ['xlsx', 'zip'],
        'docx' => ['docx', 'zip'],
        'pptx' => ['pptx', 'zip'],
        'xls' => ['xls', 'bin'],
        'doc' => ['doc', 'bin'],
        'ppt' => ['ppt', 'bin'],
        'dwg' => ['dwg', 'bin'],
        'jpg' => ['jpg', 'jpeg', 'jpe', 'jfif'],
        'jpeg' => ['jpg', 'jpeg', 'jpe', 'jfif'],
        'png' => ['png'],
        'webp' => ['webp'],
        'pdf' => ['pdf'],
    ];

    /**
     * @param  list<string>  $allowed
     */
    public static function resolve(UploadedFile $file, array $allowed): ?string
    {
        $allowed = array_values(array_unique(array_map(
            static fn (string $extension): string => strtolower($extension),
            $allowed,
        )));

        $client = strtolower($file->getClientOriginalExtension());
        $guessed = strtolower((string) ($file->guessExtension() ?? ''));

        if ($client !== '' && in_array($client, $allowed, true)) {
            if ($guessed === '' || self::contentMatchesClient($client, $guessed)) {
                return $client;
            }

            // Conteúdo inconsistente com a extensão declarada.
            return null;
        }

        if ($guessed !== '' && in_array($guessed, $allowed, true)) {
            return $guessed;
        }

        if (in_array($guessed, ['jpe', 'jfif'], true) && in_array('jpg', $allowed, true)) {
            return 'jpg';
        }

        return null;
    }

    private static function contentMatchesClient(string $client, string $guessed): bool
    {
        if ($client === $guessed) {
            return true;
        }

        $aliases = self::CONTENT_ALIASES[$client] ?? [$client];

        return in_array($guessed, $aliases, true);
    }
}
