@extends('layouts.app')

@section('title', 'Registrar celular')

@section('content')
@php
    $equipoPrefill = $equipoPrefill ?? null;
    $usarExistente = $usarExistente ?? false;
    $puedeTransferir = $puedeTransferir ?? false;
    $tiposGestion = $tiposGestion ?? \App\Models\StOrden::TIPOS_GESTION;
@endphp
<div style="padding:20px;max-width:920px;margin:0 auto;">
    <a href="{{ route('servicio.celulares.hub') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Gestión de celulares</a>
    <h2 style="font-weight:700;margin:10px 0 8px;">Registrar celular</h2>
    <p class="muted" style="margin:0 0 20px;">Servicio técnico o garantía · opcional envío entre sedes y celular de backup.</p>

    @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:8px;margin-bottom:16px;">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('servicio.ordenes.store') }}" id="form-registrar-celular">
        @csrf

        @if($equipoPrefill)
            <input type="hidden" name="equipo_id" value="{{ $equipoPrefill->id }}">
            <input type="hidden" name="usar_equipo_existente" value="1">
            <div class="panel" style="padding:12px 16px;margin-bottom:16px;background:#f0f9ff;border:1px solid #bae6fd;">
                Usando equipo existente: <strong>{{ $equipoPrefill->etiqueta() }}</strong> · {{ $equipoPrefill->identificador() }}
                · <a href="{{ route('servicio.celulares.show', $equipoPrefill) }}">Ver bitácora</a>
            </div>
        @endif

        <div class="panel" style="padding:20px;margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">1. Tipo de gestión</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                @foreach($tiposGestion as $key => $label)
                    <label style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;cursor:pointer;display:block;">
                        <input type="radio" name="tipo_gestion" value="{{ $key }}" required
                            @checked(old('tipo_gestion', \App\Models\StOrden::TIPO_ST) === $key)>
                        <strong style="margin-left:6px;">{{ $label }}</strong>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="panel" style="padding:20px;margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">2. ¿Se envía a otra sede?</h3>
            @if($puedeTransferir)
                <div style="display:flex;gap:16px;margin-bottom:14px;">
                    <label><input type="radio" name="enviar_otra_sede" value="0" id="enviar-no" @checked(! old('enviar_otra_sede'))> No · trabajo local</label>
                    <label><input type="radio" name="enviar_otra_sede" value="1" id="enviar-si" @checked(old('enviar_otra_sede') == '1')> Sí · envío</label>
                </div>
                <div id="bloque-envio" style="{{ old('enviar_otra_sede') == '1' ? '' : 'display:none;' }}display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Sede de origen *</label>
                        <select name="sede" id="sede-origen" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                            @foreach($sedes as $sede)
                                <option value="{{ $sede }}" @selected(old('sede', session('sede_local') ?: auth()->user()->sede) === $sede)>{{ $sede }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Sede de destino *</label>
                        <select name="sede_destino_envio" id="sede-destino" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                            <option value="">Seleccione…</option>
                            @foreach($sedes as $sede)
                                <option value="{{ $sede }}" @selected(old('sede_destino_envio') === $sede)>{{ $sede }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="bloque-sede-local" style="{{ old('enviar_otra_sede') == '1' ? 'display:none;' : '' }}">
                    @if(! auth()->user()->scopesServicioToOwnSede())
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Sede *</label>
                        <select name="sede_local" style="width:100%;max-width:280px;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                            @foreach($sedes as $sede)
                                <option value="{{ $sede }}" @selected(old('sede_local', old('sede', session('sede_local'))) === $sede)>{{ $sede }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="muted" style="margin:0;">Sede: <strong>{{ strtoupper((string) auth()->user()->sede) }}</strong></p>
                    @endif
                </div>
            @else
                <input type="hidden" name="enviar_otra_sede" value="0">
                <p class="muted" style="margin:0;">Solo usuarios autorizados pueden iniciar envíos. Esta orden quedará en tu sede.
                    @if(auth()->user()->scopesServicioToOwnSede())
                        (<strong>{{ strtoupper((string) auth()->user()->sede) }}</strong>)
                    @endif
                </p>
                @if(! auth()->user()->scopesServicioToOwnSede())
                    <div style="margin-top:12px;">
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Sede *</label>
                        <select name="sede" required style="width:100%;max-width:280px;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                            @foreach($sedes as $sede)
                                <option value="{{ $sede }}" @selected(old('sede', session('sede_local')) === $sede)>{{ $sede }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endif
        </div>

        <div class="panel" style="padding:20px;margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">3. Equipo, cliente y falla</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Cliente *</label>
                    <input type="text" name="cliente_nombre" required value="{{ old('cliente_nombre') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Teléfono</label>
                    <input type="text" name="cliente_telefono" value="{{ old('cliente_telefono', $equipoPrefill->telefono_asociado ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Cédula</label>
                    <input type="text" name="cliente_cedula" value="{{ old('cliente_cedula') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">IMEI</label>
                    <input type="text" name="imei" id="st-imei" value="{{ old('imei', $equipoPrefill->imei ?? '') }}" placeholder="Obligatorio si el equipo tiene IMEI" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    <div id="st-imei-hint" class="muted" style="font-size:.78rem;margin-top:4px;"></div>
                    <label id="st-usar-existente-wrap" style="display:none;margin-top:6px;font-size:.85rem;">
                        <input type="checkbox" name="usar_equipo_existente" value="1" id="st-usar-existente" @checked($usarExistente || old('usar_equipo_existente'))>
                        Usar el equipo existente y crear una nueva orden
                    </label>
                    @unless($equipoPrefill)
                        <input type="hidden" name="equipo_id" id="st-equipo-id" value="{{ old('equipo_id') }}">
                    @endunless
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Serial</label>
                    <input type="text" name="serial" value="{{ old('serial', $equipoPrefill->serial ?? '') }}" placeholder="Si no hay IMEI, el serial es obligatorio" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Marca</label>
                    <input type="text" name="marca" value="{{ old('marca', $equipoPrefill->marca ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Modelo</label>
                    <input type="text" name="modelo" value="{{ old('modelo', $equipoPrefill->modelo ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Color</label>
                    <input type="text" name="color" value="{{ old('color', $equipoPrefill->color ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Prioridad *</label>
                    <select name="prioridad" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                        @foreach($prioridades as $key => $label)
                            <option value="{{ $key }}" @selected(old('prioridad', 'normal') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Fecha prometida</label>
                    <input type="date" name="fecha_prometida" value="{{ old('fecha_prometida') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
                <div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Accesorios del cliente</label>
                    <input type="text" name="accesorios" value="{{ old('accesorios') }}" placeholder="Cargador, funda…" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                </div>
            </div>
            <div style="margin-top:12px;">
                <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Falla / motivo reportado *</label>
                <textarea name="falla" rows="3" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('falla') }}</textarea>
            </div>
            @include('servicio.ordenes._checklist_recepcion', ['checklistRecepcion' => $checklistRecepcion ?? config('servicio_tecnico.checklist_recepcion')])
            <div style="margin-top:12px;">
                <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Observaciones</label>
                <textarea name="observaciones" rows="2" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('observaciones') }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                @include('servicio.ordenes._firma_pad', ['padId' => 'firma-cliente', 'inputName' => 'firma_recepcion_cliente', 'label' => 'Firma digital del cliente'])
                @include('servicio.ordenes._firma_pad', ['padId' => 'firma-empleado', 'inputName' => 'firma_recepcion_empleado', 'label' => 'Firma digital del empleado'])
            </div>
        </div>

        <div class="panel" style="padding:20px;margin-bottom:16px;">
            <h3 style="margin:0 0 14px;">4. ¿Se entregará celular de backup?</h3>
            <div style="display:flex;gap:16px;margin-bottom:14px;">
                <label><input type="radio" name="entrega_backup" value="0" id="backup-no" @checked(! old('entrega_backup'))> No</label>
                <label><input type="radio" name="entrega_backup" value="1" id="backup-si" @checked(old('entrega_backup') == '1')> Sí</label>
            </div>
            <div id="bloque-backup" style="{{ old('entrega_backup') == '1' ? '' : 'display:none;' }}">
                <p class="muted" style="margin:0 0 12px;font-size:.85rem;">Stock autorizado de ST. Se generará un documento imprimible con el logo de la empresa.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Marca backup *</label>
                        <input type="text" name="backup_marca" value="{{ old('backup_marca') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Modelo backup *</label>
                        <input type="text" name="backup_modelo" value="{{ old('backup_modelo') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">IMEI backup</label>
                        <input type="text" name="backup_imei" value="{{ old('backup_imei') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <div>
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Serial backup</label>
                        <input type="text" name="backup_serial" value="{{ old('backup_serial') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Estado físico al entregar</label>
                        <input type="text" name="backup_estado_fisico" value="{{ old('backup_estado_fisico') }}" placeholder="Pantalla ok, carcasa sin golpes…" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Accesorios entregados</label>
                        <input type="text" name="backup_accesorios" value="{{ old('backup_accesorios') }}" placeholder="Cargador, cable…" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    </div>
                </div>
                <p class="muted" style="margin:12px 0 0;font-size:.8rem;">La firma de esta hoja es la del paso 3. En el PDF de recepción sale cómo llegó el celular y los datos del backup, en una sola hoja.</p>
            </div>
        </div>

        <div style="position:sticky;bottom:12px;background:#fff;padding:12px 0;border-top:1px solid #e2e8f0;display:flex;gap:10px;justify-content:flex-end;z-index:5;">
            <a class="btn secondary" href="{{ route('servicio.celulares.hub') }}">Cancelar</a>
            <button class="btn primary" type="submit">Crear orden</button>
        </div>
    </form>
</div>

<script>
(function () {
    const enviarSi = document.getElementById('enviar-si');
    const enviarNo = document.getElementById('enviar-no');
    const bloqueEnvio = document.getElementById('bloque-envio');
    const bloqueLocal = document.getElementById('bloque-sede-local');
    function syncEnvio() {
        if (!bloqueEnvio) return;
        const on = enviarSi && enviarSi.checked;
        bloqueEnvio.style.display = on ? 'grid' : 'none';
        if (bloqueLocal) bloqueLocal.style.display = on ? 'none' : '';
    }
    enviarSi?.addEventListener('change', syncEnvio);
    enviarNo?.addEventListener('change', syncEnvio);
    syncEnvio();

    const backupSi = document.getElementById('backup-si');
    const backupNo = document.getElementById('backup-no');
    const bloqueBackup = document.getElementById('bloque-backup');
    function syncBackup() {
        if (!bloqueBackup) return;
        bloqueBackup.style.display = (backupSi && backupSi.checked) ? '' : 'none';
    }
    backupSi?.addEventListener('change', syncBackup);
    backupNo?.addEventListener('change', syncBackup);

    const input = document.getElementById('st-imei');
    const hint = document.getElementById('st-imei-hint');
    const wrap = document.getElementById('st-usar-existente-wrap');
    const check = document.getElementById('st-usar-existente');
    const equipoId = document.getElementById('st-equipo-id');
    const lookupUrl = @json(route('servicio.celulares.lookup_imei'));
    const hasPrefill = @json((bool) $equipoPrefill);
    let timer = null;
    input?.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const imei = input.value.trim();
            if (imei.length < 8) { hint.textContent = ''; wrap.style.display = 'none'; return; }
            try {
                const res = await fetch(lookupUrl + '?imei=' + encodeURIComponent(imei), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.exists) {
                    hint.textContent = data.mensaje;
                    wrap.style.display = 'block';
                    if (equipoId) equipoId.value = data.equipo.id;
                } else {
                    hint.textContent = 'IMEI disponible para registro nuevo.';
                    wrap.style.display = 'none';
                    if (check) check.checked = false;
                    if (equipoId && !hasPrefill) equipoId.value = '';
                }
            } catch (e) { hint.textContent = ''; }
        }, 350);
    });
})();
</script>
@include('servicio.ordenes._firma_pad_script')
@endsection
