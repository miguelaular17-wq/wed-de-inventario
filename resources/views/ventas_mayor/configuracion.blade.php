@extends('layouts.app')

@section('title', 'Ventas al mayor · Configuración')

@push('head')
@include('partials.ventas-mayor-responsive')
@endpush

@section('content')
<div class="panel nomina-page vm-mobile-root">
    <div class="panel-header-flex vm-page-head">
        <div>
            <a href="{{ route('ventas_mayor.stock') }}" class="muted" style="font-size:.82rem;">← Ventas al mayor</a>
            <h1 style="margin:4px 0 0;">Configuración · Ventas al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">Solo la gerente ajusta el descuento por pago en divisas. Se aplica al total de la factura al elegir Zelle, Efectivo $ o Binance.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('ventas_mayor.configuracion.guardar') }}" class="nomina-card" style="margin-top:16px; max-width:520px;">
        @csrf
        <div class="field">
            <label>Descuento por pago en divisas (%)</label>
            <input type="number" name="descuento_divisa_pct" min="0" max="100" step="0.01"
                   value="{{ old('descuento_divisa_pct', number_format($descuentoDivisaPct, 2, '.', '')) }}" required>
            <small class="muted">Ej.: 5 = 5% menos sobre el monto completo de la factura.</small>
        </div>
        <p class="muted" style="margin:12px 0 0; font-size:.85rem;">
            Métodos en divisas: <strong>{{ implode(', ', $formasDivisa) }}</strong>.
            Transferencia, pago móvil, efectivo Bs y punto de venta no aplican descuento.
        </p>
        <div style="margin-top:16px;">
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</div>
@endsection
