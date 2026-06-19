@extends('layouts.app')

@section('content')
<div class="panel" style="max-width:480px;margin:40px auto;">
    <h2>Cambiar sede</h2>
    <p class="muted">Elija la sede con la que desea trabajar.</p>

    <form method="POST" action="{{ route('user.sede.update') }}">
        @csrf
        <label for="sede">Sede</label>
        <select name="sede" id="sede" required style="width:100%; margin:8px 0 16px;">
            <option value="">— Seleccione —</option>
            @foreach($sedes as $s)
                <option value="{{ $s }}" @selected(auth()->user()->sede === $s)>{{ $display[$s] ?? $s }}</option>
            @endforeach
        </select>

        <div>
            <button type="submit" class="btn">Guardar</button>
            <a href="{{ url('/') }}" class="btn secondary" style="margin-left:8px;">Cancelar</a>
        </div>
    </form>
</div>
@endsection
