@php
    $usd = fn ($v) => '$'.number_format((float) $v, 2);
    $bs = fn ($v) => 'Bs. '.number_format((float) $v, 2);
    $fecha = fn ($v) => \Carbon\Carbon::parse($v)->format('d/m/Y');
    $netoUsd = $totales['ingresos_usd'] - $totales['egresos_usd'];
    $netoBs = $totales['ingresos_bs'] - $totales['egresos_bs'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Flujo de caja · Cobranza</title>
<style>
    @page { margin: 22px 24px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e293b; }
    h1 { font-size: 15px; margin: 0; color: #1e3a8a; }
    .sub { color: #64748b; font-size: 9px; margin-top: 2px; }
    .resumen { width: 100%; border-collapse: collapse; margin: 10px 0 12px; }
    .resumen td { border: 1px solid #e2e8f0; padding: 6px 8px; vertical-align: top; }
    .lbl { font-size: 7.5px; text-transform: uppercase; color: #64748b; letter-spacing: .4px; }
    .big { font-size: 13px; font-weight: bold; }
    .verde { color: #0f766e; }
    .rojo { color: #b91c1c; }
    h2 { font-size: 11px; margin: 12px 0 4px; }
    table.mov { width: 100%; border-collapse: collapse; }
    table.mov th { background: #f1f5f9; color: #475569; font-size: 7.5px; text-transform: uppercase; text-align: left; padding: 4px; border-bottom: 1.5px solid #cbd5e1; }
    table.mov td { padding: 3.5px 4px; border-bottom: 1px solid #eef2f6; vertical-align: top; }
    table.mov tr.total td { font-weight: bold; border-top: 1.5px solid #cbd5e1; background: #f8fafc; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #64748b; font-size: 7.5px; }
    .tag { font-size: 7px; padding: 1px 4px; border-radius: 3px; background: #eef2ff; color: #4338ca; }
    .pie { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; }
</style>
</head>
<body>
    <h1>Flujo de Caja · Cobranza</h1>
    <div class="sub">
        Del {{ $fecha($filtros['desde']) }} al {{ $fecha($filtros['hasta']) }}
        · Apartado: {{ $filtros['ambito'] !== '' ? $ambitos[$filtros['ambito']] : 'Todos' }}
        · Cuenta: {{ $filtros['cuenta'] !== '' ? str_replace('|', ' - ', $filtros['cuenta']) : 'Todas' }}
        @if($filtros['solo_cruces']) · Solo cruces @endif
    </div>

    <table class="resumen">
        <tr>
            <td style="width:18%;"><div class="lbl">Ingresos</div><div class="big verde">{{ $usd($totales['ingresos_usd']) }}</div><div class="muted">{{ $bs($totales['ingresos_bs']) }}</div></td>
            <td style="width:18%;"><div class="lbl">Egresos</div><div class="big rojo">{{ $usd($totales['egresos_usd']) }}</div><div class="muted">{{ $bs($totales['egresos_bs']) }}</div></td>
            <td style="width:18%;"><div class="lbl">Neto</div><div class="big {{ $netoUsd < 0 ? 'rojo' : '' }}">{{ $usd($netoUsd) }}</div><div class="muted">{{ $bs($netoBs) }}</div></td>
            <td>
                <div class="lbl">Por apartado (neto $)</div>
                @foreach($ambitos as $clave => $nombre)
                    @php $a = $totales['por_ambito'][$clave]; @endphp
                    <div>{{ $nombre }}: <strong>{{ $usd($a['ingresos_usd'] - $a['egresos_usd']) }}</strong>
                        <span class="muted">(+{{ $usd($a['ingresos_usd']) }} / −{{ $usd($a['egresos_usd']) }})</span></div>
                @endforeach
            </td>
            <td style="width:14%;">
                <div class="lbl">Movimientos</div><div class="big">{{ $cantidad }}</div>
                <div class="muted">Cruces: {{ $totales['cruces'] }}</div>
            </td>
        </tr>
    </table>

    @foreach([['INGRESOS', $ingresos, 'verde', $totales['ingresos_bs'], $totales['ingresos_usd']], ['EGRESOS', $egresos, 'rojo', $totales['egresos_bs'], $totales['egresos_usd']]] as [$titulo, $lista, $clase, $totBs, $totUsd])
        <h2 class="{{ $clase }}">{{ $titulo }} ({{ $lista->count() }})</h2>
        <table class="mov">
            <thead>
                <tr>
                    <th style="width:48px;">Fecha</th>
                    <th style="width:95px;">Cuenta</th>
                    <th style="width:150px;">Qué se paga</th>
                    <th>Concepto</th>
                    <th style="width:60px;">Ref.</th>
                    <th class="num" style="width:80px;">Monto Bs</th>
                    <th class="num" style="width:62px;">Monto USD</th>
                    <th style="width:50px;">Banco</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lista as $m)
                    @php $pago = $pagos[$m->id] ?? null; @endphp
                    <tr>
                        <td>{{ $m->fecha->format('d/m/Y') }}</td>
                        <td><strong>{{ $m->banco }}</strong><div class="muted">{{ $m->titular }}</div></td>
                        <td>
                            {{ $m->nombreAmbito() }}@if($m->esCruce()) <span class="tag">Cruce</span>@endif
                            @if($pago)<div><strong>{{ $pago['titulo'] }}</strong></div><div class="muted">{{ $pago['detalle'] }}</div>@endif
                        </td>
                        <td>
                            {{ $m->concepto }}
                            @if($m->contraparte)<div class="muted">{{ $m->esIngreso() ? 'Paga' : 'Para' }}: {{ $m->contraparte }}</div>@endif
                            @if(! $m->esIngreso() && $m->tipo_gasto)<div class="muted">{{ $m->tipo_gasto }}</div>@endif
                            @if($m->forma_pago)<div class="muted">{{ str_replace('_', ' ', $m->forma_pago) }}</div>@endif
                        </td>
                        <td>{{ $m->referencia ?: '-' }}</td>
                        <td class="num">{{ $bs($m->monto_bs) }}</td>
                        <td class="num">{{ $usd($m->monto_usd) }}@if((float) $m->tasa_cambio > 0)<div class="muted">tasa {{ number_format((float) $m->tasa_cambio, 2) }}</div>@endif</td>
                        <td>{{ $estadoBanco($m) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted" style="text-align:center;padding:8px;">Sin movimientos.</td></tr>
                @endforelse
                @if($lista->isNotEmpty())
                    <tr class="total">
                        <td colspan="5" style="text-align:right;">Total {{ mb_strtolower($titulo) }}</td>
                        <td class="num">{{ $bs($totBs) }}</td>
                        <td class="num">{{ $usd($totUsd) }}</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach

    <div class="pie">Generado el {{ now()->format('d/m/Y h:i a') }}@if($generadoPor) por {{ $generadoPor }}@endif</div>
</body>
</html>
