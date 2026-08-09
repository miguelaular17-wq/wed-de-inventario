@extends('layouts.app')
@section('title', 'Nuevo Alquiler')
@section('content')

<div style="max-width:760px; margin:32px auto; padding:0 20px;">
    <div style="margin-bottom:14px;">
        <a href="{{ route('patrimonial.alquileres.index') }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">← Volver a Alquileres</a>
    </div>
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <div style="background:linear-gradient(135deg,#1a4480,#2563eb); padding:24px 28px; color:#fff;">
            <h1 style="margin:0; font-size:1.3rem; font-weight:700;">📋 Nuevo Contrato de Alquiler</h1>
        </div>
        <div style="padding:28px;">
            <form action="{{ route('patrimonial.alquileres.store') }}" method="POST">
                @csrf

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px;">
                    <div style="grid-column:1/-1; display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Propiedad *</label>
                        <select name="propiedad_id" required style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                            <option value="">Seleccionar propiedad...</option>
                            @foreach($propiedades as $prop)
                                <option value="{{ $prop->id }}" {{ (request('propiedad_id') == $prop->id || old('propiedad_id') == $prop->id) ? 'selected' : '' }}>
                                    {{ $prop->nombre }} ({{ ucfirst($prop->tipo) }})
                                </option>
                            @endforeach
                        </select>
                        @error('propiedad_id') <span style="color:#dc2626; font-size:0.78rem;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Nombre Inquilino *</label>
                        <input type="text" name="inquilino_nombre" value="{{ old('inquilino_nombre') }}" required
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Contacto / Teléfono</label>
                        <input type="text" name="inquilino_contacto" value="{{ old('inquilino_contacto') }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Nº Contrato</label>
                        <input type="text" name="contrato_nro" value="{{ old('contrato_nro') }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Tipo de Canon *</label>
                        <select name="tipo_canon" required id="tipo_canon_sel"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;"
                            onchange="toggleCanon(this.value)">
                            <option value="mensual" {{ old('tipo_canon','mensual')=='mensual'?'selected':'' }}>Mensual</option>
                            <option value="quincenal" {{ old('tipo_canon')=='quincenal'?'selected':'' }}>Quincenal</option>
                        </select>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;" id="div_mensual">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Canon Mensual ($)</label>
                        <input type="number" name="canon_mensual" value="{{ old('canon_mensual') }}" step="0.01" min="0"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px; display:none;" id="div_quincenal">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Canon Quincenal ($)</label>
                        <input type="number" name="canon_quincenal" value="{{ old('canon_quincenal') }}" step="0.01" min="0"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Día de Pago</label>
                        <input type="number" name="dia_pago" value="{{ old('dia_pago', 1) }}" min="1" max="31"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Forma de Pago</label>
                        <input type="text" name="forma_pago" value="{{ old('forma_pago') }}" placeholder="Transferencia, efectivo..."
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}"
                            style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b;">
                    </div>
                    <div style="grid-column:1/-1; display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.82rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Observaciones</label>
                        <textarea name="observaciones" rows="3" style="padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.92rem; font-family:inherit; color:#1e293b; resize:vertical;">{{ old('observaciones') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9;">
                    <a href="{{ route('patrimonial.alquileres.index') }}" style="display:inline-flex; align-items:center; padding:10px 22px; border-radius:8px; font-weight:600; font-size:0.9rem; text-decoration:none; border:1px solid #e2e8f0; color:#334155; background:#fff;">Cancelar</a>
                    <button type="submit" style="display:inline-flex; align-items:center; gap:6px; padding:10px 22px; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer; border:none; background:#2563eb; color:#fff;">💾 Registrar Alquiler</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleCanon(val) {
    document.getElementById('div_mensual').style.display   = val === 'mensual'   ? '' : 'none';
    document.getElementById('div_quincenal').style.display = val === 'quincenal' ? '' : 'none';
}
toggleCanon(document.getElementById('tipo_canon_sel').value);
</script>
@endpush
@endsection
