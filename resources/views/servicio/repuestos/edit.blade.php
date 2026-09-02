@extends('layouts.app')

@section('title', 'Editar '.$repuesto->nombre)

@section('content')
<div style="padding:20px;max-width:800px;margin:0 auto;">
    <a href="{{ route('servicio.repuestos.index') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Repuestos</a>
    <h2 style="font-weight:700;margin:10px 0 24px;">{{ $repuesto->nombre }}</h2>

    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <form method="POST" action="{{ route('servicio.repuestos.update', $repuesto) }}">
            @csrf
            @method('PUT')
            @include('servicio.repuestos._form')
            <button class="btn primary" type="submit">Guardar cambios</button>
        </form>
    </div>

    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Ajustar stock (actual: {{ $repuesto->stock }})</h3>
        <form method="POST" action="{{ route('servicio.repuestos.ajustar_stock', $repuesto) }}" class="nomina-inline-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            @csrf
            <div>
                <label>Cantidad (+/-)</label>
                <input type="number" name="cantidad" required style="width:100px;padding:8px;">
            </div>
            <div style="flex:1;min-width:200px;">
                <label>Motivo</label>
                <input type="text" name="motivo" required placeholder="Compra, corrección, etc." style="width:100%;padding:8px;">
            </div>
            <button class="btn secondary" type="submit">Aplicar</button>
        </form>
    </div>

    <div class="panel" style="padding:24px;">
        <h3 style="margin:0 0 12px;">Últimos movimientos</h3>
        @forelse($movimientos as $mov)
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem;">
                {{ $mov->created_at?->format('d/m/Y H:i') }} · {{ $mov->tipo }} · {{ $mov->cantidad > 0 ? '+' : '' }}{{ $mov->cantidad }}
                → stock {{ $mov->stock_despues }} · {{ $mov->motivo }}
            </div>
        @empty
            <p class="muted">Sin movimientos.</p>
        @endforelse
    </div>
</div>
@endsection
