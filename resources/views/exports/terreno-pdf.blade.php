<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Terrenos</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 10mm 12mm;
        }

        :root {
            --primary: #2f8f5b;
            --primary-strong: #246f47;
            --primary-soft: #eaf5ef;
            --text: #1f2a24;
            --muted: #64756a;
            --border: #d5e2da;
            --table-head: #f3f8f5;
            --table-stripe: #fafcfb;
            --success: #257949;
            --danger: #b93d3d;
            --warning: #a76c12;
            --info: #2a6f9f;
            --neutral: #5f6d64;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: var(--text);
            background: white;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .report {
            width: 100%;
        }

        .header {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            background: linear-gradient(90deg, var(--primary-strong), var(--primary));
            color: white;
            padding: 14px 16px;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.2px;
            margin-bottom: 2px;
        }

        .title p {
            font-size: 10px;
            opacity: 0.92;
        }

        .meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-size: 9px;
            text-align: right;
            color: rgba(255, 255, 255, 0.95);
        }

        .header-bottom {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 14px;
            background: var(--primary-soft);
            font-size: 9px;
            color: var(--muted);
            border-top: 1px solid rgba(255, 255, 255, 0.28);
        }

        .summary {
            display: flex;
            gap: 8px;
            margin: 8px 0 10px;
        }

        .kpi {
            flex: 1;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 10px;
            background: white;
        }

        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .kpi-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .kpi-value.primary {
            color: var(--primary-strong);
        }

        .filters-box {
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fcfefd;
            margin-bottom: 10px;
            padding: 8px 10px;
        }

        .filters-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .filters-content {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .filter-chip {
            display: inline-block;
            padding: 3px 7px;
            border: 1px solid #cfe1d6;
            border-radius: 999px;
            background: #f4fbf7;
            color: #2b6e48;
            font-size: 8px;
            font-weight: 600;
        }

        .filters-empty {
            color: var(--muted);
            font-size: 8px;
        }

        .table-wrap {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 8.6px;
        }

        .report-table thead th {
            background: var(--table-head);
            color: #2f3f36;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            font-size: 7.5px;
            font-weight: 700;
            padding: 7px 6px;
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border);
            text-align: left;
        }

        .report-table thead th:last-child {
            border-right: none;
        }

        .report-table tbody td {
            padding: 6px;
            vertical-align: top;
            border-bottom: 1px solid #e9f0ec;
            border-right: 1px solid #e9f0ec;
        }

        .report-table tbody td:last-child {
            border-right: none;
        }

        .report-table tbody tr:nth-child(even) {
            background: var(--table-stripe);
        }

        .report-table tbody tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }

        .truncate {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 7.5px;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-analise {
            color: var(--info);
            background: #e9f4fb;
            border-color: #c9dfef;
        }

        .status-negociacao {
            color: var(--warning);
            background: #fff6e9;
            border-color: #f2ddb8;
        }

        .status-minuta {
            color: #8452be;
            background: #f3ebff;
            border-color: #dcc8fb;
        }

        .status-opcao {
            color: var(--success);
            background: #e9f8ef;
            border-color: #c8e7d3;
        }

        .status-descartado {
            color: var(--danger);
            background: #fdeeee;
            border-color: #f0caca;
        }

        .status-standby {
            color: #5b6a95;
            background: #edf1fb;
            border-color: #d3dbef;
        }

        .status-default {
            color: var(--neutral);
            background: #f3f6f4;
            border-color: #dce5e0;
        }

        .money {
            color: var(--success);
            font-weight: 700;
        }

        .units {
            font-weight: 700;
            color: #255d3f;
        }

        .empty-row {
            text-align: center;
            color: var(--muted);
            padding: 16px 8px;
            font-size: 9px;
        }

        .footer {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--muted);
            font-size: 8px;
        }
    </style>
</head>
<body>
    @php
        $tenantName = function_exists('tenant') ? (tenant('name') ?? tenant('slug')) : null;
        $companyName = $tenantName ?: config('app.name', 'SIG');
        $workflowStatuses = \App\Services\Tenant\LandWorkflowService::statuses();
        $statusLabel = static function (?string $statusCode) use ($workflowStatuses): string {
            return $workflowStatuses[$statusCode]['label'] ?? 'Sem status';
        };
        $isContratoAssinado = static function (?string $statusCode): bool {
            return $statusCode === 'contrato_assinado';
        };
        $terrenosComContratoAssinado = $terrenos->filter(
            fn ($terreno) => $isContratoAssinado($terreno->workflow_status_code)
        );
        $totalContratosAssinados = (int) $terrenosComContratoAssinado->count();
        $totalUnidadesContratoAssinado = (int) $terrenosComContratoAssinado->sum(
            fn ($terreno) => (int) ($terreno->total_unidades ?? 0)
        );
        $totalVgvContratoAssinado = (float) $terrenosComContratoAssinado->sum(
            fn ($terreno) => (float) ($terreno->valor ?? 0)
        );
        $hasFilters = filled($filtros['nome'] ?? null)
            || filled($filtros['dataInicio'] ?? null)
            || filled($filtros['dataFim'] ?? null);
    @endphp

    <div class="report">
        <div class="header">
            <div class="header-top">
                <div class="title">
                    <h1>Relatório de Terrenos</h1>
                    <p>{{ $companyName }} · Gestão de Prospecção</p>
                </div>
                <div class="meta">
                    <span>Gerado em: {{ $dataGeracao }}</span>
                    <span>Total de registros: {{ $totalTerrenos }}</span>
                    <span>Ambiente: {{ app()->environment() }}</span>
                </div>
            </div>
            <div class="header-bottom">
                <span><strong>Escopo:</strong> Carteira de terrenos cadastrados</span>
                <span><strong>Formato:</strong> A4 paisagem</span>
            </div>
        </div>

        <div class="summary">
            <div class="kpi">
                <div class="kpi-label">Terrenos</div>
                <div class="kpi-value">{{ number_format($totalTerrenos, 0, ',', '.') }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Contratos Assinados</div>
                <div class="kpi-value">{{ number_format($totalContratosAssinados, 0, ',', '.') }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Unidades (Contrato Assinado)</div>
                <div class="kpi-value">{{ number_format($totalUnidadesContratoAssinado, 0, ',', '.') }}</div>
            </div>
            <div class="kpi">
                <div class="kpi-label">VGV (Contrato Assinado)</div>
                <div class="kpi-value primary">R$ {{ number_format($totalVgvContratoAssinado, 2, ',', '.') }}</div>
            </div>
        </div>

        <div class="filters-box">
            <div class="filters-title">Filtros aplicados</div>
            <div class="filters-content">
                @if($hasFilters)
                    @if(filled($filtros['nome'] ?? null))
                        <span class="filter-chip">Nome: {{ $filtros['nome'] }}</span>
                    @endif
                    @if(filled($filtros['dataInicio'] ?? null))
                        <span class="filter-chip">Data início: {{ $filtros['dataInicio'] }}</span>
                    @endif
                    @if(filled($filtros['dataFim'] ?? null))
                        <span class="filter-chip">Data fim: {{ $filtros['dataFim'] }}</span>
                    @endif
                @else
                    <span class="filters-empty">Nenhum filtro aplicado. Relatório com base completa.</span>
                @endif
            </div>
        </div>

        <div class="table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="text-center nowrap">ID</th>
                        <th>Nome</th>
                        <th>Cidade</th>
                        <th class="text-center nowrap">UF</th>
                        <th class="text-right nowrap">Área m²</th>
                        <th class="text-center nowrap">Unid.</th>
                        <th class="text-right nowrap">VGV</th>
                        <th>Responsável</th>
                        <th class="text-center nowrap">Status</th>
                        <th class="text-center nowrap">Cadastro</th>
                        <th>Regional</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($terrenos as $terreno)
                        @php
                            $statusNome = $statusLabel($terreno->workflow_status_code);
                            $statusSlug = \Illuminate\Support\Str::slug($statusNome);
                            $statusClass = match($statusSlug) {
                                'analise' => 'status-analise',
                                'negociacao' => 'status-negociacao',
                                'minuta' => 'status-minuta',
                                'opcao' => 'status-opcao',
                                'descartado' => 'status-descartado',
                                'standby' => 'status-standby',
                                default => 'status-default',
                            };
                        @endphp
                        <tr>
                            <td class="text-center nowrap">{{ $terreno->id }}</td>
                            <td><span class="truncate">{{ \Illuminate\Support\Str::limit($terreno->nome, 42) }}</span></td>
                            <td><span class="truncate">{{ $terreno->cidade?->city ?? $terreno->cidade_code ?? '—' }}</span></td>
                            <td class="text-center nowrap">{{ $terreno->estado ?? '—' }}</td>
                            <td class="text-right nowrap">
                                {{ $terreno->area_calculada ? number_format($terreno->area_calculada, 2, ',', '.') : '—' }}
                            </td>
                            <td class="text-center nowrap">
                                @if($terreno->total_unidades)
                                    <span class="units">{{ number_format($terreno->total_unidades, 0, ',', '.') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right nowrap money">
                                {{ $terreno->valor ? 'R$ ' . number_format($terreno->valor, 2, ',', '.') : '—' }}
                            </td>
                            <td><span class="truncate">{{ $terreno->responsavel?->name ?? '—' }}</span></td>
                            <td class="text-center nowrap">
                                <span class="status-badge {{ $statusClass }}">{{ $statusNome }}</span>
                            </td>
                            <td class="text-center nowrap">{{ $terreno->created_at ? $terreno->created_at->format('d/m/Y') : '—' }}</td>
                            <td><span class="truncate">{{ preg_replace('/^Regional\s+/i', '', $terreno->regional?->nome ?? '') ?: '—' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-row">Nenhum terreno encontrado para os filtros selecionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            <span>Documento gerado automaticamente pelo sistema.</span>
            <span>{{ $companyName }} · Relatório operacional de prospecção</span>
        </div>
    </div>
</body>
</html>
