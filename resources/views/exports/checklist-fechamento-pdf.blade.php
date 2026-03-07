<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist de Fechamento - {{ $terreno->nome }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: a4;
            margin: 10mm;
        }
        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-size: 11px;
            color: #1f2937;
        }
        .header-bg { background-color: #1e3a8a; } /* Dark Blue like Excel header often is */
        .section-header {
            background-color: #f3f4f6;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 8px;
            border-bottom: 2px solid #e5e7eb;
            font-size: 12px;
            margin-top: 10px;
        }
        .field-label {
            font-weight: 600;
            color: #4b5563;
        }
        .field-value {
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 4px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        .row-striped:nth-child(even) {
            background-color: #f9fafb;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
    </style>
</head>
<body class="bg-white">
    @php
    function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if(strlen($phone) == 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        }
        if(strlen($phone) == 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }
        return $phone;
    }
    @endphp
    <!-- Header -->
    <div class="header-bg text-white p-4 mb-4 rounded-sm">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold uppercase">Checklist de Fechamento</h1>
                <p class="text-sm opacity-80">Análise de Áreas - Novos Negócios</p>
            </div>
            <div class="text-right text-xs">
                <p>Data: {{ now()->format('d/m/Y') }}</p>
                <p>Responsável: {{ auth()->user()->name ?? 'Sistema' }}</p>
            </div>
        </div>
    </div>

    <!-- 1. Dados da Área -->
    <div class="section-header">1. Dados da Área</div>
    <table>
        <tr>
            <td width="20%"><span class="field-label">Nome da Área:</span></td>
            <td width="30%"><span class="field-value font-bold">{{ $terreno->nome }}</span></td>
            <td width="20%"><span class="field-label">ID / Código:</span></td>
            <td width="30%"><span class="field-value">#{{ $terreno->id }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Endereço:</span></td>
            <td><span class="field-value">{{ $terreno->endereco }}</span></td>
            <td><span class="field-label">Bairro:</span></td>
            <td><span class="field-value">{{ $terreno->bairro }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Cidade / UF:</span></td>
            <td><span class="field-value">{{ $terreno->cidade?->city ?? $terreno->cidade_code }} / {{ $terreno->estado }}</span></td>
            <td><span class="field-label">CEP:</span></td>
            <td><span class="field-value">{{ $terreno->cep ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Área Total:</span></td>
            <td><span class="field-value font-bold">{{ number_format($terreno->area_calculada, 2, ',', '.') }} m²</span></td>
            <td><span class="field-label">Área de Interesse:</span></td>
            <td><span class="field-value">{{ $extraData['area_interesse'] ?? number_format($terreno->area_calculada, 2, ',', '.') . ' m²' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Matrícula:</span></td>
            <td><span class="field-value">{{ $terreno->matricula ?? '-' }}</span></td>
            <td><span class="field-label">Zoneamento:</span></td>
            <td><span class="field-value">{{ $terreno->zoneamento ?? '-' }}</span></td>
        </tr>
         <tr>
            <td><span class="field-label">Inscrição Municipal:</span></td>
            <td colspan="3"><span class="field-value">{{ $terreno->inscricao_municipal ?? '-' }}</span></td>
        </tr>
    </table>

    <!-- 2. Proprietários e Corretores -->
    <div class="section-header">2. Contatos</div>
    <table>
        @php
            $proprietario = $terreno->proprietarios->first();
        @endphp
        <tr>
            <td width="20%"><span class="field-label">Proprietário:</span></td>
            <td width="30%"><span class="field-value">{{ $proprietario->nome ?? $extraData['proprietario_nome'] ?? '-' }}</span></td>
            <td width="20%"><span class="field-label">Contato (Tel/Email):</span></td>
            <td width="30%"><span class="field-value">{{ isset($proprietario->telefone) ? formatPhone($proprietario->telefone) : ($proprietario->email ?? $extraData['proprietario_contato'] ?? '-') }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Corretor:</span></td>
            <td><span class="field-value">{{ $terreno->corretor_externo->nome ?? $extraData['corretor_nome'] ?? '-' }}</span></td>
            <td><span class="field-label">Contato Corretor:</span></td>
            <td><span class="field-value">{{ $extraData['corretor_contato'] ?? '-' }}</span></td>
        </tr>
    </table>

    <!-- 3. Dados de Mercado -->
    <div class="section-header">3. Análise de Mercado</div>
    <table>
        <tr>
            <td width="25%"><span class="field-label">População Estimada:</span></td>
            <td width="25%"><span class="field-value">{{ $extraData['populacao'] ?? '-' }}</span></td>
            <td width="25%"><span class="field-label">Renda Per Capita:</span></td>
            <td width="25%"><span class="field-value">{{ $extraData['renda_per_capita'] ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Principal Fonte Renda:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['fonte_renda'] ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Demanda DataStore:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['demanda_datastore'] ?? '-' }} COBERTURA (NÍVEL)</span></td>
        </tr>
         <tr>
            <td><span class="field-label">Demanda Caixa:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['demanda_caixa'] ?? '-' }}</span></td>
        </tr>
    </table>

    <!-- 4. Produto e Concorrência -->
    <div class="section-header">4. Produto e Concorrência</div>
    <table>
        <tr>
            <td width="25%"><span class="field-label">Vocação / Tipologia:</span></td>
            <td width="25%"><span class="field-value">{{ $extraData['tipologia'] ?? '-' }}</span></td>
            <td width="25%"><span class="field-label">Preço Sugerido Venda:</span></td>
            <td width="25%"><span class="field-value">{{ $extraData['preco_sugerido'] ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Preço Lotes (Bairro):</span></td>
            <td><span class="field-value">{{ $extraData['preco_lotes'] ?? '-' }}</span></td>
            <td><span class="field-label">Preço Casas (Bairro):</span></td>
            <td><span class="field-value">{{ $extraData['preco_casas'] ?? '-' }}</span></td>
        </tr>
        <tr>
            <td colspan="4" class="bg-gray-50 font-bold text-xs py-2 mt-2">Concorrentes / Lançamentos (Últimos 3 anos)</td>
        </tr>
        <tr>
            <td colspan="4">
                <div class="p-2 border border-gray-100 rounded min-h-[40px]">
                     {{ $extraData['concorrentes'] ?? 'Nenhum concorrente relevante citado.' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. Infraestrutura e Aspectos Técnicos -->
    <div class="section-header">5. Infraestrutura e Aspectos Técnicos</div>
    <table>
        <tr>
            <td width="20%"><span class="field-label">Testada P/ Via:</span></td>
            <td width="30%"><span class="field-value">{{ $extraData['testada'] ?? '-' }}</span></td>
            <td width="20%"><span class="field-label">Acesso:</span></td>
            <td width="30%"><span class="field-value">{{ $extraData['acesso'] ?? '-' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Topografia/Terrapl.:</span></td>
            <td><span class="field-value">{{ $extraData['topografia'] ?? '-' }}</span></td>
            <td><span class="field-label">Rede de Água:</span></td>
            <td><span class="field-value">{{ $extraData['rede_agua'] ?? 'Não informado' }}</span></td>
        </tr>
        <tr>
            <td><span class="field-label">Rede de Esgoto:</span></td>
            <td><span class="field-value">{{ $extraData['rede_esgoto'] ?? 'Não informado' }}</span></td>
             <td><span class="field-label">Drenagem/Córrego:</span></td>
            <td><span class="field-value">{{ $extraData['drenagem'] ?? 'Não informado' }}</span></td>
        </tr>
         <tr>
            <td><span class="field-label">Plano Diretor:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['plano_diretor'] ?? '-' }}</span></td>
        </tr>
         <tr>
             <td><span class="field-label">Processo Aprovação:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['processo_aprovacao'] ?? '-' }}</span></td>
        </tr>
    </table>
    
    <!-- 6. Negociação -->
    <div class="section-header">6. Negociação</div>
    <table>
        <tr>
            <td width="20%"><span class="field-label">Preço Pedido:</span></td>
            <td width="30%"><span class="field-value font-bold text-lg">R$ {{ number_format($terreno->valor, 2, ',', '.') }}</span></td>
             <td width="20%"><span class="field-label">Valor/m²:</span></td>
            <td width="30%"><span class="field-value">R$ {{ $terreno->area_calculada > 0 ? number_format($terreno->valor / $terreno->area_calculada, 2, ',', '.') : '-' }}</span></td>
        </tr>
        <tr>
             <td><span class="field-label">Condição Pagamento:</span></td>
            <td colspan="3"><span class="field-value">{{ $extraData['condicao_pagamento'] ?? '-' }}</span></td>
        </tr>
    </table>

    <div class="mt-8 text-center text-xs text-gray-400">
        <p>Este documento é confidencial e de uso exclusivo interno.</p>
    </div>
</body>
</html>
