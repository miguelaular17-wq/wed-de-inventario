@extends('layouts.app')

@section('title', 'Comisiones de marca')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Comisiones de marca</h1>
            <p class="muted" style="margin:4px 0 0;">Samsung y Honor. El monto se carga en bolívares y se convierte con la tasa de los egresos de flujo de caja de ese día.</p>
        </div>
        <a class="btn" href="{{ route('nomina.comisiones_marca.reporte', request()->query()) }}">Reporte PDF</a>
    </div>

    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('nomina.comisiones_marca.store') }}" class="nomina-card" style="margin-top:16px;">
        @csrf
        <h3 style="margin-top:0;">Registrar</h3>
        <div class="filter-bar">
            <div class="field field-wide">
                <label>Persona</label>
                <select name="nomina_empleado_id" required>
                    <option value="">Selecciona</option>
                    @foreach($empleados as $empleado)
                        <option value="{{ $empleado->id }}" @selected(old('nomina_empleado_id') == $empleado->id)>
                            {{ $empleado->nombre() }}@if($empleado->cedula()) — {{ $empleado->cedula() }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Marca</label>
                <select name="marca" required>
                    @foreach($marcas as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected(old('marca') === $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha" id="cm-fecha" value="{{ old('fecha', now()->toDateString()) }}" required>
            </div>
            <div class="field">
                <label>Monto Bs</label>
                <input type="number" name="monto_bs" id="cm-bs" step="0.01" min="0.01" value="{{ old('monto_bs') }}" required>
            </div>
            <div class="field field-wide">
                <label>Nota</label>
                <input type="text" name="nota" maxlength="255" value="{{ old('nota') }}">
            </div>
        </div>
        <p id="cm-tasa" class="muted" style="margin:8px 0 0;">Buscando la tasa del día…</p>
        <button class="btn primary" type="submit" style="margin-top:12px;">Guardar</button>
    </form>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        </div>
        <div class="field">
            <label>Marca</label>
            <select name="marca_filtro">
                <option value="">Todas</option>
                @foreach($marcas as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected($filtros['marca'] === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field-wide">
            <label>Persona</label>
            <select name="empleado_id">
                <option value="">Todas</option>
                @foreach($empleados as $empleado)
                    <option value="{{ $empleado->id }}" @selected($filtros['empleado_id'] == $empleado->id)>{{ $empleado->nombre() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Ver reporte</button>
        </div>
    </form>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Total Bs</span><strong>Bs {{ number_format($totales['bs'], 2, ',', '.') }}</strong></div>
        <div class="nomina-kpi"><span>Total USD</span><strong>${{ number_format($totales['usd'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Samsung</span><strong>${{ number_format($totales['por_marca']['SAMSUNG']['usd'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Honor</span><strong>${{ number_format($totales['por_marca']['HONOR']['usd'], 2) }}</strong></div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Persona</th>
                    <th>Marca</th>
                    <th>Bs</th>
                    <th>Tasa</th>
                    <th>USD</th>
                    <th>Nota</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $fila)
                    <tr>
                        <td>{{ $fila->fecha->format('d/m/Y') }}</td>
                        <td>{{ $fila->empleado?->nombre() ?? '—' }}</td>
                        <td>{{ $fila->nombreMarca() }}</td>
                        <td>Bs {{ number_format($fila->monto_bs, 2, ',', '.') }}</td>
                        <td>{{ number_format($fila->tasa, 4, ',', '.') }}</td>
                        <td><strong>${{ number_format($fila->monto_usd, 2) }}</strong></td>
                        <td>{{ $fila->nota ?: '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('nomina.comisiones_marca.destroy', $fila) }}" onsubmit="return confirm('¿Eliminar esta comisión?');">
                                @csrf
                                @method('DELETE')
                                @foreach(request()->query() as $clave => $valor)
                                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                                @endforeach
                                <button class="btn" type="submit">Quitar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No hay comisiones en este rango.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    const fecha = document.getElementById('cm-fecha');
    const monto = document.getElementById('cm-bs');
    const caja = document.getElementById('cm-tasa');
    const url = @json(route('nomina.comisiones_marca.tasa'));
    let tasa = null;

    function pintar() {
        const bs = parseFloat(monto.value || '0') || 0;
        if (!fecha.value) {
            caja.textContent = 'Elige la fecha para tomar la tasa.';
            return;
        }
        if (tasa === null) {
            caja.textContent = 'Ese día no tiene tasa de egreso en flujo de caja.';
            return;
        }
        const usd = bs > 0 ? (bs / tasa) : 0;
        caja.textContent = 'Tasa del día: ' + tasa.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4})
            + (bs > 0 ? ' · USD ' + usd.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '');
    }

    async function cargar() {
        if (!fecha.value) return;
        caja.textContent = 'Buscando la tasa del día…';
        const res = await fetch(url + '?fecha=' + encodeURIComponent(fecha.value), {headers: {'Accept': 'application/json'}});
        const data = await res.json();
        tasa = data.tasa === null ? null : Number(data.tasa);
        pintar();
    }

    fecha.addEventListener('change', cargar);
    monto.addEventListener('input', pintar);
    cargar();
})();
</script>
@endsection
