@csrf
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    @if(!auth()->user()->scopesServicioToOwnSede() && !isset($orden))
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Sede *</label>
            <select name="sede" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected(old('sede', session('sede_local')) === $sede)>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Cliente *</label>
        <input type="text" name="cliente_nombre" required value="{{ old('cliente_nombre', $orden->cliente_nombre ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Teléfono</label>
        <input type="text" name="cliente_telefono" value="{{ old('cliente_telefono', $orden->cliente_telefono ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Cédula</label>
        <input type="text" name="cliente_cedula" value="{{ old('cliente_cedula', $orden->cliente_cedula ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Equipo</label>
        <input type="text" name="equipo" value="{{ old('equipo', $orden->equipo ?? '') }}" placeholder="Marca y modelo" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Serial</label>
        <input type="text" name="serial" value="{{ old('serial', $orden->serial ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Prioridad *</label>
        <select name="prioridad" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
            @foreach($prioridades as $key => $label)
                <option value="{{ $key }}" @selected(old('prioridad', $orden->prioridad ?? 'normal') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if(isset($orden))
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Estado</label>
            <select name="estado" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                @foreach($estados as $key => $label)
                    <option value="{{ $key }}" @selected(old('estado', $orden->estado) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Fecha prometida</label>
        <input type="date" name="fecha_prometida" value="{{ old('fecha_prometida', isset($orden) && $orden->fecha_prometida ? $orden->fecha_prometida->format('Y-m-d') : '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Accesorios</label>
        <input type="text" name="accesorios" value="{{ old('accesorios', $orden->accesorios ?? '') }}" placeholder="Cargador, funda…" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Falla reportada</label>
    <textarea name="falla" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('falla', $orden->falla ?? '') }}</textarea>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Diagnóstico</label>
    <textarea name="diagnostico" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('diagnostico', $orden->diagnostico ?? '') }}</textarea>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Observaciones</label>
    <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('observaciones', $orden->observaciones ?? '') }}</textarea>
</div>
