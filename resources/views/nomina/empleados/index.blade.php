@extends('layouts.app')

@section('title', 'Empleados')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Empleados</h1>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('nomina.empleados.reporte', array_filter($filters ?? [], fn ($value) => $value !== null && $value !== '')) }}" class="btn secondary">
                Descargar reporte
            </a>
            <a href="{{ route('nomina.empleados.create') }}" class="btn primary">Nuevo empleado</a>
        </div>
    </div>

    @if(($importados ?? 0) > 0)
        <div class="success">Se incorporaron {{ $importados }} personas desde la tabla clientes. Completa sede, cargo y salario en cada ficha.</div>
    @endif

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre o cédula">
        </div>
        <div class="field">
            <label>Sede / área</label>
            <select name="sede_id">
                @include('nomina.partials.sede-options', ['unidades' => $sedes, 'selected' => $filters['sede_id'] ?? '', 'placeholder' => 'Todas'])
            </select>
        </div>
        <div class="field">
            <label>Empresa</label>
            <select name="empresa_id">
                <option value="">Todas</option>
                @foreach($empresas as $empresa)
                    <option value="{{ $empresa->id }}" @selected(($filters['empresa_id'] ?? '') == $empresa->id)>{{ $empresa->codigo }} · {{ $empresa->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Cargo</label>
            <select name="cargo_id">
                <option value="">Todos</option>
                @foreach($cargos as $cargo)
                    <option value="{{ $cargo->id }}" @selected(($filters['cargo_id'] ?? '') == $cargo->id)>{{ $cargo->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Supervisor</label>
            <select name="supervisor_id">
                <option value="">Todos</option>
                @foreach($supervisores as $sup)
                    <option value="{{ $sup->id }}" @selected(($filters['supervisor_id'] ?? '') == $sup->id)>{{ $sup->nombre() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                <option value="ACTIVO" @selected(($filters['estado'] ?? '') === 'ACTIVO')>Activo</option>
                <option value="INACTIVO" @selected(($filters['estado'] ?? '') === 'INACTIVO')>Inactivo</option>
            </select>
        </div>
        <div class="field" style="display:flex; align-items:flex-end; gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ route('nomina.empleados.index') }}">Limpiar</a>
        </div>
    </form>

    <div class="emp-grid">
        @forelse($empleados as $empleado)
            @php
                $partes = preg_split('/\s+/', trim($empleado->nombre())) ?: [];
                $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '?', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
                $esArea = (bool) $empleado->sedeCatalogo?->isArea();
                $venta = (float) ($ventasHastaHoy[$empleado->id] ?? 0);
            @endphp
            <a class="emp-card" href="{{ route('nomina.empleados.show', $empleado) }}">
                <div class="emp-card-top">
                    <div class="emp-foto" aria-hidden="true">{{ $iniciales }}</div>
                    <div class="emp-id">
                        <strong>{{ $empleado->nombre() }}</strong>
                        <div class="muted">{{ $empleado->cedula() ?: 'Sin cédula' }}</div>
                    </div>
                    <span class="tag {{ $empleado->isActivo() ? 'ok' : 'no' }}">{{ $empleado->estado }}</span>
                </div>
                <dl class="emp-datos">
                    <div>
                        <dt>{{ $esArea ? 'Área' : 'Sede' }}</dt>
                        <dd>{{ $empleado->nombreSede() }}</dd>
                    </div>
                    <div>
                        <dt>Cargo</dt>
                        <dd>{{ $empleado->nombreCargo() }}</dd>
                    </div>
                    <div>
                        <dt>Salario</dt>
                        <dd>${{ number_format((float) $empleado->salario_base, 2) }}</dd>
                    </div>
                    <div>
                        <dt>Ventas quincena</dt>
                        <dd>${{ number_format($venta, 2) }}</dd>
                        <div class="muted" style="font-size:.68rem;font-weight:600;">{{ $quincenaVentas['inicio']->format('d/m') }} – hoy</div>
                    </div>
                </dl>
            </a>
        @empty
            <p class="muted" style="grid-column:1/-1;text-align:center;padding:24px;">No hay personas en la tabla clientes.</p>
        @endforelse
    </div>
    {{ $empleados->links() }}
</div>
<style>
.emp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; margin-top: 18px; }
.emp-card { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; text-decoration: none; color: inherit; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
.emp-card:hover { border-color: #99f6e4; box-shadow: 0 6px 18px rgba(15,118,110,.08); }
.emp-card-top { display: flex; align-items: center; gap: 12px; }
.emp-foto { width: 56px; height: 56px; border-radius: 14px; flex: none; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #134e4a, #0f766e); color: #fff; font-weight: 800; letter-spacing: .4px; }
.emp-id { min-width: 0; flex: 1; }
.emp-id strong { display: block; line-height: 1.2; }
.emp-datos { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; margin: 14px 0 0; }
.emp-datos div { margin: 0; }
.emp-datos dt { margin: 0; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
.emp-datos dd { margin: 2px 0 0; font-weight: 700; color: #0f172a; }
</style>
@endsection
