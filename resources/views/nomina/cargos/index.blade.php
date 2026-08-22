@extends('layouts.app')

@section('title', 'Cargos')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Cargos</h1>
            <p class="muted" style="margin:4px 0 0;">Catálogo de puestos para la ficha del empleado.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.cargos.store') }}" class="nomina-form-grid">
        @csrf
        <div class="field"><label>Nombre</label><input name="nombre" required></div>
        <div class="field field-wide"><label>Descripción</label><input name="descripcion"></div>
        <div class="field">
            <label>Estado</label>
            <select name="estado"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select>
        </div>
        <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Agregar</button></div>
    </form>

    <table class="data-table" style="margin-top:20px;">
        <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @foreach($cargos as $cargo)
                <tr>
                    <td colspan="4" style="padding:0;">
                        <form method="POST" action="{{ route('nomina.cargos.update', $cargo) }}" class="nomina-inline-form" style="padding:10px;">
                            @csrf @method('PUT')
                            <input name="nombre" value="{{ $cargo->nombre }}" required>
                            <input name="descripcion" value="{{ $cargo->descripcion }}">
                            <select name="estado">
                                <option value="ACTIVO" @selected($cargo->estado === 'ACTIVO')>Activo</option>
                                <option value="INACTIVO" @selected($cargo->estado === 'INACTIVO')>Inactivo</option>
                            </select>
                            <button class="btn" type="submit">Guardar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
