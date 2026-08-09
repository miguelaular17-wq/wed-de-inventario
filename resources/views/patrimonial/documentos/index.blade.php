@extends('layouts.app')
@section('title', 'Documentos')
@section('content')

<div style="max-width:1100px; margin:0 auto; padding:24px 20px;">
    <div style="margin-bottom:20px;">
        <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
            <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Documentos
        </div>
        <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">📁 Documentos</h1>
    </div>

    <div style="display:grid; grid-template-columns:1fr 2fr; gap:20px; align-items:start;">

        {{-- FORM --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Subir Documento</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.documentos.store') }}" method="POST" enctype="multipart/form-data">
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
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Tipo *</label>
                            <select name="tipo" required style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                @foreach($tipos as $t)
                                    <option value="{{ $t }}" {{ $tipo == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Nombre *</label>
                            <input type="text" name="nombre" required placeholder="Nombre descriptivo del documento"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Archivo *</label>
                            <input type="file" name="archivo" required style="width:100%; padding:6px; font-size:0.85rem;">
                            <div style="font-size:0.75rem; color:#94a3b8; margin-top:4px;">Máx. 20 MB. PDF, imágenes, Word, Excel...</div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Observaciones</label>
                            <textarea name="observaciones" rows="2"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box; resize:vertical;"></textarea>
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">📤 Subir Documento</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTADO --}}
        <div>
            {{-- Filtros --}}
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
                <select name="propiedad_id" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;" onchange="this.form.submit()">
                    <option value="">Todas las propiedades</option>
                    @foreach($propiedades as $prop)
                        <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                    @endforeach
                </select>
                <select name="tipo" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;" onchange="this.form.submit()">
                    <option value="">Todos los tipos</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t }}" {{ request('tipo') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
                <a href="{{ route('patrimonial.documentos.index') }}" style="padding:7px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:7px; text-decoration:none; color:#64748b; font-size:0.85rem;">Limpiar</a>
            </form>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                @forelse($documentos as $doc)
                <div style="padding:14px 18px; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600; font-size:0.92rem; color:#334155;">{{ $doc->getIcono() }} {{ $doc->nombre }}</div>
                        <div style="font-size:0.78rem; color:#94a3b8; margin-top:2px;">
                            {{ $doc->propiedad->nombre ?? '—' }} · {{ ucfirst($doc->tipo) }} · {{ $doc->getTamanoLegible() }}
                            · {{ $doc->created_at->format('d/m/Y') }}
                        </div>
                        @if($doc->observaciones)
                        <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">{{ $doc->observaciones }}</div>
                        @endif
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; flex-shrink:0;">
                        <a href="{{ filter_var($doc->ruta_archivo, FILTER_VALIDATE_URL) ? $doc->ruta_archivo : asset('storage/' . $doc->ruta_archivo) }}" target="_blank"
                           style="padding:5px 12px; background:#dbeafe; color:#1d4ed8; border:none; border-radius:7px; text-decoration:none; font-size:0.8rem; font-weight:700;">⬇ Descargar</a>
                        <form method="POST" action="{{ route('patrimonial.documentos.destroy', $doc) }}"
                              onsubmit="return confirm('¿Eliminar este documento? Esta acción es irreversible.')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:4px 8px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:6px; cursor:pointer; font-size:0.75rem;">🗑️</button>
                        </form>
                    </div>
                </div>
                @empty
                <div style="padding:40px; text-align:center; color:#94a3b8; font-size:0.88rem;">
                    <div style="font-size:2rem; margin-bottom:8px;">📁</div>
                    Sin documentos cargados aún.
                </div>
                @endforelse
                @if($documentos->hasPages())
                <div style="padding:12px 16px;">{{ $documentos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
