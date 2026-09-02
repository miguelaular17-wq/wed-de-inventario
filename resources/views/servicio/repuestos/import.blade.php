@extends('layouts.app')
@section('title', 'Importar repuestos')
@section('content')
<div style="padding:20px;max-width:720px;margin:0 auto;">
    <a href="{{ route('servicio.repuestos.index') }}" class="muted" style="text-decoration:none;font-size:.85rem;">← Repuestos</a>
    <h2 style="margin:10px 0 24px;">Importar repuestos (CSV)</h2>
    <div class="panel" style="padding:24px;">
        <p class="muted" style="margin-top:0;">Columnas: <code>nombre,codigo,categoria,stock,stock_minimo,costo,venta</code>. Separador <code>;</code> o <code>,</code>.</p>
        <p><a href="{{ route('servicio.repuestos.plantilla') }}">Descargar plantilla CSV</a></p>
        <form method="POST" action="{{ route('servicio.repuestos.import.store') }}" enctype="multipart/form-data" style="margin-top:16px;">
            @csrf
            @if(!auth()->user()->scopesServicioToOwnSede())
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:500;margin-bottom:4px;">Sede destino *</label>
                    <select name="sede" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                        @foreach($sedes as $sede)
                            <option value="{{ $sede }}" @selected(old('sede', $sedeDefault) === $sede)>{{ $sede }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:500;margin-bottom:4px;">Archivo CSV *</label>
                <input type="file" name="archivo" accept=".csv,text/csv" required>
            </div>
            <button class="btn primary" type="submit">Importar</button>
        </form>
    </div>
</div>
@endsection
