<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 10mm 12mm;
        }

        :root {
            --primary: #2e6bff;
            --primary-strong: #1a4db5;
            --text: #0b1e39;
            --muted: #54627a;
            --border: #e5eaf3;
            --table-head: #f4f6fb;
            --table-stripe: #f8fafd;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "DejaVu Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: var(--text);
            background: white;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            background: linear-gradient(90deg, var(--primary-strong), var(--primary));
            color: white;
            padding: 14px 16px;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .title p { font-size: 10px; opacity: 0.92; }

        .meta {
            text-align: right;
            font-size: 10px;
            opacity: 0.95;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px 14px;
            background: #f8fafd;
            border-top: 1px solid var(--border);
        }

        .chip {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: white;
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 9px;
        }

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary-strong);
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid var(--primary);
        }

        .section-meta {
            color: var(--muted);
            font-size: 9px;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        thead th {
            background: var(--table-head);
            text-align: left;
            padding: 7px 8px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 9px;
        }

        tbody tr:nth-child(even) { background: var(--table-stripe); }

        .num { text-align: right; font-variant-numeric: tabular-nums; }

        .footer {
            margin-top: 10px;
            color: var(--muted);
            font-size: 9px;
            display: flex;
            justify-content: space-between;
        }

        .empty {
            padding: 18px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed var(--border);
            border-radius: 8px;
        }

        .chart {
            margin: 8px 0 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            background: #fbfcfe;
        }
        .chart-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--primary-strong);
            margin-bottom: 8px;
        }
        .bar-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        .bar-label {
            width: 120px;
            font-size: 8px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bar-track {
            flex: 1;
            height: 10px;
            background: #eef2f8;
            border-radius: 999px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-strong), var(--primary));
            border-radius: 999px;
        }
        .bar-value {
            width: 70px;
            text-align: right;
            font-size: 8px;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
@php
    /** @var list<array<string, mixed>> $sections */
    $sections = $sections ?? [];
    $sectionCount = count($sections);
    $showBars = $showBars ?? true;
@endphp
<div class="header">
    <div class="header-top">
        <div class="title">
            <h1>{{ $title }}</h1>
            <p>Construtor de relatórios · SIGAPP</p>
        </div>
        <div class="meta">
            <div>Gerado em {{ $generatedAt }}</div>
            <div>As of {{ $asOf }}</div>
            @if (! empty($requestedBy))
                <div>Solicitante: {{ $requestedBy }}</div>
            @endif
        </div>
    </div>
    <div class="chips">
        <span class="chip">Capítulos: {{ $sectionCount }}</span>
        @if (! empty($filtersLabel))
            <span class="chip">Filtros: {{ $filtersLabel }}</span>
        @endif
        @foreach ($sections as $section)
            <span class="chip">{{ $section['dataset_label'] ?? $section['dataset'] ?? '—' }} ({{ $section['mode'] ?? 'aggregate' }})</span>
        @endforeach
    </div>
</div>

@if ($sectionCount === 0)
    <div class="empty">Nenhuma seção disponível para este relatório.</div>
@else
    @foreach ($sections as $section)
        @php
            $mode = $section['mode'] ?? 'aggregate';
            $rows = $section['rows'] ?? [];
            $includeSum = in_array('sum_valor', $section['metrics'] ?? [], true);
            $totalCount = $mode === 'aggregate'
                ? collect($rows)->sum(fn ($r) => (int) ($r['count'] ?? 0))
                : count($rows);
        @endphp
        <div class="section">
            <div class="section-title">{{ $section['dataset_label'] ?? $section['dataset'] ?? 'Seção' }}</div>
            <div class="section-meta">
                Modo: {{ $mode }}
                @if ($mode === 'aggregate')
                    · Dimensão: {{ $section['dimension'] ?? '—' }}
                    · Métricas: {{ implode(', ', $section['metrics'] ?? []) }}
                @else
                    · Colunas: {{ implode(', ', $section['column_labels'] ?? $section['columns'] ?? []) }}
                @endif
                · Linhas: {{ count($rows) }}
                @if ($mode === 'aggregate')
                    · Total qtd: {{ $totalCount }}
                @endif
            </div>

            @if ($showBars && $mode === 'aggregate' && !empty($section['chart_bars']))
                <div class="chart">
                    <div class="chart-title">Distribuição (barras server-side)</div>
                    @foreach ($section['chart_bars'] as $bar)
                        <div class="bar-row">
                            <div class="bar-label">{{ $bar['label'] }}</div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ max(2, min(100, $bar['percent'])) }}%;"></div>
                            </div>
                            <div class="bar-value">{{ number_format((float) $bar['value'], 0, ',', '.') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (count($rows) === 0)
                <div class="empty">Nenhum dado encontrado nesta seção.</div>
            @elseif ($mode === 'detail')
                @php $columns = $section['columns'] ?? []; $labels = $section['column_labels'] ?? $columns; @endphp
                <table>
                    <thead>
                    <tr>
                        @foreach ($labels as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                @php $value = is_array($row) ? ($row[$column] ?? null) : null; @endphp
                                <td @if (is_numeric($value)) class="num" @endif>
                                    {{ $value === null || $value === '' ? '—' : $value }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                @php
                    $metrics = $section['metrics'] ?? [];
                    $extra = array_values(array_filter([
                        in_array('sum_valor', $metrics, true) ? 'sum_valor' : null,
                        in_array('sum_custo_planejado', $metrics, true) ? 'sum_custo_planejado' : null,
                        in_array('sum_custo_realizado', $metrics, true) ? 'sum_custo_realizado' : null,
                        in_array('avg_critical_days', $metrics, true) ? 'avg_critical_days' : null,
                        in_array('sum_critical_days', $metrics, true) ? 'sum_critical_days' : null,
                    ]));
                    $extraLabels = [
                        'sum_valor' => 'Soma valor',
                        'sum_custo_planejado' => 'Custo planejado',
                        'sum_custo_realizado' => 'Custo realizado',
                        'avg_critical_days' => 'Média dias críticos',
                        'sum_critical_days' => 'Soma dias críticos',
                    ];
                @endphp
                <table>
                    <thead>
                    <tr>
                        <th>Rótulo</th>
                        <th class="num">Quantidade</th>
                        @foreach ($extra as $metric)
                            <th class="num">{{ $extraLabels[$metric] ?? $metric }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ ($row['label'] ?? '') !== '' ? $row['label'] : 'Não informado' }}</td>
                            <td class="num">{{ number_format((int) ($row['count'] ?? 0), 0, ',', '.') }}</td>
                            @foreach ($extra as $metric)
                                <td class="num">
                                    {{ !isset($row[$metric]) || $row[$metric] === null
                                        ? '—'
                                        : number_format((float) $row[$metric], 2, ',', '.') }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
@endif

<div class="footer">
    <span>Fonte: catálogo fechado do report builder · snapshot não-editável</span>
    <span>{{ $sectionCount }} capítulo(s)</span>
</div>
</body>
</html>
