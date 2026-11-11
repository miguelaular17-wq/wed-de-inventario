<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta</title>
    <style>
        @page { margin: 22px 24px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1e293b; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #b45309; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { border: none; vertical-align: middle; padding: 0; }
        .header img { height: 50px; }
        .header h1 { margin: 0; font-size: 15px; color: #92400e; }
        .header p { margin: 2px 0 0; color: #475569; font-size: 9px; }
        .rel { margin: 0 0 8px; padding: 6px 8px; background: #f5f3ff; border: 1px solid #ddd6fe; color: #5b21b6; font-weight: bold; }
        table.lista { width: 100%; border-collapse: collapse; }
        table.lista th { background: #92400e; color: #fff; font-size: 7px; text-transform: uppercase; padding: 4px 3px; text-align: left; }
        table.lista td { border-bottom: 1px solid #e2e8f0; padding: 3px; vertical-align: top; }
        tr.abono td { background: #f0fdf4; }
        tr.ajena td { background: #faf5ff; }
        .r, table.lista th.r { text-align: right; }
        .tag { color: #6d28d9; font-weight: bold; font-size: 7px; }
        .tot td { font-weight: bold; background: #f1f5f9; border-top: 2px solid #92400e; }
        tr.grupo td { background: #fff7ed; color: #9a3412; font-weight: bold; font-size: 9px; padding: 6px 4px; border-bottom: 2px solid #fdba74; }
        tr.grupo.grupo-ajeno td { background: #f5f3ff; color: #6d28d9; border-bottom-color: #c4b5fd; }
        tr.sub td { background: #fff7ed; font-weight: bold; border-bottom: 8px solid #fff; }
        tr.sub.grupo-ajeno td { background: #f5f3ff; }
        .resumen { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .resumen td { padding: 4px 6px; border: none; }
        .saldo { font-size: 13px; font-weight: bold; color: #b91c1c; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #94a3b8; }
        .footer .pagina:after { content: "Página " counter(page); }
    </style>
</head>
<body>
@php
    $m = fn ($n) => $n === null || $n === '' ? '' : '$'.number_format((float) $n, 2, ',', '.');
    $q = fn ($n) => $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="footer">
    <span>Nexo PD · Estado de cuenta · generado el {{ now()->format('d/m/Y H:i') }}</span>
    <span class="pagina" style="float:right;"></span>
</div>

<table class="header">
    <tr>
        <td style="width:66px;">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</td>
        <td>
            <h1>Estado de cuenta</h1>
            <p><strong>{{ $cliente ?: '—' }}</strong> · {{ $codigo_cliente }}</p>
        </td>
        <td style="width:150px; text-align:right; color:#64748b;">
            Corte del sistema<br><strong style="color:#1e293b;">{{ $fecha_sync ?: '—' }}</strong>
        </td>
    </tr>
</table>

@if(! empty($intermedia_de))
    <div class="rel">Intermediario de: {{ implode(', ', $intermedia_de) }}</div>
@endif
@if(! empty($intermediado_por))
    <div class="rel">Facturas llevadas por: {{ implode(', ', $intermediado_por) }}</div>
@endif

<table class="lista">
    <thead>
        <tr>
            <th>Antig.</th>
            <th>Fecha</th>
            <th>Sede</th>
            <th>Doc</th>
            <th>Nº</th>
            <th>Descripción</th>
            <th class="r">Cant.</th>
            <th class="r">Precio</th>
            <th class="r">Total</th>
            <th class="r">Pagado</th>
            <th class="r">Tot. fac.</th>
            <th class="r">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @php
            $bloques = ! empty($grupos) ? $grupos : [['propio' => true, 'titulo' => $cliente, 'lineas' => $lineas, 'totales' => $totales]];
            $varios = count($bloques) > 1;
        @endphp
        @forelse($bloques as $g)
            @if($varios)
                <tr class="grupo {{ $g['propio'] ? '' : 'grupo-ajeno' }}">
                    <td colspan="12">{{ $g['propio'] ? 'Facturas propias' : 'Como intermediario · a nombre de' }} — {{ $g['titulo'] }}</td>
                </tr>
            @endif
        @foreach($g['lineas'] as $l)
            <tr class="{{ $l['tipo'] === 'abono' ? 'abono' : '' }}">
                <td>{{ $l['antiguedad'] }}</td>
                <td>{{ $l['fecha'] }}</td>
                <td>{{ $l['sede'] }}</td>
                <td><strong>{{ $l['tipo_doc'] }}</strong></td>
                <td>{{ $l['numero_documento'] }}</td>
                <td>
                    {{ $l['descripcion'] }}
                    @if(! $varios && $l['total_factura'] !== null && ($l['a_nombre_de'] ?? null))
                        <div class="tag">A nombre de {{ $l['a_nombre_de'] }}</div>
                    @elseif($l['total_factura'] !== null && ($l['intermediario'] ?? null))
                        <div class="tag">Intermediario: {{ $l['intermediario'] }}</div>
                    @endif
                </td>
                <td class="r">{{ $q($l['cantidad']) }}</td>
                <td class="r">{{ $m($l['precio']) }}</td>
                <td class="r">{{ $m($l['total']) }}</td>
                <td class="r">{{ $m($l['pagado']) }}</td>
                <td class="r">{{ $m($l['total_factura']) }}</td>
                <td class="r"><strong>{{ $m($l['saldo_acumulado'] ?? $l['saldo_factura']) }}</strong></td>
            </tr>
        @endforeach
            @if($varios)
                <tr class="sub {{ $g['propio'] ? '' : 'grupo-ajeno' }}">
                    <td colspan="8">Subtotal {{ $g['titulo'] }}</td>
                    <td class="r">{{ $m($g['totales']['cargos']) }}</td>
                    <td class="r">{{ $m($g['totales']['abonos']) }}</td>
                    <td></td>
                    <td class="r">{{ $m($g['totales']['saldo']) }}</td>
                </tr>
            @endif
        @empty
            <tr><td colspan="12" style="text-align:center;padding:16px;color:#94a3b8;">No hay renglones sincronizados para este cliente.</td></tr>
        @endforelse
        @if($lineas)
            <tr class="tot">
                <td colspan="8">{{ $varios ? 'Total general' : 'Totales' }}</td>
                <td class="r">{{ $m($totales['cargos']) }}</td>
                <td class="r">{{ $m($totales['abonos']) }}</td>
                <td></td>
                <td class="r">{{ $m($totales['saldo']) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="resumen">
    <tr>
        <td style="color:#475569;">
            Por sede:
            @forelse($por_sede as $s)
                {{ $s['sede'] }} {{ $m($s['saldo']) }}@if(! $loop->last) · @endif
            @empty
                —
            @endforelse
        </td>
        <td style="text-align:right;">
            @if(($totales['saldo_intermediado'] ?? 0) > 0)
                <div style="color:#475569;">Propio {{ $m($totales['saldo_propio']) }} · Como intermediario {{ $m($totales['saldo_intermediado']) }}</div>
            @endif
            @if(($totales['tasa_bcv'] ?? 0) > 1)
                <div style="color:#64748b;">BCV {{ number_format($totales['tasa_bcv'], 4, ',', '.') }} · Bs {{ number_format($totales['saldo_bs'], 2, ',', '.') }}</div>
            @endif
            <div class="saldo">Saldo a pagar {{ $m($totales['saldo']) }}</div>
        </td>
    </tr>
</table>
</body>
</html>
