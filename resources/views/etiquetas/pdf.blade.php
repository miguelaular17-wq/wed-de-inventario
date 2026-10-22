<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas de precio</title>
@php
    $tipo = $tipo ?? 'clasica';
    $web = $web ?? '';
    $logosMarca = $logosMarca ?? [];
    $W = $tamano['ancho'];
    $H = $tamano['alto'];
    $sep = 2;
    $ptMm = fn (float $p) => $p * 0.3528;
    $ajustados = [];
    $ajustar = function (string $clave, string $texto, float $ancho, float $alto, array $tamanos, int $maxLineas = 3, bool $mayusculas = true) use (&$ajustados, $ajustarNombre) {
        return $ajustados[$clave] ??= $ajustarNombre($texto, $ancho, $alto, $tamanos, $maxLineas, $mayusculas);
    };
    // Una sola línea: tamaño más grande que entra en el ancho.
    $fit = fn (string $clave, string $texto, float $ancho, array $tamanos) => $ajustar('fit' . $clave . md5($texto), $texto, $ancho, 100, $tamanos, 1, false);
    $gridW = $tamano['columnas'] * ($W + $sep) - $sep;
    $offX = max(0, (201.9 - $gridW) / 2);
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

    $pad = $tipo === 'pc' ? 3 : 2.4;
    $inW = $W - 2 * $pad;

    // Clásica (7 × 5 cm)
    $topH = 13;
    $gap = 1.6;
    $nameLeft = $pad + $topH + $gap;
    $nameW = $W - $nameLeft - $pad;
    $nomPadX = 2;
    $tagH = $ptMm(5) + 1.5;
    $nomAnchoTxt = $nameW - $nomPadX - 1.6;
    $nomAltoTxt = $topH - $tagH - 0.8;
    $precioTop = $pad + $topH + $gap;
    $footH = 3;
    $precioH = $H - $precioTop - $footH - $pad - 0.6;
    $headH = 4.6;
    $boxGap = 1.6;
    $boxW = ($inW - $boxGap) / 2;

    // Precios de locura (7 × 5 cm)
    $lzW = 27;
    $lzPanelL = $pad + $lzW + 1.8;
    $lzPanelW = $W - $lzPanelL - $pad;
    $lzPanelH = $H - 2 * $pad;

    // PC / Laptop (9 × 10,5 cm, vertical)
    $pcPanelTop = $pad + 46;
    $pcPanelH = $H - $pcPanelTop - $pad - 5.2;
    $pcColW = ($inW - 8) / 2;
    $pcFilaH = 7;
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
        border-radius: 3mm; background-color: #9333ea;
        @if($fondo) background-image: url('{{ $fondo }}'); background-repeat: no-repeat; @endif
    }
    .blanco { background: #fff; border-radius: 2mm; overflow: hidden; }
    .linea { white-space: nowrap; font-weight: bold; color: #0f172a; }
    .num { left: 0; width: 100%; text-align: center; font-weight: bold; line-height: 1; white-space: nowrap; letter-spacing: -0.3pt; }

    /* Clásica */
    .caja { top: {{ $precioTop }}mm; height: {{ $precioH }}mm; }
    .caja .h {
        left: 0; top: 0; width: 100%; height: {{ $headH - 1.1 }}mm; padding-top: 1.1mm; line-height: 1; text-align: center;
        background: #1e1b4b; color: #fff; font-weight: bold; font-size: 7pt; letter-spacing: 0.2pt;
    }
    .caja .h span { color: #fde047; }
    .caja.mayor .h { background: #facc15; color: #1e1b4b; }
    .caja.mayor .h span { color: #9d174d; }
    .caja.mayor .marco { left: 0; top: 0; right: 0; bottom: 0; border: 0.7mm solid #facc15; border-radius: 2mm; }
    .ref { left: 1.8mm; top: {{ $headH + 0.8 }}mm; font-size: 5.5pt; font-weight: bold; color: #64748b; }
    .ahorro { left: 0; width: 100%; text-align: center; font-size: 5.3pt; font-weight: bold; color: #be185d; }
    .pie { top: {{ $H - $pad - $footH + 0.4 }}mm; color: #fff; font-weight: bold; white-space: nowrap; }

    /* Precios de locura */
    .lz-de { left: {{ $pad }}mm; width: {{ $lzW }}mm; color: #fff; letter-spacing: 0.3pt; }
    .lz-locura { left: {{ $pad }}mm; width: {{ $lzW }}mm; background: #3b0764; border-radius: 1.8mm; border-bottom: 0.8mm solid #facc15; }
    .lz-cinta { left: {{ $pad + 1 }}mm; width: {{ $lzW - 2 }}mm; background: #facc15; border-radius: 1mm; }
    .lz-cinta div { left: 0; width: 100%; color: #9d174d; }
    .lz-panel { left: {{ $lzPanelL }}mm; top: {{ $pad }}mm; width: {{ $lzPanelW }}mm; height: {{ $lzPanelH }}mm; }
    .lz-pill { left: 2.2mm; top: 2.2mm; width: 15mm; height: 4mm; background: #7c3aed; border-radius: 2mm; }
    .lz-pill div { left: 0; width: 100%; top: 0.9mm; font-size: 6.3pt; color: #fff; letter-spacing: 0.7pt; }

    /* PC / Laptop */
    .pc-caja { left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $inW }}mm; height: 24mm; background: #4c1d95; border-radius: 3mm; }
    .pc-pill { left: {{ $pad }}mm; top: {{ $pad + 21 }}mm; width: {{ $inW - 1.6 }}mm; height: 21mm; background: #db2777; border-radius: 3.5mm; border: 0.8mm solid #fff; }
    .pc-panel { left: {{ $pad }}mm; top: {{ $pcPanelTop }}mm; width: {{ $inW }}mm; height: {{ $pcPanelH }}mm; border-radius: 3mm; }
    .pc-ico { width: 5mm; height: 5mm; background: #db2777; border-radius: 2.5mm; }
    .pc-ico div { left: 0; width: 100%; top: 0.85mm; font-size: 8pt; color: #fff; font-weight: normal; text-align: center; }
    .pc-pie { top: {{ $H - $pad - 3.6 }}mm; color: #fff; font-size: 6.5pt; font-weight: bold; white-space: nowrap; }
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
                    $de = $fit('lzde' . ($conMarca ? 1 : 0), 'PRECIOS DE', $lzW - 1, $L['deFs']);
                    $loc = $fit('lzloc' . ($conMarca ? 1 : 0), 'LOCURA', $lzW - 2, $L['locFs']);
                    $cod = $fit('lzcod' . $e['id'], 'Cód. ' . $e['codigo'], $lzW, [5, 4.6, 4.2, 3.8]);
                    if ($lzLogoM) {
                        $mH = 6.2;
                        $mW = min($lzW - 6, $mH * $lzLogoM['ratio']);
                        $mH = $mW / $lzLogoM['ratio'];
                    }

                    $precio = $fit('lznum', $e['unidad_fmt'], $lzPanelW - 5, [26, 24, 22, 20, 18, 16, 14]);
                    $mayor = $e['con_mayor'] ? $fit('lzmay', $e['cantidad_mayor'] . '+ PZAS: REF ' . $e['mayor_fmt'] . ' C/U', $lzPanelW - 3, [6.5, 6, 5.5, 5, 4.5]) : null;
                    $nomTop = 25;
                    $nomH = $lzPanelH - $nomTop - 4.6;
                    $nom = $ajustar('lz' . $e['id'], $e['nombre'], $lzPanelW - 5, $nomH, [9, 8.5, 8, 7.5, 7, 6.5, 6, 5.5]);
                    $webFit = $fit('lzweb', $web, $lzPanelW - 3, [5, 4.6, 4.2, 4]);
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad + ($lzW - $L['logo']) / 2 }}mm; top: {{ $pad }}mm; width: {{ $L['logo'] }}mm; height: {{ $L['logo'] }}mm;">@endif
                <div class="abs lz-de b centro nw" style="top: {{ $pad + $L['de'] }}mm; font-size: {{ $de['fs'] }}pt;">PRECIOS DE</div>
                <div class="abs lz-locura" style="top: {{ $pad + $L['loc'] }}mm; height: {{ $L['locH'] }}mm;">
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ ($L['locH'] - $ptMm($loc['fs'])) / 2 - 0.4 }}mm; font-size: {{ $loc['fs'] }}pt; color: #facc15;">LOCURA</div>
                </div>
                <div class="abs lz-cinta" style="top: {{ $pad + $L['cinta'] }}mm; height: {{ $L['cintaH'] }}mm;">
                    <div class="abs b centro nw" style="top: 0.6mm; font-size: {{ $L['cintaFs'] }}pt;">PRECIOS BAJOS</div>
                    <div class="abs b centro nw" style="top: {{ $L['cintaH'] / 2 }}mm; font-size: {{ $L['cintaFs'] }}pt;">TODO EL AÑO</div>
                </div>
                @if($conMarca)
                    <div class="abs blanco" style="left: {{ $pad + 1 }}mm; top: {{ $pad + 31 }}mm; width: {{ $lzW - 2 }}mm; height: 8.8mm;">
                        @if($lzLogoM)
                            <img class="abs" src="{{ $lzLogoM['src'] }}" alt="" style="left: {{ ($lzW - 2 - $mW) / 2 }}mm; top: {{ (8.8 - $mH) / 2 }}mm; width: {{ $mW }}mm; height: {{ $mH }}mm;">
                        @else
                            @php $mf = $fit('lzmt', mb_strtoupper($lzMarca), $lzW - 4, [11, 10, 9, 8, 7, 6]); @endphp
                            <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ (8.8 - $ptMm($mf['fs'])) / 2 - 0.3 }}mm; font-size: {{ $mf['fs'] }}pt; color: #1e1b4b; letter-spacing: 0.6pt;">{{ $mf['lineas'][0] ?? '' }}</div>
                        @endif
                    </div>
                @endif
                <div class="abs b centro nw" style="left: {{ $pad }}mm; width: {{ $lzW }}mm; top: {{ $H - $pad - 2.4 }}mm; font-size: {{ $cod['fs'] }}pt; color: #fff;">{{ $cod['lineas'][0] ?? '' }}</div>

                <div class="abs lz-panel blanco">
                    <div class="abs lz-pill"><div class="abs b centro nw">PRECIO</div></div>
                    <div class="abs b der nw" style="left: 0; width: {{ $lzPanelW - 2.4 }}mm; top: 3.2mm; font-size: 4.8pt; color: #94a3b8;">{{ $fecha }}</div>
                    <div class="abs b" style="left: 2.6mm; top: 7.6mm; font-size: 5.5pt; color: #64748b;">REF</div>
                    <div class="abs num" style="top: {{ 8.6 + (10.6 - $ptMm($precio['fs'])) / 2 }}mm; font-size: {{ $precio['fs'] }}pt;">{{ $e['unidad_fmt'] }}</div>
                    @if($mayor)
                        <div class="abs b centro nw" style="left: 0; width: 100%; top: 19.8mm; font-size: {{ $mayor['fs'] }}pt; color: #7c3aed;">{{ $mayor['lineas'][0] ?? '' }}</div>
                    @endif
                    <div class="abs" style="left: 2.4mm; width: {{ $lzPanelW - 4.8 }}mm; top: 23.4mm; border-top: 0.3mm dashed #c4b5fd;"></div>
                    {!! $lineas($nom, 2.5, $nomTop + max(0, ($nomH - $altoLineas($nom)) / 2)) !!}
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $lzPanelH - 3.2 }}mm; font-size: {{ $webFit['fs'] }}pt; color: #7c3aed;">{{ $web }}</div>
                </div>
            @elseif($tipo === 'pc')
                @php
                    $specs = $e['specs'] ?? [];
                    $marca = trim((string) ($specs['marca'] ?? '')) ?: mb_strtoupper(explode(' ', $e['nombre'])[0] ?? '');
                    $precio = $fit('pcnum', $e['unidad_entero_fmt'], $inW - 14, [46, 42, 38, 34, 30, 26]);
                    $logoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    if ($logoM) {
                        $lmH = $logoM['ratio'] < 1.6 ? 10 : 8;
                        $lmW = min(48, $lmH * $logoM['ratio']);
                        $lmH = $lmW / $logoM['ratio'];
                        $codTop = 2.4 + $lmH + 1.2;
                    } else {
                        $marcaFit = $fit('pcmarca', mb_strtoupper($marca), $inW - 10, [22, 20, 18, 16, 14, 12]);
                        $codTop = 2.4 + $ptMm($marcaFit['fs']) + 1.6;
                    }
                    $codFit = $fit('pccod' . $e['id'], 'COD: ' . $e['codigo'], $inW - 6, [7, 6.5, 6, 5.5, 5]);
                    $lleno = fn ($c) => trim((string) ($specs[$c] ?? '')) !== '';
                    $pcColumnas = [
                        array_slice(array_values(array_filter(['modelo', 'procesador', 'grafica', 'pantalla'], $lleno)), 0, 4),
                        array_slice(array_values(array_filter(['ram', 'almacenamiento', 'sistema'], $lleno)), 0, 4),
                    ];
                    $specsTop = $codTop + 4.4;
                @endphp
                <div class="abs pc-caja">
                    <div class="abs b nw centro" style="left: 0; width: 100%; top: 3.2mm; font-size: 19pt; color: #fff; letter-spacing: 0.6pt;">PRECIO</div>
                    <div class="abs b nw centro" style="left: 0; width: 100%; top: 11.2mm; font-size: 21pt; color: #facc15; letter-spacing: 0.4pt;">REGULAR</div>
                    @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $inW - 20 }}mm; top: 2.4mm; width: 16.5mm; height: 16.5mm;">@endif
                </div>
                <div class="abs pc-pill">
                    <div class="abs b" style="left: 4mm; top: 3mm; font-size: 8pt; color: #fde68a;">REF</div>
                    <div class="abs num" style="top: {{ (19.4 - $ptMm($precio['fs'])) / 2 + 0.4 }}mm; font-size: {{ $precio['fs'] }}pt; color: #fff;">{{ $e['unidad_entero_fmt'] }}</div>
                </div>

                <div class="abs pc-panel blanco">
                    @if($logoM)
                        <img class="abs" src="{{ $logoM['src'] }}" alt="" style="left: {{ ($inW - $lmW) / 2 }}mm; top: 2.4mm; width: {{ $lmW }}mm; height: {{ $lmH }}mm;">
                    @else
                        <div class="abs b centro nw" style="left: 0; width: 100%; top: 2.2mm; font-size: {{ $marcaFit['fs'] }}pt; color: #0f172a; letter-spacing: 1pt;">{{ $marcaFit['lineas'][0] ?? '' }}</div>
                    @endif
                    <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $codTop }}mm; font-size: {{ $codFit['fs'] }}pt; color: #64748b;">{{ $codFit['lineas'][0] ?? '' }}</div>
                    @foreach($pcColumnas as $c => $campos)
                        @foreach($campos as $fila => $campo)
                            @php
                                $sx = 3 + $c * ($pcColW + 2);
                                $sy = $specsTop + $fila * $pcFilaH;
                                $sp = $ajustar("pc{$e['id']}{$campo}" . md5((string) $specs[$campo]), (string) $specs[$campo], $pcColW - 6.6, 6.6, [8.5, 8, 7.5, 7, 6.5, 6], 2, false);
                            @endphp
                            <div class="abs pc-ico" style="left: {{ $sx }}mm; top: {{ $sy }}mm;"><div class="abs">{{ $pcIcono[$campo] }}</div></div>
                            {!! $lineas($sp, $sx + 6.6, $sy + max(0, (5 - $altoLineas($sp)) / 2)) !!}
                        @endforeach
                    @endforeach
                </div>
                <div class="abs pc-pie" style="left: {{ $pad + 1 }}mm;">Precios en REF · {{ $fecha }}</div>
                <div class="abs pc-pie der" style="left: 0; width: {{ $W - $pad - 1 }}mm;">{{ $web }}</div>
            @else
                @php
                    $nom = $ajustar('cl' . $e['id'], $e['nombre'], $nomAnchoTxt, $nomAltoTxt, [11, 10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6]);
                    $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                    $libreH = $precioH - $headH;
                    if ($e['con_mayor']) {
                        $pu = $fit('clu', $e['unidad_fmt'], $boxW - 3, [22, 20, 18, 16, 14, 12, 10]);
                        $pm = $fit('clm', $e['mayor_fmt'], $boxW - 3, [22, 20, 18, 16, 14, 12, 10]);
                        $numFs = min($pu['fs'], $pm['fs']);
                    } else {
                        $numFs = $fit('cls', $e['unidad_fmt'], $inW - 8, [30, 28, 26, 24, 22, 20, 18])['fs'];
                    }
                    $numTop = $headH + ($libreH - $ptMm($numFs)) / 2 + 0.5;
                    $numTopMayor = $conAhorro ? $numTop - 1.3 : $numTop;
                    $ahorroTop = $precioH - $ptMm(5.3) - 1.2;
                    $cod = $fit('clcod' . $e['id'], 'Cód. ' . $e['codigo'], $inW * 0.55, [5.5, 5, 4.5, 4]);
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $topH }}mm; height: {{ $topH }}mm;">@endif

                <div class="abs blanco" style="left: {{ $nameLeft }}mm; top: {{ $pad }}mm; width: {{ $nameW }}mm; height: {{ $topH }}mm;">
                    <div class="abs b" style="left: {{ $nomPadX }}mm; top: 1.1mm; font-size: 5pt; letter-spacing: 0.5pt; color: #7c3aed;">PRODUCTO</div>
                    {!! $lineas($nom, $nomPadX, $tagH + max(0, ($nomAltoTxt - $altoLineas($nom)) / 2)) !!}
                </div>

                @if($e['con_mayor'])
                    <div class="abs caja blanco" style="left: {{ $pad }}mm; width: {{ $boxW }}mm;">
                        <div class="abs h">COMPRA <span>1 PZA</span></div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTop }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    </div>
                    <div class="abs caja blanco mayor" style="left: {{ $pad + $boxW + $boxGap }}mm; width: {{ $boxW }}mm;">
                        <div class="abs h">COMPRA <span>{{ $e['cantidad_mayor'] }}+ PZAS</span></div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTopMayor }}mm; font-size: {{ $numFs }}pt; color: #6d28d9;">{{ $e['mayor_fmt'] }}</div>
                        @if($conAhorro)
                            <div class="abs ahorro" style="top: {{ $ahorroTop }}mm;">¡AHORRAS {{ $e['ahorro_pct'] }}% C/U!</div>
                        @endif
                        <div class="abs marco"></div>
                    </div>
                @else
                    <div class="abs caja blanco" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                        <div class="abs h">PRECIO</div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTop }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    </div>
                @endif

                <div class="abs pie" style="left: {{ $pad }}mm; font-size: {{ $cod['fs'] }}pt;">{{ $cod['lineas'][0] ?? '' }}</div>
                <div class="abs pie der" style="left: 0; width: {{ $W - $pad }}mm; font-size: 5.3pt;">Precios en REF · {{ $fecha }}</div>
            @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
