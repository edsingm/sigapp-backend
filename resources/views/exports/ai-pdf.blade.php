<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 portrait;
        }

        :root {
            --primary: #2e6bff;
            --primary-strong: #1a4db5;
            --primary-soft: #eef2f8;
            --text: #0b1e39;
            --muted: #54627a;
            --border: #e5eaf3;
            --table-head: #f4f6fb;
            --table-stripe: #f8fafd;
            --success: #1e8a5b;
            --danger: #d93933;
            --warning: #e0a436;
            --info: #2e6bff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.55;
            color: var(--text);
            background: white;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Cabeçalho ─────────────────────────────────────────────── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, var(--primary-strong), var(--primary));
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .doc-header h1 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .doc-header .doc-meta {
            font-size: 9px;
            text-align: right;
            opacity: 0.9;
            line-height: 1.6;
        }

        /* ── Conteúdo gerado pela IA ───────────────────────────────── */
        .doc-body h1, .doc-body h2, .doc-body h3,
        .doc-body h4, .doc-body h5, .doc-body h6 {
            color: var(--primary-strong);
            margin-top: 18px;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .doc-body h1 { font-size: 16px; }
        .doc-body h2 { font-size: 14px; border-bottom: 1px solid var(--border); padding-bottom: 4px; }
        .doc-body h3 { font-size: 12px; }
        .doc-body h4, .doc-body h5, .doc-body h6 { font-size: 11px; }

        .doc-body p {
            margin-bottom: 8px;
        }

        .doc-body ul, .doc-body ol {
            padding-left: 18px;
            margin-bottom: 8px;
        }

        .doc-body li {
            margin-bottom: 3px;
        }

        .doc-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 10px;
        }

        .doc-body thead {
            display: table-header-group;
        }

        .doc-body th {
            background: var(--table-head);
            color: var(--primary-strong);
            font-weight: 700;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid var(--border);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .doc-body td {
            padding: 5px 8px;
            border: 1px solid var(--border);
            vertical-align: top;
        }

        .doc-body tr:nth-child(even) td {
            background: var(--table-stripe);
        }

        .doc-body tr,
        .doc-body blockquote {
            break-inside: avoid-page;
            page-break-inside: avoid;
        }

        .doc-body strong, .doc-body b { color: var(--primary-strong); }

        .doc-body blockquote {
            border-left: 3px solid var(--primary);
            padding: 6px 12px;
            margin: 10px 0;
            background: var(--primary-soft);
            border-radius: 0 6px 6px 0;
            color: var(--muted);
            font-size: 10px;
        }

        .doc-body hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 14px 0;
        }

        .doc-body h1,
        .doc-body h2,
        .doc-body h3 {
            break-after: avoid-page;
            page-break-after: avoid;
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <h1>{{ $title }}</h1>
        <div style="text-align:right;">
            <div style="background:white;border-radius:8px;padding:4px 10px;display:inline-block;margin-bottom:6px;">
                <svg viewBox="0 0 188 58" width="100" height="31" xmlns="http://www.w3.org/2000/svg" aria-label="SIGAPP"><text x="4" y="47" font-family="Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" font-weight="900" font-size="48" fill="#2e6bff" letter-spacing="-2.5">SIG</text><rect x="87" y="7" width="96" height="44" rx="11" fill="#0b1e39"/><text x="135" y="39" font-family="Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif" font-weight="800" font-size="26" fill="white" text-anchor="middle" letter-spacing="1.5">APP</text></svg>
            </div>
            <div class="doc-meta">
                Gerado por IA em {{ now()->format('d/m/Y H:i') }}<br>
                Documento confidencial
            </div>
        </div>
    </div>

    <div class="doc-body">
        {!! $content !!}
    </div>
</body>
</html>
