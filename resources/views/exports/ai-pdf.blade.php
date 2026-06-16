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
        <div class="doc-meta">
            Gerado por IA em {{ now()->format('d/m/Y H:i') }}<br>
            Documento confidencial
        </div>
    </div>

    <div class="doc-body">
        {!! $content !!}
    </div>
</body>
</html>
