<?php

namespace App\Services\Tenant;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class KmzParserService
{
    /**
     * Parseia um arquivo .kml ou .kmz e retorna as coordenadas do polígono.
     *
     * @return array<int, array{lat: float, lng: float}>
     *
     * @throws RuntimeException
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'kmz') {
            $kmlContent = $this->extractKmlFromKmz($file->getRealPath());
        } elseif ($extension === 'kml') {
            $kmlContent = file_get_contents($file->getRealPath());
            if ($kmlContent === false) {
                throw new RuntimeException('Não foi possível ler o arquivo KML.');
            }
        } else {
            throw new RuntimeException(
                "Extensão de arquivo não suportada: \"{$extension}\". Envie um arquivo .kml ou .kmz."
            );
        }

        return $this->parseKml($kmlContent);
    }

    /**
     * Abre o arquivo KMZ (ZIP) e extrai o conteúdo do primeiro .kml encontrado.
     *
     * @throws RuntimeException
     */
    public function extractKmlFromKmz(string $path): string
    {
        $zip = new ZipArchive;
        $result = $zip->open($path);

        if ($result !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo KMZ (código de erro: {$result}).");
        }

        $kmlContent = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'kml') {
                $kmlContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();

        if ($kmlContent === false || $kmlContent === null) {
            throw new RuntimeException('Nenhum arquivo .kml encontrado dentro do arquivo KMZ.');
        }

        return $kmlContent;
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
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($kmlContent);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = ! empty($errors) ? trim($errors[0]->message) : 'erro desconhecido';
            throw new RuntimeException("O arquivo KML contém XML inválido: {$msg}");
        }

        libxml_clear_errors();

        // Tenta Polygon primeiro, depois LineString como fallback
        $rawCoords = $this->findFirstCoordinateString($xml, 'Polygon/outerBoundaryIs/LinearRing/coordinates')
            ?? $this->findFirstCoordinateString($xml, 'LineString/coordinates');

        if ($rawCoords === null) {
            throw new RuntimeException(
                'Nenhum polígono ou linha encontrada no arquivo KML. '
                .'Verifique se o arquivo contém um Placemark com geometria Polygon ou LineString.'
            );
        }

        return $this->parseCoordinateString(trim($rawCoords));
    }

    /**
     * Busca uma string de coordenadas usando XPath dentro do XML.
     * Tenta primeiro sem namespace (KML simples) e depois com prefixo kml:
     * (exportações do Google Earth que declaram xmlns).
     */
    private function findFirstCoordinateString(\SimpleXMLElement $xml, string $path): ?string
    {
        // Tentativa 1: XPath sem namespace
        $results = $xml->xpath('//'.$path);
        if (! empty($results)) {
            return (string) $results[0];
        }

        // Tentativa 2: XPath com prefixo kml: para documentos com namespace declarado
        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
        $segments = explode('/', $path);
        $namespacedPath = implode('/', array_map(fn (string $s) => 'kml:'.$s, $segments));
        $results = $xml->xpath('//'.$namespacedPath);

        if (! empty($results)) {
            return (string) $results[0];
        }

        // Tentativa 3: namespace legado do Google Earth (kml/2.1)
        $xml->registerXPathNamespace('kml', 'http://earth.google.com/kml/2.1');
        $results = $xml->xpath('//'.$namespacedPath);

        if (! empty($results)) {
            return (string) $results[0];
        }

        return null;
    }

    /**
     * Converte string de coordenadas KML para array de {lat, lng}.
     * Formato KML: "lon,lat[,alt] lon,lat[,alt] ..." (longitude PRIMEIRO).
     * A coordenada de fechamento duplicada é removida.
     *
     * @return array<int, array{lat: float, lng: float}>
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

            $coords[] = [
                'lat' => (float) $parts[1], // KML: latitude é o segundo token
                'lng' => (float) $parts[0], // KML: longitude é o primeiro token
            ];
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

        return $coords;
    }
}
