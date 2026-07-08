<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<ComiteAiDossier>
 */
class ComiteAiDossierFactory extends Factory
{
    protected $model = ComiteAiDossier::class;

    public function definition(): array
    {
        return [
            'comite_revisao_id' => ComiteRevisao::factory(),
            'terreno_id' => Terreno::factory(),
            'viabilidade_id' => Viabilidade::factory(),
            'status' => 'ready',
            'prompt_version' => 1,
            'input_hash' => hash('sha256', fake()->uuid()),
            'sections' => [
                'pontos_apoio' => fake()->sentence(),
                'concorrentes' => fake()->sentence(),
                'infraestrutura' => fake()->sentence(),
                'juridico' => fake()->sentence(),
            ],
            'raw_response' => fake()->paragraph(),
            'provider' => 'openrouter',
            'model' => 'test-model',
            'generated_by' => null,
            'generated_at' => now(),
            'error_message' => null,
        ];
    }
}
