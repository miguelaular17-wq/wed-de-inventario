@extends('layouts.app')

@section('title', 'Empleados')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Empleados</h1>
        </div>
        <a href="{{ route('nomina.empleados.create') }}" class="btn primary">Nuevo empleado</a>
    </div>

    @if(($importados ?? 0) > 0)
        <div class="success">Se incorporaron {{ $importados }} personas desde la tabla clientes. Completa sede, cargo y salario en cada ficha.</div>
    @endif

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Total prestado</span><strong>${{ number_format($kpis['total_prestado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente por cobrar</span><strong>${{ number_format($kpis['total_pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Cobrado este mes</span><strong>${{ number_format($kpis['cobrado_mes'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Préstamos activos</span><strong>{{ $kpis['activos'] }}</strong></div>
        <div class="nomina-kpi warn"><span>Con cuotas vencidas</span><strong>{{ $kpis['vencidos'] }}</strong></div>
    </div>
    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Adelantos acumulado</span><strong>${{ number_format($kpisAdelantos['acumulado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Adelantos pendientes</span><strong>${{ number_format($kpisAdelantos['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Esta quincena</span><strong>${{ number_format($kpisAdelantos['esta_quincena'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Descontado en nómina</span><strong>${{ number_format($kpisAdelantos['descontado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Adelantos registrados</span><strong>{{ $kpisAdelantos['cantidad'] }}</strong></div>
    </div>

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

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Empresa</th>
                    <th>Sede</th>
                    <th>Cargo</th>
                    <th>Supervisor</th>
                    <th>Salario mensual</th>
                    <th>Adelantos</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($empleados as $empleado)
                    <tr>
                        <td>
                            <strong>{{ $empleado->nombre() }}</strong>
                            <div class="muted" style="font-size:.82rem;">{{ $empleado->cedula() }}</div>
                        </td>
                        <td>{{ $empleado->codigoEmpresa() }}</td>
                        <td>{{ $empleado->nombreSede() }}</td>
                        <td>{{ $empleado->nombreCargo() }}</td>
                        <td>{{ $empleado->nombreSupervisor() }}</td>
                        <td>${{ number_format($empleado->salario_base, 2) }}</td>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'abonos']) }}">
                                ${{ number_format((float) ($empleado->adelantos_acumulado ?? 0), 2) }}
                            </a>
                        </td>
                        <td><span class="tag {{ $empleado->isActivo() ? 'ok' : 'no' }}">{{ $empleado->estado }}</span></td>
                        <td style="text-align:right;">
                            <a href="{{ route('nomina.empleados.show', $empleado) }}">Ficha</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="text-align:center; padding:24px;">No hay personas en la tabla clientes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $empleados->links() }}
</div>
@endsection
