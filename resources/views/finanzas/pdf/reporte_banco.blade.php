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
    </style>
</head>
<body>

    <div class="header">
        <h2>Reporte de Conciliación: {{ $data['banco'] }}</h2>
        @if($data['titular'])
            <p>Titular: {{ $data['titular'] }}</p>
        @endif
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td style="color: #10b981;"><span>CONSOLIDADOS</span> Bs. {{ number_format($data['total_conciliados'], 2) }}</td>
                <td style="color: #f59e0b;"><span>EN TRÁNSITO</span> Bs. {{ number_format($data['total_transito'], 2) }}</td>
                <td style="color: #ef4444;"><span>MOVIMIENTOS BANCO</span> Bs. {{ number_format($data['total_sin_registrar'], 2) }}</td>
                <td style="color: #8b5cf6;"><span>COMISIONES</span> Bs. {{ number_format($data['total_comisiones'], 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- CONSOLIDADOS -->
    <div class="section-title">CONSOLIDADOS ({{ $data['conciliados']->count() }})</div>
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
                <td class="text-right">Bs. {{ number_format($row['monto'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin movimientos consolidados</td>
            </tr>
            @endforelse
            @if($data['conciliados']->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format($data['total_conciliados'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- EN TRANSITO -->
    <div class="section-title">EN TRÁNSITO ({{ $data['en_transito']->count() }})</div>
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

    <!-- SIN REGISTRAR / MOVIMIENTOS EN BANCO -->
    <div class="section-title">MOVIMIENTOS EN EL BANCO ({{ $data['sin_registrar']->count() }})</div>
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
                <td class="text-right">Bs. {{ number_format($row['monto'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin movimientos detectados</td>
            </tr>
            @endforelse
            @if($data['sin_registrar']->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format($data['total_sin_registrar'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- COMISIONES -->
    <div class="section-title">COMISIONES BANCARIAS ({{ $data['comisiones']->count() }})</div>
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
                <td class="text-right">Bs. {{ number_format($row['monto'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="empty-row">Sin comisiones detectadas</td>
            </tr>
            @endforelse
            @if($data['comisiones']->count() > 0)
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">Bs. {{ number_format($data['total_comisiones'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
