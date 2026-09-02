@extends('layouts.app')

@section('title', 'Nueva orden de servicio')

@section('content')
<div style="padding:20px;max-width:900px;margin:0 auto;">
    <a href="{{ route('servicio.ordenes.index') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Volver a órdenes</a>
    <h2 style="font-weight:700;margin:10px 0 24px;">Nueva orden</h2>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.ordenes.store') }}">
            @include('servicio.ordenes._form')
            <button class="btn primary" type="submit">Registrar orden</button>
        </form>
    </div>
</div>
@endsection
