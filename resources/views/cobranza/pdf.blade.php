<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Global de Cobranza</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #fff;
        }

        /* ── HEADER ─────────────────────────────────────── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 14px;
        }
        .header-logo { display: table-cell; vertical-align: middle; width: 120px; }
        .header-logo img { width: 100px; }
        .header-titles { display: table-cell; vertical-align: middle; text-align: center; }
        .header-titles h1 {
            font-size: 20px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header-titles h2 {
            font-size: 12px;
            font-weight: 400;
            color: #64748b;
            margin-top: 4px;
        }
        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 130px;
            color: #64748b;
            font-size: 10px;
        }

        /* ── SECTION TITLE ──────────────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #fff;
            background: #1e3a8a;
            padding: 6px 14px;
            margin-bottom: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ── TABLES ─────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .data-table th {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: bold;
            text-align: center;
            padding: 8px 7px;
            font-size: 10px;
            border: 1px solid #bfdbfe;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .data-table td {
            padding: 7px 8px;
            font-size: 10.5px;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }

        /* ── TOTALS ROW ─────────────────────────────────── */
        .row-total td {
            background: #dbeafe !important;
            color: #1e3a8a;
            font-weight: bold;
            border-top: 2px solid #93c5fd;
        }

        /* ── STATUS CELLS ───────────────────────────────── */
        .critico   { background: #ef4444 !important; color: #fff; font-weight: bold; text-align: center; }
        .moroso    { background: #facc15 !important; color: #000; font-weight: bold; text-align: center; }
        .reciente  { background: #86efac !important; color: #166534; font-weight: bold; text-align: center; }
        .apartado  { background: #e2e8f0 !important; color: #475569; font-weight: bold; text-align: center; }

        /* ── STATUS BADGES (detalle) ────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .badge-critico  { background: #fee2e2; color: #991b1b; }
        .badge-moroso   { background: #fef9c3; color: #854d0e; }
        .badge-reciente { background: #dcfce7; color: #166534; }
        .badge-apartado { background: #f1f5f9; color: #475569; }

        /* ── PAGE BREAK ─────────────────────────────────── */
        .page-break { page-break-before: always; }

        /* ── PAGE HEADER (repeated on each page) ────────── */
        .page-header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3a8a;
        }
        .page-header .ph-logo { display: table-cell; width: 70px; vertical-align: middle; }
        .page-header .ph-logo img { width: 55px; }
        .page-header .ph-title { display: table-cell; vertical-align: middle; }
        .page-header .ph-title h3 { font-size: 13px; color: #1e3a8a; font-weight: bold; text-transform: uppercase; }
        .page-header .ph-title p { font-size: 10px; color: #64748b; margin-top: 2px; }
        .page-header .ph-right { display: table-cell; text-align: right; vertical-align: middle; font-size: 10px; color: #64748b; width: 120px; }

        /* ── SUMMARY BAR ────────────────────────────────── */
        .summary-bar {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .summary-bar-cell {
            display: table-cell;
            padding: 8px 14px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
        }
        .summary-bar-cell:last-child { border-right: none; }
        .summary-bar-cell .sb-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-bar-cell .sb-value { font-size: 14px; font-weight: bold; color: #1e3a8a; margin-top: 2px; }
        .summary-bar-cell.green .sb-value { color: #16a34a; }
        .summary-bar-cell.red .sb-value   { color: #dc2626; }
        .summary-bar-cell.yellow .sb-value { color: #d97706; }

        /* ── PERSONAL BADGE ─────────────────────────────── */
        .personal-badge {
            background: #2563eb;
            color: white;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            margin-left: 3px;
        }

        /* ── FOOTER ─────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -10px; left: 0; right: 0;
            height: 26px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    {{-- ═══════════════════════════════════════════════════════
         PÁGINA 1: INDICADORES GLOBALES
    ═══════════════════════════════════════════════════════ --}}
    <div class="header">
        <div class="header-logo">
            <img src="{{ public_path('logo.png') }}" alt="Logo">
        </div>
        <div class="header-titles">
            <h1>Reporte Global de Cobranza</h1>
            <h2>Resumen ejecutivo al {{ date('d/m/Y') }}</h2>
        </div>
        <div class="header-right">
            Generado:<br>{{ date('d/m/Y H:i') }}
        </div>
    </div>

    {{-- KPIs GLOBALES --}}
    <div class="summary-bar">
        <div class="summary-bar-cell">
            <div class="sb-label">Total Clientes</div>
            <div class="sb-value">{{ $gran_total_clientes }}</div>
        </div>
        <div class="summary-bar-cell red">
            <div class="sb-label">Cartera Total</div>
            <div class="sb-value">${{ number_format($gran_total_saldo, 2, ',', '.') }}</div>
        </div>
        @foreach($porEstatus as $est)
            @php
                $colorClass = ['critico'=>'red','moroso'=>'yellow','reciente'=>'green','apartado'=>''][ strtolower($est->estatus) ] ?? '';
            @endphp
            <div class="summary-bar-cell {{ $colorClass }}">
                <div class="sb-label">{{ $est->estatus }}</div>
                <div class="sb-value">{{ $est->total_clientes }}</div>
            </div>
        @endforeach
    </div>

    {{-- TABLA POR SEDE --}}
    <div class="section-title">&#127970; Indicadores de Cobranza por Sede</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Sede</th>
                <th>Clientes</th>
                <th>Saldo Adeudado</th>
                <th>% del Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($porSede as $sede)
                @php $porcentaje = $gran_total_saldo > 0 ? round(($sede->total_saldo / $gran_total_saldo) * 100, 1) : 0; @endphp
                <tr>
                    <td><strong>{{ $sede->sede_nombre }}</strong></td>
                    <td class="text-center">{{ $sede->total_clientes }}</td>
                    <td class="text-right">${{ number_format($sede->total_saldo, 2, ',', '.') }}</td>
                    <td class="text-right">{{ $porcentaje }}%</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td>TOTAL GENERAL</td>
                <td class="text-center">{{ $gran_total_clientes }}</td>
                <td class="text-right">${{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                <td class="text-right">100%</td>
            </tr>
        </tbody>
    </table>

    {{-- TABLA POR ESTATUS --}}
    <div class="section-title">&#128204; Indicadores de Cobranza por Estatus</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Estatus</th>
                <th>Clientes</th>
                <th>Saldo Adeudado</th>
                <th>% del Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($porEstatus as $estatus)
                @php
                    $porcentaje = $gran_total_saldo > 0 ? round(($estatus->total_saldo / $gran_total_saldo) * 100, 1) : 0;
                    $cls = strtolower($estatus->estatus);
                @endphp
                <tr>
                    <td class="{{ $cls }}">{{ $estatus->estatus }}</td>
                    <td class="{{ $cls }}">{{ $estatus->total_clientes }}</td>
                    <td class="text-right {{ $cls }}">${{ number_format($estatus->total_saldo, 2, ',', '.') }}</td>
                    <td class="text-right {{ $cls }}">{{ $porcentaje }}%</td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td>TOTAL GENERAL</td>
                <td class="text-center">{{ $gran_total_clientes }}</td>
                <td class="text-right">${{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                <td class="text-right">100%</td>
            </tr>
        </tbody>
    </table>


    {{-- ═══════════════════════════════════════════════════════
         PÁGINAS POR SEDE
    ═══════════════════════════════════════════════════════ --}}
    @foreach($clientesPorSede as $sede => $clientes)
        @php $totalSedeSaldo = $clientes->sum('saldo'); @endphp
        <div class="page-break"></div>

        <div class="page-header">
            <div class="ph-logo"><img src="{{ public_path('logo.png') }}" alt="Logo"></div>
            <div class="ph-title">
                <h3>Detalle de Clientes — {{ mb_strtoupper($sede) }}</h3>
                <p>{{ count($clientes) }} cliente(s) &nbsp;|&nbsp; Cartera: ${{ number_format($totalSedeSaldo, 2, ',', '.') }}</p>
            </div>
            <div class="ph-right">{{ date('d/m/Y') }}</div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Monto Neto</th>
                    <th>Saldo (Falta Pagar)</th>
                    <th>Estatus</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clientes as $c)
                    @php $cls = strtolower($c->estatus); @endphp
                    <tr>
                        <td>{{ $c->codigo_cliente ?? $c->codigo }}</td>
                        <td>
                            {{ $c->nombre_cliente ?? $c->cliente }}
                            @if(!empty($c->es_personal))
                                <span class="personal-badge">PERSONAL</span>
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($c->monto_neto, 2, ',', '.') }}</td>
                        <td class="text-right" style="font-weight:bold;">${{ number_format($c->saldo, 2, ',', '.') }}</td>
                        <td><span class="badge badge-{{ $cls }}">{{ $c->estatus }}</span></td>
                        <td style="font-size:9px; color:#64748b; max-width:120px;">{{ $c->nota_anclada ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach


    {{-- ═══════════════════════════════════════════════════════
         RANKING GLOBAL
    ═══════════════════════════════════════════════════════ --}}
    <div class="page-break"></div>

    <div class="page-header">
        <div class="ph-logo"><img src="{{ public_path('logo.png') }}" alt="Logo"></div>
        <div class="ph-title">
            <h3>Ranking Global de Clientes Deudores</h3>
            <p>Ordenado de mayor a menor saldo adeudado</p>
        </div>
        <div class="ph-right">{{ date('d/m/Y') }}</div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>N°</th>
                <th>Sede</th>
                <th>Cliente</th>
                <th>Saldo Adeudado</th>
                <th>Estatus</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            @php $rank = 1; @endphp
            @foreach($clientesGlobalDesc as $c)
                @php $cls = strtolower($c->estatus); @endphp
                <tr>
                    <td class="text-center" style="color:#94a3b8; font-weight:bold;">{{ $rank++ }}</td>
                    <td>{{ $c->sede_nombre }}</td>
                    <td>
                        {{ $c->nombre_cliente ?? $c->cliente }}
                        @if(!empty($c->es_personal))
                            <span class="personal-badge">PERSONAL</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight:bold; color:#dc2626;">${{ number_format($c->saldo, 2, ',', '.') }}</td>
                    <td><span class="badge badge-{{ $cls }}">{{ $c->estatus }}</span></td>
                    <td style="font-size:9px; color:#64748b;">{{ $c->nota_anclada ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado el {{ date('d/m/Y H:i') }} &mdash; Sistema de Inventario y Cobranza &mdash; Confidencial
    </div>

</body>
</html>
