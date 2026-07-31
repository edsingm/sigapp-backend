<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; margin: 16mm 14mm; }
        :root {
            --primary: #1a4db5;
            --text: #0b1e39;
            --muted: #54627a;
            --border: #e5eaf3;
            --soft: #f4f6fb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: var(--text);
            line-height: 1.45;
        }
        .header {
            background: linear-gradient(90deg, #1a4db5, #2e6bff);
            color: white;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }
        .header h1 { font-size: 18px; margin-bottom: 4px; }
        .header p { opacity: 0.92; font-size: 10px; }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .chip {
            border: 1px solid var(--border);
            background: var(--soft);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 9px;
            color: var(--muted);
        }
        .section {
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .section h2 {
            background: var(--soft);
            padding: 8px 12px;
            font-size: 12px;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
        }
        .section .body {
            padding: 12px;
            white-space: pre-wrap;
            font-size: 10.5px;
        }
        .empty {
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            color: var(--muted);
        }
        .footer {
            margin-top: 10px;
            color: var(--muted);
            font-size: 9px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $title }}</h1>
    <p>Dossiê assistido por SIG IA · documento de apoio à decisão</p>
</div>
<div class="meta">
    <span class="chip">Revisão #{{ $review->id }}</span>
    <span class="chip">Terreno #{{ $terrenoId }}</span>
    @if ($viabilidadeId)
        <span class="chip">Viabilidade #{{ $viabilidadeId }}</span>
    @endif
    <span class="chip">Status dossiê: {{ $dossier->status }}</span>
    <span class="chip">Gerado em {{ $generatedAt }}</span>
    @if ($dossier->model)
        <span class="chip">Modelo: {{ $dossier->model }}</span>
    @endif
</div>

@if (count($sections) === 0)
    <div class="empty">Este dossiê não possui seções preenchidas.</div>
@else
    @foreach ($sections as $section)
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            <div class="body">{{ $section['body'] !== '' ? $section['body'] : '—' }}</div>
        </div>
    @endforeach
@endif

<div class="footer">
    <span>Conteúdo gerado por IA — revisar antes de decisões finais</span>
    <span>SIGAPP</span>
</div>
</body>
</html>
