<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Regional;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoStatus;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TerrenoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureUsers();

        if (TerrenoStatus::query()->count() === 0) {
            $this->call(AreaStatusSeeder::class);
        }

        if (CorretorExterno::query()->count() === 0) {
            $this->call(CorretorExternoSeeder::class);
        }

        if (Regional::query()->count() === 0) {
            $this->call(RegionalSeeder::class);
        }

        $userIds = User::query()->pluck('id')->all();
        $statusByName = TerrenoStatus::query()->pluck('id', 'nome')->all();
        $statusNames = array_keys($statusByName);
        $regionalIds = Regional::query()->pluck('id')->all();
        $corretorIds = CorretorExterno::query()->pluck('id')->all();

        $estados = ['SP', 'PR', 'AM', 'MG', 'RJ', 'SC', 'RS', 'BA', 'GO', 'PE', 'CE'];
        $cidadeCodes = ['1302603', '3550308', '4106902', '5300108', '3304557', '3106200'];
        $zonas = ['Residencial', 'Comercial', 'Industrial', 'Mista'];
        $distritos = ['Centro', 'Zona Norte', 'Zona Sul', 'Zona Leste', 'Zona Oeste'];
        $operacoesUrbanas = ['Água Espraiada', 'Faria Lima', 'Consorciada', 'Sem operação'];

        $terrenosData = [];
        for ($i = 1; $i <= 5; $i++) {
            $createdAt = $this->createdAtForIndex($i);
            $updatedAt = $this->updatedAtFromCreatedAt($createdAt);

            $statusNome = $statusNames[array_rand($statusNames)];
            $statusId = $statusByName[$statusNome];

            $dataApresentacao = $createdAt->copy()->subDays(random_int(0, 15))->toDateString();
            $dataNegociacao = null;
            $dataOpcao = null;
            $dataDescarte = null;
            $dataContrato = null;

            if (in_array($statusNome, ['Negociação', 'Opção', 'Minuta', 'Legalização', 'Contrato', 'Registro', 'Lançamento', 'Obra', 'Entregue', 'Landbank', 'StandBy'], true)) {
                $dataNegociacao = $createdAt->copy()->subDays(random_int(0, 45))->toDateString();
            }
            if (in_array($statusNome, ['Opção', 'Minuta', 'Legalização', 'Contrato', 'Registro', 'Lançamento', 'Obra', 'Entregue'], true)) {
                $dataOpcao = $createdAt->copy()->subDays(random_int(0, 90))->toDateString();
            }
            if ($statusNome === 'Descartado') {
                $dataDescarte = $createdAt->copy()->subDays(random_int(0, 60))->toDateString();
            }
            if (in_array($statusNome, ['Contrato', 'Registro', 'Lançamento', 'Obra', 'Entregue'], true)) {
                $dataContrato = $createdAt->copy()->subDays(random_int(0, 120))->toDateString();
            }

            $responsavelId = $userIds[array_rand($userIds)];
            $creatorId = $userIds[array_rand($userIds)];
            $updaterId = $userIds[array_rand($userIds)];
            $compradorId = in_array($statusNome, ['Contrato', 'Registro', 'Lançamento', 'Obra', 'Entregue'], true)
                ? $userIds[array_rand($userIds)]
                : null;

            $estado = $estados[array_rand($estados)];
            $cidadeCode = $cidadeCodes[array_rand($cidadeCodes)];
            $nome = 'Área ' . Str::title(Str::replace(['-', '_'], ' ', Str::random(8))) . " - {$estado} #{$i}";

            $terrenosData[] = [
                'nome' => $nome,
                'responsavel_id' => $responsavelId,
                'endereco' => $this->randomEndereco(),
                'corretor_id' => $corretorIds ? $corretorIds[array_rand($corretorIds)] : null,
                'estado' => $estado,
                'cidade_code' => $cidadeCode,
                'polygon_coords' => json_encode($this->randomPolygonCoords()),
                'static_map_url' => null,
                'area_calculada' => $this->randomMoney(500_000, 250_000_000),
                'status_id' => $statusId,
                'regional_id' => $regionalIds ? $regionalIds[array_rand($regionalIds)] : null,
                'cep' => $this->randomCep(),
                'bairro' => 'Bairro ' . Str::title(Str::replace(['-', '_'], ' ', Str::random(6))),
                'observacoes' => random_int(1, 4) === 1 ? null : 'Observação ' . Str::title(Str::replace(['-', '_'], ' ', Str::random(12))),
                'valor' => $this->randomMoney(150_000, 50_000_000),
                'zona' => $zonas[array_rand($zonas)],
                'distrito' => $distritos[array_rand($distritos)],
                'operacao_urbana' => $operacoesUrbanas[array_rand($operacoesUrbanas)],
                'data_apresentacao' => $dataApresentacao,
                'data_negociacao' => $dataNegociacao,
                'data_opcao' => $dataOpcao,
                'data_descarte' => $dataDescarte,
                'data_contrato' => $dataContrato,
                'comprador_id' => $compradorId,
                'created_by' => $creatorId,
                'updated_by' => $updaterId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        \Illuminate\Support\Facades\DB::table('terrenos')->insert($terrenosData);

    }

    private function createdAtForIndex(int $index): Carbon
    {
        $month = (($index - 1) % 12) + 1;
        $year = 2022 + random_int(0, 3);
        $date = Carbon::create($year, $month, 1, 0, 0, 0);
        $date = $date->addDays(random_int(0, $date->daysInMonth - 1));
        return $date->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));
    }

    private function updatedAtFromCreatedAt(Carbon $createdAt): Carbon
    {
        $endOfYear = Carbon::create(2025, 12, 31, 23, 59, 59);
        $maxDays = max(0, $createdAt->diffInDays($endOfYear));
        $addDays = random_int(0, min(30, $maxDays));
        return $createdAt->copy()->addDays($addDays);
    }

    private function ensureUsers(): void
    {
        if (User::query()->count() >= 5) {
            return;
        }

        $password = Hash::make('password');
        $existingCount = User::query()->count();
        $needed = max(0, 5 - $existingCount);

        for ($i = 1; $i <= $needed; $i++) {
            $suffix = $existingCount + $i;
            User::firstOrCreate(
                ['email' => "seed{$suffix}@example.com"],
                [
                    'name' => "Seed User {$suffix}",
                    'password' => $password,
                ]
            );
        }
    }

    private function randomEndereco(): string
    {
        $ruas = ['Rua Jorge Pinto de Souza', 'Av. Paulista', 'Rua das Flores', 'Av. Brasil', 'Rua Central', 'Av. das Nações'];
        $numero = random_int(1, 4000);
        return $ruas[array_rand($ruas)] . ", {$numero}";
    }

    private function randomCep(): string
    {
        $p1 = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
        $p2 = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        return "{$p1}-{$p2}";
    }

    private function randomMoney(int $min, int $max): float
    {
        $cents = random_int($min * 100, $max * 100);
        return $cents / 100;
    }

    private function randomPolygonCoords(): array
    {
        $baseLat = -25 + (random_int(0, 1000) / 1000) * 10;
        $baseLng = -55 + (random_int(0, 1000) / 1000) * 10;

        $points = [];
        $count = random_int(3, 6);

        for ($i = 0; $i < $count; $i++) {
            $points[] = [
                'lat' => $baseLat + (random_int(-800, 800) / 100000),
                'lng' => $baseLng + (random_int(-800, 800) / 100000),
            ];
        }

        return $points;
    }
}
