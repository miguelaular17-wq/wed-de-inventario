<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Propiedad — {{ $propiedad->nombre }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1e293b; }

        .header { display: table; width: 100%; padding-bottom: 14px; border-bottom: 3px solid #1e3a8a; margin-bottom: 22px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 110px; }
        .header-logo img { width: 90px; }
        .header-titles { display: table-cell; vertical-align: middle; text-align: center; }
        .header-titles h1 { font-size: 17px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
        .header-titles h2 { font-size: 13px; color: #334155; font-weight: 600; margin-top: 3px; }
        .header-titles h3 { font-size: 11px; color: #64748b; font-weight: 400; margin-top: 3px; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 120px; font-size: 10px; color: #64748b; }

        /* INFO GRID */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .info-grid td { padding: 7px 12px; border: 1px solid #e2e8f0; font-size: 11px; }
        .info-label { background: #f1f5f9; color: #475569; font-weight: bold; width: 130px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-value { color: #0f172a; }

        /* KPI BAR */
        .kpi-bar { display: table; width: 100%; margin-bottom: 22px; border: 1px solid #e2e8f0; }
        .kpi-cell { display: table-cell; padding: 10px 16px; text-align: center; border-right: 1px solid #e2e8f0; }
        .kpi-cell:last-child { border-right: none; }
        .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .green  { color: #059669; }
        .red    { color: #dc2626; }
        .orange { color: #d97706; }
        .blue   { color: #1e3a8a; }

        /* SECTION */
        .section-title { font-size: 11px; font-weight: bold; color: #fff; background: #1e3a8a; padding: 6px 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0; }

        /* TABLE */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .data-table th { background: #dbeafe; color: #1e3a8a; font-size: 10px; font-weight: bold; text-align: center; padding: 8px 7px; border: 1px solid #bfdbfe; text-transform: uppercase; }
        .data-table td { padding: 8px 9px; border: 1px solid #e2e8f0; font-size: 10.5px; }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table tfoot td { background: #dbeafe; font-weight: bold; border-top: 2px solid #93c5fd; color: #1e3a8a; }

        /* ALQUILER BADGE */
        .estado-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 9px; font-weight: bold; text-transform: uppercase; }

        .footer { position: fixed; bottom: -10px; left: 0; right: 0; height: 26px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-logo"><img src="{{ public_path('logo.png') }}" alt="Logo"></div>
        <div class="header-titles">
            <h1>Reporte de Propiedad</h1>
            <h2>{{ $propiedad->nombre }}</h2>
            <h3>Historial {{ $anioInicio }} – {{ $anioFin }}</h3>
        </div>
        <div class="header-right">
            <strong>{{ $propiedad->codigo }}</strong><br>
            Generado:<br>{{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    {{-- DATOS DE LA PROPIEDAD --}}
    <table class="info-grid">
        <tr>
            <td class="info-label">Nombre</td>
            <td class="info-value" style="font-weight:700; font-size:12px;">{{ $propiedad->nombre }}</td>
            <td class="info-label">Código</td>
            <td class="info-value">{{ $propiedad->codigo }}</td>
        </tr>
        <tr>
            <td class="info-label">Tipo</td>
            <td class="info-value">{{ ucfirst($propiedad->tipo) }}</td>
            <td class="info-label">Estado</td>
            <td class="info-value">
                <span class="estado-badge" style="background:{{ $propiedad->estadoColor($propiedad->estado) }}22; color:{{ $propiedad->estadoColor($propiedad->estado) }};">
                    {{ $propiedad->estadoLabel($propiedad->estado) }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="info-label">Dirección</td>
            <td class="info-value" colspan="3">{{ $propiedad->direccion ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Propietario</td>
            <td class="info-value">{{ $propiedad->propietario ?: 'N/A' }}</td>
            <td class="info-label">Responsable</td>
            <td class="info-value">{{ $propiedad->responsable ?: 'N/A' }}</td>
        </tr>
        @if($propiedad->valor_inversion)
        <tr>
            <td class="info-label">Valor Inversión</td>
            <td class="info-value" colspan="3" style="font-weight:600;">${{ number_format($propiedad->valor_inversion, 2) }}</td>
        </tr>
        @endif
        @if($alquilerActivo)
        <tr>
            <td class="info-label">Inquilino Activo</td>
            <td class="info-value">{{ $alquilerActivo->inquilino }}</td>
            <td class="info-label">Canon Mensual</td>
            <td class="info-value" style="font-weight:600; color:#2563eb;">${{ number_format($alquilerActivo->canon, 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- KPIs TOTALES --}}
    <div class="kpi-bar">
        <div class="kpi-cell">
            <div class="kpi-label">Ingresos Totales</div>
            <div class="kpi-value green">${{ number_format($totales['ingresos'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Gastos Totales</div>
            <div class="kpi-value red">${{ number_format($totales['gastos'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Comisiones</div>
            <div class="kpi-value orange">${{ number_format($totales['comisiones'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Balance Neto Total</div>
            <div class="kpi-value {{ $totales['balance'] >= 0 ? 'green' : 'red' }}">${{ number_format($totales['balance'], 2) }}</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Meses con Actividad</div>
            <div class="kpi-value blue">{{ $historial->count() }}</div>
        </div>
    </div>

    {{-- HISTORIAL MENSUAL --}}
    <div class="section-title">&#128197; Historial Mensual</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Período</th>
                <th class="text-right">Ingresos</th>
                <th class="text-right">Gastos</th>
                <th class="text-right">Comisiones</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($historial as $row)
                @php $periodo = \Carbon\Carbon::create($row['anio'], $row['mes'])->translatedFormat('F Y'); @endphp
                <tr>
                    <td><strong>{{ $periodo }}</strong></td>
                    <td class="text-right {{ $row['ingresos'] > 0 ? 'green' : '' }}">${{ number_format($row['ingresos'], 2) }}</td>
                    <td class="text-right {{ $row['gastos'] > 0 ? 'red' : '' }}">${{ number_format($row['gastos'], 2) }}</td>
                    <td class="text-right {{ $row['comisiones'] > 0 ? 'orange' : '' }}">${{ number_format($row['comisiones'], 2) }}</td>
                    <td class="text-right" style="font-weight:bold; color:{{ $row['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center" style="color:#94a3b8; padding:20px;">Sin transacciones en este período.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOTALES</td>
                <td class="text-right">${{ number_format($totales['ingresos'], 2) }}</td>
                <td class="text-right">${{ number_format($totales['gastos'], 2) }}</td>
                <td class="text-right">${{ number_format($totales['comisiones'], 2) }}</td>
                <td class="text-right" style="font-size:13px;">${{ number_format($totales['balance'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($propiedad->observaciones)
    <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:4px; padding:12px; font-size:10px; color:#78350f; margin-top: 10px;">
        <strong>Observaciones:</strong> {{ $propiedad->observaciones }}
    </div>
    @endif

    <div class="footer">
        Gestión Patrimonial &mdash; {{ $propiedad->nombre }} &mdash; Generado el {{ now()->format('d/m/Y H:i') }} &mdash; Confidencial
    </div>
</body>
</html>
