@extends('layouts.app')
@section('title', $factura->codigo())
@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.facturas.index') }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Facturas</a>
    <div class="panel-header-flex" style="margin:10px 0 16px;">
        <div>
            <h2 style="margin:0;">{{ $factura->codigo() }}</h2>
            <p class="muted">{{ $factura->sede }} · {{ $factura->etiquetaEstadoPago() }} · {{ $factura->fecha?->format('d/m/Y') }}</p>
        </div>
        <a class="btn primary" href="{{ route('servicio.facturas.edit', $factura) }}">Editar</a>
    </div>
    <div class="panel" style="padding:24px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <div><span class="muted">Cliente</span><div>{{ $factura->cliente_nombre }}</div></div>
            <div><span class="muted">Total</span><div><strong>${{ number_format($factura->total, 2) }}</strong></div></div>
            <div><span class="muted">Presupuesto</span><div>${{ number_format($factura->presupuesto ?? 0, 2) }}</div></div>
            <div><span class="muted">Mano de obra</span><div>${{ number_format($factura->costo_mano_obra ?? 0, 2) }}</div></div>
            <div><span class="muted">Refacciones</span><div>${{ number_format($factura->costo_refacciones ?? 0, 2) }}</div></div>
        </div>
        <hr style="border:none;border-top:1px dashed #e2e8f0;margin:20px 0;">
        <p class="muted">Descripción</p><p>{{ $factura->descripcion ?: '—' }}</p>
        @if($puedeEliminar)
            <form method="POST" action="{{ route('servicio.facturas.destroy', $factura) }}" style="margin-top:16px;" onsubmit="return confirm('¿Eliminar esta factura?')">
                @csrf @method('DELETE')
                <button class="btn secondary" type="submit">Eliminar</button>
            </form>
        @endif
    </div>
</div>
@endsection
