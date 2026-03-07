<?php

namespace App\Exports\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TerrenosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = Terreno::query()
            ->with([
                'status',
                'responsavel',
                'regional',
                'cidade',
            ])
            ->withSum('terrenoProdutos as total_unidades', 'unidades');

        // Aplicar filtros
        $nome = $this->filters['nome'] ?? null;
        if ($nome !== null && $nome !== '') {
            $query->whereRaw('LOWER(nome) LIKE ?', [Str::lower($nome) . '%']);
        }

        $statusIds = $this->filters['status_ids'] ?? null;
        if (is_array($statusIds) && count($statusIds)) {
            $query->whereIn('status_id', $statusIds);
        }

        $ufs = $this->filters['ufs'] ?? null;
        if (is_array($ufs) && count($ufs)) {
            $query->whereIn('estado', $ufs);
        }

        $cidades = $this->filters['cidades'] ?? null;
        if (is_array($cidades) && count($cidades)) {
            $query->whereIn('cidade_code', $cidades);
        }

        $gestores = $this->filters['gestor_ids'] ?? null;
        if (is_array($gestores) && count($gestores)) {
            $query->whereIn('responsavel_id', $gestores);
        }

        $corretores = $this->filters['corretor_ids'] ?? null;
        if (is_array($corretores) && count($corretores)) {
            $query->whereIn('corretor_id', $corretores);
        }

        $regionais = $this->filters['regional_ids'] ?? null;
        if (is_array($regionais) && count($regionais)) {
            $query->whereIn('regional_id', $regionais);
        }

        $dateField = $this->filters['date_field'] ?? 'created_at';
        if (empty($dateField)) {
            $dateField = 'created_at';
        }
        $dataInicio = $this->filters['data_inicio'] ?? null;
        $dataFim = $this->filters['data_fim'] ?? null;
        if ($dataInicio && $dataFim) {
            $query->whereBetween($dateField, [$dataInicio, $dataFim]);
        }

        $ano = $this->filters['ano'] ?? null;
        if ($ano) {
            $query->whereYear($dateField, (int) $ano);
        }

        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nome',
            'Cidade',
            'Estado',
            'Área (m²)',
            'Unidades',
            'VGV',
            'Responsável',
            'Status',
            'Data Cadastro',
            'Regional',
        ];
    }

    public function map($terreno): array
    {
        return [
            $terreno->id,
            $terreno->nome,
            $terreno->cidade?->name ?? $terreno->cidade_code ?? '',
            $terreno->estado ?? '',
            $terreno->area_calculada ?? '',
            $terreno->total_unidades ?? '',
            $terreno->valor ?? '',
            $terreno->responsavel?->name ?? '',
            $terreno->status?->nome ?? '',
            $terreno->created_at ? $terreno->created_at->format('d/m/Y') : '',
            preg_replace('/^Regional\s+/i', '', $terreno->regional?->nome ?? ''),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Estilo do cabeçalho
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED'],
                ],
            ],
        ];
    }
}
