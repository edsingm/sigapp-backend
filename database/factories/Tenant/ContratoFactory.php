<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @phpstan-extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'negociacao_id' => Negociacao::factory(),
            'contract_type' => fake()->randomElement(['compra_venda', 'parceria', 'permuta', 'minuta']),
            'contract_number' => 'CTR-'.strtoupper(Str::random(8)),
            'signed_at' => null,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'status' => 'pendente',
            'file_path' => null,
            'notes' => fake()->optional(0.2)->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn (): array => [
            'status' => 'pendente',
            'signed_at' => null,
        ]);
    }

    public function assinado(): static
    {
        return $this->state(fn (): array => [
            'status' => 'assinado',
            'signed_at' => now(),
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (): array => [
            'status' => 'cancelado',
        ]);
    }
}
