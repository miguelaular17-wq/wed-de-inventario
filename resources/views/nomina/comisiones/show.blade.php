@extends('layouts.app')

@section('title', 'Comisiones '.$periodo->etiqueta)

@push('head')
<style>
.com-modal[hidden] { display: none !important; }
.com-modal {
    position: fixed; inset: 0; z-index: 1800;
    background: rgba(15, 23, 42, .55);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 28px 16px; overflow: auto;
}
.com-modal-card {
    background: #fff; border-radius: 16px; width: min(980px, 100%);
    padding: 18px 20px 16px; box-shadow: 0 24px 60px rgba(0,0,0,.28);
}
.com-modal-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.com-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.com-tabs .btn.is-on { background: #1e3a8a; color: #fff; }
[data-ajuste-panel][hidden] { display: none !important; }
.com-alta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 12px; }
.com-alta-grid { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
.com-alta-grid label { display: flex; flex-direction: column; gap: 4px; font-size: .75rem; font-weight: 700; color: #64748b; min-width: 120px; }
.com-alta-grid label.com-alta-empleado { position: relative; flex: 1; min-width: 220px; }
.com-alta-grid input, .com-alta-grid select { font-weight: 600; color: #0f172a; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
.com-alta-lista { position: absolute; z-index: 5; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; margin-top: 4px; max-height: 220px; overflow: auto; box-shadow: 0 12px 30px rgba(15,23,42,.12); }
.com-alta-lista button { display: block; width: 100%; text-align: left; background: #fff; border: 0; border-bottom: 1px solid #f1f5f9; padding: 8px 10px; cursor: pointer; }
.com-alta-lista button:hover { background: #eff6ff; }
</style>
@endpush

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.index') }}" class="muted" style="font-size:.82rem;">← Comisiones</a>
            <h1 style="margin:4px 0 0;">Comisiones {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Pago el {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}.
            </p>
        </div>
        <div>
            <a class="btn secondary" href="{{ route('nomina.periodos.show', $periodo) }}">Ver nómina</a>
            @if($periodo->estado !== 'ABIERTO' && $periodo->estado !== 'CERRADO' && $liquidaciones->isNotEmpty())
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:8px;">
                    <form method="POST" action="{{ route('nomina.comisiones.recalcular', $periodo) }}" onsubmit="return confirm('¿Recalcular solo las comisiones de esta quincena? La nómina (sueldos y deducciones) no se modifica.')">
                        @csrf
                        <button class="btn" type="submit">Recalcular comisiones</button>
                    </form>
                    <a class="btn primary" href="{{ route('nomina.comisiones.relacion', $periodo) }}">Descargar relación PDF</a>
                    <a class="btn" href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'xlsx']) }}">Descargar Excel</a>
                    <a class="btn" href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'zip']) }}">ZIP PDF por sede y área</a>
                </div>
            @elseif($periodo->estado !== 'ABIERTO' && $liquidaciones->isNotEmpty())
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:8px;">
                    <a class="btn primary" href="{{ route('nomina.comisiones.relacion', $periodo) }}">Descargar relación PDF</a>
                    <a class="btn" href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'xlsx']) }}">Descargar Excel</a>
                    <a class="btn" href="{{ route('nomina.comisiones.relacion', ['periodo' => $periodo, 'formato' => 'zip']) }}">ZIP PDF por sede y área</a>
                </div>
            @endif
        </div>
    </div>

    @php
        $liquidacionesSt = $liquidaciones->filter(fn ($l) => $l->esServicioTecnico())->values();
        $liquidacionesVentas = $liquidaciones->reject(fn ($l) => $l->esServicioTecnico())->values();
        $modosAgregados = array_merge(
            \App\Models\Nomina\NominaEmpleado::modosComisionAgregadosSede(),
            \App\Models\Nomina\NominaEmpleado::modosComisionAgregadosEquipo()
        );
        $totalBruto = (float) $liquidaciones->sum('comision_total');
        $totalAbonos = (float) $liquidaciones->sum('abonos');
        $totalRetencion = (float) $liquidaciones->sum('retencion');
        $totalDescuentos = (float) $liquidaciones->sum('descuentos') + (float) $liquidaciones->sum('prestamos');
        $totalPagar = (float) $liquidaciones->sum('total_pagar');
        $buscarAttrs = fn ($liq) => mb_strtolower(trim(implode(' ', array_filter([
            $liq->empleado->nombre(),
            $liq->empleado->cedula(),
            $liq->empleado->nombreSede(),
            $liq->empleado->nombreCargo(),
        ]))));
        $sedeArea = app(\App\Services\Nomina\PayrollSedeAreaTotals::class);
    @endphp

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Comisión</span><strong>${{ number_format($totalBruto, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Bonos</span><strong>${{ number_format($totalAbonos, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Retención 10%</span><strong>${{ number_format($totalRetencion, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Descuentos</span><strong>${{ number_format($totalDescuentos, 2) }}</strong></div>
        <div class="nomina-kpi"><span>A pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
        <button class="btn" type="button" data-abrir-modal="modal-ajustes">Ajustes de la quincena</button>
        <button class="btn" type="button" data-abrir-modal="modal-sedes">Totales por sede y área</button>
        @if($periodo->estado !== 'ABIERTO')
            <button class="btn" type="button" data-abrir-modal="modal-txt">TXT del banco</button>
        @endif
    </div>

    <h3 style="margin:20px 0 0;">Supervisores y vendedores</h3>
    @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-comisiones-ventas'])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-comisiones-ventas">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Venta neta</th>
                    <th>Ventas telefonía</th>
                    <th>Ventas otros</th>
                    <th>Comisión</th>
                    <th>Bonos</th>
                    <th>Retención</th>
                    <th title="Sin retención 10%">Sin ret.</th>
                    <th>Desc. / préstamos</th>
                    <th>A pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liquidacionesVentas as $liq)
                    @php
                        $esSt = $liq->esServicioTecnico();
                        $esAgregado = in_array($liq->modo, $modosAgregados, true);
                        $ventaMostrada = $esSt ? $liq->ventasOtrosProductos() : $liq->totalVentas();
                        $comisionMostrada = $esSt ? $liq->comisionOtrosProductos() : (float) $liq->comision_total;
                        $abonosMostrados = (float) $liq->abonos;
                        $retencionMostrada = $esSt ? $liq->retencionOtrosProductos() : (float) $liq->retencion;
                        $descuentosMostrados = $esSt ? 0.0 : ((float) $liq->descuentos + (float) $liq->prestamos);
                        $pagarMostrado = $esSt
                            ? round($comisionMostrada + $abonosMostrados - $retencionMostrada, 2)
                            : (float) $liq->total_pagar;
                        $puedeMarcarExento = $periodo->estado !== 'ABIERTO' && $periodo->estado !== 'CERRADO';
                    @endphp
                    <tr data-grupo-clave="{{ $sedeArea->grupoDeEmpleado($liq->empleado)['clave'] }}" data-empleado-buscar="{{ $buscarAttrs($liq) }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $liq->empleado, 'tab' => 'comisiones']) }}">
                                {{ $liq->empleado->nombre() }}
                            </a>
                            @if($liq->empleado->exentoRetencionComision())
                                <div class="muted" style="font-size:.72rem;color:#0f766e;">Sin retención</div>
                            @endif
                            @if($esSt)
                                <div class="muted" style="font-size:.72rem;">ST · Otros productos</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_SEDE)
                                <div class="muted" style="font-size:.72rem;">Sede {{ $liq->empleado->nombreSede() }}</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_EQUIPO)
                                <div class="muted" style="font-size:.72rem;">Ventas del equipo</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_DIGITAL)
                                <div class="muted" style="font-size:.72rem;">Digital · ventas de trabajadores</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_PCP)
                                <div class="muted" style="font-size:.72rem;">PCP · venta neta tienda</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SAMBIL)
                                <div class="muted" style="font-size:.72rem;">Sambil · venta neta tienda</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_COLABORADOR)
                                <div class="muted" style="font-size:.72rem;">Colaborador · 5 tiendas (0,25%)</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_NUNES)
                                <div class="muted" style="font-size:.72rem;">Venta neta sede Nunes</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_MOVISTAR)
                                <div class="muted" style="font-size:.72rem;">Sin facturas ST</div>
                            @endif
                        </td>
                        @if($esAgregado)
                            <td>
                                <strong>${{ number_format($ventaMostrada, 2) }}</strong>
                                <div class="muted" style="font-size:.72rem;">Venta neta</div>
                            </td>
                            <td class="muted">—</td>
                            <td class="muted">—</td>
                        @else
                            <td>
                                <strong>${{ number_format($ventaMostrada, 2) }}</strong>
                                <div class="muted" style="font-size:.72rem;">{{ $esSt ? 'Otros productos (neto)' : 'Tel + otros (neto)' }}</div>
                            </td>
                            <td>${{ number_format($liq->base_telefonia, 2) }}</td>
                            <td>${{ number_format($liq->base_otros, 2) }}</td>
                        @endif
                        <td>${{ number_format($comisionMostrada, 2) }}</td>
                        <td>${{ number_format($abonosMostrados, 2) }}</td>
                        <td>${{ number_format($retencionMostrada, 2) }}</td>
                        <td style="text-align:center;">
                            <form method="POST" action="{{ route('nomina.comisiones.exento_retencion', [$periodo, $liq->empleado]) }}" style="margin:0;">
                                @csrf
                                <input type="hidden" name="exento_retencion_comision" value="0">
                                <input
                                    type="checkbox"
                                    name="exento_retencion_comision"
                                    value="1"
                                    @checked($liq->empleado->exentoRetencionComision())
                                    @disabled(! $puedeMarcarExento)
                                    title="Sin retención 10%"
                                    onchange="this.form.submit()"
                                >
                            </form>
                        </td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $descuentosMostrados,
                                'lineas' => $liq->lineasDescuento(),
                                'titulo' => 'Desc. / préstamos',
                            ])
                        </td>
                        <td>
                            <strong>${{ number_format($pagarMostrado, 2) }}</strong>
                            @if($esSt)
                                <div class="muted" style="font-size:.72rem;">Parte ST abajo, sin retención</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Sin liquidaciones de supervisores o vendedores en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 style="margin:24px 0 0;">Servicio técnico</h3>
    @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-comisiones-st'])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-comisiones-st">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Facturas ST</th>
                    <th>Egresos 058</th>
                    <th>Comisión</th>
                    <th>Bonos</th>
                    <th>Retención</th>
                    <th>Desc. / préstamos</th>
                    <th>A pagar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liquidacionesSt as $liq)
                    @php
                        $comisionSt = $liq->comisionSt();
                        $descuentosSt = (float) $liq->descuentos + (float) $liq->prestamos;
                        $pagarSt = round($comisionSt - $descuentosSt, 2);
                    @endphp
                    <tr data-grupo-clave="{{ $sedeArea->grupoDeEmpleado($liq->empleado)['clave'] }}" data-empleado-buscar="{{ $buscarAttrs($liq) }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $liq->empleado, 'tab' => 'comisiones']) }}">
                                {{ $liq->empleado->nombre() }}
                            </a>
                            <div class="muted" style="font-size:.72rem;">Servicio técnico</div>
                        </td>
                        <td>
                            @if($liq->ventasSt() > 0)
                                <strong>${{ number_format($liq->ventasSt(), 2) }}</strong>
                                @if($liq->baseStNeta() !== $liq->ventasSt())
                                    <div class="muted" style="font-size:.72rem;">Base neta ST: ${{ number_format($liq->baseStNeta(), 2) }}</div>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($liq->egresos058() > 0)
                                ${{ number_format($liq->egresos058(), 2) }}
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>${{ number_format($comisionSt, 2) }}</td>
                        <td>$0.00</td>
                        <td>
                            $0.00
                            <div class="muted" style="font-size:.72rem;">Sin retención</div>
                        </td>
                        <td>
                            @include('nomina.partials.descuento-comentarios', [
                                'monto' => $descuentosSt,
                                'lineas' => $liq->lineasDescuento(),
                                'titulo' => 'Desc. / préstamos',
                            ])
                        </td>
                        <td><strong>${{ number_format($pagarSt, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin liquidaciones de servicio técnico en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@php
    $ajustesPeriodo = $ajustesPeriodo ?? [
        'bonos' => collect(),
        'prestamos' => collect(),
        'faltantes' => collect(),
        'descuentos' => collect(),
        'otros' => collect(),
        'mercancia' => collect(),
    ];
    $puedeAjustar = $periodo->estado !== 'CERRADO';
@endphp

<div class="com-modal" id="modal-ajustes" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <div>
                <h3 style="margin:0;">Ajustes de la quincena</h3>
                <p class="muted" style="margin:4px 0 0;">Marca lo que se queda y pulsa Aplicar. Lo que desmarques sale de esta quincena. Arriba de cada lista puedes registrar uno nuevo.</p>
            </div>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        <div class="com-tabs">
            <button type="button" class="btn is-on" data-ajuste-tab="bonos">Bonos</button>
            <button type="button" class="btn" data-ajuste-tab="prestamos">Préstamos</button>
            <button type="button" class="btn" data-ajuste-tab="faltantes">Faltantes de caja</button>
            <button type="button" class="btn" data-ajuste-tab="descuentos">Descuentos</button>
        </div>
        @if($puedeAjustar)
            <div data-ajuste-panel="bonos">
                @include('nomina.comisiones._ajuste_alta', ['periodo' => $periodo, 'concepto' => 'bono'])
            </div>
            <div data-ajuste-panel="prestamos" hidden>
                @include('nomina.comisiones._ajuste_alta', ['periodo' => $periodo, 'concepto' => 'prestamo'])
            </div>
            <div data-ajuste-panel="faltantes" hidden>
                @include('nomina.comisiones._ajuste_alta', ['periodo' => $periodo, 'concepto' => 'faltante'])
            </div>
            <div data-ajuste-panel="descuentos" hidden>
                @include('nomina.comisiones._ajuste_alta', ['periodo' => $periodo, 'concepto' => 'descuento'])
            </div>
        @endif
        <form method="POST" action="{{ route('nomina.comisiones.bonos.aplicar', $periodo) }}" onsubmit="return confirm('¿Aplicar esta selección? Lo que no esté marcado se quita de la quincena.')">
            @csrf
            <div data-ajuste-panel="bonos">
                @include('nomina.comisiones._ajuste_tabla', ['filas' => $ajustesPeriodo['bonos'], 'campo' => 'ajuste_ids', 'puede' => $puedeAjustar, 'etiqueta' => fn ($fila) => $fila->motivo ?: 'Bono'])
            </div>
            <div data-ajuste-panel="prestamos" hidden>
                @include('nomina.comisiones._ajuste_tabla', ['filas' => $ajustesPeriodo['prestamos'], 'campo' => 'plan_ids', 'puede' => $puedeAjustar, 'etiqueta' => fn ($fila) => $fila->prestamo->motivo ?? ($fila->etiqueta ?: 'Préstamo')])
            </div>
            <div data-ajuste-panel="faltantes" hidden>
                @include('nomina.comisiones._ajuste_tabla', ['filas' => $ajustesPeriodo['faltantes'], 'campo' => 'faltante_ids', 'puede' => $puedeAjustar, 'etiqueta' => fn ($fila) => $fila->motivo ?: 'Faltante de caja'])
            </div>
            <div data-ajuste-panel="descuentos" hidden>
                @include('nomina.comisiones._ajuste_tabla', [
                    'filas' => $ajustesPeriodo['descuentos']->concat($ajustesPeriodo['otros'])->concat($ajustesPeriodo['mercancia']),
                    'campo' => null,
                    'puede' => $puedeAjustar,
                    'etiqueta' => fn ($fila) => $fila->motivo ?: 'Descuento',
                    'campoDe' => function ($fila) {
                        if ($fila instanceof \App\Models\Nomina\NominaEmpleadoAjuste) return 'descuento_ids';
                        if ($fila instanceof \App\Models\Nomina\NominaDescuentoMercancia) return 'mercancia_ids';
                        return 'otro_ids';
                    },
                ])
            </div>
            @if($puedeAjustar)
                <div style="margin-top:12px;">
                    <button class="btn primary" type="submit">Aplicar selección</button>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="com-modal" id="modal-sedes" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <h3 style="margin:0;">Totales por sede y área</h3>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        @include('nomina.partials.totales-sede-area', [
            'totalesPorGrupo' => $totalesPorGrupo ?? collect(),
            'tasaBcv' => $tasaBcv ?? 0,
            'tasaBcvEtiqueta' => 'Tasa BCV aplicada',
            'filtroTargets' => ['tabla-comisiones-ventas', 'tabla-comisiones-st'],
            'pdfRoute' => $periodo->estado !== 'ABIERTO'
                ? route('nomina.comisiones.reporte_sedes', $periodo)
                : null,
            'pdfPorGrupo' => $periodo->estado !== 'ABIERTO'
                ? route('nomina.comisiones.relacion', $periodo)
                : null,
            'mostrarTitulo' => false,
        ])
        @if(($totalesPorGrupo ?? collect())->isEmpty())
            <p class="muted">Todavía no hay totales por sede o área en esta quincena.</p>
        @endif
    </div>
</div>

@if($periodo->estado !== 'ABIERTO')
<div class="com-modal" id="modal-txt" hidden>
    <div class="com-modal-card">
        <div class="com-modal-head">
            <div>
                <h3 style="margin:0;">TXT del banco</h3>
                <p class="muted" style="margin:4px 0 0;">Los que tienen empresa se descargan. Los que no, hay que asignarla en la ficha. Tasa BCV: <strong>{{ number_format($tasaBcv, 2) }}</strong>.</p>
            </div>
            <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
        </div>
        <table class="data-table">
            <thead><tr><th>Empresa</th><th>Empleados</th><th>Comisión USD</th><th>Comisión Bs</th><th></th></tr></thead>
            <tbody>
                @forelse($bancoPorEmpresa as $fila)
                    @if($fila->empresa)
                        <tr>
                            <td>
                                <strong>{{ $fila->empresa->codigo }}</strong>
                                <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                            </td>
                            <td>{{ $fila->empleados }}</td>
                            <td>${{ number_format($fila->usd, 2) }}</td>
                            <td>Bs {{ number_format($fila->usd * $tasaBcv, 2) }}</td>
                            <td style="text-align:right;">
                                <a class="btn primary" href="{{ route('nomina.comisiones.banco', [$periodo, $fila->empresa]) }}">Descargar TXT</a>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="5"><strong>Sin empresa</strong> · {{ $fila->empleados }} persona(s) · ${{ number_format($fila->usd, 2) }}</td>
                        </tr>
                        @foreach(($fila->personas ?? collect()) as $persona)
                            <tr>
                                <td style="padding-left:24px;">
                                    <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'comisiones']) }}">{{ $persona->nombre }}</a>
                                    <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                                </td>
                                <td>1</td>
                                <td>${{ number_format($persona->usd ?? 0, 2) }}</td>
                                <td>Bs {{ number_format(($persona->usd ?? 0) * $tasaBcv, 2) }}</td>
                                <td class="muted">No se descarga</td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="5" class="muted">No hay comisiones calculadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-abrir-modal]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const modal = document.getElementById(boton.getAttribute('data-abrir-modal'));
            if (modal) modal.hidden = false;
        });
    });
    document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const modal = boton.closest('.com-modal');
            if (modal) modal.hidden = true;
        });
    });
    document.querySelectorAll('.com-modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.hidden = true;
        });
    });
    document.querySelectorAll('[data-ajuste-tab]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            activarAjuste(boton.getAttribute('data-ajuste-tab'));
        });
    });

    function activarAjuste(nombre) {
        document.querySelectorAll('[data-ajuste-tab]').forEach(function (otro) {
            otro.classList.toggle('is-on', otro.getAttribute('data-ajuste-tab') === nombre);
        });
        document.querySelectorAll('[data-ajuste-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-ajuste-panel') !== nombre;
        });
    }

    const tabInicial = new URLSearchParams(window.location.search).get('ajuste');
    if (tabInicial && document.getElementById('modal-ajustes')) {
        document.getElementById('modal-ajustes').hidden = false;
        activarAjuste(tabInicial);
    }

    document.querySelectorAll('form.com-alta').forEach(function (form) {
        const campo = form.querySelector('input[name="empleado_nombre"]');
        const idCampo = form.querySelector('input[name="empleado_id"]');
        const lista = form.querySelector('.com-alta-lista');
        const url = form.getAttribute('data-buscar-empleados');
        let timer = null;
        if (!campo || !lista || !url) return;

        campo.addEventListener('input', function () {
            idCampo.value = '';
            clearTimeout(timer);
            const texto = campo.value.trim();
            if (texto.length < 2) {
                lista.hidden = true;
                lista.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(texto), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (filas) {
                        lista.innerHTML = '';
                        if (!filas.length) {
                            lista.hidden = true;
                            return;
                        }
                        filas.forEach(function (fila) {
                            const boton = document.createElement('button');
                            boton.type = 'button';
                            const extra = fila.comision ? '' : ' · sin comisión';
                            boton.textContent = fila.nombre + (fila.cedula ? ' · ' + fila.cedula : '') + (fila.sede ? ' · ' + fila.sede : '') + extra;
                            boton.addEventListener('click', function () {
                                if (!fila.comision) return;
                                idCampo.value = fila.id;
                                campo.value = fila.nombre;
                                lista.hidden = true;
                            });
                            lista.appendChild(boton);
                        });
                        lista.hidden = false;
                    })
                    .catch(function () { lista.hidden = true; });
            }, 250);
        });

        form.addEventListener('submit', function (event) {
            if (!idCampo.value) {
                event.preventDefault();
                campo.focus();
                campo.setCustomValidity('Elige una persona de la lista.');
                campo.reportValidity();
            }
        });
        campo.addEventListener('input', function () { campo.setCustomValidity(''); });
    });
});
</script>
@endpush
