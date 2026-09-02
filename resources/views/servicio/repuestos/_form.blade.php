@csrf
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    @if(!auth()->user()->scopesServicioToOwnSede())
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Sede *</label>
            <select name="sede" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected(old('sede', $repuesto->sede ?? $sedeDefault ?? '') === $sede)>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Código</label>
        <input type="text" name="codigo" value="{{ old('codigo', $repuesto->codigo ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Nombre *</label>
        <input type="text" name="nombre" required value="{{ old('nombre', $repuesto->nombre ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Categoría</label>
        @include('servicio.partials._categoria_select', [
            'value' => $repuesto->categoria ?? 'otro',
        ])
    </div>
    @if(!isset($repuesto))
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Stock inicial</label>
            <input type="number" min="0" name="stock" value="{{ old('stock', 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Stock mínimo</label>
        <input type="number" min="0" name="stock_min" value="{{ old('stock_min', $repuesto->stock_min ?? 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Costo ($)</label>
        <input type="number" step="0.01" min="0" name="costo" value="{{ old('costo', $repuesto->costo ?? 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Precio venta ($)</label>
        <input type="number" step="0.01" min="0" name="precio_venta" value="{{ old('precio_venta', $repuesto->precio_venta ?? 0) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    @if(isset($repuesto))
        <div style="display:flex;align-items:center;padding-top:24px;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $repuesto->activo))> Activo
            </label>
        </div>
    @endif
</div>
