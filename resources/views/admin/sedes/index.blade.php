@extends('layouts.app')

@section('content')
<div class="panel">
    <h2>Ver como sede</h2>
    <p class="muted">Seleccione una sede para ver la aplicación con datos de esa sucursal.</p>

    <table>
        <thead>
            <tr><th>Sede</th><th>Acción</th></tr>
        </thead>
        <tbody>
            @foreach($sedes as $s)
                <tr>
                    <td>{{ $display[$s] ?? $s }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.sedes.use', $s) }}">
                            @csrf
                            <button class="btn" type="submit">Ver como {{ $display[$s] ?? $s }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
