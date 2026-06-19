@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="panel">
    <h1 style="margin-top:0;">Usuarios registrados</h1>
    <p class="muted">Asigne o cambie la sede de cada usuario.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Sede</th>
                    <th>Cambiar sede</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role }}</td>
                        <td>{{ $user->sede ?: '—' }}</td>
                        <td>
                            @if (! $user->isAdmin())
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="filters" style="margin:0;">
                                    @csrf
                                    <select name="sede">
                                        <option value="">— Ninguna —</option>
                                        @foreach ($sedes as $s)
                                            <option value="{{ $s }}" @selected($user->sede === $s)>
                                                {{ config('inventario.display.'.$s, $s) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn secondary">Guardar</button>
                                </form>
                            @else
                                <span class="muted">Admin</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
