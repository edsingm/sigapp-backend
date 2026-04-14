<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudo de Viabilidade #{{ $viabilidade->id }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
        }

        .page {
            padding: 32px 40px;
            min-height: 100%;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 3px solid #6366f1;
        }
        
        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 18px;
        }
        
        .header-info h1 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .header-info p {
            font-size: 11px;
            color: #64748b;
        }
        
        .header-meta {
            text-align: right;
        }
        
        .header-meta .doc-number {
            font-size: 18px;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 4px;
        }
        
        .header-meta .doc-date {
            font-size: 10px;
            color: #64748b;
        }
        
        .header-meta .doc-status {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 12px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Area Info Banner */
        .area-banner {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .area-banner-left h2 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .area-banner-left p {
            font-size: 10px;
            color: #64748b;
        }
        
        .area-banner-right {
            display: flex;
            gap: 24px;
        }
        
        .area-stat {
            text-align: center;
        }
        
        .area-stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #6366f1;
        }
        
        .area-stat-label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 16px;
            background: linear-gradient(180deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 2px;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .kpi-card {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            padding: 14px 16px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
        }
        
        .kpi-card.highlight {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
        }
        
        .kpi-card.highlight .kpi-label,
        .kpi-card.highlight .kpi-value {
            color: white;
        }
        
        .kpi-card.highlight::before {
            display: none;
        }
        
        .kpi-label {
            font-size: 8px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .kpi-value {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
        }
        
        .kpi-value.positive { color: #059669; }
        .kpi-value.negative { color: #dc2626; }
        
        .kpi-subtitle {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Parameters Grid */
        .params-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .params-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .params-table tr {
            border-bottom: 1px solid #f1f5f9;
        }
        
        .params-table tr:last-child {
            border-bottom: none;
        }
        
        .params-table td {
            padding: 8px 0;
            font-size: 9px;
        }
        
        .params-table td:first-child {
            color: #64748b;
        }
        
        .params-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
        }
        
        /* DRE Table */
        .dre-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 8.5px;
        }
        
        .dre-table thead th {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            text-align: left;
            padding: 10px 12px;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dre-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .dre-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .dre-table tbody td {
            padding: 7px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }
        
        .dre-table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .dre-table .text-right {
            text-align: right;
        }
        
        .dre-table .section-header {
            background: linear-gradient(90deg, #f1f5f9 0%, #fff 100%);
            font-weight: 700;
            color: #1e293b;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .dre-table .section-header td {
            padding: 10px 12px;
            border-top: 2px solid #e2e8f0;
        }
        
        .dre-table .subtotal {
            background: #f8fafc;
            font-weight: 600;
            color: #1e293b;
        }
        
        .dre-table .subtotal td {
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .dre-table .final-result {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            font-weight: 700;
        }
        
        .dre-table .final-result td {
            padding: 12px;
            font-size: 11px;
            border: none;
        }
        
        .dre-table .final-result td:last-child,
        .dre-table .final-result td:nth-child(2) {
            font-size: 12px;
        }
        
        /* Chart Section */
        .chart-section {
            margin-top: 24px;
            margin-bottom: 24px;
        }
        
        .chart-container {
            background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }
        
        .chart-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-title::before {
            content: '📊';
        }
        
        .chart-svg {
            width: 100%;
            height: 180px;
        }
        
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 12px;
            font-size: 9px;
        }
        
        .chart-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .chart-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        /* Footer */
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-left {
            font-size: 8px;
            color: #94a3b8;
        }
        
        .footer-left strong {
            color: #64748b;
        }
        
        .footer-right {
            font-size: 7px;
            color: #94a3b8;
            text-align: right;
        }
        
        .footer-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 4px;
            font-size: 7px;
            color: #64748b;
        }

        /* Page Break */
        .page-break {
            page-break-after: always;
        }
        
        /* Utilities */
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .text-positive { color: #059669; }
        .text-negative { color: #dc2626; }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-brand">
                <div class="header-logo">LRG</div>
                <div class="header-info">
                    <h1>Estudo de Viabilidade Econômica</h1>
                    <p>Análise Financeira de Empreendimento Imobiliário</p>
                </div>
            </div>
            <div class="header-meta">
                <div class="doc-number">#{{ $viabilidade->id }}</div>
                <div class="doc-date">Gerado em {{ $dataGeracao }}</div>
                <div class="doc-status">{{ $viabilidade->status ?? 'Análise' }}</div>
            </div>
        </div>

        <!-- Area Banner -->
        <div class="area-banner">
            <div class="area-banner-left">
                <h2>{{ $viabilidade->terreno?->nome ?? 'Área não especificada' }}</h2>
                <p>{{ $viabilidade->terreno?->cidade?->city ?? '' }}{{ $viabilidade->terreno?->estado ? ' - ' . $viabilidade->terreno?->estado : '' }}</p>
            </div>
            <div class="area-banner-right">
                <div class="area-stat">
                    <div class="area-stat-value">{{ number_format($viabilidade->terreno?->area_total ?? 0, 0, ',', '.') }}</div>
                    <div class="area-stat-label">M² Total</div>
                </div>
                <div class="area-stat">
                    <div class="area-stat-value">{{ $viabilidade->prazo_obra ?? 0 }}</div>
                    <div class="area-stat-label">Meses Obra</div>
                </div>
                <div class="area-stat">
                    <div class="area-stat-value">{{ number_format($viabilidade->parceria_vgv ?? 0, 1, ',', '.') }}%</div>
                    <div class="area-stat-label">Parceria</div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="section-title">Indicadores-Chave de Performance</div>
        <div class="kpi-grid">
            <div class="kpi-card highlight">
                <div class="kpi-label">VGV Total (Parceria)</div>
                <div class="kpi-value">R$ {{ number_format($dre['totais']['receita'] ?? 0, 0, ',', '.') }}</div>
                <div class="kpi-subtitle" style="color: rgba(255,255,255,0.7);">Valor Geral de Vendas</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Lucro Líquido</div>
                <div class="kpi-value {{ ($dre['totais']['lucro'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    R$ {{ number_format($dre['totais']['lucro'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="kpi-subtitle">Resultado Final</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Margem Líquida</div>
                <div class="kpi-value {{ ($dre['indicadores']['margem_liquida'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($dre['indicadores']['margem_liquida'] ?? 0, 1, ',', '.') }}%
                </div>
                <div class="kpi-subtitle">Lucro / Receita</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">ROI</div>
                <div class="kpi-value {{ ($dre['indicadores']['roi_percentual'] ?? 0) >= 0 ? 'positive' : 'negative' }}">
                    {{ number_format($dre['indicadores']['roi_percentual'] ?? 0, 1, ',', '.') }}%
                </div>
                <div class="kpi-subtitle">Retorno s/ Investimento</div>
            </div>
        </div>

        <!-- Parameters Grid -->
        <div class="params-grid">
            <div>
                <div class="section-title">Premissas Comerciais</div>
                <table class="params-table">
                    <tr>
                        <td>Prazo de Obra</td>
                        <td>{{ $viabilidade->prazo_obra ?? 0 }} meses</td>
                    </tr>
                    <tr>
                        <td>Parceria VGV</td>
                        <td>{{ number_format($viabilidade->parceria_vgv ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>Comissão de Vendas</td>
                        <td>{{ number_format($viabilidade->comissao ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>Marketing</td>
                        <td>{{ number_format($viabilidade->marketing ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                </table>
            </div>
            <div>
                <div class="section-title">Carga Tributária</div>
                <table class="params-table">
                    <tr>
                        <td>PIS/COFINS</td>
                        <td>{{ number_format($viabilidade->pis_cofins ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>ISS</td>
                        <td>{{ number_format($viabilidade->iss ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>Outros Impostos</td>
                        <td>{{ number_format($viabilidade->outros_impostos ?? 0, 2, ',', '.') }}%</td>
                    </tr>
                    <tr style="font-weight: 700; color: #6366f1;">
                        <td>Total Tributos</td>
                        <td>{{ number_format(($viabilidade->pis_cofins ?? 0) + ($viabilidade->iss ?? 0) + ($viabilidade->outros_impostos ?? 0), 2, ',', '.') }}%</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- DRE Table -->
        <div class="section-title">Demonstrativo de Resultado do Exercício (DRE)</div>
        <table class="dre-table">
            <thead>
                <tr>
                    <th style="width: 55%;">Descrição</th>
                    <th class="text-right" style="width: 25%;">Valor (R$)</th>
                    <th class="text-right" style="width: 20%;">% Receita</th>
                </tr>
            </thead>
            <tbody>
                @if(isset($dre['dre_itens']))
                    @php
                        $itens = $dre['dre_itens'];
                        $receitaBase = ($itens['receita_total_vendas'] ?? 0) ?: 1;
                        
                        $format = function($val) {
                            return 'R$ ' . number_format($val ?? 0, 2, ',', '.');
                        };
                        $formatPerc = function($val, $base) {
                            return number_format((($val ?? 0) / $base) * 100, 2, ',', '.') . '%';
                        };
                    @endphp

                    <!-- I. RECEITA OPERACIONAL BRUTA -->
                    <tr class="section-header">
                        <td colspan="3">I. Receita Operacional Bruta</td>
                    </tr>
                    <tr>
                        <td>1. Receita Total de Vendas (VGV)</td>
                        <td class="text-right">{{ $format($itens['receita_total_vendas'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['receita_total_vendas'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>2. Juros e Correções Monetárias</td>
                        <td class="text-right">{{ $format($itens['juros_correcoes'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['juros_correcoes'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Receita Bruta Acumulada</td>
                        <td class="text-right">{{ $format($itens['receita_bruta'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['receita_bruta'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- II. DEDUÇÕES E IMPOSTOS -->
                    <tr class="section-header">
                        <td colspan="3">II. Deduções e Impostos sobre Vendas</td>
                    </tr>
                    <tr>
                        <td>3. Tributos sobre Receita (PIS/COFINS/Outros)</td>
                        <td class="text-right">({{ $format($itens['pis_cofins_outros'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['pis_cofins_outros'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>4. ISSQN (Serviços)</td>
                        <td class="text-right">({{ $format($itens['iss'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['iss'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>5. Outras Deduções Operacionais</td>
                        <td class="text-right">({{ $format($itens['outras_deducoes'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['outras_deducoes'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Receita Líquida Operacional</td>
                        <td class="text-right">{{ $format($itens['receita_liquida'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['receita_liquida'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- III. CUSTOS DIRETOS -->
                    <tr class="section-header">
                        <td colspan="3">III. Custos Diretos do Empreendimento</td>
                    </tr>
                    <tr>
                        <td>6. Custo de Aquisição do Terreno</td>
                        <td class="text-right">({{ $format($itens['custo_terreno'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['custo_terreno'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>7. Encargos e Correção de Carteira</td>
                        <td class="text-right">({{ $format($itens['juros_correcao_carteira'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['juros_correcao_carteira'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>8. Comissões de Vendas</td>
                        <td class="text-right">({{ $format($itens['comissao'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['comissao'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>9. Taxas de Incorporação e Gestão</td>
                        <td class="text-right">({{ $format($itens['incorporacao'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['incorporacao'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>10. Obras de Edificação (Casas)</td>
                        <td class="text-right">({{ $format($itens['infra_casas'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['infra_casas'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>11. Infraestrutura Urbana (Lotes)</td>
                        <td class="text-right">({{ $format($itens['infra_lotes'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['infra_lotes'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>12. Áreas Comuns e Lazer</td>
                        <td class="text-right">({{ $format($itens['area_comum'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['area_comum'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>13. Contrapartidas e Mitigações</td>
                        <td class="text-right">({{ $format($itens['contrapartidas'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['contrapartidas'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>14. Manutenção de Canteiro</td>
                        <td class="text-right">({{ $format($itens['canteiro_mensal'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['canteiro_mensal'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>15. Prêmios de Seguros</td>
                        <td class="text-right">({{ $format($itens['seguros'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['seguros'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>16. Assistência Técnica e Pós-Obra</td>
                        <td class="text-right">({{ $format($itens['assistencia_tecnica'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['assistencia_tecnica'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Lucro Bruto Operacional</td>
                        <td class="text-right">{{ $format($itens['lucro_bruto'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['lucro_bruto'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- IV. DESPESAS OPERACIONAIS -->
                    <tr class="section-header">
                        <td colspan="3">IV. Despesas Operacionais e Administrativas</td>
                    </tr>
                    <tr>
                        <td>17. Despesas Comerciais e Administrativas</td>
                        <td class="text-right">({{ $format($itens['despesas_comerciais'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['despesas_comerciais'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>18. Marketing e Publicidade</td>
                        <td class="text-right">({{ $format($itens['marketing'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['marketing'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>19. Taxas de ITBI e IPTU</td>
                        <td class="text-right">({{ $format($itens['itbi_iptu'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['itbi_iptu'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>20. Despesas de Cartório e Registro</td>
                        <td class="text-right">({{ $format($itens['registro'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['registro'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>21. Taxas de Medição e Contratação</td>
                        <td class="text-right">({{ $format($itens['tx_medicao_contratacao'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['tx_medicao_contratacao'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>22. Custos de Contratos (Caixa)</td>
                        <td class="text-right">({{ $format($itens['contratos_caixa'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['contratos_caixa'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>23. Taxas de Produtos e Serviços (Caixa)</td>
                        <td class="text-right">({{ $format($itens['produtos_caixa'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['produtos_caixa'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>EBITDA (LAJIDA)</td>
                        <td class="text-right">{{ $format($itens['ebitda'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['ebitda'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- V. RESULTADO FINANCEIRO -->
                    <tr class="section-header">
                        <td colspan="3">V. Resultado Financeiro</td>
                    </tr>
                    <tr>
                        <td>24. Resultado Financeiro Líquido</td>
                        <td class="text-right">({{ $format($itens['outras_despesas_financeiras'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['outras_despesas_financeiras'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr>
                        <td>25. Encargos Bancários e Antecipações PJ</td>
                        <td class="text-right">({{ $format($itens['despesas_onerosas_bancos'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['despesas_onerosas_bancos'] ?? 0, $receitaBase) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>EBIT (LAJIR)</td>
                        <td class="text-right">{{ $format($itens['ebit'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['ebit'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- VI. IMPOSTOS SOBRE LUCRO -->
                    <tr class="section-header">
                        <td colspan="3">VI. Impostos sobre o Lucro</td>
                    </tr>
                    <tr>
                        <td>26. Provisão de IRPJ e CSLL</td>
                        <td class="text-right">({{ $format($itens['irpj_csll'] ?? 0) }})</td>
                        <td class="text-right">{{ $formatPerc($itens['irpj_csll'] ?? 0, $receitaBase) }}</td>
                    </tr>

                    <!-- RESULTADO FINAL -->
                    <tr class="final-result">
                        <td>RESULTADO LÍQUIDO DO EXERCÍCIO</td>
                        <td class="text-right">{{ $format($itens['lucro_liquido_projeto'] ?? 0) }}</td>
                        <td class="text-right">{{ $formatPerc($itens['lucro_liquido_projeto'] ?? 0, $receitaBase) }}</td>
                    </tr>

                @else
                    <!-- Fallback para estrutura antiga -->
                    <tr>
                        <td>Receita Operacional Bruta</td>
                        <td class="text-right">R$ {{ number_format($dre['totais']['receita'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-right">100,00%</td>
                    </tr>
                    <tr>
                        <td>(-) Impostos e Comissões</td>
                        <td class="text-right">(R$ {{ number_format($dre['totais']['impostos'] ?? 0, 2, ',', '.') }})</td>
                        <td class="text-right">{{ number_format((($dre['totais']['impostos'] ?? 0) / (($dre['totais']['receita'] ?? 0) ?: 1)) * 100, 2, ',', '.') }}%</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Receita Líquida</td>
                        <td class="text-right">R$ {{ number_format(($dre['totais']['receita'] ?? 0) - ($dre['totais']['impostos'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format(((($dre['totais']['receita'] ?? 0) - ($dre['totais']['impostos'] ?? 0)) / (($dre['totais']['receita'] ?? 0) ?: 1)) * 100, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>(-) Custos Diretos</td>
                        <td class="text-right">(R$ {{ number_format($dre['totais']['custo_direto'] ?? 0, 2, ',', '.') }})</td>
                        <td class="text-right">{{ number_format((($dre['totais']['custo_direto'] ?? 0) / (($dre['totais']['receita'] ?? 0) ?: 1)) * 100, 2, ',', '.') }}%</td>
                    </tr>
                    <tr>
                        <td>(-) Custos Operacionais</td>
                        <td class="text-right">(R$ {{ number_format($dre['totais']['custos_operacionais'] ?? 0, 2, ',', '.') }})</td>
                        <td class="text-right">{{ number_format((($dre['totais']['custos_operacionais'] ?? 0) / (($dre['totais']['receita'] ?? 0) ?: 1)) * 100, 2, ',', '.') }}%</td>
                    </tr>
                    <tr class="final-result">
                        <td>LUCRO LÍQUIDO DO PROJETO</td>
                        <td class="text-right">R$ {{ number_format($dre['totais']['lucro'] ?? 0, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((($dre['totais']['lucro'] ?? 0) / (($dre['totais']['receita'] ?? 0) ?: 1)) * 100, 2, ',', '.') }}%</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- Balance Evolution Chart -->
        <div class="chart-section">
            <div class="chart-container">
                <div class="chart-title">Evolução Projetada do Saldo</div>
                @php
                    $prazoObra = $viabilidade->prazo_obra ?? 24;
                    $receita = $dre['totais']['receita'] ?? 0;
                    $lucro = $dre['totais']['lucro'] ?? 0;
                    $custoTotal = $receita - $lucro;
                    
                    // Simulate balance evolution (simplified S-curve for construction projects)
                    $pontos = [];
                    $saldoMin = 0;
                    $saldoMax = $receita;
                    
                    for ($i = 0; $i <= 12; $i++) {
                        $mes = round(($i / 12) * $prazoObra);
                        $progresso = $i / 12;
                        
                        // Revenues follow S-curve (slower start, faster middle, slower end)
                        $receitaAcum = $receita * (3 * pow($progresso, 2) - 2 * pow($progresso, 3));
                        
                        // Costs are more linear but frontloaded
                        $custoAcum = $custoTotal * (0.3 * $progresso + 0.7 * pow($progresso, 0.7));
                        
                        $saldo = $receitaAcum - $custoAcum;
                        $pontos[] = [
                            'mes' => $mes,
                            'receita' => $receitaAcum,
                            'custo' => $custoAcum,
                            'saldo' => $saldo
                        ];
                        
                        $saldoMin = min($saldoMin, $saldo, -$custoAcum * 0.3);
                        $saldoMax = max($saldoMax, $receitaAcum);
                    }
                    
                    $chartWidth = 680;
                    $chartHeight = 140;
                    $paddingLeft = 60;
                    $paddingRight = 20;
                    $paddingTop = 10;
                    $paddingBottom = 30;
                    
                    $graphWidth = $chartWidth - $paddingLeft - $paddingRight;
                    $graphHeight = $chartHeight - $paddingTop - $paddingBottom;
                    
                    $range = $saldoMax - $saldoMin;
                    if ($range == 0) $range = 1;
                    
                    $scaleY = function($val) use ($saldoMax, $range, $graphHeight, $paddingTop) {
                        return $paddingTop + (($saldoMax - $val) / $range) * $graphHeight;
                    };
                    
                    $scaleX = function($idx) use ($graphWidth, $paddingLeft) {
                        return $paddingLeft + ($idx / 12) * $graphWidth;
                    };
                    
                    // Build path strings
                    $receitaPath = '';
                    $custoPath = '';
                    $saldoPath = '';
                    
                    foreach ($pontos as $idx => $p) {
                        $x = $scaleX($idx);
                        $yReceita = $scaleY($p['receita']);
                        $yCusto = $scaleY($p['custo']);
                        $ySaldo = $scaleY($p['saldo']);
                        
                        $cmd = $idx === 0 ? 'M' : 'L';
                        $receitaPath .= "$cmd$x,$yReceita ";
                        $custoPath .= "$cmd$x,$yCusto ";
                        $saldoPath .= "$cmd$x,$ySaldo ";
                    }
                    
                    // Area fill for saldo (positive area)
                    $saldoAreaPath = $saldoPath;
                    $saldoAreaPath .= "L" . $scaleX(12) . "," . $scaleY(0) . " ";
                    $saldoAreaPath .= "L" . $scaleX(0) . "," . $scaleY(0) . " Z";
                    
                    $zeroY = $scaleY(0);
                @endphp
                
                <svg class="chart-svg" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" preserveAspectRatio="xMidYMid meet">
                    <!-- Grid lines -->
                    <defs>
                        <linearGradient id="saldoGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#10b981;stop-opacity:0.3" />
                            <stop offset="100%" style="stop-color:#10b981;stop-opacity:0.05" />
                        </linearGradient>
                    </defs>
                    
                    <!-- Horizontal grid -->
                    @for ($i = 0; $i <= 4; $i++)
                        @php
                            $y = $paddingTop + ($i / 4) * $graphHeight;
                            $val = $saldoMax - ($i / 4) * $range;
                        @endphp
                        <line x1="{{ $paddingLeft }}" y1="{{ $y }}" x2="{{ $chartWidth - $paddingRight }}" y2="{{ $y }}" stroke="#e2e8f0" stroke-width="1"/>
                        <text x="{{ $paddingLeft - 8 }}" y="{{ $y + 3 }}" text-anchor="end" font-size="7" fill="#94a3b8">
                            {{ number_format($val / 1000000, 1) }}M
                        </text>
                    @endfor
                    
                    <!-- Zero line -->
                    <line x1="{{ $paddingLeft }}" y1="{{ $zeroY }}" x2="{{ $chartWidth - $paddingRight }}" y2="{{ $zeroY }}" stroke="#64748b" stroke-width="1" stroke-dasharray="4,2"/>
                    
                    <!-- X-axis labels -->
                    @foreach ([0, 3, 6, 9, 12] as $idx)
                        @php $mes = round(($idx / 12) * $prazoObra); @endphp
                        <text x="{{ $scaleX($idx) }}" y="{{ $chartHeight - 8 }}" text-anchor="middle" font-size="7" fill="#94a3b8">
                            Mês {{ $mes }}
                        </text>
                    @endforeach
                    
                    <!-- Saldo area fill -->
                    <path d="{{ $saldoAreaPath }}" fill="url(#saldoGradient)" />
                    
                    <!-- Lines -->
                    <path d="{{ $receitaPath }}" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="{{ $custoPath }}" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="6,3"/>
                    <path d="{{ $saldoPath }}" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    
                    <!-- End points -->
                    <circle cx="{{ $scaleX(12) }}" cy="{{ $scaleY(end($pontos)['receita']) }}" r="4" fill="#6366f1"/>
                    <circle cx="{{ $scaleX(12) }}" cy="{{ $scaleY(end($pontos)['custo']) }}" r="4" fill="#f43f5e"/>
                    <circle cx="{{ $scaleX(12) }}" cy="{{ $scaleY(end($pontos)['saldo']) }}" r="4" fill="#10b981"/>
                </svg>
                
                <div class="chart-legend">
                    <div class="chart-legend-item">
                        <div class="chart-legend-color" style="background: #6366f1;"></div>
                        <span>Receita Acumulada</span>
                    </div>
                    <div class="chart-legend-item">
                        <div class="chart-legend-color" style="background: #f43f5e;"></div>
                        <span>Custos Acumulados</span>
                    </div>
                    <div class="chart-legend-item">
                        <div class="chart-legend-color" style="background: #10b981;"></div>
                        <span>Saldo do Projeto</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-left">
                <strong>LRG Construtora</strong> — Sistema Interno de Gestão (SIG)<br>
                Documento gerado eletronicamente. Válido sem assinatura.
            </div>
            <div class="footer-right">
                <div class="footer-badge">
                    <span>📋</span> Viabilidade #{{ $viabilidade->id }}
                </div>
                <div style="margin-top: 4px;">
                    Este documento é para fins de análise interna e pode sofrer<br>alterações conforme mudanças nas premissas de mercado.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
