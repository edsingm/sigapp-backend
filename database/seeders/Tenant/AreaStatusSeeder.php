<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant\TerrenoStatus;

class AreaStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $status = [
            [
                'nome' => 'Análise',
                'cor' => '#2563EB',
                'descricao' => 'Área está sendo analisada pela equipe',
                'ativo' => true
            ],
            [
                'nome' => 'Negociação',
                'cor' => '#F59E0B',
                'descricao' => 'Área está sendo negociada',
                'ativo' => true
            ],
            [
                'nome' => 'Contrato Assinado',
                'cor' => '#F59E0B',
                'descricao' => 'Contrato assinado com sucesso',
                'ativo' => true
            ],
            [
                'nome' => 'Distratada',
                'cor' => '#DC2626',
                'descricao' => 'Área está sendo distratada',
                'ativo' => true
            ],
            [
                'nome' => 'Legalização',
                'cor' => '#10B981',
                'descricao' => 'Área está sendo legalizada',
                'ativo' => true
            ],
            [
                'nome' => 'Registro',
                'cor' => '#047857',
                'descricao' => 'Área está sendo registrada',
                'ativo' => true
            ],
            [
                'nome' => 'Lançamento',
                'cor' => '#2563EB',
                'descricao' => 'Área está sendo lançada',
                'ativo' => true
            ],
            [
                'nome' => 'Obra',
                'cor' => '#1D4ED8',
                'descricao' => 'Área está sendo construída',
                'ativo' => true
            ],
            [
                'nome' => 'Entregue',
                'cor' => '#065F46',
                'descricao' => 'Área está pronta para entrega',
                'ativo' => true
            ],
            [
                'nome' => 'Landbank',
                'cor' => '#4B5563',
                'descricao' => 'Área está sendo mantida em landbank',
                'ativo' => true
            ],
            [
                'nome' => 'Descartado',
                'cor' => '#DC2626',
                'descricao' => 'Área está sendo descartada',
                'ativo' => true
            ],
            [
                'nome' => 'StandBy',
                'cor' => '#FF00FF',
                'descricao' => 'Área está em StandBy',
                'ativo' => true
            ],
        ];

        foreach ($status as $s) {
            TerrenoStatus::updateOrCreate(
                ['nome' => $s['nome']],
                [
                    'cor' => $s['cor'],
                    'descricao' => $s['descricao'],
                    'ativo' => $s['ativo'],
                ]
            );
        }
    }
}
