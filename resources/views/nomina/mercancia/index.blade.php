@extends('layouts.app')

@section('title', 'Descuento por mercancía')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Descuento por mercancía</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Registra el descuento con motivo y elige si se aplica en nómina o en comisión.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Esta quincena</span><strong>${{ number_format($kpis['esta_quincena'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente</span><strong>${{ number_format($kpis['pendiente'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Del día</span><strong>${{ number_format($kpis['del_dia'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Personas hoy</span><strong>{{ $kpis['personas_hoy'] }}</strong></div>
        <div class="nomina-kpi"><span>Registros quincena</span><strong>{{ $kpis['cantidad'] }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;" id="mercancia-filtro">
        <div class="field">
            <label for="mercancia-fecha">Fecha del día</label>
            <input type="date" name="fecha" id="mercancia-fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar personal</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;">
            <button class="btn primary" type="submit">Buscar</button>
        </div>
    </form>
    <div style="margin-top:10px;">
        @include('nomina.partials.excel-quincena', ['excelRoute' => route('nomina.mercancia.excel'), 'fecha' => $fecha])
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Registrar descuento</h3>
        @if($q === '')
            <p class="muted" style="margin-bottom:0;">Escribe el nombre o la cédula y pulsa Buscar.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Sede</th>
                        <th>Descuento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultados as $empleado)
                        <tr>
                            <td>
                                <a href="{{ route('nomina.empleados.show', $empleado) }}">
                                    <strong>{{ $empleado->nombre() }}</strong>
                                </a>
                                <div class="muted" style="font-size:.75rem;">
                                    {{ $empleado->nombreCargo() }}
                                    @if($empleado->generaComision())
                                        · con comisión
                                    @else
                                        · sin comisión (solo nómina)
                                    @endif
                                </div>
                            </td>
                            <td>{{ $empleado->cedula() ?: '—' }}</td>
                            <td>{{ $empleado->nombreSede() }}</td>
                            <td>
                                <form method="POST" action="{{ route('nomina.mercancia.escritorio') }}" class="nomina-inline-form mercancia-registro-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                    <input type="hidden" name="fecha" class="mercancia-fecha-campo" value="{{ $fecha }}">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <select name="destino" required>
                                        <option value="NOMINA">Nómina</option>
                                        @if($empleado->generaComision())
                                            <option value="COMISION">Comisión</option>
                                        @endif
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="monto" placeholder="Monto" required style="width:110px;">
                                    <input name="motivo" placeholder="Motivo de mercancía" required style="min-width:180px;flex:1;">
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
        <h3>Cargados el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Cédula</th>
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
                            <a href="{{ route('nomina.empleados.show', $item->empleado_id) }}">
                                {{ $item->empleado?->nombre() ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $item->empleado?->cedula() ?: '—' }}</td>
                        <td>{{ $item->etiquetaDestino() }}</td>
                        <td>${{ number_format($item->monto, 2) }}</td>
                        <td>{{ $item->estado }}</td>
                        <td>{{ $item->creador?->name ?: '—' }}</td>
                        <td>{{ $item->motivo ?: '—' }}</td>
                        <td>
                            @if($item->estado === 'PENDIENTE')
                                <form method="POST" action="{{ route('nomina.mercancia.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este descuento de mercancía?')">
                                    @csrf
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin descuentos de mercancía en esta fecha.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('mercancia-fecha')?.addEventListener('change', function () {
    document.querySelectorAll('.mercancia-fecha-campo').forEach(function (el) {
        el.value = document.getElementById('mercancia-fecha').value;
    });
});
</script>
@endpush
