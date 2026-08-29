@extends('layouts.app')

@section('title', 'Adelantos')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Adelantos de sueldo</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Busca a la persona, cárgale el abono y descarga el TXT del día.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Acumulado</span><strong>${{ number_format($kpis['acumulado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente</span><strong>${{ number_format($kpis['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Esta quincena</span><strong>${{ number_format($kpis['esta_quincena'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Del día</span><strong>${{ number_format($totalDia, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Personas hoy</span><strong>{{ $delDia->pluck('empleado_id')->unique()->count() }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Fecha del día</label>
            <input type="date" name="fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar personal</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Buscar</button>
            <a class="btn" href="{{ route('nomina.adelantos.txt', ['fecha' => $fecha]) }}">Descargar TXT</a>
        </div>
    </form>
    <p class="muted" style="margin-top:8px;">Tasa BCV hoy: <strong>{{ number_format($tasaBcv, 2) }}</strong>. El TXT usa el formato del banco, un archivo por fecha.</p>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Cargar adelanto</h3>
        @if($q === '')
            <p class="muted" style="margin-bottom:0;">Escribe el nombre o la cédula y pulsa Buscar.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Sede</th>
                        <th>Monto</th>
                        <th>Motivo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultados as $empleado)
                        <tr>
                            <td>
                                <strong>{{ $empleado->nombre() }}</strong>
                                <div class="muted" style="font-size:.75rem;">{{ $empleado->nombreCargo() }}</div>
                            </td>
                            <td>{{ $empleado->cedula() ?: '—' }}</td>
                            <td>{{ $empleado->nombreSede() }}</td>
                            <td colspan="3">
                                <form method="POST" action="{{ route('nomina.adelantos.store') }}" class="nomina-inline-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <input type="number" step="0.01" min="0.01" name="monto" placeholder="Monto" required style="width:120px;">
                                    <input name="motivo" placeholder="Motivo (opcional)" style="min-width:180px;flex:1;">
                                    <button class="btn primary" type="submit">Registrar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Ningún activo coincide con “{{ $q }}”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <h3>Adelantos del {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($delDia as $abono)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $abono->empleado, 'tab' => 'abonos']) }}">
                                {{ $abono->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $abono->empleado?->cedula() ?: '—' }}</td>
                        <td>${{ number_format($abono->monto, 2) }}</td>
                        <td>{{ $abono->estado }}</td>
                        <td>{{ $abono->creador?->name ?: '—' }}</td>
                        <td>{{ $abono->motivo ?: '—' }}</td>
                        <td>
                            @if($abono->isPendiente())
                                <form method="POST" action="{{ route('nomina.abonos_sueldo.cancelar', $abono) }}" onsubmit="return confirm('¿Cancelar este adelanto? El historial se conserva.')">
                                    @csrf
                                    <input type="hidden" name="origen" value="adelantos">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Nadie pidió adelanto en esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
