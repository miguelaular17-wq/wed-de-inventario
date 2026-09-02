@csrf
@php($registro = $reparacion ?? null)
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    @if(!auth()->user()->scopesServicioToOwnSede() && ! $registro)
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
        <label style="display:block;font-weight:500;margin-bottom:4px;">Tipo *</label>
        <select name="tipo" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
            @foreach($tipos as $key => $label)
                <option value="{{ $key }}" @selected(old('tipo', $registro->tipo ?? 'garantia') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Cliente</label>
        <input type="text" name="cliente_nombre" value="{{ old('cliente_nombre', $registro->cliente_nombre ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Teléfono</label>
        <input type="text" name="cliente_telefono" value="{{ old('cliente_telefono', $registro->cliente_telefono ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Producto *</label>
        <input type="text" name="producto" required value="{{ old('producto', $registro->producto ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Categoría *</label>
        @include('servicio.partials._categoria_select', [
            'value' => $registro->categoria ?? 'otro',
            'required' => true,
        ])
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Comprobante de venta</label>
        <input type="text" name="comprobante_venta" value="{{ old('comprobante_venta', $registro->comprobante_venta ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Acción *</label>
        <select name="accion" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
            @foreach($acciones as $key => $label)
                <option value="{{ $key }}" @selected(old('accion', $registro->accion ?? 'pendiente') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Estado *</label>
        <select name="estado" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
            @foreach($estados as $key => $label)
                <option value="{{ $key }}" @selected(old('estado', $registro->estado ?? 'en_proceso') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;">Costo interno ($)</label>
        <input type="number" step="0.01" min="0" name="costo_interno" value="{{ old('costo_interno', $registro->costo_interno ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Falla</label>
    <textarea name="falla" rows="2" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('falla', $registro->falla ?? '') }}</textarea>
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Repuestos / materiales</label>
    <input type="text" name="repuestos_texto" value="{{ old('repuestos_texto', $registro->repuestos_texto ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
</div>
<div style="margin-bottom:16px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;">Observaciones</label>
    <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('observaciones', $registro->observaciones ?? '') }}</textarea>
</div>
