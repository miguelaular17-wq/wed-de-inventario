@extends('layouts.app')
@section('title', 'Editar registro')
@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.reparaciones.show', $reparacion) }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Volver</a>
    <h2 style="margin:10px 0 24px;">Editar registro</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.reparaciones.update', $reparacion) }}">
            @csrf @method('PUT')
            @include('servicio.reparaciones._form')
            <button class="btn primary" type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
@endsection
