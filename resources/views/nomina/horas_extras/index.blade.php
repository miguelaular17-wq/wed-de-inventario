@extends('layouts.app')

@section('title', 'Horas extras')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Horas extras por sede</h1>
            <p class="muted" style="margin:4px 0 0;">
                Aplica la misma cantidad a varios trabajadores o supervisores de una sede.
                Trabajador ${{ number_format($valorHoraTrabajador, 2) }} · supervisor ${{ number_format($valorHoraSupervisor, 2) }}.
            </p>
        </div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Sede / área</label>
            <select name="sede_id">
                @include('nomina.partials.sede-options', ['unidades' => $sedes, 'selected' => $sedeId ?? '', 'placeholder' => 'Elige una sede'])
            </select>
        </div>
        <div class="field">
            <label>Quiénes</label>
            <select name="alcance">
                <option value="TODOS" @selected($alcance === 'TODOS')>Todos</option>
                <option value="TRABAJADORES" @selected($alcance === 'TRABAJADORES')>Solo trabajadores</option>
                <option value="SUPERVISORES" @selected($alcance === 'SUPERVISORES')>Solo supervisores</option>
            </select>
        </div>
        <div class="field">
            <label>Fecha</label>
            <input type="date" name="fecha" value="{{ $fecha }}">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Ver personal</button>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Aplicar extras</h3>
        @if(! $sedeId)
            <p class="muted" style="margin-bottom:0;">Elige la sede y pulsa Ver personal.</p>
        @elseif($candidatos->isEmpty())
            <p class="muted" style="margin-bottom:0;">No hay activos en esa sede con el filtro elegido.</p>
        @else
            <form method="POST" action="{{ route('nomina.horas_extras.masivas') }}" id="form-extras-sede">
                @csrf
                <input type="hidden" name="sede_id" value="{{ $sedeId }}">
                <input type="hidden" name="alcance" value="{{ $alcance }}">
                <div class="nomina-form-grid" style="margin-bottom:12px;">
                    <div class="field">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="{{ $fecha }}" required>
                    </div>
                    <div class="field">
                        <label>Tipo</label>
                        <select name="unidad">
                            <option value="HORAS">Horas</option>
                            <option value="DIAS">Días (salario ÷ 30)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Cantidad</label>
                        <input type="number" step="0.25" min="0.25" name="horas" required>
                    </div>
                    <div class="field field-wide">
                        <label>Motivo</label>
                        <input name="motivo" placeholder="Opcional">
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" checked onclick="document.querySelectorAll('.extra-persona').forEach(el => el.checked = this.checked)">
                            </th>
                            <th>Empleado</th>
                            <th>Cargo</th>
                            <th>Tarifa hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($candidatos as $empleado)
                            <tr>
                                <td>
                                    <input class="extra-persona" type="checkbox" name="empleado_ids[]" value="{{ $empleado->id }}" checked>
                                </td>
                                <td>
                                    <strong>{{ $empleado->nombre() }}</strong>
                                    <div class="muted" style="font-size:.75rem;">{{ $empleado->cedula() ?: '—' }}</div>
                                </td>
                                <td>{{ $empleado->nombreCargo() }}</td>
                                <td>
                                    {{ $empleado->hora_supervisor ? 'Supervisor' : 'Trabajador' }}
                                    ${{ number_format($empleado->hora_supervisor ? $valorHoraSupervisor : $valorHoraTrabajador, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:12px;">
                    <button class="btn primary" type="submit">Aplicar a los seleccionados</button>
                </div>
            </form>
        @endif
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <h3>Registradas el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}{{ $sedeId ? ' en esta sede' : '' }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th>Cantidad</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($delDia as $extra)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $extra->empleado_id, 'tab' => 'nomina']) }}">
                                {{ $extra->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $extra->empleado?->nombreSede() ?? '—' }}</td>
                        <td>{{ number_format($extra->horas, 2) }}</td>
                        <td>{{ $extra->etiquetaUnidad() }}</td>
                        <td>${{ number_format($extra->valor_unitario, 2) }}</td>
                        <td>${{ number_format($extra->monto, 2) }}</td>
                        <td>{{ $extra->estado }}</td>
                        <td>{{ $extra->motivo ?: '—' }}</td>
                        <td>
                            @if($extra->isPendiente())
                                <form method="POST" action="{{ route('nomina.horas_extras.cancelar', $extra) }}" onsubmit="return confirm('¿Cancelar estas horas extras?')">
                                    @csrf
                                    <input type="hidden" name="origen" value="horas_extras">
                                    <input type="hidden" name="sede_id" value="{{ $sedeId }}">
                                    <input type="hidden" name="alcance" value="{{ $alcance }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nadie tiene extras en esta fecha{{ $sedeId ? ' para esa sede' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
