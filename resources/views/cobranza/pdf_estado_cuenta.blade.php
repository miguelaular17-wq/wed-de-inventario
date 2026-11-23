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
        tr.detalle td { background: #f8fafc; color: #475569; font-size: 7px; }
        tr.detalle-pag td { background: #ecfdf5; color: #166534; font-size: 7px; }
        tr.ajena td { background: #faf5ff; }
        .r, table.lista th.r { text-align: right; }
        .tag { color: #6d28d9; font-weight: bold; font-size: 7px; }
        .modo { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; vertical-align: middle; }
        .modo-res { background: #ffedd5; color: #9a3412; }
        .modo-det { background: #dbeafe; color: #1d4ed8; }
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
    $mMas = fn ($n) => $n === null || $n === '' || ! ((float) $n > 0) ? '' : '+ '.$m($n);
    $mMenos = fn ($n) => $n === null || $n === '' || ! ((float) $n > 0) ? '' : '− '.$m($n);
    $agrupar = function (array $lineas): array {
        $facs = [];
        $orden = [];
        $huerfanos = [];
        $visto = [];
        foreach ($lineas as $l) {
            if (($l['tipo'] ?? '') === 'abono') {
                $k = implode('|', [$l['numero_documento'] ?? '', $l['factura_padre'] ?? '', $l['fecha'] ?? '', $l['pagado'] ?? '', $l['descripcion'] ?? '']);
                if (isset($visto[$k])) {
                    continue;
                }
                $visto[$k] = true;
                $facKey = (string) ($l['factura_padre'] ?? '');
                if ($facKey !== '') {
                    if (! isset($facs[$facKey])) {
                        $orden[] = $facKey;
                        $facs[$facKey] = ['tipo' => 'factura', 'clave' => $facKey, 'items' => [], 'cabecera' => null, 'abonos' => []];
                    }
                    $facs[$facKey]['abonos'][] = $l;
                } else {
                    $huerfanos[] = ['tipo' => 'abono', 'mov' => $l];
                }
                continue;
            }
            $clave = (string) ($l['factura_padre'] ?? $l['numero_documento'] ?? '');
            if (! isset($facs[$clave])) {
                $orden[] = $clave;
                $facs[$clave] = ['tipo' => 'factura', 'clave' => $clave, 'items' => [], 'cabecera' => null, 'abonos' => []];
            }
            $facs[$clave]['items'][] = $l;
            if (($l['total_factura'] ?? null) !== null) {
                $facs[$clave]['cabecera'] = $l;
            }
        }
        $out = [];
        foreach ($orden as $clave) {
            $out[] = $facs[$clave];
        }

        return array_merge($out, $huerfanos);
    };
    $detallado = ! empty($detallado);
    $fmtCant = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2, ',', '.');
@endphp

<div class="footer">
    <span>Nexo PD · Estado de cuenta {{ $detallado ? 'detallado' : 'resumido' }} · generado el {{ now()->format('d/m/Y H:i') }}</span>
    <span class="pagina" style="float:right;"></span>
</div>

<table class="header">
    <tr>
        <td style="width:66px;">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</td>
        <td>
            <h1>Estado de cuenta <span class="modo {{ $detallado ? 'modo-det' : 'modo-res' }}">{{ $detallado ? 'Detallado' : 'Resumido' }}</span></h1>
            <p><strong>{{ $cliente ?: '—' }}</strong> · {{ $codigo_cliente }}</p>
        </td>
        <td style="width:190px; text-align:right; color:#64748b;">
            Corte del sistema<br><strong style="color:#1e293b;">{{ $fecha_sync ?: '—' }}</strong>
            @if(($totales['saldo_intermediado'] ?? 0) > 0)
                <div style="margin-top:3px; font-size:8px; color:#475569;">Propio {{ $m($totales['saldo_propio']) }} · Intermed. {{ $m($totales['saldo_intermediado']) }}</div>
            @endif
            @if(($totales['tasa_bcv'] ?? 0) > 1)
                <div style="margin-top:2px; font-size:8px;">BCV {{ number_format($totales['tasa_bcv'], 4, ',', '.') }} · Bs {{ number_format($totales['saldo_bs'], 2, ',', '.') }}</div>
            @endif
            <div class="saldo" style="margin-top:4px;">Saldo a pagar {{ $m($totales['saldo']) }}</div>
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
            <th class="r">Cargo (+)</th>
            <th class="r">Desc.</th>
            <th class="r">Tot. fac.</th>
            <th class="r">Abono (−)</th>
            <th class="r">Saldo acum.</th>
        </tr>
    </thead>
    <tbody>
        @php
            $bloques = ! empty($grupos) ? $grupos : [['propio' => true, 'titulo' => $cliente, 'lineas' => $lineas, 'totales' => $totales]];
            $varios = count($bloques) > 1;
        @endphp
        @forelse($bloques as $g)
            @php $saldoRun = 0.0; @endphp
            @if($varios)
                <tr class="grupo {{ $g['propio'] ? '' : 'grupo-ajeno' }}">
                    <td colspan="11">{{ $g['propio'] ? 'Facturas propias' : 'Como intermediario · a nombre de' }} — {{ $g['titulo'] }}</td>
                </tr>
            @endif
            @foreach($agrupar($g['lineas'] ?? []) as $bloque)
                @if(($bloque['tipo'] ?? '') === 'abono')
                    @php
                        $l = $bloque['mov'];
                        $saldoRun = round($saldoRun - (float) ($l['pagado'] ?? 0), 2);
                    @endphp
                    <tr class="abono">
                        <td>{{ $l['antiguedad'] }}</td>
                        <td>{{ $l['fecha'] }}</td>
                        <td>{{ $l['sede'] }}</td>
                        <td><strong style="color:#15803d;">PAG −</strong></td>
                        <td>{{ $l['numero_documento'] }}</td>
                        <td>{{ $l['descripcion'] }}</td>
                        <td class="r"></td>
                        <td class="r"></td>
                        <td class="r"></td>
                        <td class="r" style="color:#15803d; font-weight:bold;">{{ $mMenos($l['pagado']) }}</td>
                        <td class="r"><strong style="color:#b91c1c;">{{ $m($saldoRun) }}</strong></td>
                    </tr>
                @else
                    @php
                        $items = $bloque['items'] ?? [];
                        $abonosFac = $bloque['abonos'] ?? [];
                        $cab = $bloque['cabecera'] ?? ($items[0] ?? ($abonosFac[0] ?? []));
                        $cargo = round(array_sum(array_map(fn ($it) => (float) ($it['total'] ?? 0), $items)), 2);
                        $totFac = isset($cab['total_factura']) ? (float) $cab['total_factura'] : (float) $cargo;
                        $descMonto = ($cargo > $totFac + 0.05) ? round($cargo - $totFac, 2) : 0;
                        $descPct = ($descMonto > 0 && $cargo > 0) ? (int) round(($descMonto / $cargo) * 100) : 0;
                        $n = count($items);
                        $nPagos = count($abonosFac);
                        $totalPagos = round(array_sum(array_map(fn ($a) => (float) ($a['pagado'] ?? 0), $abonosFac)), 2);
                        $pagadoFac = isset($cab['pagado']) ? (float) $cab['pagado'] : 0.0;
                        $abonoEnFac = ($nPagos === 0 && $pagadoFac > 0.01) ? $pagadoFac : 0.0;
                        $resumen = $detallado
                            ? ($n <= 1 ? ($cab['descripcion'] ?? 'Factura') : ($n.' artículos'))
                            : ($n <= 1
                                ? ($cab['descripcion'] ?? 'Factura')
                                : $n.' artículos · '.($items[0]['descripcion'] ?? 'Factura'));
                        $num = $cab['numero_documento'] ?? $cab['factura_padre'] ?? ($bloque['clave'] ?? '');
                        $saldoRun = round($saldoRun + $totFac, 2);
                        $saldoTrasFac = $saldoRun;
                        if ($abonoEnFac > 0) {
                            $saldoRun = round($saldoRun - $abonoEnFac, 2);
                        }
                        $saldoMostrarFac = $abonoEnFac > 0 ? $saldoRun : $saldoTrasFac;
                    @endphp
                    <tr>
                        <td>{{ $cab['antiguedad'] ?? '' }}</td>
                        <td>{{ $cab['fecha'] ?? '' }}</td>
                        <td>{{ $cab['sede'] ?? '' }}</td>
                        <td><strong style="color:#b45309;">FAC +</strong></td>
                        <td>{{ $num }}</td>
                        <td>
                            {{ $resumen }}
                            @if(! $varios && ($cab['a_nombre_de'] ?? null))
                                <div class="tag">A nombre de {{ $cab['a_nombre_de'] }}</div>
                            @elseif($cab['intermediario'] ?? null)
                                <div class="tag">Intermediario: {{ $cab['intermediario'] }}</div>
                            @endif
                        </td>
                        <td class="r" style="color:#b45309; font-weight:bold;">{{ $mMas($cargo ?: $totFac) }}</td>
                        <td class="r" style="color:#7c3aed; font-weight:bold;">{{ $descPct >= 1 ? '− '.$m($descMonto).' ('.$descPct.'%)' : '' }}</td>
                        <td class="r">{{ $m($totFac) }}</td>
                        <td class="r" style="color:#15803d; font-weight:bold;">{{ $abonoEnFac > 0 ? $mMenos($abonoEnFac) : '' }}</td>
                        <td class="r"><strong style="color:#b91c1c;">{{ $m($saldoMostrarFac) }}</strong></td>
                    </tr>
                    @if($detallado && $n > 1)
                        @foreach($items as $it)
                            <tr class="detalle">
                                <td></td>
                                <td>{{ $it['fecha'] ?? '' }}</td>
                                <td></td>
                                <td></td>
                                <td>{{ $it['numero_documento'] ?? '' }}</td>
                                <td>
                                    {{ $it['descripcion'] ?? '' }}
                                    @if(($it['cantidad'] ?? null) !== null || ($it['precio'] ?? null) !== null)
                                        · {{ $fmtCant($it['cantidad'] ?? null) }} × {{ $m($it['precio'] ?? null) }}
                                    @endif
                                </td>
                                <td class="r">{{ $mMas($it['total'] ?? null) }}</td>
                                <td class="r"></td>
                                <td class="r"></td>
                                <td class="r"></td>
                                <td class="r"></td>
                            </tr>
                        @endforeach
                    @endif
                    @if($nPagos > 0)
                        @if($detallado)
                            @php $saldoPaso = $saldoTrasFac; @endphp
                            @foreach($abonosFac as $l)
                                @php
                                    $saldoPaso = round($saldoPaso - (float) ($l['pagado'] ?? 0), 2);
                                    $saldoRun = $saldoPaso;
                                @endphp
                                <tr class="abono {{ $nPagos > 1 ? 'detalle-pag' : '' }}">
                                    <td>{{ $l['antiguedad'] ?? '' }}</td>
                                    <td>{{ $l['fecha'] ?? '' }}</td>
                                    <td>{{ $l['sede'] ?? '' }}</td>
                                    <td><strong style="color:#15803d;">PAG −</strong></td>
                                    <td>{{ $l['numero_documento'] ?? '' }}</td>
                                    <td>{{ $l['descripcion'] ?? 'Pago / Abono' }}</td>
                                    <td class="r"></td>
                                    <td class="r"></td>
                                    <td class="r"></td>
                                    <td class="r" style="color:#15803d; font-weight:bold;">{{ $mMenos($l['pagado'] ?? null) }}</td>
                                    <td class="r"><strong style="color:#b91c1c;">{{ $m($saldoPaso) }}</strong></td>
                                </tr>
                            @endforeach
                        @else
                            @php $saldoRun = round($saldoRun - $totalPagos, 2); @endphp
                            <tr class="abono">
                                <td>{{ $abonosFac[0]['antiguedad'] ?? '' }}</td>
                                <td>{{ $abonosFac[0]['fecha'] ?? '' }}</td>
                                <td>{{ $abonosFac[0]['sede'] ?? '' }}</td>
                                <td><strong style="color:#15803d;">PAG −</strong></td>
                                <td>{{ $nPagos === 1 ? ($abonosFac[0]['numero_documento'] ?? '') : '' }}</td>
                                <td>{{ $nPagos > 1 ? ($nPagos.' pagos aplicados') : ($abonosFac[0]['descripcion'] ?? 'Pago / Abono') }}</td>
                                <td class="r"></td>
                                <td class="r"></td>
                                <td class="r"></td>
                                <td class="r" style="color:#15803d; font-weight:bold;">{{ $mMenos($totalPagos) }}</td>
                                <td class="r"><strong style="color:#b91c1c;">{{ $m($saldoRun) }}</strong></td>
                            </tr>
                        @endif
                    @endif
                @endif
            @endforeach
            @if($varios)
                <tr class="sub {{ $g['propio'] ? '' : 'grupo-ajeno' }}">
                    <td colspan="6">Subtotal {{ $g['titulo'] }}</td>
                    <td class="r" style="color:#b45309;">{{ $mMas($g['totales']['cargos']) }}</td>
                    <td></td>
                    <td></td>
                    <td class="r" style="color:#15803d;">{{ $mMenos($g['totales']['abonos']) }}</td>
                    <td class="r" style="color:#b91c1c;">{{ $m($g['totales']['saldo']) }}</td>
                </tr>
            @endif
        @empty
            <tr><td colspan="11" style="text-align:center;padding:16px;color:#94a3b8;">No hay renglones sincronizados para este cliente.</td></tr>
        @endforelse
        @if($lineas)
            <tr class="tot">
                <td colspan="6">{{ $varios ? 'Total general' : 'Totales' }}</td>
                <td class="r" style="color:#b45309;">{{ $mMas($totales['cargos']) }}</td>
                <td></td>
                <td></td>
                <td class="r" style="color:#15803d;">{{ $mMenos($totales['abonos']) }}</td>
                <td class="r" style="color:#b91c1c;">{{ $m($totales['saldo']) }}</td>
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
