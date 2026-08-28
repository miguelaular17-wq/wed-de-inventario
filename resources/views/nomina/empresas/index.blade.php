@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Empresas</h1>
            <p class="muted" style="margin:4px 0 0;">Código / RIF de la compañía que paga la nómina. Cada empleado debe pertenecer a una. El TXT del banco se arma por este código.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.empresas.store') }}" class="nomina-form-grid">
        @csrf
        <div class="field"><label>Código / RIF</label><input name="codigo" placeholder="J401722296" required></div>
        <div class="field field-wide"><label>Nombre</label><input name="nombre" required></div>
        <div class="field">
            <label>Estado</label>
            <select name="estado"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select>
        </div>
        <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Agregar</button></div>
    </form>

    <table class="data-table" style="margin-top:20px;">
        <thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th></th></tr></thead>
        <tbody>
            @foreach($empresas as $empresa)
                <tr>
                    <td colspan="4" style="padding:0;">
                        <form method="POST" action="{{ route('nomina.empresas.update', $empresa) }}" class="nomina-inline-form" style="padding:10px;">
                            @csrf @method('PUT')
                            <input name="codigo" value="{{ $empresa->codigo }}" required>
                            <input name="nombre" value="{{ $empresa->nombre }}" required style="min-width:280px;">
                            <select name="estado">
                                <option value="ACTIVO" @selected($empresa->estado === 'ACTIVO')>Activo</option>
                                <option value="INACTIVO" @selected($empresa->estado === 'INACTIVO')>Inactivo</option>
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
