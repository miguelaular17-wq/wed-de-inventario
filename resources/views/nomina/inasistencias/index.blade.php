@extends('layouts.app')

@section('title', 'Inasistencias')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Inasistencias por sede</h1>
            <p class="muted" style="margin:4px 0 0;">
                Marca quiénes faltaron un día. Cada día se descuenta del sueldo de esa quincena (salario mensual ÷ 30).
                Quien ya tiene una inasistencia ese día no se puede marcar otra vez.
            </p>
        </div>
    </div>

    @if($errors->any())<div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>@endif

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Sede / área</label>
            <select name="sede_id">
                @include('nomina.partials.sede-options', ['unidades' => $sedes, 'selected' => $sedeId ?? '', 'placeholder' => 'Elige una sede'])
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
    <div style="margin-top:10px;">
        @include('nomina.partials.excel-quincena', ['excelRoute' => route('nomina.inasistencias.excel'), 'fecha' => $fecha])
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Marcar inasistencia</h3>
        @if(! $sedeId)
            <p class="muted" style="margin-bottom:0;">Elige la sede y pulsa Ver personal.</p>
        @elseif($candidatos->isEmpty())
            <p class="muted" style="margin-bottom:0;">No hay personal activo en esa sede.</p>
        @else
            <form method="POST" action="{{ route('nomina.inasistencias.masivas') }}">
                @csrf
                <input type="hidden" name="sede_id" value="{{ $sedeId }}">
                <div class="nomina-form-grid" style="margin-bottom:12px;">
                    <div class="field">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="{{ $fecha }}" required>
                    </div>
                    <div class="field">
                        <label>Días</label>
                        <input type="number" step="0.5" min="0.5" max="31" name="cantidad" value="1" required>
                    </div>
                    <div class="field field-wide">
                        <label>Motivo</label>
                        <input name="motivo" maxlength="255" placeholder="Opcional (ej. sin justificar, reposo, permiso no remunerado)">
                    </div>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" onclick="document.querySelectorAll('.falta-persona:not(:disabled)').forEach(el => el.checked = this.checked)" title="Marcar todos">
                            </th>
                            <th>Empleado</th>
                            <th>Cargo</th>
                            <th>Valor día</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($candidatos as $empleado)
                            <tr style="{{ $empleado->ya_falto ? 'opacity:.55;' : '' }}">
                                <td>
                                    <input class="falta-persona" type="checkbox" name="empleado_ids[]" value="{{ $empleado->id }}" @disabled($empleado->ya_falto || $empleado->valor_dia <= 0)>
                                </td>
                                <td>
                                    <strong>{{ $empleado->nombre() }}</strong>
                                    <div class="muted" style="font-size:.75rem;">{{ $empleado->cedula() ?: '—' }}</div>
                                </td>
                                <td>{{ $empleado->nombreCargo() }}</td>
                                <td>
                                    @if($empleado->ya_falto)
                                        <span class="muted">Ya marcado este día</span>
                                    @elseif($empleado->valor_dia <= 0)
                                        <span class="muted">Sin salario base</span>
                                    @else
                                        ${{ number_format($empleado->valor_dia, 2) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="margin-top:12px;">
                    <button class="btn primary" type="submit">Registrar a los marcados</button>
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
                    <th>Días</th>
                    <th>Valor día</th>
                    <th>Descuento</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th>Registró</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($delDia as $falta)
                    <tr style="{{ $falta->estado === 'CANCELADO' ? 'opacity:.55;' : '' }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $falta->empleado_id, 'tab' => 'nomina']) }}">
                                {{ $falta->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $falta->empleado?->nombreSede() ?? '—' }}</td>
                        <td>{{ number_format($falta->cantidad, 2) }}</td>
                        <td>${{ number_format($falta->valor_unitario, 2) }}</td>
                        <td>${{ number_format($falta->monto, 2) }}</td>
                        <td>{{ $falta->estado }}</td>
                        <td>{{ $falta->motivo ?: '—' }}</td>
                        <td>{{ $falta->creador?->name ?? '—' }}</td>
                        <td>
                            @if($falta->isPendiente())
                                <form method="POST" action="{{ route('nomina.inasistencias.cancelar', $falta) }}" onsubmit="return confirm('¿Cancelar esta inasistencia? Ya no se descontará.')">
                                    @csrf
                                    <input type="hidden" name="origen" value="inasistencias">
                                    <input type="hidden" name="sede_id" value="{{ $sedeId }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nadie tiene inasistencias en esta fecha{{ $sedeId ? ' en esa sede' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
