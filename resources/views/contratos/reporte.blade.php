<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $contrato->numero_contrato }}</title>
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
            font-size: 13px;
            font-weight: 500;
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
        .header-right strong { color: #1e3a8a; font-size: 12px; }

        /* ── INFO GRID ──────────────────────────────────── */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .info-grid td {
            padding: 7px 12px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .info-label {
            background: #f1f5f9;
            color: #475569;
            font-weight: bold;
            width: 130px;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .info-value { color: #0f172a; }

        /* ── HIGHLIGHT BOXES ────────────────────────────── */
        .highlight-row {
            display: table;
            width: 100%;
            margin-bottom: 22px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .highlight-cell {
            display: table-cell;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #1e3a8a;
            border-radius: 4px;
            padding: 10px 14px;
            text-align: center;
        }
        .highlight-cell.green { border-top-color: #059669; }
        .highlight-cell.orange { border-top-color: #d97706; }
        .highlight-cell .hc-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .highlight-cell .hc-value { font-size: 16px; font-weight: bold; color: #1e3a8a; margin-top: 3px; }
        .highlight-cell.green .hc-value { color: #059669; }
        .highlight-cell.orange .hc-value { color: #d97706; }

        /* ── SECTION TITLE ──────────────────────────────── */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #fff;
            background: #1e3a8a;
            padding: 7px 14px;
            margin-bottom: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ── TABLE ──────────────────────────────────────── */
        .plan-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .plan-table th {
            background: #dbeafe;
            color: #1e3a8a;
            font-weight: bold;
            text-align: center;
            padding: 8px 6px;
            font-size: 10px;
            border: 1px solid #bfdbfe;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .plan-table td {
            padding: 7px 6px;
            text-align: center;
            font-size: 10.5px;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .plan-table tr:nth-child(even) td { background: #f8fafc; }
        .plan-table .text-right { text-align: right; }
        .plan-table tfoot tr td {
            background: #f1f5f9;
            font-weight: bold;
            border-top: 2px solid #94a3b8;
            color: #1e293b;
        }

        /* ── STATUS ─────────────────────────────────────── */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .status-pagado   { background: #d1fae5; color: #065f46; }
        .status-pendiente { background: #f1f5f9; color: #475569; }
        .status-vencido  { background: #fee2e2; color: #991b1b; }
        .status-parcial  { background: #fef3c7; color: #92400e; }

        /* ── SUMMARY ─────────────────────────────────────── */
        .summary-wrapper { display: table; width: 100%; margin-top: 10px; }
        .summary-left { display: table-cell; width: 55%; vertical-align: top; font-size: 10px; color: #64748b; padding-right: 20px; padding-top: 10px; }
        .summary-right { display: table-cell; width: 45%; vertical-align: top; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 7px 12px; border: 1px solid #e2e8f0; font-size: 11px; }
        .summary-table .s-label { background: #f8fafc; color: #475569; font-weight: bold; text-align: right; }
        .summary-table .s-value { text-align: right; color: #0f172a; font-weight: 600; }
        .summary-table .total-row td { background: #dbeafe; color: #1e3a8a; font-size: 13px; font-weight: bold; }

        /* ── FOOTER ─────────────────────────────────────── */
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0; right: 0;
            height: 28px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    {{-- HEADER con logo --}}
    <div class="header">
        <div class="header-logo">
            <img src="{{ public_path('logo.png') }}" alt="Logo">
        </div>
        <div class="header-titles">
            <h1>Reporte de Contrato</h1>
            <h2>Plan de Pagos — Estado de Cuenta</h2>
        </div>
        <div class="header-right">
            <strong>{{ $contrato->numero_contrato }}</strong><br>
            Generado:<br>{{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    {{-- DATOS DEL CONTRATO --}}
    <table class="info-grid">
        <tr>
            <td class="info-label">Cliente</td>
            <td class="info-value" colspan="3" style="font-weight:600; font-size:12px;">{{ $contrato->cliente }}</td>
        </tr>
        <tr>
            <td class="info-label">Garantía</td>
            <td class="info-value">{{ $contrato->garantia ?: 'N/A' }}</td>
            <td class="info-label">Fecha de Inicio</td>
            <td class="info-value">{{ $contrato->fecha_inicio?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Teléfono</td>
            <td class="info-value">{{ $contrato->telefono ?: 'N/A' }}</td>
            <td class="info-label">Sede</td>
            <td class="info-value">{{ $contrato->sede ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Frecuencia</td>
            <td class="info-value" colspan="3">{{ strtoupper($contrato->frecuencia ?? 'MENSUAL') }}</td>
        </tr>
    </table>

    {{-- HIGHLIGHT BOXES --}}
    <div class="highlight-row">
        <div class="highlight-cell">
            <div class="hc-label">Capital Original</div>
            <div class="hc-value">${{ number_format($contrato->capital, 2) }}</div>
        </div>
        <div class="highlight-cell green">
            <div class="hc-label">Total a Pagar</div>
            <div class="hc-value">${{ number_format($contrato->capital + $contrato->cuotas->whereIn('estatus', ['vencido','pendiente','parcial'])->sum('monto'), 2) }}</div>
        </div>
        <div class="highlight-cell orange">
            <div class="hc-label">Cuota Fija</div>
            <div class="hc-value">${{ number_format($contrato->cuota_fija, 2) }}</div>
        </div>
        <div class="highlight-cell">
            <div class="hc-label">Interés Mensual</div>
            <div class="hc-value">{{ number_format($contrato->interes_porcentaje * 100, 2) }}%</div>
        </div>
    </div>

    {{-- PLAN DE PAGOS --}}
    <div class="section-title">&#128197; Plan de Pagos</div>
    <table class="plan-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Vencimiento</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Int. Pagado</th>
                <th class="text-right">Abono Cap.</th>
                <th class="text-right">Saldo</th>
                <th>Forma Pago</th>
                <th>Fecha Pago</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalMonto         = 0;
                $totalPagado        = 0;
                $totalAbono         = 0;
                $totalMontoPendiente = 0; // suma del monto de cuotas sin pagar (no el saldo acumulativo)
            @endphp
            @foreach($contrato->cuotas as $cuota)
                @php
                    $totalMonto  += $cuota->monto;
                    $totalPagado += $cuota->monto_pagado;
                    $totalAbono  += $cuota->abono_capital;
                    $estLower = strtolower($cuota->estatus);
                    // Solo sumamos el monto ($240) de las cuotas VENCIDAS o pendientes
                    if (in_array($estLower, ['vencido', 'pendiente', 'parcial'])) {
                        $totalMontoPendiente += $cuota->monto;
                    }
                    $statusClass = 'status-' . $estLower;
                @endphp
                <tr>
                    <td>{{ $cuota->numero_cuota }}</td>
                    <td>{{ $cuota->fecha_vencimiento?->format('d/m/Y') }}</td>
                    <td class="text-right">${{ number_format($cuota->monto, 2) }}</td>
                    <td class="text-right">${{ number_format($cuota->monto_pagado, 2) }}</td>
                    <td class="text-right">${{ number_format($cuota->abono_capital, 2) }}</td>
                    <td class="text-right">${{ number_format($cuota->saldo, 2) }}</td>
                    <td>{{ $cuota->forma_pago ?: '—' }}</td>
                    <td>{{ $cuota->fecha_pago?->format('d/m/Y') ?: '—' }}</td>
                    <td><span class="status-badge {{ $statusClass }}">{{ strtoupper($cuota->estatus) }}</span></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align:right; font-weight:bold;">TOTALES</td>
                <td class="text-right">${{ number_format($totalMonto, 2) }}</td>
                <td class="text-right">${{ number_format($totalPagado, 2) }}</td>
                <td class="text-right">${{ number_format($totalAbono, 2) }}</td>
                <td class="text-right">—</td>{{-- El saldo acumulativo no tiene sentido en totales --}}
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    {{-- RESUMEN FINAL --}}
    <div class="summary-wrapper">
        <div class="summary-left">
            <strong>Nota:</strong> El campo "Saldo" por cuota indica el capital restante del contrato <em>al momento de esa cuota</em>,
            es un valor acumulativo descendente. El total real de la deuda pendiente se muestra en el resumen a la derecha.
        </div>
        <div class="summary-right">
            <table class="summary-table">
                <tr>
                    <td class="s-label">Capital Original Estimado</td>
                    <td class="s-value">${{ number_format($contrato->capital + $totalAbono, 2) }}</td>
                </tr>
                <tr>
                    <td class="s-label">Abonos a Capital Totales</td>
                    <td class="s-value">${{ number_format($totalAbono, 2) }}</td>
                </tr>
                <tr>
                    <td class="s-label">Capital Actual</td>
                    <td class="s-value">${{ number_format($contrato->capital, 2) }}</td>
                </tr>
                <tr>
                    <td class="s-label">Cuotas Pendientes ({{ $contrato->cuotas->whereIn('estatus', ['vencido','pendiente','parcial'])->count() }} cuota(s) × ${{ number_format($contrato->cuota_fija, 2) }})</td>
                    <td class="s-value">${{ number_format($totalMontoPendiente, 2) }}</td>
                </tr>
                @php $totalDeuda = $contrato->capital + $totalMontoPendiente; @endphp
                <tr class="total-row">
                    <td class="s-label" style="font-size:13px;">TOTAL DEUDA</td>
                    <td class="s-value" style="font-size:15px;">${{ number_format($totalDeuda, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i A') }} &mdash; Sistema de Inventario y Cobranza &mdash; {{ $contrato->sede }}
    </div>

</body>
</html>
