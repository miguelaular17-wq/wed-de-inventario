@extends('layouts.app')

@section('title', 'Seleccionar sede')

@section('content')
<div class="panel" style="max-width: 480px; margin: 40px auto;">
    <h1 style="margin-top:0;">Seleccione su sede</h1>
    <p class="muted">Elija la sucursal con la que trabajará en esta sesión.</p>
    <form method="POST" action="{{ route('sede.store') }}">
        @csrf
        <label for="sede_local">Sede local</label>
        <select name="sede_local" id="sede_local" required style="width:100%; margin: 8px 0 16px;">
            <option value="">— Seleccione —</option>
            @foreach ($sedes as $sede)
                <option value="{{ $sede }}" @selected(old('sede_local', auth()->user()?->sede) === $sede)>
                    {{ config('inventario.display.'.$sede, $sede) }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="btn">Continuar</button>
    </form>
</div>
@endsection
