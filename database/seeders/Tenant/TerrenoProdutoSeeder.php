<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TerrenoProdutoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureUsers();

        if (Produto::query()->count() === 0) {
            $this->call(ProdutoSeeder::class);
        }

        if (Terreno::query()->count() === 0) {
            $this->call(TerrenoSeeder::class);
        }

        if (!app()->environment('production')) {
            TerrenoProduto::query()->forceDelete();
        }

        $produtoIds = Produto::query()->pluck('id')->all();
        $fallbackProdutoId = $produtoIds ? $produtoIds[array_rand($produtoIds)] : null;
        $userIds = User::query()->pluck('id')->all();

        $terrenos = Terreno::query()
            ->doesntHave('terrenoProdutos')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $terrenoProdutosData = [];
        foreach ($terrenos as $terreno) {
            $createdAt = $terreno->created_at instanceof Carbon ? $terreno->created_at->copy() : now();
            $updatedAt = $terreno->updated_at instanceof Carbon ? $terreno->updated_at->copy() : $createdAt->copy();

            $unidades = random_int(20, 900);
            $valor = $this->randomMoney(190_000, 240_000);
            $permuta = random_int(1, 3) === 1 ? random_int(0, 100) : 0;
            $pgtoPorLote = random_int(1, 3) === 1 ? $this->randomMoney(5_000, 10_000) : 0;

            $createdBy = $terreno->created_by ?? ($userIds ? $userIds[array_rand($userIds)] : null);
            $updatedBy = $terreno->updated_by ?? ($userIds ? $userIds[array_rand($userIds)] : null);

            $terrenoProdutosData[] = [
                'terreno_id' => $terreno->id,
                'produto_id' => $fallbackProdutoId,
                'unidades' => $unidades,
                'valor' => $valor,
                'permuta' => $permuta,
                'pgto_por_lote' => $pgtoPorLote,
                'observacoes' => random_int(1, 4) === 1 ? null : 'Produto vinculado via seed ' . Str::title(Str::replace(['-', '_'], ' ', Str::random(10))),
                'created_by' => $createdBy,
                'updated_by' => $updatedBy,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        \Illuminate\Support\Facades\DB::table('terreno_produtos')->insert($terrenoProdutosData);

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

    private function randomMoney(int $min, int $max): float
    {
        $cents = random_int($min * 100, $max * 100);
        return $cents / 100;
    }
}
