<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas de precio</title>
@php
    $chica = $tamanoClave === 'pequena';
    $k = $chica ? 0.66 : 1.0;
    $W = $tamano['ancho'];
    $H = $tamano['alto'];
    $sep = 2;
    $pad = $chica ? 1.8 : 2.6;
    $inW = $W - 2 * $pad;
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
    $pt = fn (float $v) => round($v * $k, 1);
    $ptMm = fn (float $p) => $p * 0.3528;
    $nomPadX = $chica ? 1.6 : 2.6;
    $nomPadR = $chica ? 1.2 : 2;
    $tagH = $ptMm($pt(5.5)) + ($chica ? 1.1 : 1.8);
    $nomAnchoTxt = $nameW - $nomPadX - $nomPadR;
    $nomAltoTxt = $topH - $tagH - ($chica ? 0.6 : 0.9);
    $nomTamanos = array_map($pt, [12.5, 11.5, 10.5, 9.5, 9, 8.5, 8, 7.5, 7, 6.5]);
    $nombresAjustados = [];
    $pos = function (int $i) use ($tamano, $W, $H, $sep) {
        $col = $i % $tamano['columnas'];
        $row = intdiv($i, $tamano['columnas']);
        return [$col * ($W + $sep), $row * ($H + $sep)];
    };
    $hojaH = $tamano['filas'] * ($H + $sep) - $sep;
@endphp
<style>
    @page { margin: 7mm; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; }
    .hoja { position: relative; width: 100%; height: {{ $hojaH }}mm; }
    .salto { page-break-after: always; }
    .abs { position: absolute; }

    .etq {
        position: absolute; width: {{ $W }}mm; height: {{ $H }}mm; overflow: hidden;
        border-radius: {{ $chica ? 2.5 : 3.5 }}mm; background-color: #9333ea;
        @if($fondo) background-image: url('{{ $fondo }}'); background-repeat: no-repeat; @endif
    }
    .logo { left: {{ $pad }}mm; top: {{ $pad }}mm; width: {{ $topH }}mm; height: {{ $topH }}mm; }
    .nombre {
        left: {{ $nameLeft }}mm; top: {{ $pad }}mm; width: {{ $nameW }}mm; height: {{ $topH }}mm;
        background: #fff; border-radius: {{ $chica ? 1.5 : 2.2 }}mm; overflow: hidden;
    }
    .nombre .tag { left: {{ $nomPadX }}mm; top: {{ $chica ? 0.8 : 1.3 }}mm; font-size: {{ $pt(5.5) }}pt; letter-spacing: 0.6pt; color: #7c3aed; font-weight: bold; }
    .nombre .txt { left: {{ $nomPadX }}mm; width: {{ $nomAnchoTxt }}mm; font-weight: bold; line-height: 1.12; white-space: nowrap; color: #0f172a; }

    .caja { top: {{ $precioTop }}mm; height: {{ $precioH }}mm; background: #fff; border-radius: {{ $chica ? 1.5 : 2.2 }}mm; overflow: hidden; }
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
</style>
</head>
<body>
@foreach($paginas as $fichas)
    <div class="hoja {{ $loop->last ? '' : 'salto' }}">
        @foreach($fichas as $i => $e)
            @php
                [$x, $y] = $pos($i);
                $nombresAjustados[$e['id']] ??= $ajustarNombre($e['nombre'], $nomAnchoTxt, $nomAltoTxt, $nomTamanos);
                $nom = $nombresAjustados[$e['id']];
                $nFs = $nom['fs'];
                $txtTop = $tagH + max(0, ($nomAltoTxt - count($nom['lineas']) * $ptMm($nom['alto_linea'])) / 2);

                $conAhorro = $e['con_mayor'] && $e['ahorro_pct'] > 0;
                $numFs = $pt($e['con_mayor'] ? 23 : 30);
                $libreH = $precioH - $headH;
                $numTop = $headH + ($libreH - $ptMm($numFs)) / 2 + ($chica ? 0.3 : 0.6);
                $numTopMayor = $conAhorro ? $numTop - ($chica ? 1 : 1.5) : $numTop;
                $ahorroTop = $precioH - $ptMm($pt(6)) - ($chica ? 0.9 : 1.4);
            @endphp
            <div class="etq" style="left: {{ $x }}mm; top: {{ $y }}mm;">
                @if($logo)<img class="abs logo" src="{{ $logo }}" alt="">@endif

                <div class="abs nombre">
                    <div class="abs tag">PRODUCTO</div>
                    @foreach($nom['lineas'] as $li => $linea)
                        <div class="abs txt" style="top: {{ $txtTop + $li * $ptMm($nom['alto_linea']) }}mm; font-size: {{ $nFs }}pt; line-height: {{ $nom['alto_linea'] }}pt; height: {{ $nom['alto_linea'] }}pt;">{{ $linea }}</div>
                    @endforeach
                </div>

                @if($e['con_mayor'])
                    <div class="abs caja" style="left: {{ $pad }}mm; width: {{ $boxW }}mm;">
                        <div class="abs h">COMPRA <span>1 PZA</span></div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTop }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    </div>
                    <div class="abs caja mayor" style="left: {{ $pad + $boxW + $boxGap }}mm; width: {{ $boxW }}mm;">
                        <div class="abs h">COMPRA <span>{{ $e['cantidad_mayor'] }}+ PZAS</span></div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTopMayor }}mm; font-size: {{ $numFs }}pt; color: #6d28d9;">{{ $e['mayor_fmt'] }}</div>
                        @if($conAhorro)
                            <div class="abs ahorro" style="top: {{ $ahorroTop }}mm;">¡AHORRAS {{ $e['ahorro_pct'] }}% C/U!</div>
                        @endif
                        <div class="abs marco"></div>
                    </div>
                @else
                    <div class="abs caja" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                        <div class="abs h">PRECIO</div>
                        <div class="abs ref">REF</div>
                        <div class="abs num" style="top: {{ $numTop }}mm; font-size: {{ $numFs }}pt;">{{ $e['unidad_fmt'] }}</div>
                    </div>
                @endif

                <div class="abs pie" style="left: {{ $pad }}mm; width: {{ $inW }}mm;">
                    <span>Cód. {{ $e['codigo'] }}</span>
                    <span class="abs" style="right: 0; top: 0;">Precios en REF · {{ $fecha }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
