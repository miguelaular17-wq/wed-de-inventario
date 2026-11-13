@php
    $tot = ['capital' => $filas->sum('capital'), 'intereses' => $filas->sum('intereses'), 'total' => $filas->sum('total')];
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Estado de cuenta · {{ $intermediario->nombre }}</title>
<style>
    @page { margin: 30px 34px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; }
    .cab { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .cab td { vertical-align: middle; }
    .nombre { font-size: 15px; font-weight: bold; }
    .titulo { font-size: 22px; font-weight: bold; text-align: right; letter-spacing: .5px; }
    .muted { color: #64748b; }
    table.ec { width: 100%; border-collapse: collapse; }
    table.ec th { background: #9ca3af; color: #fff; font-size: 9.5px; font-weight: bold; text-transform: uppercase; padding: 7px 6px; text-align: left; }
    table.ec th.num { text-align: right; }
    table.ec td { padding: 5px 6px; border-bottom: 1px solid #eef2f6; }
    .num { text-align: right; white-space: nowrap; }
    table.ec tfoot td { font-weight: bold; border-top: 1.5px solid #334155; border-bottom: 0; padding-top: 7px; }
    .deuda { background: #fef08a; }
    .pie { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<table class="cab">
    <tr>
        @if($logo)<td style="width:64px;"><img src="{{ $logo }}" style="width:56px; height:56px;" alt=""></td>@endif
        <td>
            <div class="nombre">{{ $intermediario->nombre }}</div>
            @if($intermediario->documento)<div>{{ $intermediario->documento }}</div>@endif
            <div class="muted">Al {{ now()->format('d/m/Y') }}</div>
        </td>
        <td class="titulo">ESTADO DE CUENTA</td>
    </tr>
</table>

<table class="ec">
    <thead>
        <tr>
            <th style="width:15%;">Fecha<br>vencimiento</th>
            <th>Descripción</th>
            <th class="num" style="width:16%;">Capital</th>
            <th class="num" style="width:16%;">Total<br>intereses</th>
            <th class="num" style="width:16%;">Total<br>deuda</th>
        </tr>
    </thead>
    <tbody>
        @forelse($filas as $n => $f)
            <tr>
                <td>{{ $f['vence']?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $n + 1 }} - {{ $f['descripcion'] }} <span class="muted" style="font-size:8.5px;">({{ $f['contrato']->numero_contrato }})</span></td>
                <td class="num">{{ $m($f['capital']) }}</td>
                <td class="num">{{ $m($f['intereses']) }}</td>
                <td class="num">{{ $m($f['total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted" style="text-align:center; padding:16px;">Sin contratos.</td></tr>
        @endforelse
    </tbody>
    @if($filas->isNotEmpty())
        <tfoot>
            <tr>
                <td></td>
                <td style="text-align:right;">TOTALES</td>
                <td class="num">{{ $m($tot['capital']) }}</td>
                <td class="num">{{ $m($tot['intereses']) }}</td>
                <td class="num deuda">{{ $m($tot['total']) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<div class="pie">Palacio de los Detalles · Estado de cuenta de {{ $intermediario->nombre }} · Generado el {{ now()->format('d/m/Y h:i a') }}</div>
</body>
</html>
