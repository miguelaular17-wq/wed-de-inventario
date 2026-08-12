<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Mensual Patrimonial</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1e293b; }

        .header { display: table; width: 100%; padding-bottom: 14px; border-bottom: 3px solid #1e3a8a; margin-bottom: 22px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 110px; }
        .header-logo img { width: 90px; }
        .header-titles { display: table-cell; vertical-align: middle; text-align: center; }
        .header-titles h1 { font-size: 18px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
        .header-titles h2 { font-size: 12px; color: #64748b; font-weight: 400; margin-top: 4px; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 120px; font-size: 10px; color: #64748b; }

        /* KPI BAR */
        .kpi-bar { display: table; width: 100%; margin-bottom: 22px; border: 1px solid #e2e8f0; border-radius: 4px; }
        .kpi-cell { display: table-cell; padding: 10px 16px; text-align: center; border-right: 1px solid #e2e8f0; }
        .kpi-cell:last-child { border-right: none; }
        .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 16px; font-weight: bold; margin-top: 3px; }
        .green { color: #059669; }
        .red   { color: #dc2626; }
        .orange { color: #d97706; }
        .blue  { color: #1e3a8a; }

        /* SECTION */
        .section-title { font-size: 11px; font-weight: bold; color: #fff; background: #1e3a8a; padding: 6px 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0; }

        /* MAIN TABLE */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .data-table th { background: #dbeafe; color: #1e3a8a; font-size: 10px; font-weight: bold; text-align: center; padding: 8px 7px; border: 1px solid #bfdbfe; text-transform: uppercase; }
        .data-table td { padding: 8px 9px; border: 1px solid #e2e8f0; font-size: 10.5px; }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table tfoot td { background: #dbeafe; font-weight: bold; border-top: 2px solid #93c5fd; color: #1e3a8a; }

        /* TX DETAIL */
        .tx-table { width: 100%; border-collapse: collapse; margin: 0 0 10px 0; font-size: 9.5px; }
        .tx-table th { background: #f1f5f9; color: #475569; padding: 5px 8px; border: 1px solid #e2e8f0; text-align: left; }
        .tx-table td { padding: 5px 8px; border: 1px solid #e2e8f0; }
        .tx-ingreso  { color: #059669; font-weight: 600; }
        .tx-gasto    { color: #dc2626; font-weight: 600; }
        .tx-comision { color: #d97706; font-weight: 600; }

        .page-break { page-break-before: always; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; height: 26px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    @php
        use Carbon\Carbon;
        $nombreMes = Carbon::create($anio, $mes)->translatedFormat('F Y');
    @endphp

    <div class="header">
        <div class="header-logo"><img src="{{ public_path('logo.png') }}" alt="Logo"></div>
        <div class="header-titles">
            <h1>Reporte Mensual Patrimonial</h1>
            <h2>Balance de propiedades — {{ $nombreMes }}</h2>
        </div>
        <div class="header-right">Generado:<br>{{ now()->format('d/m/Y H:i') }}</div>
    </div>

    {{-- KPIs --}}
    <div class="kpi-bar">
        <div class="kpi-cell">
            <div class="kpi-label">Ingresos</div>
            <div class="kpi-value green">${{ number_format($totales['ingresos'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Gastos</div>
            <div class="kpi-value red">${{ number_format($totales['gastos'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Comisiones</div>
            <div class="kpi-value orange">${{ number_format($totales['comisiones'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Balance Neto</div>
            <div class="kpi-value {{ $totales['balance'] >= 0 ? 'green' : 'red' }}">${{ number_format($totales['balance'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Propiedades</div>
            <div class="kpi-value blue">{{ $reporte->count() }}</div>
        </div>
    </div>

    {{-- RESUMEN POR PROPIEDAD --}}
    <div class="section-title">&#127970; Resumen por Propiedad</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Propiedad</th>
                <th>Tipo</th>
                <th class="text-right">Ingresos</th>
                <th class="text-right">Gastos</th>
                <th class="text-right">Comisiones</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reporte as $row)
                @if($row['ingresos'] > 0 || $row['gastos'] > 0 || $row['comisiones'] > 0)
                <tr>
                    <td><strong>{{ $row['propiedad'] }}</strong><br><span style="font-size:9px; color:#94a3b8;">{{ $row['codigo'] }}</span></td>
                    <td class="text-center" style="color:#64748b;">{{ ucfirst($row['tipo']) }}</td>
                    <td class="text-right {{ $row['ingresos'] > 0 ? 'green' : '' }}">${{ number_format($row['ingresos'], 2) }}</td>
                    <td class="text-right {{ $row['gastos'] > 0 ? 'red' : '' }}">${{ number_format($row['gastos'], 2) }}</td>
                    <td class="text-right {{ $row['comisiones'] > 0 ? 'orange' : '' }}">${{ number_format($row['comisiones'], 2) }}</td>
                    <td class="text-right" style="font-weight:bold; color:{{ $row['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($row['balance'], 2) }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">TOTALES GENERALES</td>
                <td class="text-right">${{ number_format($totales['ingresos'], 2) }}</td>
                <td class="text-right">${{ number_format($totales['gastos'], 2) }}</td>
                <td class="text-right">${{ number_format($totales['comisiones'], 2) }}</td>
                <td class="text-right" style="font-size:13px;">${{ number_format($totales['balance'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- DETALLE DE TRANSACCIONES POR PROPIEDAD --}}
    @foreach($reporte as $row)
        @if(isset($row['transacciones']) && $row['transacciones']->isNotEmpty())
            <div class="section-title" style="margin-top: 14px;">&#128203; {{ $row['propiedad'] }} — Transacciones</div>
            <table class="tx-table" style="margin-bottom: 14px;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th style="text-align:right;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['transacciones'] as $tx)
                        <tr>
                            <td>{{ $tx->fecha ? \Carbon\Carbon::parse($tx->fecha)->format('d/m/Y') : '—' }}</td>
                            <td class="tx-{{ $tx->tipo }}">{{ ucfirst($tx->tipo) }}</td>
                            <td>{{ $tx->categoria }}</td>
                            <td style="color:#64748b;">{{ $tx->descripcion ?: '—' }}</td>
                            <td class="tx-{{ $tx->tipo }}" style="text-align:right;">${{ number_format($tx->monto, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <div class="footer">
        Gestión Patrimonial &mdash; {{ $nombreMes }} &mdash; Generado el {{ now()->format('d/m/Y H:i') }} &mdash; Confidencial
    </div>
</body>
</html>
