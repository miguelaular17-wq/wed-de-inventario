@extends('layouts.app')
@section('title', 'Inventario de Propiedades')
@section('content')

<div style="max-width:1200px; margin:0 auto; padding:24px 20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Inventario
            </div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">📦 Inventario de Propiedades</h1>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 2fr; gap:20px; align-items:start;">

        {{-- FORM --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Nuevo Artículo</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.inventario.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Propiedad *</label>
                            <select name="propiedad_id" required style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                <option value="">Seleccionar...</option>
                                @foreach($propiedades as $prop)
                                    <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Artículo *</label>
                            <input type="text" name="articulo" required placeholder="Nombre del artículo"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Cantidad *</label>
                                <input type="number" name="cantidad" value="1" min="0" required
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Estado</label>
                                <select name="estado_articulo" style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                    <option value="">—</option>
                                    <option value="bueno">Bueno</option>
                                    <option value="regular">Regular</option>
                                    <option value="dañado">Dañado</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Observación</label>
                            <textarea name="observacion" rows="2"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box; resize:vertical;"></textarea>
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">📦 Agregar Artículo</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTADO --}}
        <div>
            {{-- Filtro --}}
            <form method="GET" style="margin-bottom:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <select name="propiedad_id" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;" onchange="this.form.submit()">
                    <option value="">Todas las propiedades</option>
                    @foreach($propiedades as $prop)
                        <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                    @endforeach
                </select>
                <a href="{{ route('patrimonial.inventario.index') }}" style="padding:7px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:7px; text-decoration:none; color:#64748b; font-size:0.85rem;">Limpiar</a>
            </form>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                @forelse($items as $item)
                <div style="padding:12px 18px; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600; font-size:0.92rem; color:#334155;">{{ $item->articulo }}</div>
                        <div style="font-size:0.78rem; color:#94a3b8;">{{ $item->propiedad->nombre ?? '—' }}</div>
                        @if($item->observacion) <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">{{ $item->observacion }}</div> @endif
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-shrink:0;">
                        <span style="font-size:0.88rem; font-weight:700; color:#334155;">x{{ $item->cantidad }}</span>
                        @if($item->estado_articulo)
                        <span style="padding:2px 8px; border-radius:12px; font-size:0.72rem; font-weight:700;
                            background:{{ $item->estado_articulo === 'bueno' ? '#d1fae5' : ($item->estado_articulo === 'regular' ? '#fef3c7' : '#fee2e2') }};
                            color:{{ $item->estado_articulo === 'bueno' ? '#065f46' : ($item->estado_articulo === 'regular' ? '#92400e' : '#991b1b') }};">
                            {{ ucfirst($item->estado_articulo) }}
                        </span>
                        @endif
                        <form method="POST" action="{{ route('patrimonial.inventario.destroy', $item) }}"
                              onsubmit="return confirm('¿Eliminar este artículo?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:3px 7px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:5px; cursor:pointer; font-size:0.72rem;">🗑️</button>
                        </form>
                    </div>
                </div>
                @empty
                <div style="padding:40px; text-align:center; color:#94a3b8; font-size:0.88rem;">
                    <div style="font-size:2rem; margin-bottom:8px;">📦</div>
                    Sin artículos registrados. Agrega el primero con el formulario.
                </div>
                @endforelse
                @if($items->hasPages())
                <div style="padding:12px 16px;">{{ $items->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
