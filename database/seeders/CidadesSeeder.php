<?php

namespace Database\Seeders;

use App\Models\Central\Cidade;
use Illuminate\Database\Seeder;
use RuntimeException;

class CidadesSeeder extends Seeder
{
    public function run(): void
    {
        Cidade::query()->truncate();

        $csvPath = database_path('seeders/data/municipios.csv');
        $csvFile = fopen($csvPath, 'r');

        if ($csvFile === false) {
            throw new RuntimeException("Não foi possível abrir o arquivo {$csvPath}.");
        }

        fgetcsv($csvFile, 0, ';');

        $batch = [];

        while (($row = fgetcsv($csvFile, 0, ';')) !== false) {
            if ($row === [null] || count($row) < 17) {
                continue;
            }

            $code = $this->sanitizeString($row[1]);

            if ($code === null) {
                continue;
            }

            $batch[] = [
                'id' => (int) $row[0],
                'code' => $code,
                'city' => $this->sanitizeString($row[2]),
                'state' => $this->sanitizeString($row[3]),
                'state_code' => $this->sanitizeString($row[4]),
                'latitude' => $this->sanitizeDecimal($row[5]),
                'longitude' => $this->sanitizeDecimal($row[6]),
                'capital' => (bool) ($this->sanitizeInteger($row[7]) ?? 0),
                'area_code' => $this->sanitizeString($row[8]),
                'timezone' => $this->sanitizeString($row[9]),
                'population' => $this->sanitizeInteger($row[10]),
                'employed' => $this->sanitizeInteger($row[11]),
                'property_maximum_value' => $this->sanitizeDecimal($row[12]),
                'per_capta_income' => $this->sanitizeDecimal($row[13]),
                'buyer_demand' => $this->sanitizeDecimal($row[14]),
                'own_property' => $this->sanitizeInteger($row[15]),
                'rented_property' => $this->sanitizeInteger($row[16]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) === 500) {
                Cidade::query()->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            Cidade::query()->insert($batch);
        }

        fclose($csvFile);
    }

    private function sanitizeString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        if ($value === null || $value === '' || strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    private function sanitizeInteger(?string $value): ?int
    {
        $sanitized = $this->sanitizeString($value);

        return $sanitized !== null ? (int) $sanitized : null;
    }

    private function sanitizeDecimal(?string $value): ?float
    {
        $sanitized = $this->sanitizeString($value);

        return $sanitized !== null ? (float) str_replace(',', '.', $sanitized) : null;
    }
}
