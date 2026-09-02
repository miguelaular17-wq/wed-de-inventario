@extends('layouts.app')

@section('title', 'Deducciones y bonos')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Deducciones y bonificaciones</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Busca a la persona y carga el descuento o el bono. Se aplica al calcular esa quincena.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Deducciones quincena</span><strong>${{ number_format($kpis['deducciones'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Bonos quincena</span><strong>${{ number_format($kpis['bonos'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente</span><strong>${{ number_format($kpis['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Del día</span><strong>${{ number_format($totalDia, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Personas hoy</span><strong>{{ $delDia->pluck('empleado_id')->unique()->count() }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;" id="ajustes-filtro">
        <div class="field">
            <label for="ajustes-fecha">Fecha del día</label>
            <input type="date" name="fecha" id="ajustes-fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar personal</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Cargar ajuste</h3>
        @if($q === '')
            <p class="muted" style="margin-bottom:0;">Escribe el nombre o la cédula y pulsa Buscar.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Sede</th>
                        <th>Ajuste</th>
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
                            <td>
                                <form method="POST" action="{{ route('nomina.ajustes.escritorio') }}" class="nomina-inline-form ajuste-registro-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                    <input type="hidden" name="fecha" class="ajuste-fecha-campo" value="{{ $fecha }}">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <select name="tipo" required>
                                        <option value="DEDUCCION">Deducción</option>
                                        <option value="BONIFICACION">Bonificación</option>
                                    </select>
                                    <select name="destino" required>
                                        <option value="NOMINA">Nómina</option>
                                        @if($empleado->generaComision())
                                            <option value="COMISION">Comisión</option>
                                        @endif
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="monto" placeholder="Monto" required style="width:110px;">
                                    <input name="motivo" placeholder="Motivo" required style="min-width:160px;flex:1;">
                                    <button class="btn primary" type="submit">Registrar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Ningún activo coincide con “{{ $q }}”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <h3>Cargadas el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
                    <th>Tipo</th>
                    <th>Destino</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($delDia as $item)
                    <tr>
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $item->empleado_id, 'tab' => 'ajustes']) }}">
                                {{ $item->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $item->empleado?->cedula() ?: '—' }}</td>
                        <td>{{ $item->etiquetaTipo() }}</td>
                        <td>{{ $item->etiquetaDestino() }}</td>
                        <td>${{ number_format($item->monto, 2) }}</td>
                        <td>{{ $item->estado }}</td>
                        <td>{{ $item->creador?->name ?: '—' }}</td>
                        <td>{{ $item->motivo ?: '—' }}</td>
                        <td>
                            @if($item->isPendiente())
                                <form method="POST" action="{{ route('nomina.ajustes.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este ajuste? El historial se conserva.')">
                                    @csrf
                                    <input type="hidden" name="origen" value="ajustes">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Nadie tiene deducciones ni bonos en esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fechaInput = document.getElementById('ajustes-fecha');
    if (!fechaInput) return;

    function fechaSeleccionada() {
        return fechaInput.value || '';
    }

    function sincronizarFechaRegistro() {
        const valor = fechaSeleccionada();
        document.querySelectorAll('.ajuste-fecha-campo').forEach(function (campo) {
            campo.value = valor;
        });
    }

    fechaInput.addEventListener('change', sincronizarFechaRegistro);
    sincronizarFechaRegistro();

    document.querySelectorAll('.ajuste-registro-form').forEach(function (form) {
        form.addEventListener('submit', sincronizarFechaRegistro);
    });
});
</script>
@endpush
