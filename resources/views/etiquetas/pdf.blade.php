<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas de precio</title>
@php
    $tipo = $tipo ?? 'clasica';
    $web = $web ?? '';
    $chica = $tamanoClave === 'pequena';
    $k = $chica ? 0.66 : 1.0;
    $W = $tamano['ancho'];
    $H = $tamano['alto'];
    $sep = 2;
    $pad = $chica ? 1.8 : 2.6;
    $inW = $W - 2 * $pad;
    $pt = fn (float $v) => round($v * $k, 1);
    $ptMm = fn (float $p) => $p * 0.3528;
    $ajustados = [];
    $ajustar = function (string $clave, string $texto, float $ancho, float $alto, array $tamanos, int $maxLineas = 3, bool $mayusculas = true) use (&$ajustados, $ajustarNombre) {
        return $ajustados[$clave] ??= $ajustarNombre($texto, $ancho, $alto, $tamanos, $maxLineas, $mayusculas);
    };
    $pos = function (int $i) use ($tamano, $W, $H, $sep) {
        $col = $i % $tamano['columnas'];
        $row = intdiv($i, $tamano['columnas']);
        return [$col * ($W + $sep), $row * ($H + $sep)];
    };
    $hojaH = $tamano['filas'] * ($H + $sep) - $sep;
    $numFsPor = fn (string $txt, float $grande, float $medio, float $chico) => mb_strlen($txt) <= 5 ? $grande : (mb_strlen($txt) <= 7 ? $medio : $chico);

    // Clásica
    $topH = $chica ? 10 : 15;
    $gap = $chica ? 1.3 : 2;
    $precioTop = $pad + $topH + $gap;
    $footH = $chica ? 2.4 : 3.2;
    $precioH = $H - $precioTop - $footH - $pad - ($chica ? 0.6 : 0.8);
    $headH = $chica ? 3.6 : 5;
    $boxGap = $chica ? 1.3 : 2;
    $boxW = ($inW - $boxGap) / 2;
    $nameLeft = $pad + $topH + $gap;
    $nameW = $W - $nameLeft - $pad;
    $nomPadX = $chica ? 1.6 : 2.6;
    $nomPadR = $chica ? 1.2 : 2;
    $tagH = $ptMm($pt(5.5)) + ($chica ? 1.1 : 1.8);
    $nomAnchoTxt = $nameW - $nomPadX - $nomPadR;
    $nomAltoTxt = $topH - $tagH - ($chica ? 0.6 : 0.9);
    $nomTamanos = array_map($pt, [12.5, 11.5, 10.5, 9.5, 9, 8.5, 8, 7.5, 7, 6.5]);

    // Precios de locura
    $lzW = 38;
    $lzPanelL = $pad + $lzW + 2;
    $lzPanelW = $W - $lzPanelL - $pad;
    $lzPanelH = $H - 2 * $pad;
    $lzNomTop = 27;
    $lzNomW = $lzPanelW - 6;
    $lzNomH = $lzPanelH - $lzNomTop - 5.2;

    // PC / Laptop
    $pcW = 36;
    $pcPanelL = $pad + $pcW + 2;
    $pcPanelW = $W - $pcPanelL - $pad;
    $pcColWs = [($pcPanelW - 6.6) * 0.56, ($pcPanelW - 6.6) * 0.44];
    $pcColX = [3, 3 + $pcColWs[0] + 0.6];
    $pcFilaH = 8.2;
    $pcIcono = [
        'modelo' => '◆', 'procesador' => '⚙', 'grafica' => '▦', 'pantalla' => '▭',
        'ram' => '▤', 'almacenamiento' => '◉', 'sistema' => '⊞',
    ];
    $logosMarca = $logosMarca ?? [];
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

    .etq {
        position: absolute; width: {{ $W }}mm; height: {{ $H }}mm; overflow: hidden;
        border-radius: {{ $chica ? 2.5 : 3.5 }}mm; background-color: #9333ea;
        @if($fondo) background-image: url('{{ $fondo }}'); background-repeat: no-repeat; @endif
    }
    .blanco { background: #fff; border-radius: 2.2mm; overflow: hidden; }
    .linea { white-space: nowrap; font-weight: bold; color: #0f172a; }

    /* Clásica */
    .logo { left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $topH }}mm; height: {{ $topH }}mm; }
    .nombre { left: {{ $nameLeft }}mm; top: {{ $pad }}mm; width: {{ $nameW }}mm; height: {{ $topH }}mm; border-radius: {{ $chica ? 1.5 : 2.2 }}mm; }
    .nombre .tag { left: {{ $nomPadX }}mm; top: {{ $chica ? 0.8 : 1.3 }}mm; font-size: {{ $pt(5.5) }}pt; letter-spacing: 0.6pt; color: #7c3aed; font-weight: bold; }
    .caja { top: {{ $precioTop }}mm; height: {{ $precioH }}mm; border-radius: {{ $chica ? 1.5 : 2.2 }}mm; }
    .caja .h {
        left: 0; top: 0; width: 100%; height: {{ $headH - (($headH - $ptMm($pt(8))) / 2 - 0.2) }}mm;
        padding-top: {{ ($headH - $ptMm($pt(8))) / 2 - 0.2 }}mm; line-height: 1; text-align: center;
        background: #1e1b4b; color: #fff; font-weight: bold; font-size: {{ $pt(8) }}pt; letter-spacing: 0.3pt;
    }
    .caja .h span { color: #fde047; }
    .caja.mayor .h { background: #facc15; color: #1e1b4b; }
    .caja.mayor .h span { color: #9d174d; }
    .caja.mayor .marco { left: 0; top: 0; right: 0; bottom: 0; border: {{ $chica ? 0.5 : 0.8 }}mm solid #facc15; border-radius: {{ $chica ? 1.5 : 2.2 }}mm; }
    .ref { left: {{ $chica ? 1.5 : 2.4 }}mm; top: {{ $headH + ($chica ? 0.7 : 1) }}mm; font-size: {{ $pt(6.5) }}pt; font-weight: bold; color: #64748b; }
    .num { left: 0; width: 100%; text-align: center; font-weight: bold; line-height: 1; white-space: nowrap; letter-spacing: -0.4pt; }
    .ahorro { left: 0; width: 100%; text-align: center; font-size: {{ $pt(6) }}pt; font-weight: bold; color: #be185d; }
    .pie { top: {{ $H - $pad - $footH }}mm; height: {{ $footH }}mm; color: #fff; font-size: {{ $pt(6) }}pt; font-weight: bold; white-space: nowrap; }

    /* Precios de locura */
    .lz-logo { left: {{ $pad + ($lzW - 13) / 2 }}mm; top: {{ $pad }}mm; width: 13mm; height: 13mm; }
    .lz-de { left: {{ $pad }}mm; width: {{ $lzW }}mm; top: {{ $pad + 14 }}mm; font-size: 12pt; color: #fff; letter-spacing: 0.5pt; }
    .lz-locura {
        left: {{ $pad }}mm; width: {{ $lzW }}mm; top: {{ $pad + 19.6 }}mm; height: 11.4mm;
        background: #3b0764; border-radius: 2mm; border-bottom: 0.9mm solid #facc15;
    }
    .lz-locura div { left: 0; width: 100%; top: 1.6mm; font-size: 22pt; color: #facc15; letter-spacing: 0.4pt; }
    .lz-cinta { left: {{ $pad + 1.5 }}mm; width: {{ $lzW - 3 }}mm; top: {{ $pad + 33.6 }}mm; height: 4.2mm; background: #facc15; border-radius: 1mm; }
    .lz-cinta div { left: 0; width: 100%; top: 0.9mm; font-size: 6pt; color: #9d174d; }
    .lz-pie { color: #fff; }
    .lz-panel { left: {{ $lzPanelL }}mm; top: {{ $pad }}mm; width: {{ $lzPanelW }}mm; height: {{ $lzPanelH }}mm; }
    .lz-pill { left: 3mm; top: 2.6mm; width: 19mm; height: 4.4mm; background: #7c3aed; border-radius: 2.2mm; }
    .lz-pill div { left: 0; width: 100%; top: 0.95mm; font-size: 7.5pt; color: #fff; letter-spacing: 0.8pt; }
    .lz-ref { left: 3.4mm; top: 8.4mm; font-size: 6.5pt; color: #64748b; }
    .lz-mayor { left: 0; width: 100%; top: 21.6mm; font-size: 7pt; color: #7c3aed; }
    .lz-sep { left: 3mm; width: {{ $lzPanelW - 6 }}mm; top: 25.6mm; border-top: 0.3mm dashed #c4b5fd; }
    .lz-web { left: 0; width: 100%; top: {{ $lzPanelH - 3.3 }}mm; font-size: 5.3pt; color: #7c3aed; }

    /* PC / Laptop */
    .pc-caja { left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $pcW }}mm; height: 19mm; background: #4c1d95; border-radius: 2.5mm; }
    .pc-logo { left: {{ $pcW - 10.5 }}mm; top: 1.3mm; width: 9mm; height: 9mm; }
    .pc-precio { left: 3mm; top: 3mm; font-size: 13pt; color: #fff; letter-spacing: 0.5pt; }
    .pc-regular { left: 3mm; top: 10.6mm; font-size: 14pt; color: #facc15; letter-spacing: 0.3pt; }
    .pc-pill { left: {{ $pad }}mm; top: {{ $pad + 18.4 }}mm; width: {{ $pcW }}mm; height: 13mm; background: #db2777; border-radius: 3mm; border: 0.6mm solid #fff; }
    .pc-pill .r { left: 2.6mm; top: 2mm; font-size: 6.5pt; color: #fde68a; }
    .pc-pill .n { left: 0; width: 100%; color: #fff; line-height: 1; text-align: center; }
    .pc-pie { left: {{ $pad }}mm; width: {{ $pcW }}mm; font-size: 5.5pt; color: #fff; }
    .pc-panel { left: {{ $pcPanelL }}mm; top: {{ $pad }}mm; width: {{ $pcPanelW }}mm; height: {{ $H - 2 * $pad }}mm; }
    .pc-marca { left: 3.5mm; top: 2.2mm; font-size: 16pt; color: #0f172a; letter-spacing: 1pt; }
    .pc-cod { left: 3.6mm; top: 9.3mm; font-size: 6pt; color: #64748b; }
    .pc-ico { width: 4.4mm; height: 4.4mm; background: #db2777; border-radius: 2.2mm; }
    .pc-ico div { left: 0; width: 100%; top: 0.75mm; font-size: 7pt; color: #fff; font-weight: normal; text-align: center; }
    .pc-web { left: 0; width: 100%; top: {{ $H - 2 * $pad - 4 }}mm; font-size: 6pt; color: #7c3aed; }
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
                    $nom = $ajustar('lz' . $e['id'], $e['nombre'], $lzNomW, $lzNomH, [10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6]);
                    $nomTop = $lzNomTop + max(0, ($lzNomH - count($nom['lineas']) * $ptMm($nom['alto_linea'])) / 2);
                    $numFs = $numFsPor($e['unidad_fmt'], 30, 26, 19);
                    $cod = $ajustar('lzc' . $e['id'], 'Cód. ' . $e['codigo'], $lzW, 3, [5.3, 4.8, 4.4], 1, false);
                    $lzLogoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    $lzMarca = trim((string) ($e['specs']['marca'] ?? ''));
                    $conMarca = $lzLogoM !== null || $lzMarca !== '';
                    if ($lzLogoM) {
                        $mH = 6.4;
                        $mW = min($lzW - 10, $mH * $lzLogoM['ratio']);
                        $mH = $mW / $lzLogoM['ratio'];
                    }
                @endphp
                @if($conMarca)
                    @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad + ($lzW - 10) / 2 }}mm; top: {{ $pad }}mm; width: 10mm; height: 10mm;">@endif
                    <div class="abs lz-de b centro nw" style="top: {{ $pad + 10.4 }}mm; font-size: 11pt;">PRECIOS DE</div>
                    <div class="abs lz-locura" style="top: {{ $pad + 15.2 }}mm; height: 10mm;"><div class="abs b centro nw" style="top: 1.3mm; font-size: 19pt;">LOCURA</div></div>
                    <div class="abs lz-cinta" style="top: {{ $pad + 26.6 }}mm; height: 3.8mm;"><div class="abs b centro nw" style="top: 0.8mm; font-size: 5.6pt;">PRECIOS BAJOS TODO EL AÑO</div></div>
                    <div class="abs blanco" style="left: {{ $pad + 3 }}mm; top: {{ $pad + 31.6 }}mm; width: {{ $lzW - 6 }}mm; height: 9mm; border-radius: 2mm;">
                        @if($lzLogoM)
                            <img class="abs" src="{{ $lzLogoM['src'] }}" alt="" style="left: {{ ($lzW - 6 - $mW) / 2 }}mm; top: {{ (9 - $mH) / 2 }}mm; width: {{ $mW }}mm; height: {{ $mH }}mm;">
                        @else
                            <div class="abs b centro nw" style="left: 0; width: 100%; top: 2.4mm; font-size: {{ mb_strlen($lzMarca) <= 8 ? 12 : 9 }}pt; color: #1e1b4b; letter-spacing: 0.8pt;">{{ mb_strtoupper($lzMarca) }}</div>
                        @endif
                    </div>
                @else
                    @if($logo)<img class="abs lz-logo" src="{{ $logo }}" alt="">@endif
                    <div class="abs lz-de b centro nw">PRECIOS DE</div>
                    <div class="abs lz-locura"><div class="abs b centro nw">LOCURA</div></div>
                    <div class="abs lz-cinta"><div class="abs b centro nw">PRECIOS BAJOS TODO EL AÑO</div></div>
                @endif
                <div class="abs lz-pie b centro nw" style="left: {{ $pad }}mm; width: {{ $lzW }}mm; top: {{ $H - $pad - 2.6 }}mm; font-size: {{ $cod['fs'] }}pt;">{{ $cod['lineas'][0] ?? '' }}</div>

                <div class="abs lz-panel blanco">
                    <div class="abs lz-pill"><div class="abs b centro nw">PRECIO</div></div>
                    <div class="abs lz-ref b">REF</div>
                    <div class="abs num" style="top: {{ 8.6 + (12 - $ptMm($numFs)) / 2 }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    @if($e['con_mayor'])
                        <div class="abs lz-mayor b centro nw">{{ $e['cantidad_mayor'] }}+ PZAS: REF {{ $e['mayor_fmt'] }} C/U</div>
                    @endif
                    <div class="abs lz-sep"></div>
                    @foreach($nom['lineas'] as $li => $linea)
                        <div class="abs linea" style="left: 3mm; top: {{ $nomTop + $li * $ptMm($nom['alto_linea']) }}mm; font-size: {{ $nom['fs'] }}pt; line-height: {{ $nom['alto_linea'] }}pt; height: {{ $nom['alto_linea'] }}pt;">{{ $linea }}</div>
                    @endforeach
                    <div class="abs lz-web b centro nw">{{ $web }} · {{ $fecha }}</div>
                </div>
            @elseif($tipo === 'pc')
                @php
                    $specs = $e['specs'] ?? [];
                    $marca = trim((string) ($specs['marca'] ?? '')) ?: mb_strtoupper(explode(' ', $e['nombre'])[0] ?? '');
                    $marcaFs = mb_strlen($marca) <= 6 ? 16 : (mb_strlen($marca) <= 9 ? 13 : 10.5);
                    $precioFs = $numFsPor($e['unidad_entero_fmt'], 26, 22, 18);
                    $logoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    if ($logoM) {
                        $lmH = $logoM['ratio'] < 1.6 ? 7.6 : 6.4;
                        $lmW = min(30, $lmH * $logoM['ratio']);
                        $lmH = $lmW / $logoM['ratio'];
                        $codTop = 2 + $lmH + 0.9;
                    } else {
                        $codTop = 9.3;
                    }
                    $lleno = fn ($c) => trim((string) ($specs[$c] ?? '')) !== '';
                    $izq = array_values(array_filter(['modelo', 'procesador', 'pantalla'], $lleno));
                    $der = array_values(array_filter(['grafica', 'ram', 'almacenamiento', 'sistema'], $lleno));
                    while (count($izq) < 3 && count($der) > 3) {
                        $izq[] = array_shift($der);
                    }
                    $pcColumnas = [$izq, array_slice($der, 0, 3)];
                    $specsTop = max(13.4, $codTop + 3.6);
                @endphp
                <div class="abs pc-caja">
                    <div class="abs pc-precio b nw">PRECIO</div>
                    <div class="abs pc-regular b nw">REGULAR</div>
                    @if($logo)<img class="abs pc-logo" src="{{ $logo }}" alt="">@endif
                </div>
                <div class="abs pc-pill">
                    <div class="abs r b">REF</div>
                    <div class="abs n b nw" style="top: {{ (11.8 - $ptMm($precioFs)) / 2 + 0.9 }}mm; font-size: {{ $precioFs }}pt;">{{ $e['unidad_entero_fmt'] }}</div>
                </div>
                <div class="abs pc-pie b centro nw" style="top: {{ $pad + 34.4 }}mm;">Precios en REF</div>
                <div class="abs pc-pie b centro nw" style="top: {{ $pad + 37.4 }}mm; font-weight: normal;">{{ $fecha }}</div>

                <div class="abs pc-panel blanco">
                    @if($logoM)
                        <img class="abs" src="{{ $logoM['src'] }}" alt="" style="left: {{ ($pcPanelW - $lmW) / 2 }}mm; top: 2mm; width: {{ $lmW }}mm; height: {{ $lmH }}mm;">
                    @else
                        <div class="abs pc-marca b nw centro" style="left: 0; width: 100%; font-size: {{ $marcaFs }}pt;">{{ $marca }}</div>
                    @endif
                    <div class="abs pc-cod b nw centro" style="left: 0; width: 100%; top: {{ $codTop }}mm;">COD: {{ $e['codigo'] }}</div>
                    @foreach($pcColumnas as $c => $campos)
                        @php $fila = 0; @endphp
                        @foreach($campos as $campo)
                            @php
                                $sx = $pcColX[$c];
                                $sy = $specsTop + $fila * ($specsTop > 13.4 ? 7.6 : $pcFilaH);
                                $fila++;
                                $sp = $ajustar("pc{$e['id']}{$campo}" . md5((string) $specs[$campo]), (string) $specs[$campo], $pcColWs[$c] - 5.6, 7.4, [7.5, 7, 6.5, 6, 5.5], 2, false);
                                $spTop = $sy + max(0, (4.4 - count($sp['lineas']) * $ptMm($sp['alto_linea'])) / 2);
                            @endphp
                            <div class="abs pc-ico" style="left: {{ $sx }}mm; top: {{ $sy }}mm;"><div class="abs">{{ $pcIcono[$campo] }}</div></div>
                            @foreach($sp['lineas'] as $li => $linea)
                                <div class="abs linea" style="left: {{ $sx + 5.6 }}mm; top: {{ $spTop + $li * $ptMm($sp['alto_linea']) }}mm; font-size: {{ $sp['fs'] }}pt; line-height: {{ $sp['alto_linea'] }}pt; height: {{ $sp['alto_linea'] }}pt; text-transform: none;">{{ $linea }}</div>
                            @endforeach
                        @endforeach
                    @endforeach
                    <div class="abs pc-web b centro nw">{{ $web }}</div>
                </div>
            @else
                @php
                    $nom = $ajustar('cl' . $e['id'], $e['nombre'], $nomAnchoTxt, $nomAltoTxt, $nomTamanos);
                    $txtTop = $tagH + max(0, ($nomAltoTxt - count($nom['lineas']) * $ptMm($nom['alto_linea'])) / 2);
                    $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                    $numFs = $pt($e['con_mayor'] ? 23 : 30);
                    $libreH = $precioH - $headH;
                    $numTop = $headH + ($libreH - $ptMm($numFs)) / 2 + ($chica ? 0.3 : 0.6);
                    $numTopMayor = $conAhorro ? $numTop - ($chica ? 1 : 1.5) : $numTop;
                    $ahorroTop = $precioH - $ptMm($pt(6)) - ($chica ? 0.9 : 1.4);
                @endphp
                @if($logo)<img class="abs logo" src="{{ $logo }}" alt="">@endif

                <div class="abs nombre blanco">
                    <div class="abs tag">PRODUCTO</div>
                    @foreach($nom['lineas'] as $li => $linea)
                        <div class="abs linea" style="left: {{ $nomPadX }}mm; top: {{ $txtTop + $li * $ptMm($nom['alto_linea']) }}mm; font-size: {{ $nom['fs'] }}pt; line-height: {{ $nom['alto_linea'] }}pt; height: {{ $nom['alto_linea'] }}pt;">{{ $linea }}</div>
                    @endforeach
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

                <div class="abs pie" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                    <span>Cód. {{ $e['codigo'] }}</span>
                    <span class="abs" style="right: 0; top: 0;">Precios en REF · {{ $fecha }}</span>
                </div>
            @endif
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
