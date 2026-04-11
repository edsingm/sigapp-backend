<?php

namespace App\Services\Tenant;

use RuntimeException;

class PortalTerrenoScraperService
{
    public function extractFromHtml(string $html): array
    {
        $coordinateSets = $this->extractCoordinateSets($html);

        preg_match_all(
            '/var\s+(poligono_[A-Za-z0-9_]+)\s*=\s*new\s+google\.maps\.Polygon\s*\(\s*\{(.*?)\}\s*\)\s*;/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $terrenos = [];

        foreach ($matches as $match) {
            $polygonVariable = $match[1];
            $configBody = $match[2];
            $pathsReference = $this->extractValue($configBody, 'paths') ?? $polygonVariable;

            $terrenos[] = [
                'id' => $this->extractValue($configBody, 'idterreno') ?? str_replace('poligono_', '', $polygonVariable),
                'nome' => $this->extractValue($configBody, 'descricao') ?? '',
                'gestor' => $this->extractValue($configBody, 'gestor') ?? '',
                'status' => $this->extractValue($configBody, 'status') ?? '',
                'poligono' => $coordinateSets[$pathsReference] ?? $coordinateSets[$polygonVariable] ?? [],
            ];
        }

        if ($terrenos === []) {
            throw new RuntimeException('Nenhum terreno foi encontrado no HTML informado.');
        }

        return $terrenos;
    }

    private function extractCoordinateSets(string $html): array
    {
        preg_match_all(
            '/var\s+(poligono_[A-Za-z0-9_]+)\s*=\s*\[(.*?)\]\s*;/s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $coordinateSets = [];

        foreach ($matches as $match) {
            $coordinateSets[$match[1]] = $this->parseCoordinates($match[2]);
        }

        return $coordinateSets;
    }

    private function parseCoordinates(string $coordinateBody): array
    {
        preg_match_all(
            '/lat\s*:\s*([-+]?\d+(?:\.\d+)?)\s*,\s*lng\s*:\s*([-+]?\d+(?:\.\d+)?)/',
            $coordinateBody,
            $matches,
            PREG_SET_ORDER
        );

        $coordinates = [];

        foreach ($matches as $match) {
            $coordinates[] = [
                'lat' => (float) $match[1],
                'lng' => (float) $match[2],
            ];
        }

        return $coordinates;
    }

    private function extractValue(string $configBody, string $field): ?string
    {
        $pattern = sprintf(
            '/\b%s\s*:\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|([A-Za-z0-9_.-]+))/s',
            preg_quote($field, '/')
        );

        if (! preg_match($pattern, $configBody, $matches)) {
            return null;
        }

        $value = $matches[1] !== ''
            ? $matches[1]
            : ($matches[2] !== '' ? $matches[2] : $matches[3]);

        return stripcslashes($value);
    }
}
