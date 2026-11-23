@extends('layouts.app')

@section('title', 'Ventas al mayor · Clientes')

@push('head')
@include('partials.ventas-mayor-responsive')
@endpush

@section('content')
<div class="panel vm-mobile-root">
    <div class="vm-page-head">
        <div>
            <h1 style="margin:0;">Clientes al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ number_format($total) }} clientes · {{ number_format($conFotos) }} con fotos/archivos
                · hasta 4 archivos por cliente (bucket Clientes VM)
            </p>
        </div>
        <div class="vm-page-actions">
            <a href="{{ route('ventas_mayor.facturas') }}" class="btn secondary">Facturas</a>
            <a href="{{ route('ventas_mayor.create') }}" class="btn secondary">+ Factura</a>
            <a href="#vm-nuevo-cliente" class="btn primary" onclick="document.getElementById('vm-nuevo-cliente').style.display='block'; return true;">+ Cliente</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <form method="GET" class="vm-filtros" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:1; min-width:220px;">
            <label style="display:block; font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Buscar</label>
            <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, RIF, teléfono o correo…" style="width:100%; padding:9px 11px; border:1px solid #cbd5e1; border-radius:8px;">
        </div>
        <div>
            <label style="display:block; font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:4px;">Fotos</label>
            <select name="fotos" style="padding:9px 11px; border:1px solid #cbd5e1; border-radius:8px;">
                <option value="">Todos</option>
                <option value="con" @selected($fotos === 'con')>Con fotos</option>
                <option value="sin" @selected($fotos === 'sin')>Sin fotos</option>
            </select>
        </div>
        <button type="submit" class="btn primary">Buscar</button>
        @if($q !== '' || $fotos !== '')
            <a href="{{ route('ventas_mayor.clientes.index') }}" class="btn secondary">Limpiar</a>
        @endif
    </form>

    <div id="vm-nuevo-cliente" style="display:none; margin-top:14px; padding:14px; background:#f8fafc; border:1px dashed #94a3b8; border-radius:12px;">
        <h3 style="margin:0 0 10px; font-size:.95rem; color:#1e3a8a;">Nuevo cliente</h3>
        <form method="POST" action="{{ route('ventas_mayor.clientes.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px;">
                <div><label style="font-size:.75rem; font-weight:700;">RIF / cédula *</label><input type="text" name="documento" required maxlength="40" placeholder="J-40123456-7" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;"></div>
                <div style="grid-column:span 2;"><label style="font-size:.75rem; font-weight:700;">Nombre *</label><input type="text" name="nombre" required maxlength="200" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;"></div>
                <div><label style="font-size:.75rem; font-weight:700;">Teléfono</label><input type="text" name="telefono" maxlength="60" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;"></div>
                <div><label style="font-size:.75rem; font-weight:700;">Correo</label><input type="email" name="email" maxlength="120" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;"></div>
                <div style="grid-column:1/-1;"><label style="font-size:.75rem; font-weight:700;">Dirección</label><input type="text" name="direccion" maxlength="300" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;"></div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:.75rem; font-weight:700;">Fotos / archivos (hasta 4)</label>
                    <input type="file" name="archivos[]" accept="image/*,application/pdf" multiple style="width:100%; margin-top:4px;">
                </div>
            </div>
            <div style="margin-top:10px; display:flex; gap:8px;">
                <button type="submit" class="btn primary">Guardar cliente</button>
                <button type="button" class="btn secondary" onclick="document.getElementById('vm-nuevo-cliente').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>

    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table vm-m-cards" style="font-size:.84rem;">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th>Fotos</th>
                    <th>Facturas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                    <tr>
                        <td data-label="Cliente"><strong>{{ $c->nombre }}</strong></td>
                        <td data-label="Documento" style="font-family:ui-monospace,monospace;">{{ $c->documento }}</td>
                        <td data-label="Contacto">
                            <div>{{ $c->telefono ?: '—' }}</div>
                            <div class="muted" style="font-size:.72rem;">{{ $c->email ?: '' }}</div>
                        </td>
                        <td data-label="Fotos">
                            @if(($c->archivos_count ?? 0) > 0)
                                <span class="tag ok">📷 {{ $c->archivos_count }}/4</span>
                            @else
                                <span class="tag no">Sin fotos</span>
                            @endif
                        </td>
                        <td data-label="Facturas">{{ $c->facturas_count ?? 0 }}</td>
                        <td data-label="" class="vm-m-acciones">
                            <a href="{{ route('ventas_mayor.clientes.show', $c) }}" class="btn secondary" style="padding:6px 10px; font-size:.8rem;">Ver / archivos</a>
                            <a href="{{ route('ventas_mayor.create', ['cliente_id' => $c->id]) }}" class="btn primary" style="padding:6px 10px; font-size:.8rem;">Facturar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="text-align:center; padding:28px;">No hay clientes con ese criterio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px;">{{ $clientes->links() }}</div>
</div>
@endsection
