@php
    $presNombre = fn ($p) => $p->presentacion === 'caja' ? 'caja' : ($p->presentacion === 'paquete' ? 'paquete' : 'unidad');
    $filas = $productos->chunk(3);
    $descPct = (float) ($descuentoDivisaPct ?? 0);
    $descFactor = (float) ($descuentoFactor ?? max(0, 1 - ($descPct / 100)));
    $descEtiqueta = $descuentoEtiqueta ?? (rtrim(rtrim(number_format($descPct, 2, '.', ''), '0'), '.').'%');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Catálogo al mayor</title>
<style>
    @page { margin: 24px 26px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0f172a; }
    .cab { width: 100%; border-collapse: collapse; border-bottom: 3px solid #1e3a8a; margin-bottom: 10px; }
    .cab td { vertical-align: middle; padding-bottom: 8px; }
    .titulo { font-size: 18px; font-weight: bold; color: #1e3a8a; }
    .muted { color: #64748b; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 8px; }
    td.card { width: 33.33%; border: 1px solid #cbd5e1; border-radius: 10px; padding: 8px; vertical-align: top; }
    .foto { height: 120px; text-align: center; background: #f8fafc; border-radius: 8px; }
    .foto img { max-width: 100%; max-height: 116px; margin-top: 2px; }
    .sinfoto { height: 120px; line-height: 120px; color: #94a3b8; text-align: center; background: #f8fafc; border-radius: 8px; }
    .pres { display: inline-block; background: #6d28d9; color: #fff; font-weight: bold; font-size: 8px; padding: 2px 6px; border-radius: 8px; margin-top: 4px; }
    .meta { font-size: 7.5px; color: #64748b; text-transform: uppercase; margin-top: 5px; }
    .nombre { font-weight: bold; font-size: 9.5px; margin-top: 2px; height: 30px; overflow: hidden; }
    .precio { margin-top: 5px; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 4px; }
    .precio table { width: 100%; border-collapse: collapse; }
    .precio td { width: 33%; text-align: center; vertical-align: top; padding: 2px; }
    .precio .l { font-size: 6.5px; color: #64748b; text-transform: uppercase; font-weight: bold; }
    .precio .v { font-size: 11px; font-weight: bold; color: #6d28d9; }
    .precio .deal { color: #16a34a; }
    .precio .lote { color: #0f172a; }
    .precio .h { font-size: 6px; color: #94a3b8; }
    .disp { font-size: 8px; color: #166534; font-weight: bold; margin-top: 3px; text-align: center; }
    .pie { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<table class="cab">
    <tr>
        <td style="width:62px;">@if($logo)<img src="{{ $logo }}" style="width:54px; height:54px;" alt="">@endif</td>
        <td>
            <div class="titulo">Catálogo al mayor</div>
            <div class="muted">Palacio de los Detalles · Unidad · Desc. divisas (−{{ $descEtiqueta }}) · Unidad de manejo · {{ now()->format('d/m/Y') }}@if($categoria !== '') · {{ $categoria }}@endif</div>
        </td>
        <td style="text-align:right;" class="muted">{{ $productos->count() }} productos</td>
    </tr>
</table>

<table class="grid">
    @foreach($filas as $fila)
        <tr>
            @foreach($fila as $p)
                <td class="card">
                    @if(! empty($fotos[$p->id]))
                        <div class="foto"><img src="{{ $fotos[$p->id] }}" alt=""></div>
                    @else
                        <div class="sinfoto">Sin foto</div>
                    @endif
                    <div class="meta">{{ $p->categoria }} · {{ \App\Support\Sku::principal($p->codigo) }}</div>
                    <div class="nombre">{{ $p->nombre }}</div>
                    @if($p->presentacion !== 'unidad')<span class="pres">{{ $p->presentacion_texto }}</span>@endif
                    @php
                        $precioUnidad = (float) $p->precio_presentacion;
                        $precioDivisa = round($precioUnidad * $descFactor, 2);
                        $lote = (int) ($p->lote_inicial ?? 0);
                    @endphp
                    <div class="precio">
                        <table>
                            <tr>
                                <td>
                                    <div class="l">Unidad</div>
                                    <div class="v">${{ number_format($precioUnidad, 2) }}</div>
                                    <div class="h">por {{ $presNombre($p) }}</div>
                                </td>
                                <td>
                                    <div class="l">−{{ $descEtiqueta }}</div>
                                    <div class="v deal">${{ number_format($precioDivisa, 2) }}</div>
                                    <div class="h">desc. divisas</div>
                                </td>
                                <td>
                                    <div class="l">Unidad de manejo</div>
                                    <div class="v lote">{{ $lote > 0 ? $lote : '—' }}</div>
                                    <div class="h">{{ $lote > 0 ? 'mín. compra' : 'sin mínimo' }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="disp">Disponible: {{ \App\Models\Mayor\MayorCatalogo::cantidadTexto((int) ($p->disponible_filtro ?? $p->disponible_presentaciones), $p->presentacion) }}</div>
                </td>
            @endforeach
            @for($i = $fila->count(); $i < 3; $i++)<td style="width:33.33%;"></td>@endfor
        </tr>
    @endforeach
</table>

<div class="pie">Palacio de los Detalles · Catálogo al mayor · Precios sujetos a cambio y a disponibilidad</div>
</body>
</html>
