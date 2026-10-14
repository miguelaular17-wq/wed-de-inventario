@extends('layouts.app')

@section('title', 'Retenciones')

@section('content')
@php
    $dinero = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $periodo = $resumen['anio'] ? 'Año '.$resumen['anio'] : 'Todos los años';
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Retenciones</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $periodo }}, acumulado al {{ $resumen['hasta'] }}.
                Es la retención de comisión de las quincenas ya calculadas.
            </p>
        </div>
        <form method="get" action="{{ route('nomina.retenciones.index') }}" style="display:flex;gap:8px;align-items:center;margin:0;">
            @if($sede !== '')
                <input type="hidden" name="sede" value="{{ $sede }}">
            @endif
            <label for="ret-anio" class="muted" style="font-size:.8rem;font-weight:600;">Año</label>
            <select id="ret-anio" name="anio" onchange="this.form.submit()" style="padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;">
                @foreach($resumen['anios'] as $anio)
                    <option value="{{ $anio }}" @selected($anioParam === (string) $anio)>{{ $anio }}</option>
                @endforeach
                <option value="todo" @selected($anioParam === 'todo')>Todos</option>
            </select>
        </form>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;">
        <div style="padding:12px 16px;border:1px solid var(--border);border-radius:12px;min-width:180px;">
            <div class="muted" style="font-size:.75rem;">Total retenido</div>
            <strong style="font-size:1.25rem;">{{ $dinero($resumen['total']) }}</strong>
        </div>
        <div style="padding:12px 16px;border:1px solid var(--border);border-radius:12px;min-width:140px;">
            <div class="muted" style="font-size:.75rem;">Sedes</div>
            <strong style="font-size:1.25rem;">{{ count($resumen['sedes']) }}</strong>
        </div>
        <div style="padding:12px 16px;border:1px solid var(--border);border-radius:12px;min-width:140px;">
            <div class="muted" style="font-size:.75rem;">Trabajadores</div>
            <strong style="font-size:1.25rem;">{{ $trabajadoresTotal }}</strong>
        </div>
    </div>

    <h2 style="margin:22px 0 8px;font-size:1.05rem;">Por sede</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th style="text-align:right;">Trabajadores</th>
                    <th style="text-align:right;">Quincenas</th>
                    <th style="text-align:right;">Retención</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resumen['sedes'] as $fila)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.retenciones.index', ['anio' => $anioParam, 'sede' => $fila['nombre']]) }}">
                                {{ $fila['nombre'] }}
                            </a>
                        </td>
                        <td style="text-align:right;">{{ $fila['trabajadores'] }}</td>
                        <td style="text-align:right;">{{ $fila['quincenas'] }}</td>
                        <td style="text-align:right;"><strong>{{ $dinero($fila['retencion']) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No hay retenciones en este período.</td></tr>
                @endforelse
                @if($resumen['sedes'] !== [])
                    <tr>
                        <td><strong>Total</strong></td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;"><strong>{{ $dinero($resumen['total']) }}</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin:22px 0 8px;">
        <h2 style="margin:0;font-size:1.05rem;">Por trabajador{{ $sede !== '' ? ' · '.$sede : '' }}</h2>
        @if($sede !== '')
            <a class="btn secondary" href="{{ route('nomina.retenciones.index', ['anio' => $anioParam]) }}">Ver todas las sedes</a>
        @endif
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Trabajador</th>
                    <th>Sede</th>
                    <th style="text-align:right;">Quincenas</th>
                    <th style="text-align:right;">Retención</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resumen['trabajadores'] as $fila)
                    <tr>
                        <td>
                            @if(auth()->check() && auth()->user()->canAccess('nomina'))
                                <a href="{{ route('nomina.empleados.show', ['empleado' => $fila['id'], 'tab' => 'comisiones']) }}">{{ $fila['nombre'] }}</a>
                            @else
                                {{ $fila['nombre'] }}
                            @endif
                        </td>
                        <td>{{ $fila['sede'] }}</td>
                        <td style="text-align:right;">{{ $fila['quincenas'] }}</td>
                        <td style="text-align:right;"><strong>{{ $dinero($fila['retencion']) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No hay trabajadores con retención en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
