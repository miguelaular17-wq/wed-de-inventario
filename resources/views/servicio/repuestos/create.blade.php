@extends('layouts.app')

@section('title', 'Nuevo repuesto')

@section('content')
<div style="padding:20px;max-width:700px;margin:0 auto;">
    <a href="{{ route('servicio.repuestos.index') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Repuestos</a>
    <h2 style="font-weight:700;margin:10px 0 24px;">Agregar repuesto</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.repuestos.store') }}">
            @csrf
            @include('servicio.repuestos._form')
            <button class="btn primary" type="submit">Guardar</button>
        </form>
    </div>
</div>
@endsection
