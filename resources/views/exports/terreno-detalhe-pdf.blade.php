<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Área - {{ $area->nome }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        @page {
            size: a4;
        }
        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin: 0;
            padding: 0;
        }
        .bg-primary { background-color: #7c3aed; }
        .text-primary { color: #7c3aed; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        #map {
            width: 100%;
            height: 350px;
            background-color: #f4f4f5;
            border-bottom: 1px solid #e4e4e7;
        }
        .avoid-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body class="bg-white text-zinc-900">
    <!-- Header -->
    <div class="bg-primary text-white p-4">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold uppercase tracking-tight">{{ $area->nome }}</h1>
                <p class="text-purple-100 mt-1">Relatório Detalhado de Prospecção</p>
            </div>
            <div class="text-right">
                <p class="text-sm opacity-80">ID da Área: #{{ $area->id }}</p>
                <p class="text-sm opacity-80">Gerado em: {{ $dataGeracao }}</p>
                <p class="text-sm opacity-80">Gerado por: {{ $geradoPor }}</p>
            </div>
        </div>
    </div>

    <div id="map"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const coords = <?php echo json_encode($area->polygon_coords); ?>;
            
            if (coords && coords.length > 0) {
                // Inicializar o mapa (desativar interações para o PDF)
                const map = L.map('map', {
                    zoomControl: false,
                    attributionControl: false,
                    dragging: false,
                    scrollWheelZoom: false,
                    doubleClickZoom: false
                });

                // Adicionar camada de satélite híbrida (Google Maps)
                L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                    subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
                }).addTo(map);

                // Criar o polígono
                const polygon = L.polygon(coords, {
                    color: '#fff',
                    fillColor: '#7c3aed',
                    fillOpacity: 0.4,
                    weight: 2
                }).addTo(map);

                // Ajustar o zoom para o polígono com mais margem (diminuindo o zoom)
                map.fitBounds(polygon.getBounds(), { padding: [100, 100] });
            } else {
                document.getElementById('map').innerHTML = '<div class="flex items-center justify-center h-full text-zinc-400 italic">Coordenadas do polígono não disponíveis</div>';
            }
        });
    </script>

    <div class="p-8 space-y-8">
        <!-- Resumo e Status -->
        <div class="grid grid-cols-4 gap-4 avoid-break">
            <div class="border border-zinc-200 p-4 rounded-xl bg-zinc-50/50">
                <p class="text-[10px] text-zinc-500 uppercase font-bold mb-2">Status</p>
                @php
                    $statusColor = $area->areaStatus?->cor ?? '#71717a';
                    $statusBg = $statusColor . '20';
                @endphp
                <span class="badge" @style([
                    'background-color' => $statusBg,
                    'color' => $statusColor,
                ])>
                    {{ $area->areaStatus?->nome ?? 'Sem Status' }}
                </span>
            </div>
            <div class="border border-zinc-200 p-4 rounded-xl bg-zinc-50/50">
                <p class="text-sm text-zinc-500 uppercase font-bold mb-1">Área Total</p>
                <p class="font-bold text-sm text-zinc-900">{{ number_format($area->area_calculada, 2, ',', '.') }} <span class="text-xs font-normal text-zinc-500">m²</span></p>
            </div>
            <div class="border border-zinc-200 p-4 rounded-xl bg-zinc-50/50">
                <p class="text-sm text-zinc-500 uppercase font-bold mb-1">VGV Estimado</p>
                <p class="font-bold text-sm text-green-700">R$ {{ number_format($vgvEstimado, 2, ',', '.') }}</p>
            </div>
            <div class="border border-zinc-200 p-4 rounded-xl bg-zinc-50/50">
                <p class="text-sm text-zinc-500 uppercase font-bold mb-1">Unidades</p>
                <p class="font-bold text-sm text-blue-700">{{ $area->total_unidades ?? 0 }}</p>
            </div>
        </div>

        <!-- Localização -->
        <div class="space-y-4 avoid-break">
            <h2 class="text-xl font-bold border-b border-zinc-200 pb-2">Localização e Endereço</h2>
            <div class="grid grid-cols-2 gap-6">
                <div class="space-y-2">
                    <p><span class="text-zinc-500 font-medium">Endereço:</span> {{ $area->endereco }}</p>
                    <p><span class="text-zinc-500 font-medium">Bairro:</span> {{ $area->bairro }}</p>
                    <p><span class="text-zinc-500 font-medium">Cidade/UF:</span> {{ $area->cidade?->city ?? $area->cidade_code }} / {{ $area->estado }}</p>
                    <p><span class="text-zinc-500 font-medium">CEP:</span> {{ $area->cep ?? '—' }}</p>
                </div>
                <div class="space-y-2 text-right">
                    <p><span class="text-zinc-500 font-medium">Regional:</span> {{ $area->regional?->nome ?? '—' }}</p>
                    <p><span class="text-zinc-500 font-medium">Gestor Responsável:</span> {{ $area->responsavel?->name ?? '—' }}</p>
                </div>
            </div>
        </div>

        <!-- Informações Técnicas -->
        <div class="grid grid-cols-2 gap-8 avoid-break">
            <div class="space-y-4">
                <h2 class="text-xl font-bold border-b border-zinc-200 pb-2">Dados do Terreno</h2>
                <table class="w-full text-sm">
                    <tr class="border-b border-zinc-100">
                        <td class="py-2 text-zinc-500 font-medium">Matrícula</td>
                        <td class="py-2 text-right font-semibold">{{ $area->matricula ?? '—' }}</td>
                    </tr>
                    <tr class="border-b border-zinc-100">
                        <td class="py-2 text-zinc-500 font-medium">Inscrição Municipal</td>
                        <td class="py-2 text-right font-semibold">{{ $area->inscricao_municipal ?? '—' }}</td>
                    </tr>
                    <tr class="border-b border-zinc-100">
                        <td class="py-2 text-zinc-500 font-medium">Zoneamento</td>
                        <td class="py-2 text-right font-semibold">{{ $area->zoneamento ?? '—' }}</td>
                    </tr>
                </table>
            </div>

            <div class="space-y-4">
                <h2 class="text-xl font-bold border-b border-zinc-200 pb-2">Proprietário(s)</h2>
                @if($area->proprietarios->count() > 0)
                    @foreach($area->proprietarios as $p)
                        <div class="text-sm space-y-1 {{ !$loop->last ? 'border-b border-zinc-100 pb-2 mb-2' : '' }}">
                            <p class="font-bold text-zinc-900">{{ $p->nome }}</p>
                            <p><span class="text-zinc-500">Documento:</span> {{ $p->documento ?? '—' }}</p>
                            <p><span class="text-zinc-500">E-mail:</span> {{ $p->email ?? '—' }}</p>
                            <p><span class="text-zinc-500">Telefone:</span> {{ $p->telefone ?? '—' }}</p>
                        </div>
                    @endforeach
                @else
                    <p class="text-sm text-zinc-500 italic">Nenhum proprietário vinculado.</p>
                @endif
            </div>
        </div>

        <!-- Produtos -->
        <div class="space-y-4 avoid-break">
            <h2 class="text-xl font-bold border-b border-zinc-200 pb-2">Produtos Planejados</h2>
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-500 uppercase text-[10px] font-bold">
                    <tr>
                        <th class="py-2 px-4 text-left">Produto</th>
                        <th class="py-2 px-4 text-center">Unidades</th>
                        <th class="py-2 px-4 text-right">Valor Unitário</th>
                        <th class="py-2 px-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($area->areaProdutos as $ap)
                    <tr class="border-b border-zinc-100">
                        <td class="py-3 px-4">{{ $ap->produto?->name ?? '—' }}</td>
                        <td class="py-3 px-4 text-center">{{ $ap->unidades }}</td>
                        <td class="py-3 px-4 text-right">R$ {{ number_format($ap->valor, 2, ',', '.') }}</td>
                        <td class="py-3 px-4 text-right font-bold">R$ {{ number_format($ap->unidades * $ap->valor, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Notas e Informações -->
        @if($area->areaInfos->count() > 0)
        <div class="space-y-4 avoid-break">
            <h2 class="text-xl font-bold border-b border-zinc-200 pb-2">Histórico e Notas</h2>
            <div class="space-y-4">
                @foreach($area->areaInfos as $info)
                <div class="bg-zinc-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-xs font-bold text-primary uppercase">{{ $info->user?->name ?? 'Sistema' }}</p>
                        <p class="text-[10px] text-zinc-400">{{ $info->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <p class="text-sm text-zinc-700 leading-relaxed">{{ $info->descricao }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Footer -->
    <div class="p-8 border-t border-zinc-100 text-center mt-auto">
        <p class="text-[10px] text-zinc-400">LRG Construtora - Documento gerado eletronicamente para fins de análise interna.</p>
        <p class="text-[10px] text-zinc-400">Página 1 de 1</p>
    </div>
</body>
</html>
