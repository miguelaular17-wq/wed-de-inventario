<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reparaciones internas resueltas</title>
<style>
    @page { margin: 24px 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0f172a; }
    .cab { width: 100%; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; }
    .cab td { vertical-align: middle; }
    h1 { font-size: 15px; margin: 0; color: #1e3a8a; }
    h2 { font-size: 12px; margin: 14px 0 5px; }
    .muted { color: #64748b; }
    table.t { width: 100%; border-collapse: collapse; }
    table.t th { background: #1e3a8a; color: #fff; padding: 5px; text-align: left; font-size: 8.5px; }
    table.t td { padding: 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .kpi { display: inline-block; margin-right: 14px; }
</style>
</head>
<body>
@php
    $resultados = \App\Models\StOrden::RESULTADOS_INTERNA;
    $etiqueta = fn ($clave) => $clave === \App\Models\StOrden::RESULTADO_REPARADO ? 'Reparado (técnico)' : ($resultados[$clave] ?? $clave);
@endphp
<table class="cab">
    <tr>
        <td>
            <h1>Reparaciones internas resueltas{{ $resultadoFiltro ? ' · '.$etiqueta($resultadoFiltro) : '' }}</h1>
            <div class="muted">
                {{ $filtroSede ? 'Sede '.$filtroSede : 'Todas las sedes' }}
                @if($desde || $hasta) · ingreso {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '…' }} al {{ $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : '…' }}@endif
                · generado por {{ $generadoPor }} el {{ now()->format('d/m/Y h:i A') }}
            </div>
        </td>
        @if($logoPath)<td style="text-align:right;"><img src="{{ $logoPath }}" style="height:34px;"></td>@endif
    </tr>
</table>
<div>
    @foreach($resultados as $clave => $texto)
        <span class="kpi"><strong>{{ $etiqueta($clave) }}:</strong> {{ $porResultado[$clave] ?? 0 }}</span>
    @endforeach
    <span class="kpi"><strong>Total:</strong> {{ $total }}</span>
</div>

@forelse($grupos as $clave => $ordenes)
    <h2>{{ $etiqueta($clave) }} · {{ $ordenes->count() }} órdenes</h2>
    <table class="t">
        <thead><tr><th>Orden</th><th>Fecha</th><th>Marca</th><th>Equipo</th><th>Sede origen</th><th>Creada por</th><th>Reparó</th><th>Cliente / Motivo</th></tr></thead>
        <tbody>
            @foreach($ordenes as $orden)
                <tr>
                    <td>{{ $orden->codigo() }}</td>
                    <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') }}</td>
                    <td>{{ $orden->marca_reporte }}</td>
                    <td>{{ $orden->equipo }}{{ $orden->esLote() ? ' × '.$orden->unidades() : '' }}@if($orden->serial || $orden->imei)<br><span class="muted">{{ $orden->serial ?: $orden->imei }}</span>@endif</td>
                    <td>{{ $orden->sedeOrigen() }}</td>
                    <td>{{ $orden->creador?->name ?: '—' }}</td>
                    <td>{{ $orden->nombreReparador() ?? '—' }}</td>
                    <td>{{ $orden->cliente_nombre ?: '—' }}@if($orden->falla)<br><span class="muted">{{ \Illuminate\Support\Str::limit($orden->falla, 70) }}</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="muted">No hay reparaciones internas resueltas para estos filtros.</p>
@endforelse
</body>
</html>
