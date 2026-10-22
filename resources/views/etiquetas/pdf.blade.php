<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas de precio</title>
@php
    $Svc = \App\Services\EtiquetasPrecioService::class;

    $tipo = $tipo ?? 'clasica';
    $web = $web ?? '';
    $logosMarca = $logosMarca ?? [];
    $W = (float) $tamano['ancho'];
    $H = (float) $tamano['alto'];
    $sep = $Svc::SEPARACION;
    $base = $Svc::TAMANOS[$tipo] ?? $Svc::TAMANOS['clasica'];
    // El diseño se dibujó a la medida por defecto; con otra medida se escala.
    $k = min($W / $base['ancho'], $H / $base['alto']);
    $m = fn (float $mm) => $mm * $k;
    $fs = fn (array $pts) => array_map(fn ($p) => round($p * $k, 1), $pts);
    $ptMm = fn (float $p) => $p * 0.3528;

    $ajustados = [];
    $ajustar = function (string $clave, string $texto, float $ancho, float $alto, array $tamanos, int $maxLineas = 3, bool $mayusculas = true) use (&$ajustados, $ajustarNombre) {
        return $ajustados[$clave] ??= $ajustarNombre($texto, $ancho, $alto, $tamanos, $maxLineas, $mayusculas);
    };
    $fit = fn (string $clave, string $texto, float $ancho, array $tamanos) => $ajustar('fit' . $clave . md5($texto), $texto, $ancho, 1000, $tamanos, 1, false);
    $gridW = $tamano['columnas'] * ($W + $sep) - $sep;
    $offX = max(0, ($Svc::HOJA_UTIL['ancho'] - $gridW) / 2);
    $pos = function (int $i) use ($tamano, $W, $H, $sep, $offX) {
        $col = $i % $tamano['columnas'];
        $row = intdiv($i, $tamano['columnas']);
        return [$offX + $col * ($W + $sep), $row * ($H + $sep)];
    };
    $hojaH = $tamano['filas'] * ($H + $sep) - $sep;
    $lineas = function (array $aj, float $left, float $top, string $extra = '') use ($ptMm) {
        $html = '';
        foreach ($aj['lineas'] as $li => $l) {
            $html .= '<div class="abs linea" style="left: ' . $left . 'mm; top: ' . ($top + $li * $ptMm($aj['alto_linea'])) . 'mm; font-size: ' . $aj['fs'] . 'pt; line-height: ' . $aj['alto_linea'] . 'pt; height: ' . $aj['alto_linea'] . 'pt;' . $extra . '">' . e($l) . '</div>';
        }
        return $html;
    };
    $altoLineas = fn (array $aj) => count($aj['lineas']) * $ptMm($aj['alto_linea']);

    $pad = $m($tipo === 'pc' ? 3 : 2.4);
    $inW = $W - 2 * $pad;
    $radio = $m(3);

    // Clásica
    $topH = $m(13);
    $gap = $m(1.6);
    $nameLeft = $pad + $topH + $gap;
    $nameW = $W - $nameLeft - $pad;
    $nomPadX = $m(2);
    $tagH = $ptMm(5 * $k) + $m(1.5);
    $nomAnchoTxt = $nameW - $nomPadX - $m(1.6);
    $nomAltoTxt = $topH - $tagH - $m(0.8);
    $precioTop = $pad + $topH + $gap;
    $footH = $m(3);
    $precioH = $H - $precioTop - $footH - $pad - $m(0.6);
    $boxGap = $m(1.6);
    $boxW = ($inW - $boxGap) / 2;
    $headH1 = $m(7.2);
    $headH2 = $m(11.6);
    $cabFs = $fs([13, 12, 11, 10, 9]);

    // Precios de locura
    $lzW = $m(27);
    $lzPanelL = $pad + $lzW + $m(1.8);
    $lzPanelW = $W - $lzPanelL - $pad;
    $lzPanelH = $H - 2 * $pad;

    // PC / Laptop
    $pcCajaH = $m(20);
    $pcPillTop = $pad + $m(17.5);
    $pcPillH = $m(17);
    $pcPanelTop = $pcPillTop + $pcPillH + $m(2.6);
    $pcPanelH = $H - $pcPanelTop - $pad - $m(4.4);
    $pcColW = ($inW - $m(8)) / 2;
    $pcIcono = [
        'modelo' => '◆', 'procesador' => '⚙', 'grafica' => '▦', 'pantalla' => '▭',
        'ram' => '▤', 'almacenamiento' => '◉', 'sistema' => '⊞',
    ];
@endphp
<style>
    @page { margin: 7mm; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
    .hoja { position: relative; width: 100%; height: {{ $hojaH }}mm; }
    .salto { page-break-after: always; }
    .abs { position: absolute; }
    .b { font-weight: bold; }
    .nw { white-space: nowrap; }
    .centro { text-align: center; }
    .der { text-align: right; }
    .etq {
        position: absolute; width: {{ $W }}mm; height: {{ $H }}mm; overflow: hidden;
        border-radius: {{ $radio }}mm; background-color: #9333ea;
        @if($fondo) background-image: url('{{ $fondo }}'); background-repeat: no-repeat; @endif
    }
    .blanco { background: #fff; border-radius: {{ $m(2) }}mm; overflow: hidden; }
    .linea { white-space: nowrap; font-weight: bold; color: #0f172a; }
    .num { left: 0; width: 100%; text-align: center; font-weight: bold; line-height: 1; white-space: nowrap; letter-spacing: -0.3pt; }

    .caja { top: {{ $precioTop }}mm; height: {{ $precioH }}mm; }
    .caja .h { left: 0; top: 0; width: 100%; background: #1e1b4b; color: #fff; font-weight: bold; }
    .caja .h div { left: 0; width: 100%; text-align: center; white-space: nowrap; line-height: 1; }
    .caja .h span { color: #fde047; }
    .caja.mayor .h { background: #facc15; color: #1e1b4b; }
    .caja.mayor .h span { color: #9d174d; }
    .caja.mayor .marco { left: 0; top: 0; right: 0; bottom: 0; border: {{ $m(0.7) }}mm solid #facc15; border-radius: {{ $m(2) }}mm; }
    .ref { left: {{ $m(1.8) }}mm; font-size: {{ round(5.5 * $k, 1) }}pt; font-weight: bold; color: #64748b; }
    .ahorro { left: 0; width: 100%; text-align: center; font-size: {{ round(5.3 * $k, 1) }}pt; font-weight: bold; color: #be185d; }
    .pie { top: {{ $H - $pad - $footH + $m(0.4) }}mm; color: #fff; font-weight: bold; white-space: nowrap; }

    .lz-de { left: {{ $pad }}mm; width: {{ $lzW }}mm; color: #fff; letter-spacing: 0.3pt; }
    .lz-locura { left: {{ $pad }}mm; width: {{ $lzW }}mm; background: #3b0764; border-radius: {{ $m(1.8) }}mm; border-bottom: {{ $m(0.8) }}mm solid #facc15; }
    .lz-cinta { left: {{ $pad + $m(1) }}mm; width: {{ $lzW - $m(2) }}mm; background: #facc15; border-radius: {{ $m(1) }}mm; }
    .lz-cinta div { left: 0; width: 100%; color: #9d174d; }
    .lz-panel { left: {{ $lzPanelL }}mm; top: {{ $pad }}mm; width: {{ $lzPanelW }}mm; height: {{ $lzPanelH }}mm; }
    .lz-pill { left: {{ $m(2.2) }}mm; top: {{ $m(2.2) }}mm; width: {{ $m(15) }}mm; height: {{ $m(4) }}mm; background: #7c3aed; border-radius: {{ $m(2) }}mm; }
    .lz-pill div { left: 0; width: 100%; top: {{ $m(0.9) }}mm; font-size: {{ round(6.3 * $k, 1) }}pt; color: #fff; letter-spacing: 0.7pt; }

    .pc-caja { left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $inW }}mm; height: {{ $pcCajaH }}mm; background: #4c1d95; border-radius: {{ $radio }}mm; }
    .pc-pill { left: {{ $pad }}mm; top: {{ $pcPillTop }}mm; width: {{ $inW - $m(1.4) }}mm; height: {{ $pcPillH }}mm; background: #db2777; border-radius: {{ $m(3.5) }}mm; border: {{ $m(0.7) }}mm solid #fff; }
    .pc-panel { left: {{ $pad }}mm; top: {{ $pcPanelTop }}mm; width: {{ $inW }}mm; height: {{ $pcPanelH }}mm; border-radius: {{ $radio }}mm; }
    .pc-ico { background: #db2777; }
    .pc-ico div { left: 0; width: 100%; color: #fff; font-weight: normal; text-align: center; }
    .pc-pie { top: {{ $H - $pad - $m(3.2) }}mm; color: #fff; font-size: {{ round(6 * $k, 1) }}pt; font-weight: bold; white-space: nowrap; }
</style>
</head>
<body>
@foreach($paginas as $fichas)
    <div class="hoja {{ $loop->last ? '' : 'salto' }}">
        @foreach($fichas as $i => $e)
            @php [$x, $y] = $pos($i); @endphp
            <div class="etq" style="left: {{ $x }}mm; top: {{ $y }}mm;">
            @if($tipo === 'locura')
                @php
                    $lzLogoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    $lzMarca = trim((string) ($e['specs']['marca'] ?? ''));
                    $conMarca = $lzLogoM !== null || $lzMarca !== '';
                    $L = $conMarca
                        ? ['logo' => 9.5, 'de' => 10.2, 'deFs' => [9, 8.5, 8], 'loc' => 14.4, 'locH' => 8.6, 'locFs' => [16, 15, 14, 13], 'cinta' => 24, 'cintaH' => 6, 'cintaFs' => 4.6]
                        : ['logo' => 13, 'de' => 14.2, 'deFs' => [10, 9.5, 9, 8.5, 8], 'loc' => 19.4, 'locH' => 10, 'locFs' => [18, 17, 16, 15, 14], 'cinta' => 31, 'cintaH' => 6.6, 'cintaFs' => 5];
                    $de = $fit('lzde' . ($conMarca ? 1 : 0), 'PRECIOS DE', $lzW - $m(1), $fs($L['deFs']));
                    $loc = $fit('lzloc' . ($conMarca ? 1 : 0), 'LOCURA', $lzW - $m(2), $fs($L['locFs']));
                    $cod = $fit('lzcod' . $e['id'], 'Cód. ' . $e['codigo'], $lzW, $fs([5, 4.6, 4.2, 3.8]));
                    if ($lzLogoM) {
                        $mH = $m(6.2);
                        $mW = min($lzW - $m(6), $mH * $lzLogoM['ratio']);
                        $mH = $mW / $lzLogoM['ratio'];
                    }
                    $precio = $fit('lznum', $e['unidad_fmt'], $lzPanelW - $m(5), $fs([26, 24, 22, 20, 18, 16, 14]));
                    $mayor = $e['con_mayor'] ? $fit('lzmay', $e['cantidad_mayor'] . '+ PZAS: REF ' . $e['mayor_fmt'] . ' C/U', $lzPanelW - $m(3), $fs([6.5, 6, 5.5, 5, 4.5])) : null;
                    $nomTop = $m(25);
                    $nomH = $lzPanelH - $nomTop - $m(4.6);
                    $nom = $ajustar('lz' . $e['id'], $e['nombre'], $lzPanelW - $m(5), $nomH, $fs([9, 8.5, 8, 7.5, 7, 6.5, 6, 5.5]));
                    $webFit = $fit('lzweb', $web, $lzPanelW - $m(3), $fs([5, 4.6, 4.2, 4]));
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad + ($lzW - $m($L['logo'])) / 2 }}mm; top: {{ $pad }}mm; width: {{ $m($L['logo']) }}mm; height: {{ $m($L['logo']) }}mm;">@endif
                <div class="abs lz-de b centro nw" style="top: {{ $pad + $m($L['de']) }}mm; font-size: {{ $de['fs'] }}pt;">PRECIOS DE</div>
                <div class="abs lz-locura" style="top: {{ $pad + $m($L['loc']) }}mm; height: {{ $m($L['locH']) }}mm;">
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ ($m($L['locH']) - $ptMm($loc['fs'])) / 2 - $m(0.4) }}mm; font-size: {{ $loc['fs'] }}pt; color: #facc15;">LOCURA</div>
                </div>
                <div class="abs lz-cinta" style="top: {{ $pad + $m($L['cinta']) }}mm; height: {{ $m($L['cintaH']) }}mm;">
                    <div class="abs b centro nw" style="top: {{ $m(0.6) }}mm; font-size: {{ round($L['cintaFs'] * $k, 1) }}pt;">PRECIOS BAJOS</div>
                    <div class="abs b centro nw" style="top: {{ $m($L['cintaH']) / 2 }}mm; font-size: {{ round($L['cintaFs'] * $k, 1) }}pt;">TODO EL AÑO</div>
                </div>
                @if($conMarca)
                    <div class="abs blanco" style="left: {{ $pad + $m(1) }}mm; top: {{ $pad + $m(31) }}mm; width: {{ $lzW - $m(2) }}mm; height: {{ $m(8.8) }}mm;">
                        @if($lzLogoM)
                            <img class="abs" src="{{ $lzLogoM['src'] }}" alt="" style="left: {{ ($lzW - $m(2) - $mW) / 2 }}mm; top: {{ ($m(8.8) - $mH) / 2 }}mm; width: {{ $mW }}mm; height: {{ $mH }}mm;">
                        @else
                            @php $mf = $fit('lzmt', mb_strtoupper($lzMarca), $lzW - $m(4), $fs([11, 10, 9, 8, 7, 6])); @endphp
                            <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ ($m(8.8) - $ptMm($mf['fs'])) / 2 - $m(0.3) }}mm; font-size: {{ $mf['fs'] }}pt; color: #1e1b4b; letter-spacing: 0.6pt;">{{ $mf['lineas'][0] ?? '' }}</div>
                        @endif
                    </div>
                @endif
                <div class="abs b centro nw" style="left: {{ $pad }}mm; width: {{ $lzW }}mm; top: {{ $H - $pad - $m(2.4) }}mm; font-size: {{ $cod['fs'] }}pt; color: #fff;">{{ $cod['lineas'][0] ?? '' }}</div>

                <div class="abs lz-panel blanco">
                    <div class="abs lz-pill"><div class="abs b centro nw">PRECIO</div></div>
                    <div class="abs b der nw" style="left: 0; width: {{ $lzPanelW - $m(2.4) }}mm; top: {{ $m(3.2) }}mm; font-size: {{ round(4.8 * $k, 1) }}pt; color: #94a3b8;">{{ $fecha }}</div>
                    <div class="abs b" style="left: {{ $m(2.6) }}mm; top: {{ $m(7.6) }}mm; font-size: {{ round(5.5 * $k, 1) }}pt; color: #64748b;">REF</div>
                    <div class="abs num" style="top: {{ $m(8.6) + ($m(10.6) - $ptMm($precio['fs'])) / 2 }}mm; font-size: {{ $precio['fs'] }}pt;">{{ $e['unidad_fmt'] }}</div>
                    @if($mayor)
                        <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $m(19.8) }}mm; font-size: {{ $mayor['fs'] }}pt; color: #7c3aed;">{{ $mayor['lineas'][0] ?? '' }}</div>
                    @endif
                    <div class="abs" style="left: {{ $m(2.4) }}mm; width: {{ $lzPanelW - $m(4.8) }}mm; top: {{ $m(23.4) }}mm; border-top: 0.3mm dashed #c4b5fd;"></div>
                    {!! $lineas($nom, $m(2.5), $nomTop + max(0, ($nomH - $altoLineas($nom)) / 2)) !!}
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $lzPanelH - $m(3.2) }}mm; font-size: {{ $webFit['fs'] }}pt; color: #7c3aed;">{{ $web }}</div>
                </div>
            @elseif($tipo === 'pc')
                @php
                    $specs = $e['specs'] ?? [];
                    $marca = trim((string) ($specs['marca'] ?? '')) ?: mb_strtoupper(explode(' ', $e['nombre'])[0] ?? '');
                    $precio = $fit('pcnum', $e['unidad_entero_fmt'], $inW - $m(14), $fs([40, 36, 32, 28, 24, 20]));
                    $logoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    if ($logoM) {
                        $lmH = $m($logoM['ratio'] < 1.6 ? 8 : 6.4);
                        $lmW = min($m(42), $lmH * $logoM['ratio']);
                        $lmH = $lmW / $logoM['ratio'];
                        $codTop = $m(2) + $lmH + $m(0.9);
                    } else {
                        $marcaFit = $fit('pcmarca', mb_strtoupper($marca), $inW - $m(10), $fs([18, 16, 14, 12]));
                        $codTop = $m(2) + $ptMm($marcaFit['fs']) + $m(1.4);
                    }
                    $codFit = $fit('pccod' . $e['id'], 'COD: ' . $e['codigo'], $inW - $m(6), $fs([6.5, 6, 5.5, 5]));
                    $lleno = fn ($c) => trim((string) ($specs[$c] ?? '')) !== '';
                    $izq = array_values(array_filter(['modelo', 'procesador', 'pantalla'], $lleno));
                    $der = array_values(array_filter(['grafica', 'ram', 'almacenamiento', 'sistema'], $lleno));
                    while (count($izq) < 3 && count($der) > 3) {
                        $izq[] = array_pop($der);
                    }
                    $pcColumnas = [$izq, array_slice($der, 0, 3)];
                    $filas = max(1, count($pcColumnas[0]), count($pcColumnas[1]));
                    $specsTop = $codTop + $ptMm($codFit['fs']) + $m(1.6);
                    $filaH = min($m(8), ($pcPanelH - $specsTop - $m(1)) / $filas);
                    $ico = min($m(5), $filaH - $m(0.8));
                @endphp
                <div class="abs pc-caja">
                    <div class="abs b nw centro" style="left: 0; width: 100%; top: {{ $m(2.4) }}mm; font-size: {{ round(16 * $k, 1) }}pt; color: #fff; letter-spacing: 0.6pt;">PRECIO</div>
                    <div class="abs b nw centro" style="left: 0; width: 100%; top: {{ $m(9) }}mm; font-size: {{ round(18 * $k, 1) }}pt; color: #facc15; letter-spacing: 0.4pt;">REGULAR</div>
                    @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $inW - $m(16.5) }}mm; top: {{ $m(1.6) }}mm; width: {{ $m(13.5) }}mm; height: {{ $m(13.5) }}mm;">@endif
                </div>
                <div class="abs pc-pill">
                    <div class="abs b" style="left: {{ $m(4) }}mm; top: {{ $m(2.4) }}mm; font-size: {{ round(7 * $k, 1) }}pt; color: #fde68a;">REF</div>
                    <div class="abs num" style="top: {{ ($pcPillH - $m(1.4) - $ptMm($precio['fs'])) / 2 + $m(0.4) }}mm; font-size: {{ $precio['fs'] }}pt; color: #fff;">{{ $e['unidad_entero_fmt'] }}</div>
                </div>

                <div class="abs pc-panel blanco">
                    @if($logoM)
                        <img class="abs" src="{{ $logoM['src'] }}" alt="" style="left: {{ ($inW - $lmW) / 2 }}mm; top: {{ $m(2) }}mm; width: {{ $lmW }}mm; height: {{ $lmH }}mm;">
                    @else
                        <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $m(1.8) }}mm; font-size: {{ $marcaFit['fs'] }}pt; color: #0f172a; letter-spacing: 1pt;">{{ $marcaFit['lineas'][0] ?? '' }}</div>
                    @endif
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $codTop }}mm; font-size: {{ $codFit['fs'] }}pt; color: #64748b;">{{ $codFit['lineas'][0] ?? '' }}</div>
                    @foreach($pcColumnas as $c => $campos)
                        @foreach($campos as $fila => $campo)
                            @php
                                $sx = $m(3) + $c * ($pcColW + $m(2));
                                $sy = $specsTop + $fila * $filaH;
                                $sp = $ajustar("pc{$e['id']}{$campo}" . md5((string) $specs[$campo]) . round($filaH, 2), (string) $specs[$campo], $pcColW - $ico - $m(1.6), $filaH - $m(0.3), $fs([8.5, 8, 7.5, 7, 6.5, 6, 5.5]), 2, false);
                            @endphp
                            <div class="abs pc-ico" style="left: {{ $sx }}mm; top: {{ $sy + ($filaH - $ico) / 2 - $m(0.4) }}mm; width: {{ $ico }}mm; height: {{ $ico }}mm; border-radius: {{ $ico / 2 }}mm;">
                                <div class="abs" style="top: {{ $ico * 0.17 }}mm; font-size: {{ round($ico * 1.6, 1) }}pt;">{{ $pcIcono[$campo] }}</div>
                            </div>
                            {!! $lineas($sp, $sx + $ico + $m(1.6), $sy + max(0, ($filaH - $altoLineas($sp)) / 2) - $m(0.4)) !!}
                        @endforeach
                    @endforeach
                </div>
                <div class="abs pc-pie" style="left: {{ $pad + $m(1) }}mm;">Precios en REF · {{ $fecha }}</div>
                <div class="abs pc-pie der" style="left: 0; width: {{ $W - $pad - $m(1) }}mm;">{{ $web }}</div>
            @else
                @php
                    $nom = $ajustar('cl' . $e['id'], $e['nombre'], $nomAnchoTxt, $nomAltoTxt, $fs([11, 10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6]));
                    $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                    if ($e['con_mayor']) {
                        $headH = $headH2;
                        $txtMayor = $e['cantidad_mayor'] . '+ PZAS';
                        $hFs = min(
                            $fit('cabc', 'COMPRA', $boxW - $m(2), $cabFs)['fs'],
                            $fit('cabm' . $txtMayor, $txtMayor, $boxW - $m(2), $cabFs)['fs'],
                        );
                        $hLinea = $ptMm($hFs) * 1.08;
                        $hTop = max($m(0.4), ($headH - 2 * $hLinea) / 2 + $m(0.2));
                        $pu = $fit('clu', $e['unidad_fmt'], $boxW - $m(3), $fs([22, 20, 18, 16, 14, 12, 10]));
                        $pm = $fit('clm', $e['mayor_fmt'], $boxW - $m(3), $fs([22, 20, 18, 16, 14, 12, 10]));
                        $numFs = min($pu['fs'], $pm['fs']);
                    } else {
                        $headH = $headH1;
                        $hFs = $fit('cabs', 'PRECIO', $inW - $m(4), $cabFs)['fs'];
                        $hTop = max($m(0.4), ($headH - $ptMm($hFs)) / 2 + $m(0.2));
                        $numFs = $fit('cls', $e['unidad_fmt'], $inW - $m(8), $fs([30, 28, 26, 24, 22, 20, 18]))['fs'];
                    }
                    $libreH = $precioH - $headH;
                    $numTop = $headH + ($libreH - $ptMm($numFs)) / 2 + $m(0.6);
                    $numTopMayor = $conAhorro ? $numTop - $m(1.3) : $numTop;
                    $ahorroTop = $precioH - $ptMm(5.3 * $k) - $m(1.2);
                    $cod = $fit('clcod' . $e['id'], 'Cód. ' . $e['codigo'], $inW * 0.55, $fs([5.5, 5, 4.5, 4]));
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $topH }}mm; height: {{ $topH }}mm;">@endif

                <div class="abs blanco" style="left: {{ $nameLeft }}mm; top: {{ $pad }}mm; width: {{ $nameW }}mm; height: {{ $topH }}mm;">
                    <div class="abs b" style="left: {{ $nomPadX }}mm; top: {{ $m(1.1) }}mm; font-size: {{ round(5 * $k, 1) }}pt; letter-spacing: 0.5pt; color: #7c3aed;">PRODUCTO</div>
                    {!! $lineas($nom, $nomPadX, $tagH + max(0, ($nomAltoTxt - $altoLineas($nom)) / 2)) !!}
                </div>

                @if($e['con_mayor'])
                    @foreach([['1 PZA', $e['unidad_fmt'], $pad, false], [$txtMayor, $e['mayor_fmt'], $pad + $boxW + $boxGap, true]] as [$cant, $valor, $bx, $esMayor])
                        <div class="abs caja blanco {{ $esMayor ? 'mayor' : '' }}" style="left: {{ $bx }}mm; width: {{ $boxW }}mm;">
                            <div class="abs h" style="height: {{ $headH }}mm;">
                                <div class="abs" style="top: {{ $hTop }}mm; font-size: {{ $hFs }}pt;">COMPRA</div>
                                <div class="abs" style="top: {{ $hTop + $hLinea }}mm; font-size: {{ $hFs }}pt;"><span>{{ $cant }}</span></div>
                            </div>
                            <div class="abs ref" style="top: {{ $headH + $m(0.8) }}mm;">REF</div>
                            <div class="abs num" style="top: {{ $esMayor ? $numTopMayor : $numTop }}mm; font-size: {{ $numFs }}pt; {{ $esMayor ? 'color: #6d28d9;' : '' }}">{{ $valor }}</div>
                            @if($esMayor && $conAhorro)
                                <div class="abs ahorro" style="top: {{ $ahorroTop }}mm;">¡AHORRAS {{ $e['ahorro_pct'] }}% C/U!</div>
                            @endif
                            @if($esMayor)<div class="abs marco"></div>@endif
                        </div>
                    @endforeach
                @else
                    <div class="abs caja blanco" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                        <div class="abs h" style="height: {{ $headH }}mm;">
                            <div class="abs" style="top: {{ $hTop }}mm; font-size: {{ $hFs }}pt; letter-spacing: 1.2pt;">PRECIO</div>
                        </div>
                        <div class="abs ref" style="top: {{ $headH + $m(0.8) }}mm;">REF</div>
                        <div class="abs num" style="top: {{ $numTop }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    </div>
                @endif

                <div class="abs pie" style="left: {{ $pad }}mm; font-size: {{ $cod['fs'] }}pt;">{{ $cod['lineas'][0] ?? '' }}</div>
                <div class="abs pie der" style="left: 0; width: {{ $W - $pad }}mm; font-size: {{ round(5.3 * $k, 1) }}pt;">Precios en REF · {{ $fecha }}</div>
            @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
