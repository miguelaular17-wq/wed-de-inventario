<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contratos</title>
    <style>
        @page { margin: 22px 24px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e293b; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #1a4273; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { border: none; vertical-align: middle; padding: 0; }
        .header img { height: 52px; }
        .header h1 { margin: 0; font-size: 15px; color: #1a4273; }
        .header p { margin: 2px 0 0; color: #64748b; font-size: 8.5px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .kpis td { border: 1px solid #cbd5e1; background: #f1f5f9; text-align: center; padding: 6px; width: 16.6%; }
        .kpis span { display: block; font-size: 7px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .kpis strong { display: block; margin-top: 3px; font-size: 11px; color: #1a4273; }
        table.lista { width: 100%; border-collapse: collapse; }
        table.lista th { background: #1a4273; color: #fff; font-size: 7.5px; text-transform: uppercase; padding: 5px 4px; text-align: left; }
        table.lista td { border-bottom: 1px solid #e2e8f0; padding: 4px; }
        table.lista tr.vencido td { background: #fef2f2; }
        table.lista tr.pagado td { background: #f0fdf4; }
        .r, table.lista th.r { text-align: right; }
        .c, table.lista th.c { text-align: center; }
        .rojo { color: #dc2626; font-weight: bold; }
        .verde { color: #059669; font-weight: bold; }
        .badge { color: #fff; padding: 1px 6px; border-radius: 8px; font-size: 7px; font-weight: bold; }
        .total td { background: #e2e8f0; font-weight: bold; border-top: 2px solid #1a4273; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #94a3b8; }
        .footer .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
@php
    $dinero = fn ($n) => '$'.number_format((float) $n, 2);
    $colores = ['VENCIDO' => '#dc2626', 'PAGADO' => '#059669', 'LIQUIDADO' => '#475569', 'ACTIVO' => '#3b82f6'];
@endphp

<div class="footer">
    <span>Nexo PD · Contratos · generado el {{ now()->format('d/m/Y H:i') }}</span>
    <span class="pagina" style="float:right;"></span>
</div>

<table class="header">
    <tr>
        <td style="width:70px;">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</td>
        <td>
            <h1>Palacio de los Detalles — Contratos</h1>
            <p>
                {{ $totales['contratos'] }} contrato{{ $totales['contratos'] === 1 ? '' : 's' }} activo{{ $totales['contratos'] === 1 ? '' : 's' }}
                @foreach($filtros as $etiqueta => $valor) · {{ $etiqueta }}: <strong>{{ $valor }}</strong>@endforeach
            </p>
        </td>
        <td style="width:120px;text-align:right;color:#64748b;">Fecha<br><strong style="color:#1e293b;">{{ now()->format('d/m/Y') }}</strong></td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><span>Contratos</span><strong>{{ $totales['contratos'] }}</strong></td>
        <td><span>Capital</span><strong>{{ $dinero($totales['capital']) }}</strong></td>
        <td><span>Saldo pendiente</span><strong>{{ $dinero($totales['saldo']) }}</strong></td>
        <td><span>Vencidos</span><strong style="color:#dc2626;">{{ $totales['vencidos'] }}</strong></td>
        <td><span>Saldo vencido</span><strong style="color:#dc2626;">{{ $dinero($totales['saldo_vencido']) }}</strong></td>
        <td><span>Al día</span><strong style="color:#3b82f6;">{{ $totales['activos'] }}</strong></td>
    </tr>
</table>

<table class="lista">
    <thead>
        <tr>
            <th>Contrato</th>
            <th>Cliente</th>
            <th>Contacto</th>
            <th>Sede</th>
            <th class="r">Capital</th>
            <th class="r">Cuota</th>
            <th class="r">Saldo pend.</th>
            <th class="c">Días atraso</th>
            <th class="c">Estatus</th>
            <th class="c">Próx. venc.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($contratos as $c)
            <tr class="{{ strtolower($c->_estatus_general) }}">
                <td><strong>{{ $c->numero_contrato }}</strong></td>
                <td>{{ $c->cliente }}</td>
                <td>{{ $c->contacto ?: ($c->telefono ?: '—') }}</td>
                <td>{{ $c->sede ?: '—' }}</td>
                <td class="r">{{ $dinero($c->capital) }}</td>
                <td class="r">{{ $dinero($c->cuota_fija) }}</td>
                <td class="r {{ $c->_saldo_pendiente > 0 ? 'rojo' : 'verde' }}">{{ $dinero($c->_saldo_pendiente) }}</td>
                <td class="c {{ $c->_dias_atraso > 0 ? 'rojo' : '' }}">{{ $c->_dias_atraso > 0 ? $c->_dias_atraso.'d' : '—' }}</td>
                <td class="c"><span class="badge" style="background:{{ $colores[$c->_estatus_general] ?? '#3b82f6' }};">{{ $c->_estatus_general }}</span></td>
                <td class="c">{{ $c->_proxima_cuota?->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="c" style="padding:20px;color:#94a3b8;">No hay contratos con esos filtros.</td></tr>
        @endforelse
        @if($contratos->isNotEmpty())
            <tr class="total">
                <td colspan="4">Total ({{ $totales['contratos'] }})</td>
                <td class="r">{{ $dinero($totales['capital']) }}</td>
                <td></td>
                <td class="r">{{ $dinero($totales['saldo']) }}</td>
                <td colspan="3"></td>
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
