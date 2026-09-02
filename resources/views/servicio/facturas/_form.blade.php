@csrf
@php($doc = $factura ?? null)
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    @if(!auth()->user()->scopesServicioToOwnSede() && ! $doc)
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;">Sede *</label>
            <select name="sede" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected(old('sede', session('sede_local')) === $sede)>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Cliente *</label>
        <input type="text" name="cliente_nombre" required value="{{ old('cliente_nombre', $doc->cliente_nombre ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Estado de pago *</label>
        <select name="estado_pago" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
            @foreach($estadosPago as $key => $label)
                <option value="{{ $key }}" @selected(old('estado_pago', $doc->estado_pago ?? 'pendiente') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Fecha</label>
        <input type="date" name="fecha" value="{{ old('fecha', $doc && $doc->fecha ? $doc->fecha->format('Y-m-d') : now()->format('Y-m-d')) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Presupuesto ($)</label>
        <input type="number" step="0.01" min="0" name="presupuesto" value="{{ old('presupuesto', $doc->presupuesto ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Mano de obra ($)</label>
        <input type="number" step="0.01" min="0" name="costo_mano_obra" value="{{ old('costo_mano_obra', $doc->costo_mano_obra ?? 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Refacciones ($)</label>
        <input type="number" step="0.01" min="0" name="costo_refacciones" value="{{ old('costo_refacciones', $doc->costo_refacciones ?? 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Descripción del trabajo</label>
    <textarea name="descripcion" rows="3" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('descripcion', $doc->descripcion ?? '') }}</textarea>
</div>
