<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class KmzParserService
{
    private const int MAX_ARCHIVE_ENTRIES = 100;

    private const int MAX_UNCOMPRESSED_KML_BYTES = 20 * 1024 * 1024;

    /**
     * Parseia um arquivo .kml ou .kmz e retorna as coordenadas do polígono.
     *
     * @return array<int, array{lat: float, lng: float}>
     *
     * @throws RuntimeException
     */
    public function parse(UploadedFile $file): array
    {
        $geometries = $this->parseMany($file);

        return $geometries[0]['coords'];
    }

    /**
     * @return list<array{source_entry: string|null, placemark_name: string|null, geometry_index: int, coords: list<array{lat: float, lng: float}>, geometry_hash: string, bounds: array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}}>
     */
    public function parseMany(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $documents = match ($extension) {
            'kmz' => $this->extractKmlDocumentsFromKmz($file->getRealPath()),
            'kml' => [[
                'entry' => $file->getClientOriginalName(),
                'content' => $this->readKmlFile($file->getRealPath()),
            ]],
            default => throw new RuntimeException(
                "Extensão de arquivo não suportada: \"{$extension}\". Envie um arquivo .kml ou .kmz."
            ),
        };

        $geometries = [];
        foreach ($documents as $document) {
            array_push($geometries, ...$this->parseManyKml($document['content'], $document['entry']));
        }
        if ($geometries === []) {
            throw new RuntimeException('Nenhum polígono ou linha foi encontrado no arquivo.');
        }

        return $geometries;
    }

    /**
     * Abre o arquivo KMZ (ZIP) e extrai o conteúdo do primeiro .kml encontrado.
     *
     * @throws RuntimeException
     */
    public function extractKmlFromKmz(string $path): string
    {
        return $this->extractKmlDocumentsFromKmz($path)[0]['content'];
    }

    /**
     * @return list<array{entry: string, content: string}>
     */
    private function extractKmlDocumentsFromKmz(string $path): array
    {
        $zip = new ZipArchive;
        $result = $zip->open($path);

        if ($result !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo KMZ (código de erro: {$result}).");
        }

        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            $zip->close();

            throw new RuntimeException('O arquivo KMZ contém itens demais para ser processado com segurança.');
        }

        $documents = [];
        $totalSize = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'kml') {
                    continue;
                }

                $metadata = $zip->statIndex($i);
                $entrySize = is_array($metadata) ? (int) $metadata['size'] : 0;
                $totalSize += $entrySize;
                if ($entrySize > self::MAX_UNCOMPRESSED_KML_BYTES || $totalSize > self::MAX_UNCOMPRESSED_KML_BYTES) {
                    throw new RuntimeException('O conteúdo KML descompactado excede o limite de 20 MB.');
                }

                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Não foi possível ler o arquivo KML dentro do KMZ.');
                }

                try {
                    $kmlContent = stream_get_contents($stream, self::MAX_UNCOMPRESSED_KML_BYTES + 1);
                } finally {
                    fclose($stream);
                }

                if ($kmlContent === false) {
                    throw new RuntimeException('Não foi possível ler o arquivo KML dentro do KMZ.');
                }

                if (strlen($kmlContent) > self::MAX_UNCOMPRESSED_KML_BYTES) {
                    throw new RuntimeException('O conteúdo KML descompactado excede o limite de 20 MB.');
                }

                $documents[] = ['entry' => $name, 'content' => $kmlContent];
            }
        } finally {
            $zip->close();
        }

        if ($documents === []) {
            throw new RuntimeException('Nenhum arquivo .kml encontrado dentro do arquivo KMZ.');
        }

        return $documents;
    }

    /**
     * Parseia o conteúdo XML de um arquivo KML e extrai as coordenadas.
     * Tenta primeiro Polygon, com fallback para LineString.
     *
     * @return array<int, array{lat: float, lng: float}>
     *
     * @throws RuntimeException
     */
    public function parseKml(string $kmlContent): array
    {
        $geometries = $this->parseManyKml($kmlContent, null);

        return $geometries[0]['coords'];
    }

    /**
     * @return list<array{source_entry: string|null, placemark_name: string|null, geometry_index: int, coords: list<array{lat: float, lng: float}>, geometry_hash: string, bounds: array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}}>
     */
    public function parseManyKml(string $kmlContent, ?string $sourceEntry = null): array
    {
        $xml = $this->loadXml($kmlContent);
        $placemarks = $xml->xpath('//*[local-name()="Placemark"]') ?: [];
        $geometries = [];
        $geometryIndex = 0;

        foreach ($placemarks as $placemark) {
            $nameNodes = $placemark->xpath('./*[local-name()="name"]') ?: [];
            $name = isset($nameNodes[0]) && trim((string) $nameNodes[0]) !== ''
                ? trim((string) $nameNodes[0])
                : null;
            $polygons = $placemark->xpath('.//*[local-name()="Polygon"]') ?: [];
            foreach ($polygons as $polygon) {
                if (($polygon->xpath('.//*[local-name()="innerBoundaryIs"]') ?: []) !== []) {
                    throw new RuntimeException('Polígonos com áreas internas (buracos) ainda não são suportados.');
                }
                $coordinateNodes = $polygon->xpath('.//*[local-name()="outerBoundaryIs"]/*[local-name()="LinearRing"]/*[local-name()="coordinates"]') ?: [];
                if (! isset($coordinateNodes[0])) {
                    continue;
                }
                $geometries[] = $this->geometry(
                    (string) $coordinateNodes[0],
                    $sourceEntry,
                    $name,
                    $geometryIndex++,
                );
            }
        }

        if ($geometries === []) {
            $lineStrings = $xml->xpath('//*[local-name()="LineString"]/*[local-name()="coordinates"]') ?: [];
            foreach ($lineStrings as $lineString) {
                $geometries[] = $this->geometry((string) $lineString, $sourceEntry, null, $geometryIndex++);
            }
        }

        if ($geometries === []) {
            throw new RuntimeException(
                'Nenhum polígono ou linha encontrada no arquivo KML. '
                .'Verifique se o arquivo contém um Placemark com geometria Polygon ou LineString.'
            );
        }

        return $geometries;
    }

    /**
     * Limpa problemas comuns em KMLs gerados por ferramentas CAD (ex: Metrica TOPO CAD):
     * 1. Injeta namespace gx: faltante para elementos <gx:altitudeMode> etc.
     * 2. Remove linhas de código JavaScript injetadas erroneamente no XML.
     */
    private function sanitizeKml(string $kml): string
    {
        // Injeta xmlns:gx quando o arquivo usa gx: mas não declara o namespace
        if (str_contains($kml, 'gx:') && ! str_contains($kml, 'xmlns:gx=')) {
            $gxNs = 'xmlns:gx="http://www.google.com/kml/ext/2.2"';
            $kml = preg_replace('/<kml\b/', "<kml {$gxNs}", $kml, 1) ?? $kml;
        }

        // Remove linhas de JavaScript injetadas fora de tags XML (artefato de exportadores CAD)
        $kml = (string) preg_replace('/^[^<\n]*\bvar\s+\w+\s*=\s*new\b[^\n]*$/m', '', $kml);

        return $kml;
    }

    /**
     * Converte string de coordenadas KML para array de {lat, lng}.
     * Formato KML: "lon,lat[,alt] lon,lat[,alt] ..." (longitude PRIMEIRO).
     * A coordenada de fechamento duplicada é removida.
     *
     * @return non-empty-list<array{lat: float, lng: float}>
     *
     * @throws RuntimeException
     */
    private function parseCoordinateString(string $rawCoords): array
    {
        $triplets = preg_split('/\s+/', $rawCoords);
        $triplets = array_values(array_filter((array) $triplets, fn (string $t) => $t !== ''));

        $coords = [];

        foreach ($triplets as $triplet) {
            $parts = explode(',', $triplet);
            if (count($parts) < 2) {
                continue;
            }
            if (! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                throw new RuntimeException('O arquivo KML contém coordenadas não numéricas.');
            }

            $lat = (float) $parts[1];
            $lng = (float) $parts[0];
            if (! is_finite($lat) || ! is_finite($lng)
                || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new RuntimeException('O arquivo KML contém latitude ou longitude fora do intervalo válido.');
            }
            $coords[] = ['lat' => $lat, 'lng' => $lng];
        }

        if (empty($coords)) {
            throw new RuntimeException(
                'O arquivo KML não contém coordenadas válidas no polígono encontrado.'
            );
        }

        // Google Earth fecha o anel repetindo a primeira coordenada — remove se for duplicata
        if (count($coords) > 1) {
            $first = $coords[0];
            $last = $coords[count($coords) - 1];
            if ($first['lat'] === $last['lat'] && $first['lng'] === $last['lng']) {
                array_pop($coords);
            }
        }

        if (count($coords) < 3) {
            throw new RuntimeException('O polígono deve conter ao menos três pontos.');
        }

        return $coords;
    }

    private function readKmlFile(string $path): string
    {
        $size = filesize($path);
        if ($size !== false && $size > self::MAX_UNCOMPRESSED_KML_BYTES) {
            throw new RuntimeException('O arquivo KML excede o limite de 20 MB.');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Não foi possível ler o arquivo KML.');
        }

        return $content;
    }

    private function loadXml(string $kmlContent): \SimpleXMLElement
    {
        $kmlContent = $this->sanitizeKml($kmlContent);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($kmlContent, \SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = ! empty($errors) ? trim($errors[0]->message) : 'erro desconhecido';
            throw new RuntimeException("O arquivo KML contém XML inválido: {$message}");
        }
        libxml_clear_errors();

        return $xml;
    }

    /**
     * @return array{source_entry: string|null, placemark_name: string|null, geometry_index: int, coords: list<array{lat: float, lng: float}>, geometry_hash: string, bounds: array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}}
     */
    private function geometry(string $rawCoords, ?string $sourceEntry, ?string $name, int $index): array
    {
        $coords = $this->parseCoordinateString(trim($rawCoords));
        $minLat = $maxLat = $coords[0]['lat'];
        $minLng = $maxLng = $coords[0]['lng'];
        foreach ($coords as $coordinate) {
            $minLat = min($minLat, $coordinate['lat']);
            $maxLat = max($maxLat, $coordinate['lat']);
            $minLng = min($minLng, $coordinate['lng']);
            $maxLng = max($maxLng, $coordinate['lng']);
        }
        $canonical = array_map(
            static fn (array $coordinate): array => [
                'lat' => round($coordinate['lat'], 8),
                'lng' => round($coordinate['lng'], 8),
            ],
            $coords,
        );

        return [
            'source_entry' => $sourceEntry,
            'placemark_name' => $name,
            'geometry_index' => $index,
            'coords' => $coords,
            'geometry_hash' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR)),
            'bounds' => [
                'min_lat' => $minLat,
                'max_lat' => $maxLat,
                'min_lng' => $minLng,
                'max_lng' => $maxLng,
            ],
        ];
    }
}
