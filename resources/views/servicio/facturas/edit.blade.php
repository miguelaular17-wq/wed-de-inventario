@extends('layouts.app')
@section('title', 'Editar factura')
@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.facturas.show', $factura) }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Volver</a>
    <h2 style="margin:10px 0 24px;">Editar {{ $factura->codigo() }}</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.facturas.update', $factura) }}">
            @csrf @method('PUT')
            @include('servicio.facturas._form')
            <button class="btn primary" type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
@endsection
