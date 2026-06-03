<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Proprietario>
 */
class ProprietarioFactory extends Factory
{
    protected $model = Proprietario::class;

    public function definition(): array
    {
        return [
            'terreno_id' => Terreno::factory(),
            'nome' => fake()->name(),
            'rg' => fake()->numerify('##.###.###-#'),
            'cpf_cnpj' => fake()->numerify('###.###.###-##'),
            'nascimento' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'tipo_pessoa' => Proprietario::TIPO_FISICA,
            'estado_civil' => fake()->randomElement(['solteiro', 'casado', 'divorciado', 'viuvo']),
            'nacionalidade' => 'Brasileira',
            'profissao' => fake()->optional(0.7)->jobTitle(),
            'porcentagem_terreno' => 100.00,
            'email' => fake()->optional(0.7)->safeEmail(),
            'telefone' => fake()->numerify('(##) 9####-####'),
            'endereco' => fake()->optional(0.5)->streetAddress(),
            'cidade' => fake()->city(),
            'estado' => fake()->randomElement(['SP', 'RJ', 'MG', 'PR', 'SC']),
            'cep' => fake()->numerify('#####-###'),
        ];
    }

    public function fisica(): static
    {
        return $this->state(fn (): array => [
            'tipo_pessoa' => Proprietario::TIPO_FISICA,
            'cpf_cnpj' => fake()->numerify('###.###.###-##'),
            'nascimento' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
        ]);
    }

    public function juridica(): static
    {
        return $this->state(fn (): array => [
            'tipo_pessoa' => Proprietario::TIPO_JURIDICA,
            'cpf_cnpj' => fake()->numerify('##.###.###/####-##'),
            'nascimento' => null,
        ]);
    }
}
