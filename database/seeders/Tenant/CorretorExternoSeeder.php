<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\CorretorExterno;
use Illuminate\Database\Seeder;

class CorretorExternoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $corretores = [
            [
                'nome' => 'Corretor Externo 1',
                'email' => 'corretor1@example.com',
                'telefone' => '11999999999',
                'creci' => 12345,
            ],
            [
                'nome' => 'Corretor Externo 2',
                'email' => 'corretor2@example.com',
                'telefone' => '11888888888',
                'creci' => 67890,
            ],
        ];

        foreach ($corretores as $corretor) {
            CorretorExterno::updateOrCreate(
                ['email' => $corretor['email']],
                [
                    'nome' => $corretor['nome'],
                    'telefone' => $corretor['telefone'],
                    'creci' => $corretor['creci'],
                ]
            );
        }
    }
}
