@extends('layouts.app')

@section('title', 'Comisiones '.$periodo->etiqueta)

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
        $liquidacionesVentas = $liquidaciones->values();
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
    @endphp

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Comisión</span><strong>${{ number_format($totalBruto, 2) }}</strong></div>
        <div class="nomina-kpi"><span>Abonos</span><strong>${{ number_format($totalAbonos, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Retención 10%</span><strong>${{ number_format($totalRetencion, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Descuentos</span><strong>${{ number_format($totalDescuentos, 2) }}</strong></div>
        <div class="nomina-kpi"><span>A pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <h3 style="margin:20px 0 0;">Supervisores y vendedores</h3>
    @include('nomina.partials.empleado-tabla-buscador', ['target' => 'tabla-comisiones-ventas'])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-comisiones-ventas">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Venta neta</th>
                    <th>Base telefonía</th>
                    <th>Base otros</th>
                    <th>Comisión</th>
                    <th>Abonos</th>
                    <th>Retención</th>
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
                    @endphp
                    <tr data-empleado-buscar="{{ $buscarAttrs($liq) }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $liq->empleado, 'tab' => 'comisiones']) }}">
                                {{ $liq->empleado->nombre() }}
                            </a>
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
                        <td>${{ number_format($descuentosMostrados, 2) }}</td>
                        <td>
                            <strong>${{ number_format($pagarMostrado, 2) }}</strong>
                            @if($esSt)
                                <div class="muted" style="font-size:.72rem;">Parte ST abajo, sin retención</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Sin liquidaciones de supervisores o vendedores en esta quincena.</td></tr>
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
                    <th>Abonos</th>
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
                    <tr data-empleado-buscar="{{ $buscarAttrs($liq) }}">
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
                        <td>${{ number_format($descuentosSt, 2) }}</td>
                        <td><strong>${{ number_format($pagarSt, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin liquidaciones de servicio técnico en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($periodo->estado !== 'ABIERTO')
    <div class="nomina-card" style="margin-top:16px;">
        <h3>Archivo para el banco</h3>
        <p class="muted">Un TXT por empresa, igual que en nómina. Tasa BCV hoy: <strong>{{ number_format($tasaBcv, 2) }}</strong>.</p>
        <table class="data-table">
            <thead><tr><th>Empresa</th><th>Empleados</th><th>Comisión USD</th><th>Comisión Bs</th><th></th></tr></thead>
            <tbody>
                @forelse($bancoPorEmpresa as $fila)
                    <tr>
                        <td>
                            @if($fila->empresa)
                                <strong>{{ $fila->empresa->codigo }}</strong>
                                <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                            @else
                                <span class="muted">Sin empresa asignada</span>
                                @foreach(($fila->personas ?? collect()) as $persona)
                                    <div style="font-size:.78rem;margin-top:4px;">
                                        <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'comisiones']) }}">
                                            {{ $persona->nombre }}
                                        </a>
                                        <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $fila->empleados }}</td>
                        <td>${{ number_format($fila->usd, 2) }}</td>
                        <td>Bs {{ number_format($fila->usd * $tasaBcv, 2) }}</td>
                        <td style="text-align:right;">
                            @if($fila->empresa)
                                <a class="btn primary" href="{{ route('nomina.comisiones.banco', [$periodo, $fila->empresa]) }}">Descargar TXT</a>
                            @else
                                <span class="muted">Asigna empresa en la ficha</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No hay comisiones calculadas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
