@extends('layouts.app')
@section('title', $propiedad->nombre . ' - Editar')
@section('content')

<div class="pat-form-wrap" style="max-width:800px; margin:32px auto; padding:0 20px;">
    <div style="margin-bottom:14px;">
        <a href="{{ route('patrimonial.propiedades.show', $propiedad) }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">
            ← Volver a {{ $propiedad->nombre }}
        </a>
    </div>

    <div class="pat-form-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <div style="background:linear-gradient(135deg,#1a4480,#2563eb); padding:24px 28px; color:#fff;">
            <h1 style="margin:0; font-size:1.3rem; font-weight:700;">✏️ Editar: {{ $propiedad->nombre }}</h1>
            <p style="margin:4px 0 0; opacity:0.8; font-size:0.85rem;">{{ $propiedad->codigo }}</p>
        </div>
        <div style="padding:28px;">
            <form action="{{ route('patrimonial.propiedades.update', $propiedad) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div style="font-size:0.85rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px; margin:0 0 14px; padding-bottom:8px; border-bottom:2px solid #e2e8f0;">📋 Información Básica</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px;">
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Código *</label>
                        <input type="text" name="codigo" value="{{ old('codigo', $propiedad->codigo) }}" required
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                        @error('codigo') <span style="color:#dc2626; font-size:0.78rem;">{{ $message }}</span> @enderror
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Nombre *</label>
                        <input type="text" name="nombre" value="{{ old('nombre', $propiedad->nombre) }}" required
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Tipo *</label>
                        <select name="tipo" required style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                            @foreach(['casa', 'apartamento', 'local', 'galpón', 'terreno', 'condominio', 'vehículo', 'otro'] as $t)
                                <option value="{{ $t }}" {{ old('tipo', $propiedad->tipo) == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Estado *</label>
                        <select name="estado" required style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                            @foreach(['disponible'=>'Disponible','alquilado'=>'Alquilado','uso_propio'=>'Uso Propio','remodelacion'=>'Remodelación','no_disponible'=>'No Disponible'] as $v => $l)
                                <option value="{{ $v }}" {{ old('estado', $propiedad->estado) == $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion', $propiedad->direccion) }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Ubicación</label>
                        <input type="text" name="ubicacion" value="{{ old('ubicacion', $propiedad->ubicacion) }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Propietario</label>
                        <input type="text" name="propietario" value="{{ old('propietario', $propiedad->propietario) }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Responsable</label>
                        <input type="text" name="responsable" value="{{ old('responsable', $propiedad->responsable) }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Fecha Adquisición</label>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion', optional($propiedad->fecha_adquisicion)->format('Y-m-d')) }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Inversión Inicial ($)</label>
                        <input type="number" name="valor_inversion" value="{{ old('valor_inversion', $propiedad->valor_inversion) }}" step="0.01" min="0"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                </div>

                <div style="margin-top:18px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Fotos adicionales</label>
                    <input type="file" name="fotos[]" multiple accept="image/*" style="padding:6px;">
                    @if($propiedad->fotos && count($propiedad->fotos) > 0)
                        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:12px;">
                            @foreach($propiedad->fotos as $foto)
                                <div style="position:relative; display:inline-block;">
                                    <img src="{{ filter_var($foto, FILTER_VALIDATE_URL) ? $foto : asset('storage/' . $foto) }}" style="width:100px; height:75px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0;">
                                    <button type="button" onclick="deleteFoto('{{ $foto }}')" style="position:absolute; top:-6px; right:-6px; background:#ef4444; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.2);">✕</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div style="margin-top:18px; display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Observaciones</label>
                    <textarea name="observaciones" rows="3" style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b; resize:vertical;">{{ old('observaciones', $propiedad->observaciones) }}</textarea>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px; padding-top:20px; border-top:1px solid #f1f5f9;">
                    <a href="{{ route('patrimonial.propiedades.show', $propiedad) }}" style="display:inline-flex; align-items:center; gap:6px; padding:10px 22px; border-radius:8px; font-weight:600; font-size:0.9rem; text-decoration:none; cursor:pointer; border:1px solid #e2e8f0; color:#334155; background:#fff;">Cancelar</a>
                    <button type="submit" style="display:inline-flex; align-items:center; gap:6px; padding:10px 22px; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer; border:none; background:#2563eb; color:#fff;">💾 Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="delete-foto-form" method="POST" action="{{ route('patrimonial.propiedades.delete_foto', $propiedad) }}" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="foto_url" id="delete-foto-url">
</form>

<script>
function deleteFoto(url) {
    if(confirm('¿Eliminar esta foto de la propiedad?')) {
        document.getElementById('delete-foto-url').value = url;
        document.getElementById('delete-foto-form').submit();
    }
}
</script>
@endsection
