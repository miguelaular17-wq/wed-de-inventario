@extends('layouts.app')

@section('title', $empleado ? 'Editar empleado' : 'Nuevo empleado')

@push('head')
<style>
.emp-form-sec {
    margin-top: 18px; padding: 16px 18px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff;
}
.emp-form-sec h3 { margin: 0 0 4px; font-size: 1rem; }
.emp-form-sec .emp-sec-lead { margin: 0 0 14px; font-size: .82rem; color: #64748b; }
.emp-form-sec .nomina-form-grid { margin-top: 0; }
.emp-foto-field { display: flex; align-items: center; gap: 16px; margin: 0 0 14px; }
.emp-foto-preview {
    width: 88px; height: 88px; border-radius: 16px; overflow: hidden; flex: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #134e4a, #0f766e); color: #fff; font-weight: 800; font-size: 1.5rem;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .12);
}
.emp-foto-preview img { width: 100%; height: 100%; object-fit: cover; }
.emp-foto-acciones { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.emp-foto-acciones .muted { font-size: .78rem; }
.emp-flags {
    display: flex; flex-wrap: wrap; gap: 10px 18px; margin-top: 12px;
    padding-top: 12px; border-top: 1px dashed #e2e8f0;
}
.emp-flags label {
    display: flex; gap: 8px; align-items: center; margin: 0;
    text-transform: none; letter-spacing: 0; font-size: .88rem; font-weight: 600; color: #334155; cursor: pointer;
}
.emp-comision-detalle {
    display: block; margin-top: 6px; font-size: .78rem; color: #64748b; min-height: 1.2em;
}
.emp-form-sec .nomina-sup-list { max-height: 180px; }
.emp-codigos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.emp-jefes-preview {
    display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px;
}
.emp-jefe-chip {
    display: inline-flex; flex-direction: column; gap: 2px;
    padding: 8px 12px; border-radius: 10px; background: #f1f5f9; border: 1px solid #e2e8f0;
    min-width: 160px;
}
.emp-jefe-chip strong { font-size: .88rem; color: #0f172a; }
.emp-jefe-chip span { font-size: .75rem; color: #64748b; }
@media (max-width: 720px) {
    .emp-codigos { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">{{ $empleado ? 'Editar empleado' : 'Nuevo empleado' }}</h1>
            <p class="muted" style="margin:4px 0 0;">Completa persona, puesto, salario y comisión. El equipo de un supervisor sale de su sede o área.</p>
        </div>
        <a href="{{ route('nomina.empleados.index') }}" class="btn secondary">Volver</a>
    </div>

    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    @php
        $modoActual = old('modo_comision', $empleado?->modo_comision ?? \App\Models\Nomina\NominaEmpleado::COMISION_NINGUNA);
        $fotoActual = $empleado?->foto_url;
        $partesNombre = preg_split('/\s+/', trim((string) $empleado?->nombre())) ?: [];
        $inicialesForm = mb_strtoupper(mb_substr($partesNombre[0] ?? '', 0, 1).mb_substr($partesNombre[1] ?? '', 0, 1)) ?: '+';
        $codigosVendedorOld = old('codigos_vendedor');
        if (! is_array($codigosVendedorOld)) {
            $codigosVendedorOld = $empleado?->codigosVendedor() ?? [];
            if ($codigosVendedorOld === [] && old('codigo_vendedor')) {
                $codigosVendedorOld = [old('codigo_vendedor')];
            }
        }
        $codigosVendedorOld = array_pad(array_values($codigosVendedorOld), 3, '');
    @endphp

    <form method="POST" action="{{ $empleado ? route('nomina.empleados.update', $empleado) : route('nomina.empleados.store') }}" class="nomina-form" enctype="multipart/form-data">
        @csrf
        @if($empleado) @method('PUT') @endif

        <section class="emp-form-sec">
            <h3>1. Persona</h3>
            <p class="emp-sec-lead">Identidad y contacto.</p>

            <div class="emp-foto-field">
                <label class="emp-foto-preview" for="emp-foto-input" title="Cambiar foto">
                    <img id="emp-foto-img" src="{{ $fotoActual }}" alt="" style="{{ $fotoActual ? '' : 'display:none;' }}">
                    <span id="emp-foto-ini" style="{{ $fotoActual ? 'display:none;' : '' }}">{{ $inicialesForm }}</span>
                </label>
                <div class="emp-foto-acciones">
                    <strong>Foto</strong>
                    <span class="muted">JPG, PNG o WEBP · hasta 10 MB</span>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                        <label for="emp-foto-input" class="btn secondary" style="cursor:pointer;">{{ $fotoActual ? 'Cambiar foto' : 'Subir foto' }}</label>
                        <input type="file" name="foto" id="emp-foto-input" accept="image/jpeg,image/png,image/webp" capture="user" style="display:none;">
                        @if($fotoActual)
                            <label style="display:flex; align-items:center; gap:6px; font-size:.85rem; cursor:pointer; text-transform:none; letter-spacing:0;">
                                <input type="checkbox" name="quitar_foto" value="1" id="emp-foto-quitar"> Quitar foto
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="nomina-form-grid">
                @if(!$empleado)
                <div class="field field-wide">
                    <label>Persona (tabla clientes)</label>
                    <select name="cliente_id" id="nomina-cliente">
                        <option value="">— Seleccionar persona existente —</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}" data-cedula="{{ $cliente->cedula }}" data-nombre="{{ $cliente->nombre }}" @selected(old('cliente_id') == $cliente->id)>
                                {{ $cliente->nombre }} · {{ $cliente->cedula }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="field">
                    <label>Cédula</label>
                    <input name="cedula" id="nomina-cedula" value="{{ old('cedula', $empleado?->cedula()) }}" required>
                </div>
                <div class="field">
                    <label>Nombre completo</label>
                    <input name="nombre" id="nomina-nombre" value="{{ old('nombre', $empleado?->nombre()) }}" required>
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $empleado?->email) }}">
                </div>
                <div class="field">
                    <label>Teléfono</label>
                    <input name="telefono" value="{{ old('telefono', $empleado?->telefono) }}">
                </div>
                <div class="field field-wide">
                    <label>Usuario del sistema</label>
                    <select name="user_id">
                        <option value="">— Ninguno —</option>
                        @foreach($usuarios as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id', $empleado?->user_id) == $user->id)>{{ $user->name }} ({{ $user->role }})</option>
                        @endforeach
                    </select>
                    <span class="muted" style="font-size:.78rem;">Para nómina de equipo o facturas de taller (servicio técnico).</span>
                </div>
            </div>
        </section>

        <section class="emp-form-sec">
            <h3>2. Puesto y jefatura</h3>
            <p class="emp-sec-lead">Elige sede y cargo; si no es supervisor, verás quién queda como su jefe.</p>

            <div class="nomina-form-grid">
                <div class="field">
                    <label>Sede / área</label>
                    <select name="sede_id" id="emp-sede">
                        @include('nomina.partials.sede-options', ['unidades' => $sedes, 'selected' => old('sede_id', $empleado?->sede_id), 'placeholder' => '— Seleccionar —'])
                    </select>
                </div>
                <div class="field">
                    <label>Empresa (código / RIF)</label>
                    <select name="empresa_id">
                        <option value="">— Seleccionar —</option>
                        @foreach($empresas as $empresa)
                            <option value="{{ $empresa->id }}" @selected(old('empresa_id', $empleado?->empresa_id) == $empresa->id)>
                                {{ $empresa->codigo }} · {{ $empresa->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cargo</label>
                    <select name="cargo_id" id="emp-cargo">
                        <option value="" data-personal-cargo="0">— Seleccionar —</option>
                        @foreach($cargos as $cargo)
                            @php
                                $cargoConEquipo = str_starts_with(mb_strtoupper($cargo->nombre, 'UTF-8'), 'SUPERVISOR')
                                    || str_starts_with(mb_strtoupper($cargo->nombre, 'UTF-8'), 'SUPERVISORA');
                            @endphp
                            <option
                                value="{{ $cargo->id }}"
                                data-personal-cargo="{{ $cargoConEquipo ? '1' : '0' }}"
                                @selected(old('cargo_id', $empleado?->cargo_id) == $cargo->id)
                            >{{ $cargo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field field-wide" id="aviso-organigrama">
                    <label id="aviso-org-label">Supervisor(es)</label>
                    <div id="aviso-org-jefes" class="emp-jefes-preview" hidden></div>
                    <p class="muted" id="aviso-org-texto" style="margin:6px 0 0;font-size:.88rem;line-height:1.4;"></p>
                    <input type="hidden" name="es_supervisor" id="flag-es-supervisor" value="{{ old('es_supervisor', $empleado?->es_supervisor) ? '1' : '0' }}">
                </div>
            </div>

            <div class="emp-flags">
                <label>
                    <input type="checkbox" name="es_servicio_tecnico" value="1" @checked(old('es_servicio_tecnico', $empleado?->es_servicio_tecnico))>
                    Pertenece a Servicio Técnico
                </label>
            </div>
        </section>

        <section class="emp-form-sec">
            <h3>3. Salario</h3>
            <p class="emp-sec-lead">Cómo se paga la quincena (aparte de la comisión).</p>

            <div class="nomina-form-grid">
                <div class="field">
                    <label>Fecha de ingreso</label>
                    <input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', optional($empleado?->fecha_ingreso)->format('Y-m-d')) }}">
                </div>
                <div class="field">
                    <label>Salario (USD)</label>
                    <input type="number" step="0.01" min="0" name="salario_base" value="{{ old('salario_base', $empleado?->salario_base ?? 0) }}" required>
                    <span class="muted" style="font-size:.78rem;">Como en la relación de personal.</span>
                </div>
                <div class="field">
                    <label>Tipo de salario</label>
                    <select name="tipo_salario">
                        @foreach([
                            'MENSUAL' => 'Mensual (la quincena paga la mitad)',
                            'QUINCENAL' => 'Quincenal (el monto ya es de la quincena)',
                            'SOLO_COMISION' => 'Solo comisión',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('tipo_salario', $empleado?->tipo_salario ?? 'MENSUAL') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Estado</label>
                    <select name="estado">
                        <option value="ACTIVO" @selected(old('estado', $empleado?->estado ?? 'ACTIVO') === 'ACTIVO')>Activo</option>
                        <option value="INACTIVO" @selected(old('estado', $empleado?->estado) === 'INACTIVO')>Inactivo</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="emp-form-sec">
            <h3>4. Comisión</h3>
            <p class="emp-sec-lead">Códigos de Profit y cómo se calcula la comisión.</p>

            <div class="nomina-form-grid">
                <div class="field field-wide">
                    <label>Códigos de vendedor (Profit)</label>
                    <div class="emp-codigos">
                        @for($i = 0; $i < 3; $i++)
                            <input
                                name="codigos_vendedor[]"
                                value="{{ $codigosVendedorOld[$i] }}"
                                placeholder="{{ $i === 0 ? 'Principal' : 'Código '.($i + 1).' (opcional)' }}"
                            >
                        @endfor
                    </div>
                    <span class="muted" style="font-size:.78rem;">Como aparecen en la columna VENDEDOR. Las ventas de todos suman aquí.</span>
                </div>
                <div class="field field-wide">
                    <label>Modo de comisión</label>
                    <select name="modo_comision" id="emp-modo-comision" required>
                        @foreach(\App\Models\Nomina\NominaEmpleado::modosComision() as $value => $label)
                            <option value="{{ $value }}"
                                    data-detalle="{{ \App\Models\Nomina\NominaEmpleado::detalleModoComision($value) }}"
                                    @selected($modoActual === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <span class="emp-comision-detalle" id="emp-modo-detalle">{{ \App\Models\Nomina\NominaEmpleado::detalleModoComision($modoActual) }}</span>
                </div>
            </div>

            <div class="emp-flags">
                <label>
                    <input type="checkbox" name="exento_retencion_comision" value="1" @checked(old('exento_retencion_comision', $empleado?->exento_retencion_comision))>
                    Sin retención de comisión (10%)
                </label>
            </div>
        </section>

        <div style="margin-top:18px;">
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('emp-foto-input')?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    const img = document.getElementById('emp-foto-img');
    img.src = URL.createObjectURL(file);
    img.style.display = '';
    document.getElementById('emp-foto-ini').style.display = 'none';
    const quitar = document.getElementById('emp-foto-quitar');
    if (quitar) quitar.checked = false;
});
document.getElementById('emp-foto-quitar')?.addEventListener('change', function () {
    document.getElementById('emp-foto-img').style.opacity = this.checked ? '.25' : '1';
});

document.getElementById('nomina-cliente')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const cedula = document.getElementById('nomina-cedula');
    const nombre = document.getElementById('nomina-nombre');
    if (!cedula || !nombre) return;
    cedula.value = opt.dataset.cedula || '';
    nombre.value = opt.dataset.nombre || '';
});

(function () {
    const sel = document.getElementById('emp-modo-comision');
    const det = document.getElementById('emp-modo-detalle');
    if (!sel || !det) return;
    function sync() {
        const opt = sel.options[sel.selectedIndex];
        det.textContent = opt?.dataset?.detalle || '';
    }
    sel.addEventListener('change', sync);
    sync();
})();

(function () {
    const cargo = document.getElementById('emp-cargo');
    const sede = document.getElementById('emp-sede');
    const flag = document.getElementById('flag-es-supervisor');
    const texto = document.getElementById('aviso-org-texto');
    const label = document.getElementById('aviso-org-label');
    const preview = document.getElementById('aviso-org-jefes');
    if (!cargo || !sede || !flag || !texto || !label || !preview) return;

    const jefesPorSede = @json($jefesPorSede ?? []);
    const msgSup = 'Cargo de supervisor: queda con personal a cargo de su sede/área y reporta a la gerente.';

    function renderJefes(lista) {
        preview.innerHTML = '';
        if (!lista || !lista.length) {
            preview.hidden = true;
            return;
        }
        lista.forEach(function (jefe) {
            const chip = document.createElement('div');
            chip.className = 'emp-jefe-chip';
            chip.innerHTML = '<strong></strong><span></span>';
            chip.querySelector('strong').textContent = jefe.nombre || '—';
            chip.querySelector('span').textContent = jefe.cargo || 'Supervisor';
            preview.appendChild(chip);
        });
        preview.hidden = false;
    }

    function sync() {
        const opt = cargo.options[cargo.selectedIndex];
        const esSup = opt?.dataset?.personalCargo === '1';
        const sedeId = String(sede.value || '');
        flag.value = esSup ? '1' : '0';

        if (esSup) {
            label.textContent = 'Organigrama';
            renderJefes([]);
            texto.textContent = msgSup;
            return;
        }

        label.textContent = listaTitulo(jefesPorSede[sedeId] || []);
        const lista = jefesPorSede[sedeId] || [];
        renderJefes(lista);
        if (!sedeId) {
            texto.textContent = 'Elige una sede o área para ver quién será su supervisor.';
        } else if (!lista.length) {
            texto.textContent = 'Esta sede/área aún no tiene supervisor activo.';
        } else {
            texto.textContent = '';
        }
    }

    function listaTitulo(lista) {
        if (lista.length > 1) return 'Sus supervisores';
        if (lista.length === 1 && /gerente/i.test(lista[0].cargo || '')) return 'Su gerente';
        return 'Su supervisor';
    }

    cargo.addEventListener('change', sync);
    sede.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
