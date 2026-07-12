<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoCondicao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<ContratoCondicao> */
class ContratoCondicaoFactory extends Factory
{
    protected $model = ContratoCondicao::class;

    public function definition(): array
    {
        return [
            'contrato_id' => Contrato::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => 'pending',
            'due_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }
}
