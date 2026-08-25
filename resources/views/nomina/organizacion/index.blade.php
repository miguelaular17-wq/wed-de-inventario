@extends('layouts.app')

@section('title', 'Organigrama')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Estructura organizacional</h1>
            <p class="muted" style="margin:4px 0 0;">En cada tienda el personal es de los supervisores de sede. La gerente supervisa a esos supervisores, no al piso.</p>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <div class="field">
            <label>Sede / área</label>
            <select name="sede_id">
                @include('nomina.partials.sede-options', ['unidades' => $sedes, 'selected' => $filters['sedeId'] ?? '', 'placeholder' => 'Todas'])
            </select>
        </div>
        <div class="field">
            <label>Supervisor</label>
            <select name="supervisor_id">
                <option value="">Todos</option>
                @foreach($supervisores as $sup)
                    <option value="{{ $sup->id }}" @selected($filters['supervisorId'] == $sup->id)>{{ $sup->nombre() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Cargo</label>
            <select name="cargo_id">
                <option value="">Todos</option>
                @foreach($cargos as $cargo)
                    <option value="{{ $cargo->id }}" @selected($filters['cargoId'] == $cargo->id)>{{ $cargo->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="display:flex; align-items:flex-end; gap:8px;">
            <button class="btn primary" type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ route('nomina.organizacion') }}">Limpiar</a>
        </div>
    </form>

    @foreach($arbol as $nodo)
        <div class="nomina-org-sede">
            <h2>{{ $nodo['sede']->etiquetaTipo() }}: {{ $nodo['sede']->nombre }} <span class="muted">{{ $nodo['sede']->codigo }}</span></h2>
            @if($nodo['gerentes']->isNotEmpty())
                <div class="nomina-org-gerente">
                    <span class="muted">Gerente</span>
                    <div class="nomina-org-people">
                        @foreach($nodo['gerentes'] as $gerente)
                            <a href="{{ route('nomina.empleados.show', $gerente) }}">{{ $gerente->nombre() }}</a>
                        @endforeach
                    </div>
                    <p class="muted" style="margin:6px 0 0; font-size:.78rem;">Supervisa a los supervisores de esta sede.</p>
                </div>
            @endif

            @if($nodo['sede']->isArea())
                @forelse($nodo['grupos'] as $grupo)
                    <div class="nomina-org-sup">
                        <strong>Supervisor de área: <a href="{{ route('nomina.empleados.show', $grupo['supervisor']) }}">{{ $grupo['supervisor']->nombre() }}</a></strong>
                        <span class="muted"> · {{ $grupo['supervisor']->nombreCargo() }}</span>
                        <ul>
                            @forelse($grupo['empleados'] as $emp)
                                <li><a href="{{ route('nomina.empleados.show', $emp) }}">{{ $emp->nombre() }}</a> · {{ $emp->nombreCargo() }}</li>
                            @empty
                                <li class="muted">Sin personal asignado</li>
                            @endforelse
                        </ul>
                    </div>
                @empty
                    @if($nodo['gerentes']->isEmpty())
                        <p class="muted">No hay supervisores en esta área.</p>
                    @endif
                @endforelse
            @else
                <div class="nomina-org-sup">
                    <strong>Supervisores de sede</strong>
                    @if($nodo['supervisores']->isNotEmpty())
                        <div class="nomina-org-people">
                            @foreach($nodo['supervisores'] as $sup)
                                <a href="{{ route('nomina.empleados.show', $sup) }}">{{ $sup->nombre() }}</a>
                                <span class="muted">{{ $sup->nombreCargo() }}</span>
                            @endforeach
                        </div>
                        <ul>
                            @forelse($nodo['equipo'] as $emp)
                                <li><a href="{{ route('nomina.empleados.show', $emp) }}">{{ $emp->nombre() }}</a> · {{ $emp->nombreCargo() }}</li>
                            @empty
                                <li class="muted">Sin personal de piso</li>
                            @endforelse
                        </ul>
                    @else
                        <p class="muted" style="margin:8px 0 0;">No hay supervisores en esta sede.</p>
                        @if($nodo['equipo']->isNotEmpty())
                            <ul>
                                @foreach($nodo['equipo'] as $emp)
                                    <li><a href="{{ route('nomina.empleados.show', $emp) }}">{{ $emp->nombre() }}</a> · {{ $emp->nombreCargo() }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            @endif

            @if($nodo['sin_supervisor']->isNotEmpty())
                <div class="nomina-org-sup">
                    <strong>Sin supervisor</strong>
                    <ul>
                        @foreach($nodo['sin_supervisor'] as $emp)
                            <li><a href="{{ route('nomina.empleados.show', $emp) }}">{{ $emp->nombre() }}</a> · {{ $emp->nombreCargo() }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
