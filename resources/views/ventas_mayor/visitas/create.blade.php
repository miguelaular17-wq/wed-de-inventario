@extends('layouts.app')

@section('title', 'Hoja de ruta · Nueva visita')

@section('content')
<style>
    .hr-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; background: #fff; }
    .hr-card h3 { margin: 0 0 12px; font-size: .95rem; color: #1e3a8a; display: flex; gap: 8px; align-items: center; }
    .hr-paso { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: #1e3a8a; color: #fff; font-size: .75rem; align-items: center; justify-content: center; }
    .hr-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .hr-row > div { flex: 1; min-width: 170px; }
    .hr-card label { display: block; font-size: .78rem; font-weight: 600; margin-bottom: 3px; color: #334155; }
    .hr-card input, .hr-card select, .hr-card textarea { width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 7px; box-sizing: border-box; font-size: .9rem; }
    .hr-res { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; }
    .hr-res label { margin: 0; }
    .hr-res input { display: none; }
    .hr-res span { display: block; text-align: center; padding: 10px; border: 1.5px solid #cbd5e1; border-radius: 9px; cursor: pointer; font-weight: 700; font-size: .85rem; color: #475569; }
    .hr-res input:checked + span { border-color: #1e3a8a; background: #eff6ff; color: #1e3a8a; }
    #hr-firma { width: 100%; height: 190px; border: 2px dashed #94a3b8; border-radius: 10px; background: #fff; touch-action: none; cursor: crosshair; display: block; }
    .hr-sug { position: absolute; z-index: 20; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 24px rgba(15,23,42,.15); display: none; max-height: 240px; overflow: auto; }
    .hr-sug button { display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-bottom: 1px solid #f1f5f9; background: #fff; cursor: pointer; font-size: .85rem; }
    .hr-sug button:hover { background: #eff6ff; }
    #hr-previas img { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
</style>
<div class="panel" style="max-width:900px; margin:0 auto;">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
        <div>
            <h1 style="margin:0;">Nueva visita</h1>
            <p class="muted" style="margin:4px 0 0;">Hoja de ruta para captar clientes. La firma y la evidencia son obligatorias.</p>
        </div>
        <a href="{{ route('ventas_mayor.visitas.index') }}" class="btn secondary">← Hoja de ruta</a>
    </div>

    @if($errors->any())
        <div class="alert error" style="margin-bottom:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('ventas_mayor.visitas.store') }}" enctype="multipart/form-data" id="hr-form">
        @csrf
        <input type="hidden" name="cliente_id" id="hr-cliente-id" value="{{ old('cliente_id') }}">
        <input type="hidden" name="latitud" id="hr-lat" value="{{ old('latitud') }}">
        <input type="hidden" name="longitud" id="hr-lng" value="{{ old('longitud') }}">

        <div class="hr-card">
            <h3><span class="hr-paso">1</span> Negocio visitado</h3>
            <div class="hr-row">
                <div style="flex:2; position:relative;">
                    <label>Nombre del negocio *</label>
                    <input type="text" name="negocio_nombre" id="hr-negocio" maxlength="200" value="{{ old('negocio_nombre') }}" required autocomplete="off" placeholder="Si ya es cliente, aparece al escribir">
                    <div class="hr-sug" id="hr-sug"></div>
                    <div id="hr-es-cliente" style="display:none; margin-top:4px; font-size:.78rem; color:#15803d; font-weight:700;">✓ Ya es cliente al mayor</div>
                </div>
                <div><label>RIF / cédula</label><input type="text" name="negocio_documento" id="hr-doc" maxlength="40" value="{{ old('negocio_documento') }}"></div>
            </div>
            <div class="hr-row" style="margin-top:8px;">
                <div><label>Teléfono del negocio</label><input type="text" name="negocio_telefono" id="hr-tel" maxlength="60" value="{{ old('negocio_telefono') }}"></div>
                <div><label>Zona / sector</label><input type="text" name="zona" maxlength="120" value="{{ old('zona') }}" placeholder="Ej. Centro, Av. Bolívar"></div>
            </div>
            <div class="hr-row" style="margin-top:8px;">
                <div><label>Dirección</label><input type="text" name="negocio_direccion" id="hr-dir" maxlength="300" value="{{ old('negocio_direccion') }}"></div>
            </div>
            <div id="hr-gps" class="muted" style="font-size:.75rem; margin-top:6px;">📍 Obteniendo ubicación…</div>
        </div>

        <div class="hr-card">
            <h3><span class="hr-paso">2</span> Persona que atendió</h3>
            <div class="hr-row">
                <div style="flex:2;"><label>Nombre y apellido *</label><input type="text" name="atendio_nombre" id="hr-atendio" maxlength="200" value="{{ old('atendio_nombre') }}" required></div>
                <div><label>Cargo</label><input type="text" name="atendio_cargo" maxlength="120" value="{{ old('atendio_cargo') }}" placeholder="Dueño, encargado…"></div>
            </div>
            <div class="hr-row" style="margin-top:8px;">
                <div><label>Cédula</label><input type="text" name="atendio_documento" maxlength="40" value="{{ old('atendio_documento') }}"></div>
                <div><label>Teléfono</label><input type="text" name="atendio_telefono" maxlength="60" value="{{ old('atendio_telefono') }}"></div>
            </div>
        </div>

        <div class="hr-card">
            <h3><span class="hr-paso">3</span> Resultado</h3>
            <div class="hr-res">
                @foreach(\App\Models\Mayor\MayorVisita::RESULTADOS as $clave => $texto)
                    <label><input type="radio" name="resultado" value="{{ $clave }}" @checked(old('resultado', 'interesado') === $clave) required><span>{{ $texto }}</span></label>
                @endforeach
            </div>
            <div class="hr-row" style="margin-top:10px;">
                <div><label>Fecha de la visita</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                <div><label>Próxima visita</label><input type="date" name="proxima_visita" value="{{ old('proxima_visita') }}"></div>
            </div>
            <div style="margin-top:8px;"><label>Observaciones</label><textarea name="observaciones" rows="2" maxlength="2000" placeholder="Qué productos le interesan, volumen, condiciones…">{{ old('observaciones') }}</textarea></div>
        </div>

        <div class="hr-card">
            <h3><span class="hr-paso">4</span> Firma de quien atendió *</h3>
            <canvas id="hr-firma"></canvas>
            <input type="hidden" name="firma" id="hr-firma-dato">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                <span class="muted" style="font-size:.75rem;">Firma con el dedo o el mouse dentro del recuadro.</span>
                <button type="button" class="btn secondary" style="padding:3px 10px; font-size:.78rem;" id="hr-limpiar">Borrar firma</button>
            </div>
        </div>

        <div class="hr-card">
            <h3><span class="hr-paso">5</span> Evidencia de la visita *</h3>
            <input type="file" name="evidencias[]" id="hr-evidencias" accept="image/*,.pdf" capture="environment" multiple required>
            <p class="muted" style="font-size:.75rem; margin:6px 0 0;">Foto del local, de la vitrina o con la persona que atendió (hasta 6).</p>
            <div id="hr-previas" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;"></div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <a href="{{ route('ventas_mayor.visitas.index') }}" class="btn secondary">Cancelar</a>
            <button type="submit" class="btn primary" style="padding:11px 24px; font-weight:800;">Guardar visita</button>
        </div>
    </form>
</div>

<script>
(function () {
    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

    // ── Firma ──
    const canvas = $('hr-firma'), ctx = canvas.getContext('2d');
    let dibujando = false, trazos = 0;
    function ajustar() {
        const r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
        canvas.width = r.width * dpr; canvas.height = r.height * dpr;
        ctx.scale(dpr, dpr); ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
        trazos = 0;
    }
    const punto = e => { const r = canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    canvas.addEventListener('pointerdown', e => { dibujando = true; canvas.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...punto(e)); });
    canvas.addEventListener('pointermove', e => { if (!dibujando) return; ctx.lineTo(...punto(e)); ctx.stroke(); trazos++; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => canvas.addEventListener(ev, () => { dibujando = false; }));
    $('hr-limpiar').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); trazos = 0; });
    ajustar();
    window.addEventListener('resize', () => { if (!trazos) ajustar(); });

    // ── Evidencias: vista previa ──
    $('hr-evidencias').addEventListener('change', e => {
        const cont = $('hr-previas'); cont.innerHTML = '';
        [...e.target.files].slice(0, 6).forEach(f => {
            if (f.type.startsWith('image/')) {
                const img = document.createElement('img'); img.src = URL.createObjectURL(f); cont.appendChild(img);
            } else {
                const d = document.createElement('div'); d.textContent = '📄 ' + f.name; d.style.cssText = 'padding:8px; border:1px solid #e2e8f0; border-radius:8px; font-size:.8rem;'; cont.appendChild(d);
            }
        });
    });

    // ── Ubicación ──
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(p => {
            $('hr-lat').value = p.coords.latitude.toFixed(7); $('hr-lng').value = p.coords.longitude.toFixed(7);
            $('hr-gps').textContent = `📍 Ubicación guardada (±${Math.round(p.coords.accuracy)} m)`;
        }, () => { $('hr-gps').textContent = '📍 Sin ubicación (el teléfono no la dio). Se guarda igual.'; }, {enableHighAccuracy: true, timeout: 10000});
    } else {
        $('hr-gps').textContent = '📍 Este equipo no da la ubicación.';
    }

    // ── Si ya es cliente ──
    let t;
    $('hr-negocio').addEventListener('input', e => {
        clearTimeout(t);
        $('hr-cliente-id').value = ''; $('hr-es-cliente').style.display = 'none';
        const q = e.target.value.trim(), box = $('hr-sug');
        if (q.length < 3) { box.style.display = 'none'; return; }
        t = setTimeout(async () => {
            const r = await fetch(@json(route('ventas_mayor.clientes.buscar')) + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}});
            const {clientes} = await r.json();
            if (!clientes.length) { box.style.display = 'none'; return; }
            box.innerHTML = clientes.map((c, k) => `<button type="button" data-k="${k}"><strong>${esc(c.nombre)}</strong> · ${esc(c.documento)} <span class="muted">(ya es cliente)</span></button>`).join('');
            box.querySelectorAll('[data-k]').forEach(b => b.addEventListener('click', () => {
                const c = clientes[b.dataset.k];
                $('hr-cliente-id').value = c.id; $('hr-negocio').value = c.nombre; $('hr-doc').value = c.documento;
                if (c.telefono) $('hr-tel').value = c.telefono;
                if (c.direccion) $('hr-dir').value = c.direccion;
                $('hr-es-cliente').style.display = ''; box.style.display = 'none';
            }));
            box.style.display = 'block';
        }, 250);
    });
    document.addEventListener('click', e => { if (!$('hr-sug').contains(e.target) && e.target !== $('hr-negocio')) $('hr-sug').style.display = 'none'; });

    $('hr-form').addEventListener('submit', e => {
        if (trazos < 5) { e.preventDefault(); alert('Falta la firma de la persona que atendió.'); return; }
        if (!$('hr-evidencias').files.length) { e.preventDefault(); alert('Sube al menos una foto de evidencia de la visita.'); return; }
        $('hr-firma-dato').value = canvas.toDataURL('image/png');
    });
})();
</script>
@endsection
