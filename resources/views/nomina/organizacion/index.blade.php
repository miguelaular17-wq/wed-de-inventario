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

    @forelse($arbol as $nodo)
        @php
            $esArea = $nodo['sede']->isArea();
            $nSup = $esArea ? $nodo['grupos']->count() : $nodo['supervisores']->count();
            $nPiso = $nodo['equipo']->count()
                + $nodo['grupos']->sum(fn ($g) => $g['empleados']->count())
                + $nodo['sin_supervisor']->count();
            $porCargo = $nodo['equipo']
                ->groupBy(fn ($e) => $e->nombreCargo() ?: 'Sin cargo')
                ->sortByDesc(fn ($grupo) => $grupo->count());
        @endphp
        <section class="org-sede">
            <header class="org-sede-head">
                <div>
                    <span class="org-kicker">{{ $nodo['sede']->etiquetaTipo() }}</span>
                    <h2>{{ $nodo['sede']->nombre }} <span>{{ $nodo['sede']->codigo }}</span></h2>
                </div>
                <div class="org-counts">
                    @if($nodo['gerentes']->isNotEmpty())
                        <span>{{ $nodo['gerentes']->count() }} {{ $nodo['gerentes']->count() === 1 ? 'gerente' : 'gerentes' }}</span>
                    @endif
                    <span>{{ $nSup }} {{ $nSup === 1 ? 'supervisor' : 'supervisores' }}</span>
                    <span>{{ $nPiso }} en piso</span>
                </div>
            </header>

            <div class="org-chart">
                @if($nodo['gerentes']->isNotEmpty())
                    <div class="org-row">
                        @foreach($nodo['gerentes'] as $gerente)
                            @include('nomina.organizacion._persona', ['empleado' => $gerente, 'tono' => 'gerente'])
                        @endforeach
                    </div>
                @endif

                @if($esArea)
                    @if($nodo['grupos']->isNotEmpty())
                        <div class="org-branch">
                            <div class="org-cols">
                                @foreach($nodo['grupos'] as $grupo)
                                    <div class="org-col">
                                        @include('nomina.organizacion._persona', ['empleado' => $grupo['supervisor'], 'tono' => 'sup'])
                                        <div class="org-col-team">
                                            @forelse($grupo['empleados'] as $emp)
                                                @include('nomina.organizacion._persona', ['empleado' => $emp, 'tono' => 'piso', 'compacto' => true])
                                            @empty
                                                <p class="muted org-empty">Sin personal asignado</p>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif($nodo['gerentes']->isEmpty())
                        <p class="muted org-empty">No hay supervisores en esta área.</p>
                    @endif
                @else
                    @if($nodo['supervisores']->isNotEmpty())
                        <div class="org-branch">
                            <div class="org-row">
                                @foreach($nodo['supervisores'] as $sup)
                                    @include('nomina.organizacion._persona', ['empleado' => $sup, 'tono' => 'sup'])
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="muted org-empty">No hay supervisores en esta sede.</p>
                    @endif

                    @if($porCargo->isNotEmpty())
                        <div class="org-piso">
                            <h3>Personal de piso</h3>
                            @foreach($porCargo as $cargo => $personas)
                                <div class="org-cargo">
                                    <div class="org-cargo-head">
                                        <strong>{{ $cargo }}</strong>
                                        <span>{{ $personas->count() }}</span>
                                    </div>
                                    <div class="org-chips">
                                        @foreach($personas as $emp)
                                            @include('nomina.organizacion._persona', ['empleado' => $emp, 'tono' => 'piso', 'compacto' => true])
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif($nodo['supervisores']->isNotEmpty())
                        <p class="muted org-empty">Sin personal de piso</p>
                    @endif
                @endif

                @if($nodo['sin_supervisor']->isNotEmpty())
                    <div class="org-piso">
                        <h3>Sin supervisor</h3>
                        <div class="org-chips">
                            @foreach($nodo['sin_supervisor'] as $emp)
                                @include('nomina.organizacion._persona', ['empleado' => $emp, 'tono' => 'piso', 'compacto' => true])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @empty
        <p class="muted" style="margin-top:18px;">No hay sedes para esos filtros.</p>
    @endforelse
</div>
@endsection
