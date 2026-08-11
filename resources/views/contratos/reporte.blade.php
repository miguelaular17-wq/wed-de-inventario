<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte - {{ $contrato->numero_contrato }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        h1 { color: #1e3a8a; margin: 0; font-size: 20px; }
        h3 { color: #475569; margin: 5px 0 0; font-size: 14px; font-weight: 500; }
        
        .info-box { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-box td { padding: 6px 10px; border: 1px solid #e2e8f0; }
        .info-label { font-weight: bold; background: #f8fafc; color: #475569; width: 150px; }
        .info-value { color: #0f172a; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #f1f5f9; color: #334155; font-weight: bold; text-align: center; padding: 8px; font-size: 11px; border: 1px solid #cbd5e1; }
        .table td { padding: 8px; text-align: center; font-size: 11px; border: 1px solid #cbd5e1; }
        .table .text-right { text-align: right; }
        
        .status-pagado { color: #059669; font-weight: bold; }
        .status-pendiente { color: #64748b; }
        .status-vencido { color: #dc2626; font-weight: bold; }
        .status-parcial { color: #d97706; font-weight: bold; }
        
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 30px; text-align: center; font-size: 10px; color: #94a3b8; }
        
        .summary { width: 50%; float: right; margin-top: 20px; border-collapse: collapse; }
        .summary td { padding: 6px 10px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>

    <div class="header">
        <h1>REPORTE DE CONTRATO</h1>
        <h3>{{ $contrato->numero_contrato }}</h3>
    </div>

    <table class="info-box">
        <tr>
            <td class="info-label">Cliente</td>
            <td class="info-value" colspan="3">{{ $contrato->cliente }}</td>
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
            <td class="info-label">Capital</td>
            <td class="info-value">${{ number_format($contrato->capital, 2) }}</td>
            <td class="info-label">Total a Pagar</td>
            <td class="info-value">${{ number_format($contrato->total_a_pagar, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label">Cuota Fija</td>
            <td class="info-value">${{ number_format($contrato->cuota_fija, 2) }}</td>
            <td class="info-label">Interés Mensual</td>
            <td class="info-value">{{ number_format($contrato->interes_porcentaje * 100, 2) }}%</td>
        </tr>
    </table>

    <h3 style="color: #1e3a8a; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 15px;">Plan de Pagos / Estado de Cuenta</h3>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Vencimiento</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Int. Pagado</th>
                <th class="text-right">Abono Cap.</th>
                <th class="text-right">Saldo</th>
                <th>F. Pago</th>
                <th>Fecha Pago</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalMonto = 0;
                $totalPagado = 0;
                $totalAbono = 0;
                $totalSaldo = 0;
                $maxSaldoPendiente = 0;
            @endphp
            @foreach($contrato->cuotas as $cuota)
                @php
                    $totalMonto += $cuota->monto;
                    $totalPagado += $cuota->monto_pagado;
                    $totalAbono += $cuota->abono_capital;
                    $totalSaldo += $cuota->saldo;
                    
                    // El saldo real pendiente es el saldo de la última cuota sin pagar
                    if (strtolower($cuota->estatus) !== 'pagado' && $cuota->saldo > $maxSaldoPendiente) {
                        $maxSaldoPendiente = $cuota->saldo;
                    }
                    
                    $statusClass = 'status-' . strtolower($cuota->estatus);
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
                    <td class="{{ $statusClass }}">{{ strtoupper($cuota->estatus) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f8fafc; font-weight: bold;">
                <td colspan="2" class="text-right">TOTALES</td>
                <td class="text-right">${{ number_format($totalMonto, 2) }}</td>
                <td class="text-right">${{ number_format($totalPagado, 2) }}</td>
                <td class="text-right">${{ number_format($totalAbono, 2) }}</td>
                <td class="text-right">${{ number_format($totalSaldo, 2) }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <table class="summary">
        <tr>
            <td class="info-label text-right">Capital Original Estimado</td>
            <td class="text-right">${{ number_format($contrato->capital + $totalAbono, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label text-right">Abonos a Capital Totales</td>
            <td class="text-right">${{ number_format($totalAbono, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label text-right" style="font-size: 14px; background: #e2e8f0;">CAPITAL ACTUAL</td>
            <td class="text-right" style="font-size: 14px; font-weight: bold; background: #e2e8f0;">${{ number_format($contrato->capital, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label text-right">Cuotas Pendientes</td>
            <td class="text-right">${{ number_format($maxSaldoPendiente, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label text-right" style="font-size: 15px; background: #dbeafe; color: #1e3a8a;">TOTAL DEUDA </td>
            <td class="text-right" style="font-size: 15px; font-weight: bold; background: #dbeafe; color: #1e3a8a;">${{ number_format($maxSaldoPendiente, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i A') }} — Sistema de Inventario y Cobranza
    </div>

</body>
</html>
