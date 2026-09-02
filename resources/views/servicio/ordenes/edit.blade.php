@extends('layouts.app')

@section('title', 'Editar '.$orden->codigo())

@section('content')
<div style="padding:20px;max-width:960px;margin:0 auto;">
    <a href="{{ route('servicio.ordenes.show', $orden) }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Volver a {{ $orden->codigo() }}</a>
    <h2 style="font-weight:700;margin:10px 0 24px;">Editar {{ $orden->codigo() }}</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.ordenes.update', $orden) }}">
            @csrf
            @method('PUT')
            @include('servicio.ordenes._form')
            @include('servicio.ordenes._form_edit_extras')
            <button class="btn primary" type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
@endsection
