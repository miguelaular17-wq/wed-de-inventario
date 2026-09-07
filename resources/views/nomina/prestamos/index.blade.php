@extends('layouts.app')

@section('title', 'Préstamos')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Préstamos</h1>
            <p class="muted" style="margin:4px 0 0;">
                Quincena {{ $quincena['etiqueta'] }}. Lista de deudores (suma de todos sus préstamos).
                Abre a la persona para cobrar o programar descuento de nómina/comisión.
            </p>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Deudores</span><strong>{{ $kpis['deudores'] }}</strong></div>
        <div class="nomina-kpi"><span>Total prestado</span><strong>${{ number_format($kpis['total_prestamo'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Total pagado</span><strong>${{ number_format($kpis['total_pagado'] ?? 0, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Saldo vivo</span><strong>${{ number_format($kpis['saldo'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Esta quincena</span><strong>${{ number_format($kpis['programado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Pendiente global</span><strong>${{ number_format($kpisGlobales['total_pendiente'], 2) }}</strong></div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Fecha TXT</label>
            <input type="date" name="fecha" value="{{ $fecha }}">
        </div>
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre o cédula" autofocus>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
            <button class="btn primary" type="submit">Buscar</button>
            <a class="btn" href="{{ route('nomina.prestamos.txt', ['fecha' => $fecha]) }}">Descargar TXT por empresa</a>
        </div>
    </form>
    <p class="muted" style="margin-top:8px;">
        Tasa flujo de caja (BCV) hoy: <strong>{{ number_format($tasaBcv, 2) }}</strong>.
        El banco pide un TXT por empresa (si hay varias, baja un ZIP).
        @if($kpis['nomina'] > 0 || $kpis['comision'] > 0)
            · Programado: nómina ${{ number_format($kpis['nomina'], 2) }} · comisión ${{ number_format($kpis['comision'], 2) }}
        @endif
    </p>

    @if(($txtPorEmpresa ?? collect())->isNotEmpty())
        <div class="nomina-card" style="margin-top:16px;">
            <h3 style="margin-top:0;">TXT del {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Personas</th>
                        <th>Monto USD</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($txtPorEmpresa as $fila)
                        <tr>
                            <td>
                                <strong>{{ $fila->empresa?->nombre ?: 'Sin empresa' }}</strong>
                                @if($fila->empresa?->codigo)
                                    <div class="muted" style="font-size:.75rem;">{{ $fila->empresa->codigo }}</div>
                                @endif
                            </td>
                            <td>{{ $fila->empleados }}</td>
                            <td>${{ number_format($fila->usd, 2) }}</td>
                            <td>
                                <a class="btn secondary" href="{{ route('nomina.prestamos.txt', ['fecha' => $fecha, 'empresa' => $fila->empresa->id ?? 0]) }}">TXT</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Generar préstamo</h3>
        @if($q === '')
            <p class="muted" style="margin-bottom:0;">Busca por nombre o cédula para registrar un préstamo a cualquier empleado.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Cédula</th>
                        <th>Sede</th>
                        <th>Monto / motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resultadosAlta as $empleado)
                        <tr>
                            <td>
                                <strong>{{ $empleado->nombre() }}</strong>
                                <div class="muted" style="font-size:.75rem;">{{ $empleado->nombreCargo() }}</div>
                            </td>
                            <td>{{ $empleado->cedula() ?: '—' }}</td>
                            <td>{{ $empleado->nombreSede() }}</td>
                            <td>
                                <form method="POST" action="{{ route('nomina.prestamos.escritorio') }}" class="nomina-inline-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    @csrf
                                    <input type="hidden" name="empleado_id" value="{{ $empleado->id }}">
                                    <input type="hidden" name="fecha" value="{{ $fecha }}">
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <input type="number" step="0.01" min="0.01" name="monto_original" placeholder="Monto $" required style="width:120px;">
                                    <input name="motivo" placeholder="Motivo (opcional)" style="min-width:160px;flex:1;">
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

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;" id="lista-deudores">Quién debe</h3>
        @if($deudores->isEmpty())
            <p class="muted" style="margin-bottom:0;">Nadie tiene saldo pendiente{{ $q !== '' ? ' que coincida con “'.$q.'”' : '' }}.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Préstamos</th>
                            <th>Total prestado</th>
                            <th>Pagado</th>
                            <th>Saldo</th>
                            <th>Esta quincena</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deudores as $fila)
                            @php
                                $empleado = $fila['empleado'];
                                $programado = ($planesPorEmpleado->get($empleado->id) ?? collect())->sum('monto');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $empleado->nombre() }}</strong>
                                    @if($empleado->cedula())
                                        <div class="muted" style="font-size:.75rem;">C.I. {{ $empleado->cedula() }}</div>
                                    @endif
                                    @if($fila['genera_comision'])
                                        <div class="muted" style="font-size:.75rem;">Gana comisión</div>
                                    @endif
                                </td>
                                <td>{{ $fila['cantidad'] }}</td>
                                <td>${{ number_format($fila['total_prestamo'], 2) }}</td>
                                <td>${{ number_format($fila['total_pagado'], 2) }}</td>
                                <td><strong>${{ number_format($fila['saldo'], 2) }}</strong></td>
                                <td>
                                    @if($programado > 0)
                                        ${{ number_format((float) $programado, 2) }}
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="btn primary" style="padding:4px 10px;font-size:.8rem;" href="#cobrar-{{ $empleado->id }}">Cobrar / descontar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($delDia->isNotEmpty())
        <div class="nomina-card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Préstamos del {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Empleado</th>
                        <th>Empresa</th>
                        <th>Monto</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($delDia as $p)
                        <tr>
                            <td>{{ $p->id }}</td>
                            <td>{{ $p->empleado?->nombre() ?: '—' }}</td>
                            <td>{{ $p->empleado?->empresa?->nombre ?: 'Sin empresa' }}</td>
                            <td>${{ number_format((float) $p->monto_original, 2) }}</td>
                            <td>{{ $p->motivo ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
.prestamo-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 80;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, .45);
}
.prestamo-modal:target {
    display: flex;
}
.prestamo-modal-backdrop {
    position: absolute;
    inset: 0;
}
.prestamo-modal form {
    position: relative;
    z-index: 1;
}
.prestamo-modal-pago { display: none; }
.prestamo-modal:has(select[name="modo"] option[value="PAGO"]:checked) .prestamo-modal-pago { display: block; }
</style>

@foreach($deudores as $fila)
    @php $empleado = $fila['empleado']; @endphp
    <div id="cobrar-{{ $empleado->id }}" class="prestamo-modal">
        <a class="prestamo-modal-backdrop" href="#lista-deudores" aria-label="Cerrar"></a>
        <form method="POST" action="{{ route('nomina.prestamos.cobrar', $empleado) }}" class="nomina-card" style="margin:0;max-width:480px;width:100%;">
            @csrf
            <input type="hidden" name="q" value="{{ $q }}">
            <h3 style="margin-top:0;">{{ $empleado->nombre() }}</h3>
            <p class="muted" style="margin-top:0;">Saldo pendiente: ${{ number_format($fila['saldo'], 2) }}</p>

            <div class="nomina-form-grid">
                <div class="field">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="field">
                    <label>Monto ($)</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $fila['saldo'] }}" name="monto" required>
                </div>
                <div class="field field-wide">
                    <label>Acción</label>
                    <select name="modo">
                        <option value="PAGO">Registrar pago ahora</option>
                        <option value="NOMINA">Descontar de nómina (esta quincena)</option>
                        @if($fila['genera_comision'])
                            <option value="COMISION">Descontar de comisión (esta quincena)</option>
                        @endif
                    </select>
                </div>
                <div class="field field-wide prestamo-modal-pago">
                    <label>Tipo de pago</label>
                    <select name="tipo">
                        <option value="EFECTIVO">Pago en efectivo</option>
                        <option value="TRANSFERENCIA">Transferencia</option>
                        <option value="EXTRAORDINARIO">Abono extraordinario</option>
                        <option value="AJUSTE">Ajuste</option>
                    </select>
                </div>
                <div class="field field-wide">
                    <label>Préstamo (opcional)</label>
                    <select name="prestamo_id">
                        <option value="">FIFO automático (más antiguos primero)</option>
                        @foreach($fila['prestamos'] as $p)
                            <option value="{{ $p->id }}">#{{ $p->id }} · ${{ number_format((float) $p->saldo_pendiente, 2) }}{{ $p->motivo ? ' · '.$p->motivo : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field field-wide">
                    <label>Observación</label>
                    <input name="observacion" placeholder="Opcional">
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
                <a class="btn secondary" href="#lista-deudores">Cancelar</a>
                <button type="submit" class="btn primary">Confirmar</button>
            </div>
        </form>
    </div>
@endforeach
@endsection
