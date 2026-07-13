<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoAprovacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<NegociacaoAprovacao> */
class NegociacaoAprovacaoFactory extends Factory
{
    protected $model = NegociacaoAprovacao::class;

    public function definition(): array
    {
        return [
            'negociacao_id' => Negociacao::factory(),
            'area' => 'juridico',
            'decision' => 'pending',
        ];
    }
}
