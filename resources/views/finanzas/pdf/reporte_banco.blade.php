<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Conciliación - {{ $data['banco'] }} {{ $data['titular'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0 0 5px 0;
            color: #1e3a8a;
        }
        .header p {
            margin: 0;
            color: #64748b;
        }
        .summary-box {
            border: 1px solid #e2e8f0;
            padding: 10px;
            margin-bottom: 20px;
            background-color: #f8fafc;
        }
        .summary-box table {
            width: 100%;
        }
        .summary-box td {
            text-align: center;
            font-weight: bold;
        }
        .summary-box td span {
            display: block;
            font-weight: normal;
            font-size: 10px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .section-title {
            background-color: #f1f5f9;
            padding: 5px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            border-left: 4px solid #1e3a8a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: left;
        }
        table.data-table th {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8fafc;
        }
        .empty-row {
            text-align: center;
            font-style: italic;
            color: #94a3b8;
        }
        .nota-resumen {
            margin: 0 0 6px;
            font-size: 10px;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>Reporte de Conciliación: {{ $data['banco'] }}</h2>
        @if($data['titular'])
            <p>Titular: {{ $data['titular'] }}@if(!empty($data['cuenta_digitos'])) · {{ $data['cuenta_digitos'] }}@endif</p>
        @endif
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td style="color: #10b981;"><span>CONSOLIDADOS</span> Bs. {{ number_format($data['total_conciliados'], 2) }}</td>
                <td style="color: #f59e0b;"><span>EN TRÁNSITO</span> Bs. {{ number_format($data['total_transito'], 2) }}</td>
                <td style="color: #ef4444;"><span>MOVIMIENTOS BANCO</span> Bs. {{ number_format($data['total_sin_registrar'], 2) }}</td>
                <td style="color: #8b5cf6;"><span>COMISIONES</span> Bs. {{ number_format($data['total_comisiones'], 2) }} / ${{ number_format($data['total_comisiones_usd'] ?? 0, 2) }}</td>
                <td style="color: #f43f5e;"><span>PAGO CRÉDITO</span> Bs. {{ number_format(abs($data['total_pagos_credito'] ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- CONSOLIDADOS -->
    <div class="section-title">CONSOLIDADOS ({{ $data['conciliados']->count() }})</div>
    @if($data['por_dia']['conciliados'] ?? null)
        @include('finanzas.pdf.partials.resumen_por_dia', ['filas' => $data['por_dia']['conciliados'], 'total' => $data['conciliados']->count(), 'limite' => $data['limite_detalle']])
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th>FECHA</th>
                <th>REFERENCIA</th>
                <th>MOTIVO</th>
                <th class="text-right">MONTO BS.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['conciliados'] as $row)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $row['referencia'] }}</td>
                <td>{{ $row['motivo'] }}</td>
                @php $esEgreso = ($row['tipo'] ?? 'cargo') === 'cargo'; @endphp
                <td class="text-right" style="color: {{ $esEgreso ? '#dc2626' : '#059669' }};">
                    {{ $esEgreso ? '-' : '+' }}Bs. {{ number_format(abs((float) $row['monto']), 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin movimientos consolidados</td>
            </tr>
            @endforelse
            @if($data['conciliados']->count() > 0)
            @php
                $ingresosCons = (float) $data['conciliados']->where('tipo', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                $egresosCons = (float) $data['conciliados']->where('tipo', '!=', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
            @endphp
            <tr class="total-row">
                <td colspan="3" class="text-right">Ingresos / Egresos / Neto:</td>
                <td class="text-right">
                    +Bs. {{ number_format($ingresosCons, 2) }}<br>
                    -Bs. {{ number_format($egresosCons, 2) }}<br>
                    Bs. {{ number_format($ingresosCons - $egresosCons, 2) }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <!-- EN TRANSITO -->
    <div class="section-title">EN TRÁNSITO ({{ $data['en_transito']->count() }})</div>
    @if($data['por_dia']['en_transito'] ?? null)
        @include('finanzas.pdf.partials.resumen_por_dia', ['filas' => $data['por_dia']['en_transito'], 'total' => $data['en_transito']->count(), 'limite' => $data['limite_detalle']])
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th>FECHA</th>
                <th>REFERENCIA</th>
                <th>CONCEPTO / MOTIVO</th>
                <th class="text-right">MONTO BS.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['en_transito'] as $row)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $row['referencia'] }}</td>
                <td>{{ $row['concepto'] }} <br><small>{{ $row['motivo'] }}</small></td>
                <td class="text-right">Bs. {{ number_format($row['monto_bs'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin egresos en tránsito</td>
            </tr>
            @endforelse
            @if($data['en_transito']->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format($data['total_transito'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <!-- SIN REGISTRAR / MOVIMIENTOS EN BANCO -->
    <div class="section-title">MOVIMIENTOS EN EL BANCO ({{ $data['sin_registrar']->count() }})</div>
    @if($data['por_dia']['sin_registrar'] ?? null)
        @include('finanzas.pdf.partials.resumen_por_dia', ['filas' => $data['por_dia']['sin_registrar'], 'total' => $data['sin_registrar']->count(), 'limite' => $data['limite_detalle']])
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th>FECHA</th>
                <th>REFERENCIA</th>
                <th>DESCRIPCIÓN DEL BANCO</th>
                <th class="text-right">MONTO BS.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['sin_registrar'] as $row)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $row['referencia'] }}</td>
                <td>{{ $row['descripcion'] }}</td>
                @php $esEgreso = ($row['tipo'] ?? 'cargo') === 'cargo'; @endphp
                <td class="text-right" style="color: {{ $esEgreso ? '#dc2626' : '#059669' }};">
                    {{ $esEgreso ? '-' : '+' }}Bs. {{ number_format(abs((float) $row['monto']), 2) }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin movimientos detectados</td>
            </tr>
            @endforelse
            @if($data['sin_registrar']->count() > 0)
            @php
                $ingresosBanco = (float) $data['sin_registrar']->where('tipo', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
                $egresosBanco = (float) $data['sin_registrar']->where('tipo', '!=', 'abono')->sum(fn ($r) => abs((float) $r['monto']));
            @endphp
            <tr class="total-row">
                <td colspan="3" class="text-right">Ingresos / Egresos / Neto:</td>
                <td class="text-right">
                    +Bs. {{ number_format($ingresosBanco, 2) }}<br>
                    -Bs. {{ number_format($egresosBanco, 2) }}<br>
                    Bs. {{ number_format($ingresosBanco - $egresosBanco, 2) }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <!-- COMISIONES -->
    <div class="section-title">COMISIONES BANCARIAS ({{ $data['comisiones']->count() }})</div>
    @if($data['por_dia']['comisiones'] ?? null)
        @include('finanzas.pdf.partials.resumen_por_dia', ['filas' => $data['por_dia']['comisiones'], 'total' => $data['comisiones']->count(), 'limite' => $data['limite_detalle']])
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th>FECHA</th>
                <th>DESCRIPCIÓN</th>
                <th>REFERENCIA</th>
                <th class="text-right">MONTO BS.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['comisiones'] as $row)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $row['descripcion'] }}</td>
                <td>{{ $row['referencia'] }}</td>
                <td class="text-right">
                    Bs. {{ number_format($row['monto'], 2) }}
                    @if(($row['monto_usd'] ?? null) !== null)
                        <br>${{ number_format($row['monto_usd'], 2) }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin comisiones detectadas</td>
            </tr>
            @endforelse
            @if($data['comisiones']->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format($data['total_comisiones'], 2) }} / ${{ number_format($data['total_comisiones_usd'] ?? 0, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    <!-- PAGO DE CRÉDITO -->
    <div class="section-title">PAGO DE CRÉDITO ({{ ($data['pagos_credito'] ?? collect())->count() }})</div>
    @if($data['por_dia']['pagos_credito'] ?? null)
        @include('finanzas.pdf.partials.resumen_por_dia', ['filas' => $data['por_dia']['pagos_credito'], 'total' => $data['pagos_credito']->count(), 'limite' => $data['limite_detalle']])
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th>FECHA</th>
                <th>DESCRIPCIÓN</th>
                <th>REFERENCIA</th>
                <th class="text-right">MONTO BS.</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['pagos_credito'] ?? collect()) as $row)
            <tr>
                <td class="text-center">{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $row['descripcion'] }}</td>
                <td>{{ $row['referencia'] }}</td>
                <td class="text-right">Bs. {{ number_format(abs($row['monto']), 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin pagos de crédito detectados</td>
            </tr>
            @endforelse
            @if(($data['pagos_credito'] ?? collect())->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format(abs($data['total_pagos_credito'] ?? 0), 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>
    @endif

</body>
</html>
