@extends('layouts.app')

@section('title', 'Días libres')

@section('content')
@php
    $diasSemana = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Calendario de días libres</h1>
            <p class="muted" style="margin:4px 0 0;">
                @if($esRrhh)
                    RRHH: revisa por sede, modifica celdas y aprueba lo pendiente.
                @else
                    Marca <strong>L</strong> en los días libres de tu personal. RRHH aprobará el calendario.
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <span class="pill" style="background:#fef9c3;color:#854d0e;">Pendientes: {{ $pendientes }}</span>
            <span class="pill" style="background:#dcfce7;color:#166534;">Aprobados: {{ $aprobados }}</span>
            <a class="btn" href="{{ route('nomina.dias_libres.pdf', array_filter(['desde' => $desde, 'hasta' => $hasta, 'sede_id' => $sedeId])) }}">Descargar PDF</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" required>
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}" required>
        </div>
        @if($esRrhh)
            <div class="field">
                <label>Sede / área</label>
                <select name="sede_id">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" @selected((int) $sedeId === (int) $sede->id)>
                            {{ $sede->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Ver calendario</button>
        </div>
    </form>

    @if($esRrhh && $pendientes > 0)
        <form method="POST" action="{{ route('nomina.dias_libres.aprobar') }}" style="margin-top:12px;">
            @csrf
            <input type="hidden" name="desde" value="{{ $desde }}">
            <input type="hidden" name="hasta" value="{{ $hasta }}">
            @if($sedeId)
                <input type="hidden" name="sede_id" value="{{ $sedeId }}">
            @endif
            <button class="btn" type="submit" onclick="return confirm('¿Aprobar todos los días pendientes del rango visible?')">
                Aprobar pendientes del rango
            </button>
        </form>
    @endif

    <div class="nomina-card" style="margin-top:16px;overflow:auto;">
        <h3 style="margin-top:0;">Registro de días libres</h3>
        <p class="muted" style="margin-top:0;">Haz clic en una celda para marcar o quitar <strong>L</strong>.</p>

        @if($empleados->isEmpty())
            <p class="muted">
                @if($esRrhh)
                    No hay empleados activos@if($sedeId) en esa sede@endif.
                @else
                    No tienes personal a cargo vinculado en nómina.
                @endif
            </p>
        @else
            <table class="data-table dias-libres-grid" id="dias-libres-tabla">
                <thead>
                    <tr>
                        <th style="min-width:180px;position:sticky;left:0;background:#fff;z-index:2;">Trabajador</th>
                        @foreach($fechas as $f)
                            <th style="text-align:center;min-width:46px;font-size:11px;line-height:1.2;">
                                {{ $f->format('d/m') }}
                                <div class="muted" style="font-weight:400;">{{ $diasSemana[(int) $f->format('w')] }}</div>
                            </th>
                        @endforeach
                        <th style="text-align:center;background:#dcfce7;min-width:70px;">Días libres</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($empleados as $emp)
                        @php $totalLibre = 0; @endphp
                        <tr data-empleado="{{ $emp->id }}">
                            <td style="position:sticky;left:0;background:#fff;z-index:1;">
                                <strong>{{ $emp->nombre() }}</strong>
                                @if($emp->es_supervisor)
                                    <span class="pill" style="background:#dbeafe;color:#1d4ed8;margin-left:4px;">Supervisor</span>
                                @endif
                                <div class="muted" style="font-size:12px;">
                                    {{ $emp->nombreCargo() }}
                                    @if($emp->nombreSede()) · {{ $emp->nombreSede() }}@endif
                                </div>
                            </td>
                            @foreach($fechas as $f)
                                @php
                                    $key = $emp->id.'|'.$f->toDateString();
                                    $dia = $mapa[$key] ?? null;
                                    $libre = (bool) $dia;
                                    if ($libre) { $totalLibre++; }
                                    $estado = $dia?->estado;
                                    $cls = 'dl-cell';
                                    if ($libre && $estado === 'APROBADO') $cls .= ' dl-aprobado';
                                    elseif ($libre) $cls .= ' dl-pendiente';
                                @endphp
                                <td style="text-align:center;padding:2px;">
                                    <button
                                        type="button"
                                        class="{{ $cls }}"
                                        data-empleado-id="{{ $emp->id }}"
                                        data-fecha="{{ $f->toDateString() }}"
                                        data-libre="{{ $libre ? '1' : '0' }}"
                                        data-estado="{{ $estado ?? '' }}"
                                        title="{{ $libre ? ($estado === 'APROBADO' ? 'Aprobado' : 'Pendiente RRHH') : 'Marcar libre' }}"
                                    >{{ $libre ? 'L' : '·' }}</button>
                                </td>
                            @endforeach
                            <td class="dl-total" style="text-align:center;font-weight:700;background:#f0fdf4;">{{ $totalLibre }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted" style="margin:12px 0 0;font-size:12px;">
                <span class="dl-legenda dl-pendiente">L</span> pendiente
                &nbsp;·&nbsp;
                <span class="dl-legenda dl-aprobado">L</span> aprobado
            </p>
        @endif
    </div>
</div>

<style>
.dias-libres-grid .dl-cell {
    width: 36px;
    height: 32px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #f9fafb;
    color: #9ca3af;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    line-height: 1;
}
.dias-libres-grid .dl-cell:hover { border-color: #93c5fd; }
.dias-libres-grid .dl-pendiente {
    background: #fef08a !important;
    color: #854d0e !important;
    border-color: #facc15 !important;
}
.dias-libres-grid .dl-aprobado {
    background: #86efac !important;
    color: #14532d !important;
    border-color: #22c55e !important;
}
.dl-legenda {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 20px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 11px;
}
.pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}
</style>

@if(! $empleados->isEmpty())
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}';
    const url = @json(route('nomina.dias_libres.toggle'));
    const esRrhh = @json($esRrhh);

    function recount(row) {
        const n = row.querySelectorAll('.dl-cell[data-libre="1"]').length;
        const total = row.querySelector('.dl-total');
        if (total) total.textContent = String(n);
    }

    document.querySelectorAll('.dl-cell').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!esRrhh && btn.dataset.estado === 'APROBADO') {
                alert('Ese día ya está aprobado por RRHH.');
                return;
            }
            btn.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        empleado_id: Number(btn.dataset.empleadoId),
                        fecha: btn.dataset.fecha,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudo guardar');
                }
                if (data.libre) {
                    btn.dataset.libre = '1';
                    btn.dataset.estado = data.estado || 'PENDIENTE';
                    btn.textContent = 'L';
                    btn.classList.remove('dl-pendiente', 'dl-aprobado');
                    btn.classList.add(data.estado === 'APROBADO' ? 'dl-aprobado' : 'dl-pendiente');
                    btn.title = data.estado === 'APROBADO' ? 'Aprobado' : 'Pendiente RRHH';
                } else {
                    btn.dataset.libre = '0';
                    btn.dataset.estado = '';
                    btn.textContent = '·';
                    btn.classList.remove('dl-pendiente', 'dl-aprobado');
                    btn.title = 'Marcar libre';
                }
                recount(btn.closest('tr'));
            } catch (e) {
                alert(e.message || 'Error al guardar');
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
</script>
@endif
@endsection
