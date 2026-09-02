@extends('layouts.app')
@section('title', 'Nuevo registro')
@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.reparaciones.index') }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Garantías</a>
    <h2 style="margin:10px 0 24px;">Nuevo registro</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.reparaciones.store') }}">
            @include('servicio.reparaciones._form')
            <button class="btn primary" type="submit">Guardar</button>
        </form>
    </div>
</div>
@endsection
