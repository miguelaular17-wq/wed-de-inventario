@extends('layouts.app')

@section('title', $empleado ? 'Editar empleado' : 'Nuevo empleado')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">{{ $empleado ? 'Editar empleado' : 'Nuevo empleado' }}</h1>
            <p class="muted" style="margin:4px 0 0;">Sede o área → Cargo → Supervisor(es). Una sede puede tener dos supervisores. Algunas personas reportan directo a la gerente.</p>
        </div>
        <a href="{{ route('nomina.empleados.index') }}" class="btn secondary">Volver</a>
    </div>

    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $empleado ? route('nomina.empleados.update', $empleado) : route('nomina.empleados.store') }}" class="nomina-form" enctype="multipart/form-data">
        @csrf
        @if($empleado) @method('PUT') @endif

        <h3>Información personal</h3>
        @php
            $fotoActual = $empleado?->foto_url;
            $partesNombre = preg_split('/\s+/', trim((string) $empleado?->nombre())) ?: [];
            $inicialesForm = mb_strtoupper(mb_substr($partesNombre[0] ?? '', 0, 1).mb_substr($partesNombre[1] ?? '', 0, 1)) ?: '+';
        @endphp
        <div class="emp-foto-field">
            <label class="emp-foto-preview" for="emp-foto-input" title="Cambiar foto">
                <img id="emp-foto-img" src="{{ $fotoActual }}" alt="" style="{{ $fotoActual ? '' : 'display:none;' }}">
                <span id="emp-foto-ini" style="{{ $fotoActual ? 'display:none;' : '' }}">{{ $inicialesForm }}</span>
            </label>
            <div class="emp-foto-acciones">
                <strong>Foto del empleado</strong>
                <span class="muted">JPG, PNG o WEBP, hasta 10 MB. Se recorta cuadrada automáticamente.</span>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                    <label for="emp-foto-input" class="btn secondary" style="cursor:pointer;">{{ $fotoActual ? 'Cambiar foto' : 'Subir foto' }}</label>
                    <input type="file" name="foto" id="emp-foto-input" accept="image/jpeg,image/png,image/webp" capture="user" style="display:none;">
                    @if($fotoActual)
                        <label style="display:flex; align-items:center; gap:6px; font-size:.85rem; cursor:pointer;">
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
            <div class="field">
                <label>Usuario del sistema</label>
                <select name="user_id">
                    <option value="">— Ninguno —</option>
                    @foreach($usuarios as $user)
                        <option value="{{ $user->id }}" @selected(old('user_id', $empleado?->user_id) == $user->id)>{{ $user->name }} ({{ $user->role }})</option>
                    @endforeach
                </select>
                <small class="muted">Supervisores: enlaza el usuario para nómina de equipo. Servicio técnico: enlaza su usuario para que vea solo sus facturas de taller en la quincena.</small>
            </div>
        </div>

        <h3>Información laboral</h3>
        <div class="nomina-form-grid">
            <div class="field">
                <label>Sede / área</label>
                <select name="sede_id">
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
                <select name="cargo_id">
                    <option value="">— Seleccionar —</option>
                    @foreach($cargos as $cargo)
                        <option value="{{ $cargo->id }}" @selected(old('cargo_id', $empleado?->cargo_id) == $cargo->id)>{{ $cargo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field field-wide">
                <label>Supervisores</label>
                @php
                    $jefesSel = collect(old('supervisor_ids', $empleado?->jefes?->pluck('id')->all() ?: array_filter([$empleado?->supervisor_id])));
                @endphp
                <div class="nomina-sup-picker" data-max="2">
                    <div class="nomina-sup-chips" data-empty="Sin supervisor asignado">
                        @foreach($supervisores as $sup)
                            @if($jefesSel->contains($sup->id))
                                <button type="button" class="nomina-sup-chip" data-id="{{ $sup->id }}">
                                    {{ $sup->nombre() }}
                                    <span aria-hidden="true">×</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <input type="search" class="nomina-sup-search" placeholder="Buscar por nombre o sede…" autocomplete="off">
                    <div class="nomina-sup-list">
                        @forelse($supervisores as $sup)
                            <label class="nomina-sup-option">
                                <input type="checkbox" name="supervisor_ids[]" value="{{ $sup->id }}" @checked($jefesSel->contains($sup->id))>
                                <span class="nomina-sup-option-text">
                                    <strong>{{ $sup->nombre() }}</strong>
                                    <em>{{ $sup->nombreSede() }}{{ $sup->cargoCatalogo?->nombre ? ' · '.$sup->cargoCatalogo->nombre : '' }}</em>
                                </span>
                            </label>
                        @empty
                            <p class="muted" style="margin:8px 0;">No hay supervisores activos.</p>
                        @endforelse
                    </div>
                    <p class="nomina-sup-hint muted">Hasta dos jefes (sede compartida). Vacío si reporta a la gerente o no tiene supervisor.</p>
                </div>
            </div>
            <div class="field">
                <label>Fecha de ingreso</label>
                <input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', optional($empleado?->fecha_ingreso)->format('Y-m-d')) }}">
            </div>
            <div class="field">
                <label>Salario mensual (USD)</label>
                <input type="number" step="0.01" min="0" name="salario_base" value="{{ old('salario_base', $empleado?->salario_base ?? 0) }}" required>
                <span class="muted" style="font-size:.78rem;">Como en la relación de personal. La nómina quincenal paga la mitad si el tipo es mensual.</span>
            </div>
            <div class="field">
                <label>Tipo de salario</label>
                <select name="tipo_salario">
                    @foreach(['MENSUAL' => 'Mensual (la quincena paga la mitad)', 'QUINCENAL' => 'Quincenal (el monto ya es de la quincena)', 'SOLO_COMISION' => 'Solo comisión'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('tipo_salario', $empleado?->tipo_salario ?? 'MENSUAL') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @php
                $codigosVendedorOld = old('codigos_vendedor');
                if (! is_array($codigosVendedorOld)) {
                    $codigosVendedorOld = $empleado?->codigosVendedor() ?? [];
                    if ($codigosVendedorOld === [] && old('codigo_vendedor')) {
                        $codigosVendedorOld = [old('codigo_vendedor')];
                    }
                }
                $codigosVendedorOld = array_pad(array_values($codigosVendedorOld), 3, '');
            @endphp
            <div class="field field-wide">
                <label>Códigos de vendedor</label>
                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;">
                    @for($i = 0; $i < 3; $i++)
                        <input
                            name="codigos_vendedor[]"
                            value="{{ $codigosVendedorOld[$i] }}"
                            placeholder="{{ $i === 0 ? 'Principal (Profit)' : 'Código '.($i + 1).' (opcional)' }}"
                        >
                    @endfor
                </div>
                <span class="muted" style="font-size:.78rem;">Hasta 3 códigos como aparecen en Profit (columna VENDEDOR). Las ventas de todos suman a este empleado.</span>
            </div>
            <div class="field">
                <label>Modo de comisión</label>
                <select name="modo_comision" required>
                    @foreach(\App\Models\Nomina\NominaEmpleado::modosComision() as $value => $label)
                        <option value="{{ $value }}" @selected(old('modo_comision', $empleado?->modo_comision ?? \App\Models\Nomina\NominaEmpleado::COMISION_NINGUNA) === $value)>
                            {{ $label }}
                        </option>
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
            <div class="field" style="display:flex; align-items:flex-end;">
                <label style="display:flex; gap:8px; align-items:center; text-transform:none; letter-spacing:0;">
                    <input type="checkbox" name="es_supervisor" value="1" @checked(old('es_supervisor', $empleado?->es_supervisor))>
                    Puede tener personal a cargo
                </label>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <label style="display:flex; gap:8px; align-items:center; text-transform:none; letter-spacing:0;">
                    <input type="checkbox" name="es_servicio_tecnico" value="1" @checked(old('es_servicio_tecnico', $empleado?->es_servicio_tecnico))>
                    Pertenece a Servicio Técnico
                </label>
            </div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <label style="display:flex; gap:8px; align-items:center; text-transform:none; letter-spacing:0;">
                    <input type="checkbox" name="exento_retencion_comision" value="1" @checked(old('exento_retencion_comision', $empleado?->exento_retencion_comision))>
                    Sin retención de comisión (10%)
                </label>
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</div>
@push('head')
<style>
.emp-foto-field { display: flex; align-items: center; gap: 16px; margin: 4px 0 16px; }
.emp-foto-preview { width: 96px; height: 96px; border-radius: 18px; overflow: hidden; flex: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #134e4a, #0f766e); color: #fff; font-weight: 800; font-size: 1.6rem; box-shadow: 0 2px 8px rgba(15, 23, 42, .15); }
.emp-foto-preview img { width: 100%; height: 100%; object-fit: cover; }
.emp-foto-acciones { display: flex; flex-direction: column; gap: 2px; }
.emp-foto-acciones .muted { font-size: .82rem; }
</style>
@endpush
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
    const picker = document.querySelector('.nomina-sup-picker');
    if (!picker) return;
    const max = Number(picker.dataset.max || 2);
    const chips = picker.querySelector('.nomina-sup-chips');
    const search = picker.querySelector('.nomina-sup-search');
    const options = [...picker.querySelectorAll('.nomina-sup-option')];

    function selectedBoxes() {
        return options.map((opt) => opt.querySelector('input')).filter((el) => el.checked);
    }

    function renderChips() {
        chips.innerHTML = '';
        selectedBoxes().forEach((input) => {
            const option = input.closest('.nomina-sup-option');
            const name = option.querySelector('strong')?.textContent?.trim() || input.value;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nomina-sup-chip';
            btn.dataset.id = input.value;
            btn.innerHTML = name + ' <span aria-hidden="true">×</span>';
            chips.appendChild(btn);
        });
        chips.classList.toggle('is-empty', selectedBoxes().length === 0);
        const atMax = selectedBoxes().length >= max;
        options.forEach((opt) => {
            const input = opt.querySelector('input');
            input.disabled = atMax && !input.checked;
            opt.classList.toggle('is-disabled', input.disabled);
            opt.classList.toggle('is-checked', input.checked);
        });
    }

    picker.addEventListener('change', (e) => {
        if (e.target.matches('input[type="checkbox"]') && selectedBoxes().length > max) {
            e.target.checked = false;
        }
        renderChips();
    });

    chips.addEventListener('click', (e) => {
        const btn = e.target.closest('.nomina-sup-chip');
        if (!btn) return;
        const input = picker.querySelector('input[value="' + btn.dataset.id + '"]');
        if (input) input.checked = false;
        renderChips();
    });

    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        options.forEach((opt) => {
            opt.hidden = q !== '' && !opt.textContent.toLowerCase().includes(q);
        });
    });

    renderChips();
})();
</script>
@endpush
@endsection
