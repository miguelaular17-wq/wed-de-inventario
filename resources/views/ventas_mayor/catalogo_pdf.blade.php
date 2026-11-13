@php
    $presNombre = fn ($p) => $p->presentacion === 'caja' ? 'caja' : ($p->presentacion === 'paquete' ? 'paquete' : 'unidad');
    $filas = $productos->chunk(3);
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
    .precio { margin-top: 5px; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 4px; text-align: center; }
    .precio .v { font-size: 15px; font-weight: bold; color: #6d28d9; }
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
            <div class="muted">Palacio de los Detalles · Precios al mayor · {{ now()->format('d/m/Y') }}@if($categoria !== '') · {{ $categoria }}@endif</div>
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
                    <div class="precio">
                        <div class="v">${{ number_format($p->precio_presentacion, 2) }}</div>
                        <div>por {{ $presNombre($p) }}@if($p->unidades_por > 1) · ${{ number_format($p->precio_presentacion / $p->unidades_por, 2) }} c/u @endif</div>
                    </div>
                    <div class="disp">Disponible: {{ \App\Models\Mayor\MayorCatalogo::cantidadTexto($p->disponible_presentaciones, $p->presentacion) }}</div>
                </td>
            @endforeach
            @for($i = $fila->count(); $i < 3; $i++)<td style="width:33.33%;"></td>@endfor
        </tr>
    @endforeach
</table>

<div class="pie">Palacio de los Detalles · Catálogo al mayor · Precios sujetos a cambio y a disponibilidad</div>
</body>
</html>
