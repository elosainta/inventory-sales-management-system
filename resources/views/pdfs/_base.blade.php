<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 30px 36px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            color: #1c1917;
            margin: 0;
        }

        /* Header */
        .pdf-header {
            border-bottom: 2px solid hsl(20, 60%, 45%);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .pdf-header h1 {
            margin: 0 0 4px 0;
            font-size: 22pt;
            color: hsl(20, 60%, 45%);
            font-weight: bold;
        }
        .pdf-header .meta {
            color: #78716c;
            font-size: 9pt;
        }

        /* Section title */
        h2 {
            margin: 0 0 4px 0;
            font-size: 16pt;
        }
        .subtitle {
            color: #78716c;
            font-size: 10pt;
            margin: 0 0 16px 0;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        thead {
            background: #f5f5f4;
            border-bottom: 1.5px solid #e7e5e4;
        }
        th {
            text-align: left;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #57534e;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #f5f5f4;
            font-size: 10pt;
        }
        .right { text-align: right; }
        .num { font-family: 'Courier New', monospace; }
        .muted { color: #78716c; }

        /* Footer */
        .pdf-footer {
            position: fixed;
            bottom: -18px;
            left: 0;
            right: 0;
            text-align: center;
            color: #a8a29e;
            font-size: 8pt;
        }

        /* Summary box */
        .summary {
            background: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 10pt;
        }
        .summary strong { color: hsl(20, 60%, 45%); }
    </style>
</head>
<body>
    <div class="pdf-header">
        <h1>Inventory, Sales and Management System</h1>
        <div class="meta">
            {{ $title }} &middot; Generated {{ now()->format('M d, Y · H:i') }}
            @if(auth()->user()) &middot; By {{ auth()->user()->name }} @endif
        </div>
    </div>

    {{ $slot }}

    <div class="pdf-footer">
        Inventory, Sales and Management System &middot; Confidential
    </div>
</body>
</html>