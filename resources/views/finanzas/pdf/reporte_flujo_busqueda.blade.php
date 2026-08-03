<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Flujo de Caja</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #333; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #1e3a8a; }
        .header p { margin: 5px 0 0; color: #475569; font-size: 12px; }
        .section-title { font-size: 14px; font-weight: bold; color: #1e40af; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; word-wrap: break-word; }
        th { background-color: #f8fafc; color: #1e293b; font-weight: bold; font-size: 9px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: #dc2626; }
        .text-success { color: #166534; }
        .muted { color: #64748b; font-size: 8px; }
        .total-row { background-color: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <h2>Reporte de Flujo de Caja</h2>
    <p>Desde: {{ date('d/m/Y', strtotime($data['fecha_desde'])) }} - Hasta: {{ date('d/m/Y', strtotime($data['fecha_hasta'])) }}</p>
    @if($data['q'])
        <p style="font-size: 10px; color: #64748b;">
            Filtros aplicados: [Búsqueda: "{{ $data['q'] }}"]
        </p>
    @endif
</div>

<!-- EGRESOS REALIZADOS -->
@if($data['egresos']->count() > 0)
<div class="section-title">EGRESOS REALIZADOS</div>
<table>
    <thead>
        <tr>
            <th style="width: 8%">Fecha</th>
            <th style="width: 22%">Origen ➔ Destino (Beneficiario)</th>
            <th style="width: 15%">Tipo Gasto</th>
            <th style="width: 15%">Motivo</th>
            <th style="width: 10%" class="text-right">USD</th>
            <th style="width: 10%" class="text-right">Dif. Camb.</th>
            <th style="width: 10%" class="text-right">BS</th>
            <th style="width: 10%" class="text-right">Comisión</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['egresos'] as $mov)
        <tr>
            <td>{{ date('d/m/y', strtotime($mov->fecha)) }}</td>
            <td>
                <strong>{{ $mov->banco }}</strong><br>
                <span class="muted">{{ $mov->titular }}</span>
                @if($mov->banco_receptor || $mov->titular_receptor)
                    <br><span style="color: #94a3b8;">➔</span> <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                    <span class="muted">{{ $mov->titular_receptor }}</span>
                @endif
            </td>
            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
            <td>{{ $mov->motivo ?: '-' }}</td>
            <td class="text-right">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
            <td class="text-right text-danger">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTALES EGRESOS</td>
            <td class="text-right">${{ number_format($data['tot_egresos_usd'], 2) }}</td>
            <td class="text-right text-danger">${{ number_format($data['tot_egresos_dif'], 2) }}</td>
            <td class="text-right">Bs.{{ number_format($data['tot_egresos_bs'], 2) }}</td>
            <td class="text-right">Bs.{{ number_format($data['tot_egresos_com'], 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

<!-- OTROS EGRESOS -->
@if($data['otros']->count() > 0)
<div class="section-title">OTROS EGRESOS (AVANCES Y CAMBIOS)</div>
<table>
    <thead>
        <tr>
            <th style="width: 8%">Fecha</th>
            <th style="width: 22%">Origen ➔ Destino (Beneficiario)</th>
            <th style="width: 15%">Tipo Gasto</th>
            <th style="width: 15%">Motivo</th>
            <th style="width: 10%" class="text-right">USD</th>
            <th style="width: 10%" class="text-right">Dif. Camb.</th>
            <th style="width: 10%" class="text-right">BS</th>
            <th style="width: 10%" class="text-right">Comisión</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['otros'] as $mov)
        <tr>
            <td>{{ date('d/m/y', strtotime($mov->fecha)) }}</td>
            <td>
                <strong>{{ $mov->banco }}</strong><br>
                <span class="muted">{{ $mov->titular }}</span>
                @if($mov->banco_receptor || $mov->titular_receptor)
                    <br><span style="color: #94a3b8;">➔</span> <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                    <span class="muted">{{ $mov->titular_receptor }}</span>
                @endif
            </td>
            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
            <td>{{ $mov->motivo ?: '-' }}</td>
            <td class="text-right">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
            <td class="text-right text-danger">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTALES OTROS EGRESOS</td>
            <td class="text-right">${{ number_format($data['tot_otros_usd'], 2) }}</td>
            <td class="text-right text-danger">${{ number_format($data['tot_otros_dif'], 2) }}</td>
            <td class="text-right">Bs.{{ number_format($data['tot_otros_bs'], 2) }}</td>
            <td class="text-right">Bs.{{ number_format($data['tot_otros_com'], 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

<!-- TRASLADOS -->
@if($data['traslados']->count() > 0)
<div class="section-title">TRASLADOS</div>
<table>
    <thead>
        <tr>
            <th style="width: 8%">Fecha</th>
            <th style="width: 22%">Banco Emisor ➔ Banco Receptor</th>
            <th style="width: 15%">Tipo Gasto</th>
            <th style="width: 15%">Motivo</th>
            <th style="width: 10%" class="text-right">USD</th>
            <th style="width: 10%" class="text-right">Dif. Camb.</th>
            <th style="width: 10%" class="text-right">BS</th>
            <th style="width: 10%" class="text-right">Comisión</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['traslados'] as $mov)
        <tr>
            <td>{{ date('d/m/y', strtotime($mov->fecha)) }}</td>
            <td>
                <strong>{{ $mov->banco }}</strong><br>
                <span class="muted">{{ $mov->titular }}</span>
                @if($mov->banco_receptor || $mov->titular_receptor)
                    <br><span style="color: #94a3b8;">➔</span> <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                    <span class="muted">{{ $mov->titular_receptor }}</span>
                @endif
            </td>
            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
            <td>{{ $mov->motivo ?: '-' }}</td>
            <td class="text-right">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
            <td class="text-right text-danger">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
            <td class="text-right">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTALES TRASLADOS</td>
            <td class="text-right">${{ number_format($data['tot_traslados_usd'], 2) }}</td>
            <td class="text-right text-danger">-</td>
            <td class="text-right">Bs.{{ number_format($data['tot_traslados_bs'], 2) }}</td>
            <td class="text-right">Bs.{{ number_format($data['tot_traslados_com'], 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

<!-- EGRESOS DIVISAS -->
@if($data['divisas']->count() > 0)
<div class="section-title">EGRESOS EN DIVISAS</div>
<table>
    <thead>
        <tr>
            <th style="width: 8%">Fecha</th>
            <th style="width: 22%">Origen ➔ Destino (Beneficiario)</th>
            <th style="width: 15%">Tipo Gasto</th>
            <th style="width: 15%">Motivo</th>
            <th style="width: 10%" class="text-right">USD</th>
            <th style="width: 10%" class="text-right">Dif. Camb.</th>
            <th style="width: 10%" class="text-right">BS</th>
            <th style="width: 10%" class="text-right">Comisión</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['divisas'] as $mov)
        <tr>
            <td>{{ date('d/m/y', strtotime($mov->fecha)) }}</td>
            <td>
                <strong>{{ $mov->banco }}</strong><br>
                <span class="muted">{{ $mov->titular }}</span>
                @if($mov->banco_receptor || $mov->titular_receptor)
                    <br><span style="color: #94a3b8;">➔</span> <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                    <span class="muted">{{ $mov->titular_receptor }}</span>
                @endif
            </td>
            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
            <td>{{ $mov->motivo ?: '-' }}</td>
            <td class="text-right">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
            <td class="text-right text-danger">-</td>
            <td class="text-right">-</td>
            <td class="text-right">-</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4" class="text-right">TOTALES DIVISAS</td>
            <td class="text-right">${{ number_format($data['tot_divisas_usd'], 2) }}</td>
            <td class="text-right text-danger">-</td>
            <td class="text-right">-</td>
            <td class="text-right">-</td>
        </tr>
    </tbody>
</table>
@endif

@if($data['egresos']->count() == 0 && $data['otros']->count() == 0 && $data['traslados']->count() == 0 && $data['divisas']->count() == 0)
    <p style="text-align: center; color: #64748b; margin-top: 40px;">No se encontraron registros para los filtros seleccionados.</p>
@endif

</body>
</html>
