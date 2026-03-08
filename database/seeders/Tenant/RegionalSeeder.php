<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Regional;
use App\Models\Tenant\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::first()?->id;

        if (!$adminId) {
            $this->command?->warn('RegionalSeeder ignorado: nenhum usuário encontrado no tenant.');
            return;
        }

        $regionaisData = [
            [
                'nome' => 'Regional Matriz',
                'estado' => 'São Paulo',
                'cidade' => 'São Paulo',
                'endereco' => 'Avenida Paulista',
                'numero' => '1000',
                'telefone' => '(11) 3000-0000',
                'celular' => '(11) 99000-0000',
                'observacoes' => 'Regional Matriz',
                'responsavel_id' => $adminId,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        ];

        foreach ($regionaisData as $index => $data) {
            Regional::updateOrCreate(
                ['nome' => $data['nome']],
                [
                    'estado' => $data['estado'],
                    'cidade' => $data['cidade'],
                    'endereco' => $data['endereco'],
                    'numero' => $data['numero'],
                    'telefone' => $data['telefone'],
                    'celular' => $data['celular'],
                    'observacoes' => $data['observacoes'],
                    'responsavel_id' => $data['responsavel_id'],
                    'created_by' => $data['created_by'],
                    'updated_by' => $data['updated_by'],
                ]
            );
        }
    }
}
