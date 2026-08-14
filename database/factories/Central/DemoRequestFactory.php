<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\DemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<DemoRequest>
 */
class DemoRequestFactory extends Factory
{
    protected $model = DemoRequest::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->company(),
            'city' => fake()->city(),
            'role' => 'Direção / sócio',
            'land_context' => fake()->optional()->sentence(),
            'source' => 'demonstracao',
            'page' => 'demonstracao',
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'accepted_privacy' => true,
            'accepted_at' => now(),
            'privacy_document_key' => 'privacy_policy',
            'privacy_document_version' => (string) config('legal.privacy_policy.version'),
            'privacy_document_hash' => (string) config('legal.privacy_policy.hash'),
        ];
    }
}
