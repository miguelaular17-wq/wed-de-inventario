@csrf
@php
    $equipoPrefill = $equipoPrefill ?? null;
    $usarExistente = $usarExistente ?? (bool) old('usar_equipo_existente');
    $tiposGestion = $tiposGestion ?? \App\Models\StOrden::TIPOS_GESTION;
@endphp
@if($equipoPrefill)
    <input type="hidden" name="equipo_id" value="{{ $equipoPrefill->id }}">
    <input type="hidden" name="usar_equipo_existente" value="1">
    <div class="panel" style="padding:12px 16px;margin-bottom:16px;background:#f0f9ff;border:1px solid #bae6fd;">
        Usando equipo existente: <strong>{{ $equipoPrefill->etiqueta() }}</strong> · {{ $equipoPrefill->identificador() }}
        · <a href="{{ route('servicio.celulares.show', $equipoPrefill) }}">Ver bitácora</a>
    </div>
@endif
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    @if(!isset($orden))
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Tipo de gestión *</label>
            <select name="tipo_gestion" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                @foreach($tiposGestion as $key => $label)
                    <option value="{{ $key }}" @selected(old('tipo_gestion', \App\Models\StOrden::TIPO_ST) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
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
        <input type="text" name="cliente_telefono" value="{{ old('cliente_telefono', $orden->cliente_telefono ?? ($equipoPrefill->telefono_asociado ?? '')) }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Cédula</label>
        <input type="text" name="cliente_cedula" value="{{ old('cliente_cedula', $orden->cliente_cedula ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">IMEI</label>
        <input type="text" name="imei" id="st-imei" value="{{ old('imei', $orden->imei ?? ($equipoPrefill->imei ?? '')) }}" placeholder="Obligatorio si el equipo tiene IMEI" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;" @disabled(isset($orden))>
        @if(!isset($orden))
            <div id="st-imei-hint" class="muted" style="font-size:.78rem;margin-top:4px;"></div>
            <label id="st-usar-existente-wrap" style="display:none;margin-top:6px;font-size:.85rem;">
                <input type="checkbox" name="usar_equipo_existente" value="1" id="st-usar-existente" @checked($usarExistente || old('usar_equipo_existente'))>
                Usar el equipo existente y crear una nueva orden
            </label>
            <input type="hidden" name="equipo_id" id="st-equipo-id" value="{{ old('equipo_id', $equipoPrefill->id ?? '') }}">
        @endif
    </div>
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Serial</label>
        <input type="text" name="serial" value="{{ old('serial', $orden->serial ?? ($equipoPrefill->serial ?? '')) }}" placeholder="Si no hay IMEI, el serial es obligatorio" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
    </div>
    @if(!isset($orden))
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Marca</label>
            <input type="text" name="marca" value="{{ old('marca', $equipoPrefill->marca ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Modelo</label>
            <input type="text" name="modelo" value="{{ old('modelo', $equipoPrefill->modelo ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Color</label>
            <input type="text" name="color" value="{{ old('color', $equipoPrefill->color ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
    @endif
    <div>
        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Equipo (texto libre)</label>
        <input type="text" name="equipo" value="{{ old('equipo', $orden->equipo ?? ($equipoPrefill?->etiqueta() ?? '')) }}" placeholder="Marca y modelo" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
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
@if(!isset($orden))
<script>
(function () {
    const input = document.getElementById('st-imei');
    const hint = document.getElementById('st-imei-hint');
    const wrap = document.getElementById('st-usar-existente-wrap');
    const check = document.getElementById('st-usar-existente');
    const equipoId = document.getElementById('st-equipo-id');
    if (!input) return;
    let timer = null;
    const lookupUrl = @json(route('servicio.celulares.lookup_imei'));
    const hasPrefill = @json((bool) $equipoPrefill);
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const imei = input.value.trim();
            if (imei.length < 8) {
                hint.textContent = '';
                wrap.style.display = 'none';
                return;
            }
            try {
                const res = await fetch(lookupUrl + '?imei=' + encodeURIComponent(imei), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.exists) {
                    hint.textContent = data.mensaje;
                    wrap.style.display = 'block';
                    equipoId.value = data.equipo.id;
                } else {
                    hint.textContent = 'IMEI disponible para registro nuevo.';
                    wrap.style.display = 'none';
                    check.checked = false;
                    if (!hasPrefill) equipoId.value = '';
                }
            } catch (e) {
                hint.textContent = '';
            }
        }, 350);
    });
})();
</script>
@endif
