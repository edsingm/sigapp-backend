<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoOferta;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<NegociacaoOferta> */
class NegociacaoOfertaFactory extends Factory
{
    protected $model = NegociacaoOferta::class;

    public function definition(): array
    {
        return [
            'negociacao_id' => Negociacao::factory(),
            'version' => 1,
            'offer_type' => 'proposal',
            'amount' => fake()->randomFloat(2, 100000, 5000000),
            'business_model' => 'permuta',
            'terms' => [],
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
