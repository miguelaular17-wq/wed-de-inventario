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
    $pcPillH = $m(11);
    $anchoTextoMm = $anchoTextoMm ?? fn (string $t, float $fs) => mb_strlen($t) * $fs * 0.62 * 0.3528;
    $pcPanelTop = $pcPillTop + $pcPillH + $m(2.6);
    $pcPanelH = $H - $pcPanelTop - $pad - $m(4.4);
    $pcColW = ($inW - $m(8)) / 2;
    $pcIcono = [
        'modelo' => '◆', 'procesador' => '⚙', 'grafica' => '▦', 'pantalla' => '▭',
        'ram' => '▤', 'almacenamiento' => '◉', 'sistema' => '⊞',
    ];

    // Tecno House: medidas tomadas del diseño de referencia; el ancho y el alto escalan por separado.
    $esTh = str_starts_with($tipo, 'th_');
    $logoTh = $logoTh ?? null;
    $iconosTh = $iconosTh ?? [];
    $mx = fn (float $mm) => $mm * $W / $base['ancho'];
    $my = fn (float $mm) => $mm * $H / $base['alto'];
    $thEtiquetas = '/(^|\s)(Modelo|Procesador|Gráfica|Pantalla|Ram|Disco|Almacenamiento|Cámara|Batería|OS):/u';
    $thLineas = function (array $aj, float $left, float $top) use ($ptMm, $thEtiquetas) {
        $html = '';
        foreach ($aj['lineas'] as $li => $l) {
            $txt = preg_replace($thEtiquetas, '$1<b>$2:</b>', e($l));
            $html .= '<div class="abs th-txt" style="left: ' . $left . 'mm; top: ' . ($top + $li * $ptMm($aj['alto_linea'])) . 'mm; font-size: ' . $aj['fs'] . 'pt; line-height: ' . $aj['alto_linea'] . 'pt; height: ' . $aj['alto_linea'] . 'pt;">' . $txt . '</div>';
        }
        return $html;
    };
    // Temporada: gorro (Navidad) o sombrero de bruja (Halloween) sobre el círculo del logo.
    $tema = $tema ?? 'ninguno';
    $gorro = $gorro ?? null;
    $ponGorro = function (float $circL, float $circT, float $d) use ($gorro) {
        if (! $gorro) {
            return '';
        }
        $gw = $d * 0.85;
        $gh = $gw / $gorro['ratio'];
        // El ala del dibujo queda al 84 % de su alto: se apoya al 30 % del círculo, un poco a la derecha.
        return '<img class="abs" src="' . $gorro['src'] . '" alt="" style="left: ' . ($circL + ($d - $gw) / 2 + $d * 0.06) . 'mm; top: ' . ($circT + $d * 0.30 - $gh * 0.84) . 'mm; width: ' . $gw . 'mm; height: ' . $gh . 'mm;">';
    };
    $thLogo = function (float $top, float $alto) use ($logoTh, $W, $ponGorro) {
        if (! $logoTh) {
            return '';
        }
        $ancho = min($W - 8, $alto * $logoTh['ratio']);
        $alto = $ancho / $logoTh['ratio'];
        $left = ($W - $ancho) / 2;
        return '<img class="abs" src="' . $logoTh['src'] . '" alt="" style="left: ' . $left . 'mm; top: ' . $top . 'mm; width: ' . $ancho . 'mm; height: ' . $alto . 'mm;">'
            . $ponGorro($left, $top, $alto);
    };
    $thCabecera = function (float $left, float $top, float $ancho, float $alto, string $l1, string $l2, array $tamanos, string $clave) use ($fit, $ptMm, $m) {
        $fsCab = min(
            $fit($clave . 'a' . $l1, $l1, $ancho - $m(2), $tamanos)['fs'],
            $fit($clave . 'b' . $l2, $l2, $ancho - $m(2), $tamanos)['fs'],
            round(($alto - $m(1.2)) / 2 / 1.06 / 0.3528, 1),
        );
        $lh = $ptMm($fsCab) * 1.06;
        $t = max($m(0.3), ($alto - 2 * $lh) / 2 - $m(0.1));
        return '<div class="abs th-cab" style="left: ' . $left . 'mm; top: ' . $top . 'mm; width: ' . $ancho . 'mm; height: ' . $alto . 'mm;">'
            . '<div class="abs" style="top: ' . $t . 'mm; font-size: ' . $fsCab . 'pt;">' . e($l1) . '</div>'
            . '<div class="abs th-fucsia" style="top: ' . ($t + $lh) . 'mm; font-size: ' . $fsCab . 'pt;">' . e($l2) . '</div></div>';
    };
    // Precio con decimales chicos arriba ("3" grande y ".00" pequeño a la altura del tope del número).
    $partesPrecio = fn (string $valor) => preg_match('/^(.*?)(\.\d{1,2})$/u', $valor, $pp) ? [$pp[1], $pp[2]] : [$valor, ''];
    $anchoSup = function (string $valor, float $fs) use ($partesPrecio, $anchoTextoMm) {
        [$ent, $dec] = $partesPrecio($valor);
        return $anchoTextoMm($ent, $fs) + ($dec !== '' ? $anchoTextoMm($dec, $fs * 0.5) + 0.25 : 0);
    };
    $fitSup = function (array $valores, float $ancho, array $tamanos) use ($anchoSup) {
        foreach ($tamanos as $fsSup) {
            if (max(array_map(fn ($v) => $anchoSup($v, $fsSup), $valores)) <= $ancho) {
                return $fsSup;
            }
        }
        return end($tamanos);
    };
    $precioSup = function (string $valor, float $fs, float $top, float $anchoCaja, string $color = '#0f172a') use ($partesPrecio, $anchoTextoMm) {
        [$ent, $dec] = $partesPrecio($valor);
        $fsDec = round($fs * 0.5, 1);
        $wE = $anchoTextoMm($ent, $fs);
        $wD = $dec !== '' ? $anchoTextoMm($dec, $fsDec) + 0.25 : 0;
        $left = max(0, ($anchoCaja - $wE - $wD) / 2);
        $html = '<div class="abs sup-num" style="left: ' . $left . 'mm; top: ' . $top . 'mm; font-size: ' . $fs . 'pt; color: ' . $color . ';">' . e($ent) . '</div>';
        if ($dec !== '') {
            // El tope del glifo chico queda alineado con el del grande.
            $html .= '<div class="abs sup-num" style="left: ' . ($left + $wE + 0.25) . 'mm; top: ' . ($top + ($fs - $fsDec) * 0.3528 * 0.13) . 'mm; font-size: ' . $fsDec . 'pt; color: ' . $color . ';">' . e($dec) . '</div>';
        }
        return $html;
    };
    $thPrecio = function (float $left, float $top, float $ancho, float $alto, string $valor, array $tamanos, string $clave, string $color = '#0b1f4d') use ($fitSup, $precioSup, $ptMm, $m, $k) {
        $fsP = $fitSup([$valor], $ancho - $m(5), $tamanos);
        return '<div class="abs th-blanco" style="left: ' . $left . 'mm; top: ' . $top . 'mm; width: ' . $ancho . 'mm; height: ' . $alto . 'mm;">'
            . '<div class="abs b" style="left: ' . $m(1.3) . 'mm; top: ' . $m(0.8) . 'mm; font-size: ' . round(4.6 * $k, 1) . 'pt; color: #64748b;">REF</div>'
            . $precioSup($valor, $fsP, ($alto - $ptMm($fsP)) / 2 + $m(0.5), $ancho, $color) . '</div>';
    };
    $thLema = function (float $centroY, float $anchoMax, array $tamanos) use ($fit, $anchoTextoMm, $ptMm, $W) {
        $a = '¡Tecnología ';
        $b = 'a tu alcance!';
        $fsL = $fit('thlema' . round($anchoMax, 2), $a . $b, $anchoMax, $tamanos)['fs'];
        $wa = $anchoTextoMm($a, $fsL);
        $left = ($W - $wa - $anchoTextoMm($b, $fsL)) / 2;
        $top = $centroY - $ptMm($fsL) / 2;
        return '<div class="abs b nw" style="left: ' . $left . 'mm; top: ' . $top . 'mm; font-size: ' . $fsL . 'pt; color: #fff;">' . e($a) . '</div>'
            . '<div class="abs b nw" style="left: ' . ($left + $wa) . 'mm; top: ' . $top . 'mm; font-size: ' . $fsL . 'pt; color: #22d3ee;">' . e($b) . '</div>';
    };
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
    .sup-num { font-weight: bold; line-height: 1; white-space: nowrap; }

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
    .pc-pill { top: {{ $pcPillTop }}mm; height: {{ $pcPillH }}mm; background: #db2777; border-radius: {{ $m(3.5) }}mm; border: {{ $m(0.7) }}mm solid #fff; }
    .pc-panel { left: {{ $pad }}mm; top: {{ $pcPanelTop }}mm; width: {{ $inW }}mm; height: {{ $pcPanelH }}mm; border-radius: {{ $radio }}mm; }
    .pc-ico { background: #db2777; }
    .pc-ico div { left: 0; width: 100%; color: #fff; font-weight: normal; text-align: center; }
    .pc-pie { top: {{ $H - $pad - $m(3.2) }}mm; color: #fff; font-size: {{ round(6 * $k, 1) }}pt; font-weight: bold; white-space: nowrap; }

    .etq.th { background-color: #041a4a; }
    .th-cab { background: #2e1065; border: {{ $m(0.35) }}mm solid #38bdf8; border-radius: {{ $m(1.6) }}mm; color: #fff; }
    .th-cab div { left: 0; width: 100%; text-align: center; white-space: nowrap; line-height: 1; font-weight: bold; letter-spacing: 0.3pt; }
    .th-fucsia { color: #e879f9; }
    .th-blanco { background: #fff; border-radius: {{ $m(2) }}mm; overflow: hidden; }
    .th-blanco .num { color: #0b1f4d; }
    .th-txt { white-space: nowrap; color: #0b1f4d; font-weight: normal; }

    @if($tema === 'halloween')
        .etq { background-color: #7e22ce; }
        .caja .h { background: #1c1917; }
        .caja .h span { color: #fb923c; }
        .caja.mayor .h { background: #f97316; color: #1c1917; }
        .caja.mayor .h span { color: #581c87; }
        .caja.mayor .marco { border-color: #f97316; }
        .ahorro { color: #c2410c; }
        .lz-locura { background: #1c1917; border-bottom-color: #f97316; }
        .lz-cinta { background: #fb923c; }
        .lz-cinta div { color: #1c1917; }
        .lz-pill { background: #ea580c; }
        .pc-caja { background: #1c1917; }
        .pc-pill, .pc-ico { background: #ea580c; }
        .th-cab { background: #1c1917; border-color: #f97316; }
        .th-fucsia { color: #fb923c; }
    @elseif($tema === 'navidad')
        .etq { background-color: #b91c1c; }
        .caja .h { background: #14532d; }
        .caja .h span { color: #fde047; }
        .caja.mayor .h { background: #dc2626; color: #fff; }
        .caja.mayor .h span { color: #fde047; }
        .ahorro { color: #b91c1c; }
        .lz-locura { background: #14532d; }
        .lz-cinta div { color: #b91c1c; }
        .lz-pill { background: #b91c1c; }
        .pc-caja { background: #14532d; }
        .pc-pill, .pc-ico { background: #b91c1c; }
        .th-cab { background: #7f1d1d; border-color: #facc15; }
        .th-fucsia { color: #fde047; }
    @endif
</style>
</head>
<body>
@foreach($paginas as $fichas)
    <div class="hoja {{ $loop->last ? '' : 'salto' }}">
        @foreach($fichas as $i => $e)
            @php [$x, $y] = $pos($i); @endphp
            <div class="etq {{ $esTh ? 'th' : '' }}" style="left: {{ $x }}mm; top: {{ $y }}mm;">
            @if($tipo === 'th_pc' || $tipo === 'th_cel')
                @php
                    $specs = $e['specs'] ?? [];
                    $esCel = $tipo === 'th_cel';
                    $val = fn (string $c) => trim((string) ($specs[$c] ?? ''));
                    $marca = $val('marca');
                    $logoM = $logosMarca[$e['logo_marca'] ?? ''] ?? null;
                    $modelo = $val('modelo');
                    // Con el logo de la misma marca a la vista no se repite la marca en el modelo.
                    if ($logoM && $marca !== '' && ($e['logo_marca'] ?? '') === mb_strtolower($marca) && mb_stripos($modelo, $marca . ' ') === 0) {
                        $modelo = trim(mb_substr($modelo, mb_strlen($marca) + 1));
                    }
                    $pantalla = (string) preg_replace('/^pantalla\s+/iu', '', $val('pantalla'));
                    $ram = (string) preg_replace('/\s+RAM$/iu', '', $val('ram'));
                    $alm = $val('almacenamiento');
                    $memoria = trim(($ram !== '' ? 'Ram: ' . $ram : '') . ($ram !== '' && $alm !== '' ? '   ' : '') . ($alm !== '' ? ($esCel ? 'Almacenamiento: ' : 'Disco: ') . $alm : ''));
                    $logoSis = $logosMarca[$e['logo_sistema'] ?? ''] ?? null;
                    $iconoOs = preg_match('/windows/i', $val('sistema')) ? 'windows' : 'sistema';
                    $filasTh = $esCel
                        ? [['celular', 'Modelo', $modelo], ['pantalla', 'Pantalla', $pantalla], ['ram', '', $memoria], ['chip', 'Procesador', $val('procesador')], ['camara', 'Cámara', $val('camara')], ['bateria', 'Batería', $val('bateria')], ['sistema', 'OS', $val('sistema'), $logoSis]]
                        : [['laptop', 'Modelo', $modelo], ['chip', 'Procesador', $val('procesador')], ['grafica', 'Gráfica', $val('grafica')], ['pantalla', 'Pantalla', $pantalla], ['ram', '', $memoria], [$iconoOs, 'OS', $val('sistema'), $logoSis]];
                    $filasTh = array_slice(array_values(array_filter($filasTh, fn ($f) => $f[2] !== '')), 0, 6);
                    if ($filasTh === []) {
                        $filasTh = [[$esCel ? 'celular' : 'laptop', '', $e['nombre']]];
                    }

                    $panL = $mx(8.2);
                    $panT = $my(35);
                    $panW = $mx(76);
                    $panH = $my(38.7);
                    $nF = count($filasTh);
                    $filaH = min($my(6.9), ($panH - $my(4.4)) / $nF);
                    $filasTop = $panT + ($panH - $nF * $filaH) / 2;
                    $icoD = min($m(6.4), $filaH - $m(0.3));
                    $icoCx = $mx(8.7);
                    $txtL = $icoCx + $icoD / 2 + $mx(1.6);
                    $txtW = $panL + $panW - $mx(2) - $txtL;

                    $marcaL = $mx(64.4);
                    $marcaT = $my(23.8);
                    $marcaW = $mx(19.8);
                    $marcaH = $my(7);
                    if ($logoM) {
                        $lmW = min($marcaW - $m(2.4), ($marcaH - $m(1.8)) * $logoM['ratio']);
                        $lmH = $lmW / $logoM['ratio'];
                    } elseif ($marca !== '') {
                        $marcaFit = $fit('thmarca' . $marca, mb_strtoupper($marca), $marcaW - $m(2), $fs([12, 11, 10, 9, 8, 7, 6]));
                    }
                    $codFit = $fit('thcod' . $e['id'], 'COD: ' . $e['codigo'], $W - $m(10), $fs([5, 4.6, 4.2, 3.8]));
                @endphp
                {!! $thLogo($my(3.4), $m(8)) !!}

                {!! $thCabecera($mx(10.6), $my(13.7), $mx(23.6), $my(7.9), 'PROMO', 'EN DIVISA', $fs([10, 9.5, 9, 8.5, 8, 7.5]), 'thpromo') !!}
                {!! $thPrecio($mx(8.8), $my(21.6), $mx(26.9), $my(10), $e['unidad_usd_fmt'] ?? $e['unidad_entero_fmt'], $fs([24, 22, 20, 18, 16, 14, 12]), 'thpnum') !!}
                {!! $thCabecera($mx(40.3), $my(16.7), $mx(19.2), $my(6.7), 'PRECIO', 'REGULAR', $fs([8, 7.5, 7, 6.5, 6]), 'threg') !!}
                {!! $thPrecio($mx(38.4), $my(23.4), $mx(20.5), $my(7.9), $e['regular_usd_fmt'] ?? $e['regular_entero_fmt'], $fs([18, 17, 16, 15, 14, 12, 11, 10]), 'thrnum') !!}

                @if($logoM || $marca !== '')
                    <div class="abs th-blanco" style="left: {{ $marcaL }}mm; top: {{ $marcaT }}mm; width: {{ $marcaW }}mm; height: {{ $marcaH }}mm;">
                        @if($logoM)
                            <img class="abs" src="{{ $logoM['src'] }}" alt="" style="left: {{ ($marcaW - $lmW) / 2 }}mm; top: {{ ($marcaH - $lmH) / 2 }}mm; width: {{ $lmW }}mm; height: {{ $lmH }}mm;">
                        @else
                            <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ ($marcaH - $ptMm($marcaFit['fs'])) / 2 - $m(0.2) }}mm; font-size: {{ $marcaFit['fs'] }}pt; color: #0b1f4d; letter-spacing: 0.6pt;">{{ $marcaFit['lineas'][0] ?? '' }}</div>
                        @endif
                    </div>
                @endif

                <div class="abs th-blanco" style="left: {{ $panL }}mm; top: {{ $panT }}mm; width: {{ $panW }}mm; height: {{ $panH }}mm; border-radius: {{ $m(3) }}mm;"></div>
                @foreach($filasTh as $f => $filaTh)
                    @php
                        [$icono, $etiqueta, $valor] = $filaTh;
                        $logoFila = $filaTh[3] ?? null;
                        $fy = $filasTop + $f * $filaH;
                        $texto = $etiqueta !== '' ? $etiqueta . ': ' . $valor : $valor;
                        $sp = $ajustar('th' . $e['id'] . $f . md5($texto) . round($filaH, 2), $texto, $txtW, $filaH - $m(0.3), $fs([9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6, 5.5]), 2, false);
                    @endphp
                    @if(isset($iconosTh[$icono]))
                        <img class="abs" src="{{ $iconosTh[$icono] }}" alt="" style="left: {{ $icoCx - $icoD / 2 }}mm; top: {{ $fy + ($filaH - $icoD) / 2 }}mm; width: {{ $icoD }}mm; height: {{ $icoD }}mm;">
                    @endif
                    @if($logoFila)
                        @php
                            // El sistema con su logo (ChromeOS) en lugar del texto.
                            $fsOs = min(round(9.5 * $k, 1), round(($filaH - $m(0.6)) / 0.3528 / 1.2, 1));
                            $osW = $anchoTextoMm('OS: ', $fsOs);
                            $lsH = min($filaH - $m(1.2), $m(5));
                            $lsW = min($txtW - $osW, $lsH * $logoFila['ratio']);
                            $lsH = $lsW / $logoFila['ratio'];
                        @endphp
                        <div class="abs th-txt" style="left: {{ $txtL }}mm; top: {{ $fy + ($filaH - $ptMm($fsOs)) / 2 - $m(0.3) }}mm; font-size: {{ $fsOs }}pt;"><b>OS:</b></div>
                        <img class="abs" src="{{ $logoFila['src'] }}" alt="" style="left: {{ $txtL + $osW }}mm; top: {{ $fy + ($filaH - $lsH) / 2 }}mm; width: {{ $lsW }}mm; height: {{ $lsH }}mm;">
                    @else
                        {!! $thLineas($sp, $txtL, $fy + max(0, ($filaH - $altoLineas($sp)) / 2)) !!}
                    @endif
                @endforeach

                {!! $thLema($my(76.9), $W - $m(14), $fs([11, 10.5, 10, 9.5, 9, 8.5, 8, 7])) !!}
                <div class="abs b centro nw" style="left: 0; width: 100%; top: {{ $my(79.9) }}mm; font-size: {{ $codFit['fs'] }}pt; color: #bae6fd;">{{ $codFit['lineas'][0] ?? '' }}</div>
            @elseif($tipo === 'th_clasica')
                @php
                    $nomL = $mx(3);
                    $nomT = $my(8.8);
                    $nomW = $W - 2 * $mx(3);
                    $nomH = $my(11.6);
                    $nomTxtT = $m(2.6);
                    $nomTxtH = $nomH - $nomTxtT - $m(0.6);
                    $nom = $ajustar('thcl' . $e['id'], $e['nombre'], $nomW - $m(4), $nomTxtH, $fs([10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6]));
                    $codFit = $fit('thclcod' . $e['id'], 'Cód. ' . $e['codigo'], $nomW - $m(3), $fs([4.6, 4.2, 3.8]));
                    $areaT = $my(22.2);
                    $areaH = $my(22.4);
                    $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                @endphp
                {!! $thLogo($my(2.2), $m(5.4)) !!}
                <div class="abs th-blanco" style="left: {{ $nomL }}mm; top: {{ $nomT }}mm; width: {{ $nomW }}mm; height: {{ $nomH }}mm;">
                    <div class="abs b nw" style="left: {{ $m(1.5) }}mm; top: {{ $m(0.7) }}mm; font-size: {{ $codFit['fs'] }}pt; color: #64748b;">{{ $codFit['lineas'][0] ?? '' }}</div>
                    @foreach($nom['lineas'] as $li => $l)
                        <div class="abs linea centro" style="left: 0; width: 100%; top: {{ $nomTxtT + max(0, ($nomTxtH - $altoLineas($nom)) / 2) + $li * $ptMm($nom['alto_linea']) }}mm; font-size: {{ $nom['fs'] }}pt; line-height: {{ $nom['alto_linea'] }}pt; height: {{ $nom['alto_linea'] }}pt; color: #0b1f4d;">{{ $l }}</div>
                    @endforeach
                </div>

                @if($e['con_mayor'])
                    @php
                        $colGap = $mx(2.4);
                        $colW = ($nomW - $colGap) / 2;
                        $cabH = $my(7.6);
                        $cajaH = $areaH - $cabH;
                        $txtMayor = $e['texto_mayor'] ?? $e['cantidad_mayor'] . '+ PZAS';
                        $numTams = $fs([20, 18, 16, 14, 12, 10]);
                        $numFsCl = $fitSup([$e['unidad_fmt'], $e['mayor_fmt']], $colW - $m(5), $numTams);
                        $numTamsIguales = [$numFsCl];
                    @endphp
                    @foreach([[$e['texto_unidad'] ?? '1 PZA', $e['unidad_fmt'], $nomL, false], [$txtMayor, $e['mayor_fmt'], $nomL + $colW + $colGap, true]] as [$cant, $valor, $cx, $esMayor])
                        {!! $thCabecera($cx + $mx(2.5), $areaT, $colW - 2 * $mx(2.5), $cabH, 'COMPRA', $cant, $fs([8.5, 8, 7.5, 7, 6.5, 6]), 'thclcab') !!}
                        {!! $thPrecio($cx, $areaT + $cabH, $colW, $cajaH, $valor, $numTamsIguales, 'thcl' . ($esMayor ? 'm' : 'u'), $esMayor ? '#7e22ce' : '#0b1f4d') !!}
                        @if($esMayor && $conAhorro)
                            @php $ahTh = $fit('thclah' . $e['texto_ahorro'], $e['texto_ahorro'], $colW - $m(1.5), $fs([4.8, 4.4, 4, 3.6])); @endphp
                            <div class="abs b centro nw" style="left: {{ $cx }}mm; width: {{ $colW }}mm; top: {{ $areaT + $areaH - $m(2.6) }}mm; font-size: {{ $ahTh['fs'] }}pt; color: #c026d3;">{{ $e['texto_ahorro'] }}</div>
                        @endif
                    @endforeach
                @else
                    @php
                        $txtUnidadTh = $e['texto_unidad'] ?? '1 PZA';
                        $txtPrecioTh = $txtUnidadTh === '1 PZA' ? 'PRECIO' : 'COMPRA ' . $txtUnidadTh;
                        $cabW = $txtPrecioTh === 'PRECIO' ? $mx(26) : $mx(40);
                        $cajaW = $mx(44);
                    @endphp
                    <div class="abs th-cab" style="left: {{ ($W - $cabW) / 2 }}mm; top: {{ $areaT + $my(0.6) }}mm; width: {{ $cabW }}mm; height: {{ $my(6) }}mm;">
                        @php $fsP = $fit('thclp' . $txtPrecioTh, $txtPrecioTh, $cabW - $m(3), $fs([10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5]))['fs']; @endphp
                        <div class="abs" style="top: {{ ($my(6) - $ptMm($fsP)) / 2 + $m(0.15) }}mm; font-size: {{ $fsP }}pt; letter-spacing: {{ $txtPrecioTh === 'PRECIO' ? 1.2 : 0.3 }}pt;">{{ $txtPrecioTh }}</div>
                    </div>
                    {!! $thPrecio(($W - $cajaW) / 2, $areaT + $my(6.6), $cajaW, $areaH - $my(6.6), $e['unidad_fmt'], $fs([28, 26, 24, 22, 20, 18, 16, 14]), 'thcls') !!}
                @endif

                {!! $thLema($my(47.3), $W - $m(10), $fs([7.5, 7, 6.5, 6, 5.5, 5])) !!}
            @elseif($tipo === 'locura')
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
                    $precio = ['fs' => $fitSup([$e['unidad_fmt']], $lzPanelW - $m(5), $fs([26, 24, 22, 20, 18, 16, 14]))];
                    $mayor = $e['con_mayor'] ? $fit('lzmay', $e['texto_mayor'] . ': REF ' . $e['mayor_fmt'] . ($e['es_paquete'] ? '' : ' C/U'), $lzPanelW - $m(3), $fs([6.5, 6, 5.5, 5, 4.5])) : null;
                    $nomTop = $m(25);
                    $nomH = $lzPanelH - $nomTop - $m(4.6);
                    $nom = $ajustar('lz' . $e['id'], $e['nombre'], $lzPanelW - $m(5), $nomH, $fs([9, 8.5, 8, 7.5, 7, 6.5, 6, 5.5]));
                    $webFit = $fit('lzweb', $web, $lzPanelW - $m(3), $fs([5, 4.6, 4.2, 4]));
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad + ($lzW - $m($L['logo'])) / 2 }}mm; top: {{ $pad }}mm; width: {{ $m($L['logo']) }}mm; height: {{ $m($L['logo']) }}mm;">{!! $ponGorro($pad + ($lzW - $m($L['logo'])) / 2, $pad, $m($L['logo'])) !!}@endif
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
                    {!! $precioSup($e['unidad_fmt'], $precio['fs'], $m(8.6) + ($m(10.6) - $ptMm($precio['fs'])) / 2, $lzPanelW) !!}
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
                    $precio = $fit('pcnum', $e['unidad_entero_fmt'], $inW - $m(30), $fs([22, 21, 20, 19, 18, 17, 16]));
                    $refFs = round(6.5 * $k, 1);
                    $pillPad = $m(4);
                    $refW = $anchoTextoMm('REF', $refFs);
                    $numW = $anchoTextoMm($e['unidad_entero_fmt'], $precio['fs']);
                    $pillW = min($inW, $pillPad + $refW + $m(2.2) + $numW + $pillPad);
                    $pillLeft = $pad + ($inW - $pillW) / 2;
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
                    @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $inW - $m(16.5) }}mm; top: {{ $m(1.6) }}mm; width: {{ $m(13.5) }}mm; height: {{ $m(13.5) }}mm;">{!! $ponGorro($inW - $m(16.5), $m(1.6), $m(13.5)) !!}@endif
                </div>
                <div class="abs pc-pill" style="left: {{ $pillLeft }}mm; width: {{ $pillW - 2 * $m(0.7) }}mm;">
                    <div class="abs b nw" style="left: {{ $pillPad }}mm; top: {{ ($pcPillH - $m(1.4) - $ptMm($refFs)) / 2 }}mm; font-size: {{ $refFs }}pt; color: #fde68a;">REF</div>
                    <div class="abs b nw" style="left: {{ $pillPad + $refW + $m(2.2) }}mm; top: {{ ($pcPillH - $m(1.4) - $ptMm($precio['fs'])) / 2 + $m(0.3) }}mm; font-size: {{ $precio['fs'] }}pt; line-height: 1; color: #fff; letter-spacing: -0.3pt;">{{ $e['unidad_entero_fmt'] }}</div>
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
                            @php $logoSisPc = $campo === 'sistema' ? ($logosMarca[$e['logo_sistema'] ?? ''] ?? null) : null; @endphp
                            @if($logoSisPc)
                                @php
                                    $lsH = min($filaH - $m(1.2), $m(4.6));
                                    $lsW = min($pcColW - $ico - $m(1.6), $lsH * $logoSisPc['ratio']);
                                    $lsH = $lsW / $logoSisPc['ratio'];
                                @endphp
                                <img class="abs" src="{{ $logoSisPc['src'] }}" alt="" style="left: {{ $sx + $ico + $m(1.6) }}mm; top: {{ $sy + ($filaH - $lsH) / 2 - $m(0.4) }}mm; width: {{ $lsW }}mm; height: {{ $lsH }}mm;">
                            @else
                                {!! $lineas($sp, $sx + $ico + $m(1.6), $sy + max(0, ($filaH - $altoLineas($sp)) / 2) - $m(0.4)) !!}
                            @endif
                        @endforeach
                    @endforeach
                </div>
                <div class="abs pc-pie" style="left: {{ $pad + $m(1) }}mm;">Precios en REF · {{ $fecha }}</div>
                <div class="abs pc-pie der" style="left: 0; width: {{ $W - $pad - $m(1) }}mm;">{{ $web }}</div>
            @else
                @php
                    $nom = $ajustar('cl' . $e['id'], $e['nombre'], $nomAnchoTxt, $nomAltoTxt, $fs([11, 10, 9.5, 9, 8.5, 8, 7.5, 7, 6.5, 6]));
                    $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                    $txtUnidad = $e['texto_unidad'] ?? '1 PZA';
                    $txtPrecio = $txtUnidad === '1 PZA' ? 'PRECIO' : 'COMPRA ' . $txtUnidad;
                    if ($e['con_mayor']) {
                        $headH = $headH2;
                        $txtMayor = $e['texto_mayor'] ?? $e['cantidad_mayor'] . '+ PZAS';
                        $hFs = min(
                            $fit('cabc', 'COMPRA', $boxW - $m(2), $cabFs)['fs'],
                            $fit('cabu' . $txtUnidad, $txtUnidad, $boxW - $m(2), $cabFs)['fs'],
                            $fit('cabm' . $txtMayor, $txtMayor, $boxW - $m(2), $cabFs)['fs'],
                        );
                        $hLinea = $ptMm($hFs) * 1.08;
                        $hTop = max($m(0.4), ($headH - 2 * $hLinea) / 2 + $m(0.2));
                        $numFs = $fitSup([$e['unidad_fmt'], $e['mayor_fmt']], $boxW - $m(3), $fs([22, 20, 18, 16, 14, 12, 10]));
                    } else {
                        $headH = $headH1;
                        $hFs = $fit('cabs' . $txtPrecio, $txtPrecio, $inW - $m(4), $cabFs)['fs'];
                        $hTop = max($m(0.4), ($headH - $ptMm($hFs)) / 2 + $m(0.2));
                        $numFs = $fitSup([$e['unidad_fmt']], $inW - $m(8), $fs([30, 28, 26, 24, 22, 20, 18]));
                    }
                    $libreH = $precioH - $headH;
                    $numTop = $headH + ($libreH - $ptMm($numFs)) / 2 + $m(0.6);
                    $numTopMayor = $conAhorro ? $numTop - $m(1.3) : $numTop;
                    $ahorroTop = $precioH - $ptMm(5.3 * $k) - $m(1.2);
                    $cod = $fit('clcod' . $e['id'], 'Cód. ' . $e['codigo'], $inW * 0.55, $fs([5.5, 5, 4.5, 4]));
                @endphp
                @if($logo)<img class="abs" src="{{ $logo }}" alt="" style="left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $topH }}mm; height: {{ $topH }}mm;">{!! $ponGorro($pad, $pad, $topH) !!}@endif

                <div class="abs blanco" style="left: {{ $nameLeft }}mm; top: {{ $pad }}mm; width: {{ $nameW }}mm; height: {{ $topH }}mm;">
                    <div class="abs b" style="left: {{ $nomPadX }}mm; top: {{ $m(1.1) }}mm; font-size: {{ round(5 * $k, 1) }}pt; letter-spacing: 0.5pt; color: #7c3aed;">PRODUCTO</div>
                    {!! $lineas($nom, $nomPadX, $tagH + max(0, ($nomAltoTxt - $altoLineas($nom)) / 2)) !!}
                </div>

                @if($e['con_mayor'])
                    @foreach([[$txtUnidad, $e['unidad_fmt'], $pad, false], [$txtMayor, $e['mayor_fmt'], $pad + $boxW + $boxGap, true]] as [$cant, $valor, $bx, $esMayor])
                        <div class="abs caja blanco {{ $esMayor ? 'mayor' : '' }}" style="left: {{ $bx }}mm; width: {{ $boxW }}mm;">
                            <div class="abs h" style="height: {{ $headH }}mm;">
                                <div class="abs" style="top: {{ $hTop }}mm; font-size: {{ $hFs }}pt;">COMPRA</div>
                                <div class="abs" style="top: {{ $hTop + $hLinea }}mm; font-size: {{ $hFs }}pt;"><span>{{ $cant }}</span></div>
                            </div>
                            <div class="abs ref" style="top: {{ $headH + $m(0.8) }}mm;">REF</div>
                            {!! $precioSup($valor, $numFs, $esMayor ? $numTopMayor : $numTop, $boxW, $esMayor ? '#6d28d9' : '#0f172a') !!}
                            @if($esMayor && $conAhorro)
                                @php $ahCl = $fit('clah' . $e['texto_ahorro'], $e['texto_ahorro'], $boxW - $m(1.5), $fs([5.3, 4.9, 4.5, 4.1, 3.7])); @endphp
                                <div class="abs ahorro" style="top: {{ $ahorroTop }}mm; font-size: {{ $ahCl['fs'] }}pt;">{{ $e['texto_ahorro'] }}</div>
                            @endif
                            @if($esMayor)<div class="abs marco"></div>@endif
                        </div>
                    @endforeach
                @else
                    <div class="abs caja blanco" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                        <div class="abs h" style="height: {{ $headH }}mm;">
                            <div class="abs" style="top: {{ $hTop }}mm; font-size: {{ $hFs }}pt; letter-spacing: {{ $txtPrecio === 'PRECIO' ? 1.2 : 0.3 }}pt;">{{ $txtPrecio }}</div>
                        </div>
                        <div class="abs ref" style="top: {{ $headH + $m(0.8) }}mm;">REF</div>
                        {!! $precioSup($e['unidad_fmt'], $numFs, $numTop, $inW) !!}
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
