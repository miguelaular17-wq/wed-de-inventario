@extends('layouts.app')

@section('title', 'Cliente · '.$cliente->nombre)

@push('head')
@include('partials.ventas-mayor-responsive')
@endpush

@section('content')
<div class="panel vm-mobile-root">
    <div class="vm-page-head">
        <div>
            <h1 style="margin:0;">{{ $cliente->nombre }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $cliente->documento }}
                ·
                @if($cliente->archivos->count() > 0)
                    <span style="color:#166534; font-weight:700;">📷 {{ $cliente->archivos->count() }}/4 archivos</span>
                @else
                    <span style="color:#991b1b; font-weight:700;">Sin fotos</span>
                @endif
                · {{ $cliente->facturas_count }} facturas
            </p>
        </div>
        <div class="vm-page-actions">
            <a href="{{ route('ventas_mayor.clientes.index') }}" class="btn secondary">← Clientes</a>
            <a href="{{ route('ventas_mayor.create', ['cliente_id' => $cliente->id]) }}" class="btn primary">+ Facturar</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <div style="display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:14px; margin-top:14px;">
        <div class="vm-card" style="border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; background:#fff;">
            <h3 style="margin:0 0 12px; font-size:.95rem; color:#1e3a8a;">Datos</h3>
            <form method="POST" action="{{ route('ventas_mayor.clientes.update', $cliente) }}" enctype="multipart/form-data">
                @csrf
                <div style="display:grid; gap:10px;">
                    <div>
                        <label style="font-size:.75rem; font-weight:700;">Documento</label>
                        <input type="text" value="{{ $cliente->documento }}" disabled style="width:100%; padding:8px 10px; border:1px solid #e2e8f0; border-radius:7px; background:#f8fafc;">
                    </div>
                    <div>
                        <label style="font-size:.75rem; font-weight:700;">Nombre *</label>
                        <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required maxlength="200" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:.75rem; font-weight:700;">Teléfono</label>
                            <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" maxlength="60" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;">
                        </div>
                        <div>
                            <label style="font-size:.75rem; font-weight:700;">Correo</label>
                            <input type="email" name="email" value="{{ old('email', $cliente->email) }}" maxlength="120" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:.75rem; font-weight:700;">Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion', $cliente->direccion) }}" maxlength="300" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:7px;">
                    </div>
                    @if($cliente->archivos->count() < $maxArchivos)
                        <div>
                            <label style="font-size:.75rem; font-weight:700;">Agregar archivos (quedan {{ $maxArchivos - $cliente->archivos->count() }})</label>
                            <input type="file" name="archivos[]" accept="image/*,application/pdf" multiple style="width:100%; margin-top:4px;">
                        </div>
                    @endif
                </div>
                <button type="submit" class="btn primary" style="margin-top:12px;">Guardar cambios</button>
            </form>
        </div>

        <div class="vm-card" style="border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; background:#fff;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:12px;">
                <h3 style="margin:0; font-size:.95rem; color:#1e3a8a;">Archivos ({{ $cliente->archivos->count() }}/{{ $maxArchivos }})</h3>
                @if($cliente->archivos->count() < $maxArchivos)
                    <form method="POST" action="{{ route('ventas_mayor.clientes.archivos.store', $cliente) }}" enctype="multipart/form-data" style="margin:0;">
                        @csrf
                        <label class="btn secondary" style="padding:4px 10px; font-size:.78rem; margin:0; cursor:pointer;">
                            + Subir
                            <input type="file" name="archivo" accept="image/*,application/pdf" hidden onchange="this.form.submit()">
                        </label>
                    </form>
                @endif
            </div>

            @if($cliente->archivos->isEmpty())
                <p class="muted" style="margin:0; font-size:.88rem;">Aún no tiene fotos ni PDFs. Súbelos aquí; se guardan en el bucket <strong>Clientes VM</strong>.</p>
            @else
                <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:10px;">
                    @foreach($cliente->archivos as $a)
                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:8px; background:#f8fafc; position:relative;">
                            @if($a->esImagen())
                                <a href="{{ $a->url }}" target="_blank" rel="noopener">
                                    <img src="{{ $a->url }}" alt="" style="width:100%; height:90px; object-fit:cover; border-radius:8px;">
                                </a>
                            @else
                                <a href="{{ $a->url }}" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;height:90px;background:#e2e8f0;border-radius:8px;font-weight:800;font-size:.8rem;">PDF</a>
                            @endif
                            <div style="font-size:.68rem; margin-top:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $a->nombre_archivo }}">{{ $a->nombre_archivo }}</div>
                            <form method="POST" action="{{ route('ventas_mayor.clientes.archivos.destroy', [$cliente, $a]) }}" onsubmit="return confirm('¿Quitar este archivo?');" style="position:absolute; top:4px; right:4px; margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="width:22px;height:22px;border:0;border-radius:50%;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;line-height:1;">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="vm-card" style="border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; background:#fff; margin-top:14px;">
        <h3 style="margin:0 0 10px; font-size:.95rem; color:#1e3a8a;">Últimas facturas</h3>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.84rem;">
                <thead><tr><th>Número</th><th>Fecha</th><th style="text-align:right;">Total $</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse($facturas as $f)
                        <tr>
                            <td><strong>{{ $f->numeroFormateado() }}</strong></td>
                            <td>{{ $f->fecha->format('d/m/Y') }}</td>
                            <td style="text-align:right;">${{ number_format($f->total_usd, 2) }}</td>
                            <td>
                                @if($f->estaAnulada())
                                    <span class="tag no">Anulada</span>
                                @else
                                    <span class="tag ok">Emitida</span>
                                @endif
                            </td>
                            <td><a href="{{ route('ventas_mayor.show', $f) }}" class="btn secondary" style="padding:4px 8px; font-size:.75rem;">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted" style="text-align:center;">Sin facturas aún.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media (max-width: 900px) {
    .panel .vm-page-head + .alert + div[style*="grid-template-columns"],
    .panel > div[style*="grid-template-columns:minmax"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
@endsection
