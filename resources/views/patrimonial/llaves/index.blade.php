@extends('layouts.app')
@section('title', 'Control de Llaves')
@section('content')

<div style="max-width:1100px; margin:0 auto; padding:24px 20px;">
    <div style="margin-bottom:20px;">
        <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
            <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Llaves
        </div>
        <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">🔑 Control de Llaves</h1>
    </div>

    <div style="display:grid; grid-template-columns:1fr 2fr; gap:20px; align-items:start;">

        {{-- FORM --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Registrar Llave</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.llaves.store') }}" method="POST">
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
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Descripción *</label>
                            <input type="text" name="descripcion" required placeholder="Llave 1, Llave principal..."
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Ubicación Actual</label>
                            <input type="text" name="ubicacion_actual" placeholder="Administración, Inquilino, Propietario..."
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Responsable</label>
                            <input type="text" name="responsable"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Fecha Entrega</label>
                                <input type="date" name="fecha_entrega"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Fecha Devolución</label>
                                <input type="date" name="fecha_devolucion"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Observaciones</label>
                            <textarea name="observaciones" rows="2"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box; resize:vertical;"></textarea>
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">🔑 Registrar Llave</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTADO --}}
        <div>
            <form method="GET" style="margin-bottom:14px; display:flex; gap:10px; flex-wrap:wrap;">
                <select name="propiedad_id" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;" onchange="this.form.submit()">
                    <option value="">Todas las propiedades</option>
                    @foreach($propiedades as $prop)
                        <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                    @endforeach
                </select>
                <a href="{{ route('patrimonial.llaves.index') }}" style="padding:7px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:7px; text-decoration:none; color:#64748b; font-size:0.85rem;">Limpiar</a>
            </form>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;">
                            <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Propiedad</th>
                            <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Descripción</th>
                            <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Ubicación</th>
                            <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Responsable</th>
                            <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Entrega</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($llaves as $llave)
                        <tr onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; font-weight:600; color:#334155; font-size:0.85rem;">{{ $llave->propiedad->nombre ?? '—' }}</td>
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#475569;">{{ $llave->descripcion }}</td>
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.85rem;">{{ $llave->ubicacion_actual ?? '—' }}</td>
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.85rem;">{{ $llave->responsable ?? '—' }}</td>
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.82rem;">{{ optional($llave->fecha_entrega)->format('d/m/Y') ?? '—' }}</td>
                            <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9;">
                                <form method="POST" action="{{ route('patrimonial.llaves.destroy', $llave) }}"
                                      onsubmit="return confirm('¿Eliminar este registro?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="padding:3px 7px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:5px; cursor:pointer; font-size:0.72rem;">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="padding:30px; text-align:center; color:#94a3b8;">Sin llaves registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($llaves->hasPages())
                <div style="padding:12px 16px;">{{ $llaves->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
