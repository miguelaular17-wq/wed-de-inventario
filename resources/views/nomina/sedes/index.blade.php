@extends('layouts.app')

@section('title', 'Sedes y áreas')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Sedes y áreas</h1>
            <p class="muted" style="margin:4px 0 0;">Sedes de tienda, Depósito y Administración. Áreas sin sede: Marketing, Call center e Inventario.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.sedes.store') }}" class="nomina-form-grid">
        @csrf
        <div class="field"><label>Nombre</label><input name="nombre" required></div>
        <div class="field"><label>Código</label><input name="codigo" required></div>
        <div class="field"><label>Dirección</label><input name="direccion"></div>
        <div class="field">
            <label>Tipo</label>
            <select name="tipo">
                <option value="SEDE">Sede</option>
                <option value="AREA">Área (sin tienda)</option>
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <label style="display:flex;gap:8px;align-items:center;text-transform:none;letter-spacing:0;">
                <input type="checkbox" name="excluir_comision" value="1">
                Esta sede/área no genera comisión
            </label>
        </div>
        <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Agregar</button></div>
    </form>

    <table class="data-table" style="margin-top:20px;">
        <thead><tr><th>Nombre</th><th>Código</th><th>Tipo</th><th>Dirección</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @foreach($sedes as $sede)
                <tr>
                    <td colspan="6" style="padding:0;">
                        <form method="POST" action="{{ route('nomina.sedes.update', $sede) }}" class="nomina-inline-form" style="padding:10px;">
                            @csrf @method('PUT')
                            <input name="nombre" value="{{ $sede->nombre }}" required>
                            <input name="codigo" value="{{ $sede->codigo }}" required>
                            <select name="tipo">
                                <option value="SEDE" @selected(($sede->tipo ?? 'SEDE') === 'SEDE')>Sede</option>
                                <option value="AREA" @selected(($sede->tipo ?? '') === 'AREA')>Área</option>
                            </select>
                            <input name="direccion" value="{{ $sede->direccion }}">
                            <select name="estado">
                                <option value="ACTIVO" @selected($sede->estado === 'ACTIVO')>Activo</option>
                                <option value="INACTIVO" @selected($sede->estado === 'INACTIVO')>Inactivo</option>
                            </select>
                            <label style="display:flex;gap:5px;align-items:center;font-size:.8rem;">
                                <input type="checkbox" name="excluir_comision" value="1" @checked($sede->excluir_comision)>
                                Sin comisión
                            </label>
                            <button class="btn" type="submit">Guardar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
