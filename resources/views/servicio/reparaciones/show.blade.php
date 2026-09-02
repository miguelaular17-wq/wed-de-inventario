@extends('layouts.app')
@section('title', 'Registro #'.$reparacion->id)
@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.reparaciones.index') }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Garantías</a>
    <div class="panel-header-flex" style="margin:10px 0 16px;">
        <div>
            <h2 style="margin:0;">{{ $reparacion->producto }}</h2>
            <p class="muted">{{ $reparacion->etiquetaTipo() }} · {{ $reparacion->sede }} · {{ $reparacion->etiquetaEstado() }}</p>
        </div>
        <a class="btn primary" href="{{ route('servicio.reparaciones.edit', $reparacion) }}">Editar</a>
    </div>
    <div class="panel" style="padding:24px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <div><span class="muted">Cliente</span><div>{{ $reparacion->cliente_nombre ?: '—' }}</div></div>
            <div><span class="muted">Teléfono</span><div>{{ $reparacion->cliente_telefono ?: '—' }}</div></div>
            <div><span class="muted">Categoría</span><div>{{ $reparacion->etiquetaCategoria() }}</div></div>
            <div><span class="muted">Acción</span><div>{{ $reparacion->etiquetaAccion() }}</div></div>
            <div><span class="muted">Comprobante</span><div>{{ $reparacion->comprobante_venta ?: '—' }}</div></div>
            <div><span class="muted">Costo interno</span><div>${{ number_format($reparacion->costo_interno ?? 0, 2) }}</div></div>
        </div>
        <hr style="border:none;border-top:1px dashed #e2e8f0;margin:20px 0;">
        <p class="muted">Falla</p><p>{{ $reparacion->falla ?: '—' }}</p>
        <p class="muted" style="margin-top:12px;">Repuestos</p><p>{{ $reparacion->repuestos_texto ?: '—' }}</p>
        <p class="muted" style="margin-top:12px;">Observaciones</p><p>{{ $reparacion->observaciones ?: '—' }}</p>
        @if($puedeEliminar)
            <form method="POST" action="{{ route('servicio.reparaciones.destroy', $reparacion) }}" style="margin-top:16px;" onsubmit="return confirm('¿Eliminar este registro?')">
                @csrf @method('DELETE')
                <button class="btn secondary" type="submit">Eliminar</button>
            </form>
        @endif
    </div>
</div>
@endsection
