<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\AiContextRecommendation;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<AiContextRecommendation> */
class AiContextRecommendationFactory extends Factory
{
    protected $model = AiContextRecommendation::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'terreno',
            'entity_id' => 1,
            'intent' => 'score',
            'parameters' => [],
            'input_hash' => hash('sha256', fake()->uuid()),
            'output' => ['confidence' => 0.5],
            'status' => 'proposed',
            'created_by' => User::factory(),
        ];
    }
}
