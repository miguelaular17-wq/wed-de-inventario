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
.com-modal-card.com-modal-txt { width: min(920px, 100%); }
.txt-acciones { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 14px; }
.txt-empresas-main { margin: 0 0 12px; }
.txt-empresas-main .data-table { margin: 0; }
.txt-excluir { margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; background: #f8fafc; }
.txt-excluir > summary { cursor: pointer; font-weight: 700; color: #334155; list-style: none; }
.txt-excluir > summary::-webkit-details-marker { display: none; }
.txt-excluir > summary::before { content: '▸ '; color: #64748b; }
.txt-excluir[open] > summary::before { content: '▾ '; }
.txt-excluir-body { margin-top: 12px; }
.txt-filtros { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin: 0 0 12px; }
.txt-filtros label { display: flex; flex-direction: column; gap: 4px; font-size: .75rem; font-weight: 700; color: #64748b; min-width: 160px; }
.txt-filtros select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; color: #0f172a; }
.txt-lista-wrap { max-height: min(42vh, 420px); overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
.txt-lista-wrap table { margin: 0; }
.txt-lista-wrap tr[hidden] { display: none !important; }
.com-bono-rapido { display: inline-flex; align-items: center; margin: 0; }
.com-bono-valor {
    border: 0; background: transparent; padding: 0; margin: 0;
    color: #1d4ed8; font: inherit; font-weight: 700; cursor: pointer;
    text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 3px;
}
.com-bono-valor:hover { color: #1e3a8a; }
.com-bono-input {
    width: 5.5rem; padding: 4px 6px; border: 1px solid #93c5fd; border-radius: 6px;
    font-weight: 700; color: #0f172a; font-size: .85rem;
}
</style>
@endpush

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.comisiones.index') }}" class="muted" style="font-size:.82rem;">← Volver</a>
            <h1 style="margin:4px 0 0;">Comisiones {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Pago el {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}.
                @if($periodo->estado === 'CERRADO')
                    · <strong>{{ $periodo->estado }}</strong> (solo lectura)
                @elseif($periodo->estado === 'PAGADO')
                    · <strong>{{ $periodo->estado }}</strong> · puedes recalcular aquí o usar Ajuste para diferencias
                @elseif($periodo->estado !== 'ABIERTO')
                    · <strong>{{ $periodo->estado }}</strong>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if(! in_array($periodo->estado, ['ABIERTO', 'CERRADO'], true))
                <form method="POST" action="{{ route('nomina.comisiones.recalcular', $periodo) }}"
                      onsubmit="return confirm(@json($periodo->estado === 'PAGADO'
                          ? '¿Recalcular todas las comisiones de esta quincena YA PAGADA? Se actualizan montos (incluye bonos de productos meta) sin reabrir el período.'
                          : '¿Recalcular todas las comisiones de esta quincena con ventas y bonos de productos meta actuales?'))">
                    @csrf
                    <button type="submit" class="btn primary">Recalcular comisiones</button>
                </form>
            @endif
            @if($periodo->estado !== 'ABIERTO')
                <a class="btn {{ $periodo->estado === 'CERRADO' ? 'primary' : 'secondary' }}" href="{{ route('nomina.comisiones.diferenciales.create', $periodo) }}">Ajuste / recálculo</a>
                <a class="btn secondary" href="{{ route('nomina.comisiones.diferenciales.index', $periodo) }}">Ver diferenciales</a>
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

    @php
        $filtrosEmpleadoSedes = $liquidaciones->map(fn ($liq) => $liq->empleado?->nombreSede())->filter()->unique()->sort()->values();
        $filtrosEmpleadoCargos = $liquidaciones->map(fn ($liq) => $liq->empleado?->nombreCargo())->filter()->unique()->sort()->values();
    @endphp

    <h3 style="margin:20px 0 0;">Supervisores y vendedores</h3>
    @include('nomina.partials.empleado-tabla-buscador', [
        'target' => 'tabla-comisiones-ventas',
        'filtrosSede' => $filtrosEmpleadoSedes,
        'filtrosCargo' => $filtrosEmpleadoCargos,
    ])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-comisiones-ventas">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th>Cargo</th>
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
                        $puedeEditarComision = ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true);
                        $puedeMarcarExento = $puedeEditarComision && $periodo->estado !== 'ABIERTO';
                        $puedeBonoRapido = $puedeEditarComision;
                    @endphp
                    <tr data-grupo-clave="{{ $sedeArea->grupoDeEmpleado($liq->empleado)['clave'] }}"
                        data-empleado-buscar="{{ $buscarAttrs($liq) }}"
                        data-empleado-sede="{{ $liq->empleado->nombreSede() }}"
                        data-empleado-cargo="{{ $liq->empleado->nombreCargo() }}">
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
                                <div class="muted" style="font-size:.72rem;">Supervisor de tienda</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SUPERVISOR_EQUIPO)
                                <div class="muted" style="font-size:.72rem;">Ventas del equipo</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_DIGITAL)
                                <div class="muted" style="font-size:.72rem;">Digital · ventas de trabajadores</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_PCP)
                                <div class="muted" style="font-size:.72rem;">PCP · venta neta tienda</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SAMBIL)
                                <div class="muted" style="font-size:.72rem;">Sambil · venta neta tienda</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_SAMBIL_SIN_HS)
                                <div class="muted" style="font-size:.72rem;">Sambil · sin Honor/Samsung</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_TECNOHOUSE_SIN_HS)
                                <div class="muted" style="font-size:.72rem;">Tecno House · sin Honor/Samsung</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_COLABORADOR)
                                <div class="muted" style="font-size:.72rem;">Colaborador · 5 tiendas (0,25%)</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_NUNES)
                                <div class="muted" style="font-size:.72rem;">Venta neta sede Nunes</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_MOVISTAR)
                                <div class="muted" style="font-size:.72rem;">Sin facturas ST</div>
                            @elseif($liq->modo === \App\Models\Nomina\NominaEmpleado::COMISION_VENTAS_PROPIAS_SIN_HS)
                                <div class="muted" style="font-size:.72rem;">Sin teléfonos Honor/Samsung</div>
                            @endif
                        </td>
                        <td>{{ $liq->empleado->nombreSede() }}</td>
                        <td>{{ $liq->empleado->nombreCargo() }}</td>
                        @if($esAgregado)
                            <td>
                                <strong>${{ number_format($ventaMostrada, 2) }}</strong>
                                <div class="muted" style="font-size:.72rem;">Venta neta</div>
                                @if(in_array($liq->modo, [
                                    \App\Models\Nomina\NominaEmpleado::COMISION_SAMBIL_SIN_HS,
                                    \App\Models\Nomina\NominaEmpleado::COMISION_TECNOHOUSE_SIN_HS,
                                ], true) && $liq->ventasHonorSamsungExcluidas() > 0)
                                    <div class="muted" style="font-size:.72rem;color:#b45309;margin-top:2px;">
                                        Sin Honor/Samsung: ${{ number_format($liq->ventasHonorSamsungExcluidas(), 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="muted">—</td>
                            <td class="muted">—</td>
                        @else
                            <td>
                                <strong>${{ number_format($ventaMostrada, 2) }}</strong>
                                <div class="muted" style="font-size:.72rem;">{{ $esSt ? 'Otros productos (neto)' : 'Tel + otros (neto)' }}</div>
                                @if(in_array($liq->modo, [
                                    \App\Models\Nomina\NominaEmpleado::COMISION_VENTAS_PROPIAS_SIN_HS,
                                    \App\Models\Nomina\NominaEmpleado::COMISION_SAMBIL_SIN_HS,
                                    \App\Models\Nomina\NominaEmpleado::COMISION_TECNOHOUSE_SIN_HS,
                                ], true) && $liq->ventasHonorSamsungExcluidas() > 0)
                                    <div class="muted" style="font-size:.72rem;color:#b45309;margin-top:2px;">
                                        Sin comisión:
                                        @if($liq->ventasSamsungExcluidas() > 0)
                                            Samsung ${{ number_format($liq->ventasSamsungExcluidas(), 2) }}
                                        @endif
                                        @if($liq->ventasSamsungExcluidas() > 0 && $liq->ventasHonorExcluidas() > 0)
                                            ·
                                        @endif
                                        @if($liq->ventasHonorExcluidas() > 0)
                                            Honor ${{ number_format($liq->ventasHonorExcluidas(), 2) }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>${{ number_format($liq->base_telefonia, 2) }}</td>
                            <td>${{ number_format($liq->base_otros, 2) }}</td>
                        @endif
                        <td data-col="comision">${{ number_format($comisionMostrada, 2) }}</td>
                        <td data-col="bonos">
                            @include('nomina.partials.bono-rapido', [
                                'periodo' => $periodo,
                                'empleado' => $liq->empleado,
                                'monto' => $liq->bonoManual(),
                                'bonoMeta' => $liq->bonoProductosMeta(),
                                'puede' => $puedeBonoRapido,
                            ])
                        </td>
                        <td data-col="retencion">${{ number_format($retencionMostrada, 2) }}</td>
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
                        <td data-col="descuentos">
                            @include('nomina.partials.descuento-comision-celda', [
                                'periodo' => $periodo,
                                'empleado' => $liq->empleado,
                                'monto' => $descuentosMostrados,
                                'lineas' => $liq->lineasDescuento(),
                                'titulo' => 'Desc. / préstamos',
                                'puede' => $puedeBonoRapido,
                            ])
                        </td>
                        <td data-col="pagar">
                            <strong>${{ number_format($pagarMostrado, 2) }}</strong>
                            @if($esSt)
                                <div class="muted" style="font-size:.72rem;">Parte ST abajo, sin retención</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted">Sin liquidaciones de supervisores o vendedores en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 style="margin:24px 0 0;">Servicio técnico</h3>
    @include('nomina.partials.empleado-tabla-buscador', [
        'target' => 'tabla-comisiones-st',
        'filtrosSede' => $filtrosEmpleadoSedes,
        'filtrosCargo' => $filtrosEmpleadoCargos,
    ])

    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table" id="tabla-comisiones-st">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th>Cargo</th>
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
                    <tr data-grupo-clave="{{ $sedeArea->grupoDeEmpleado($liq->empleado)['clave'] }}"
                        data-empleado-buscar="{{ $buscarAttrs($liq) }}"
                        data-empleado-sede="{{ $liq->empleado->nombreSede() }}"
                        data-empleado-cargo="{{ $liq->empleado->nombreCargo() }}">
                        <td>
                            <a href="{{ route('nomina.empleados.show', ['empleado' => $liq->empleado, 'tab' => 'comisiones']) }}">
                                {{ $liq->empleado->nombre() }}
                            </a>
                            <div class="muted" style="font-size:.72rem;">Servicio técnico</div>
                        </td>
                        <td>{{ $liq->empleado->nombreSede() }}</td>
                        <td>{{ $liq->empleado->nombreCargo() }}</td>
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
                        @php
                            $manualesSt = ($egresosStManuales ?? collect())->get($liq->empleado_id, collect());
                            $flujoSt = ($egresosStFlujo ?? collect())->get($liq->empleado_id, collect());
                            $excluidosSt = ($egresosStExcluidos ?? collect())->get($liq->empleado_id, []);
                            $puedeEgresosSt = ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true);
                        @endphp
                        <td>
                            @if($liq->egresos058() > 0)
                                ${{ number_format($liq->egresos058(), 2) }}
                            @else
                                <span class="muted">—</span>
                            @endif
                            @if($flujoSt->isNotEmpty())
                                @php $nExcluidos = count(array_intersect($excluidosSt, $flujoSt->pluck('id')->all())); @endphp
                                <details style="margin-top:4px;">
                                    <summary style="cursor:pointer;font-size:.72rem;color:#1d4ed8;">
                                        Ver egresos ({{ $flujoSt->count() }})
                                        @if($nExcluidos > 0)
                                            · {{ $nExcluidos }} sin descontar
                                        @endif
                                    </summary>
                                    <form method="POST" action="{{ route('nomina.comisiones.egresos_st.aplicar', [$periodo, $liq->empleado]) }}" style="margin-top:6px;min-width:240px;max-width:320px;">
                                        @csrf
                                        <p class="muted" style="margin:0 0 6px;font-size:.7rem;">Marca los que se descuentan de la comisión. Los desmarcados no se restan.</p>
                                        <div style="display:grid;gap:4px;max-height:180px;overflow:auto;">
                                            @foreach($flujoSt as $egreso)
                                                <label style="display:flex;gap:6px;align-items:flex-start;font-size:.72rem;{{ in_array($egreso->id, $excluidosSt, true) ? 'opacity:.65;' : '' }}">
                                                    <input
                                                        type="checkbox"
                                                        name="egreso_ids[]"
                                                        value="{{ $egreso->id }}"
                                                        @checked(! in_array($egreso->id, $excluidosSt, true))
                                                        @disabled(! $puedeEgresosSt)
                                                        style="margin-top:2px;"
                                                    >
                                                    <span>
                                                        {{ \Illuminate\Support\Carbon::parse($egreso->fecha)->format('d/m') }}
                                                        · <strong>${{ number_format((float) $egreso->monto_usd_calc, 2) }}</strong>
                                                        <span class="muted">· {{ \Illuminate\Support\Str::limit((string) $egreso->detalle, 48) }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if($puedeEgresosSt)
                                            <button type="submit" class="btn" style="font-size:.75rem;padding:4px 8px;margin-top:6px;">Aplicar y recalcular</button>
                                        @endif
                                    </form>
                                </details>
                            @endif
                            @foreach($manualesSt as $manual)
                                <div class="muted" style="font-size:.72rem;display:flex;gap:4px;align-items:center;">
                                    <span>{{ $manual->monto >= 0 ? '+' : '−' }}${{ number_format(abs((float) $manual->monto), 2) }} · {{ $manual->motivo }}</span>
                                    @if($puedeEgresosSt)
                                        <form method="POST" action="{{ route('nomina.comisiones.egresos_st.destroy', [$periodo, $manual]) }}" onsubmit="return confirm('¿Quitar este egreso 058 manual y recalcular?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Quitar" style="border:0;background:none;color:#b91c1c;cursor:pointer;font-weight:700;">×</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                            @if($puedeEgresosSt)
                                <details style="margin-top:4px;">
                                    <summary style="cursor:pointer;font-size:.72rem;color:#1d4ed8;">+ Monto manual</summary>
                                    <form method="POST" action="{{ route('nomina.comisiones.egresos_st.store', [$periodo, $liq->empleado]) }}" style="display:grid;gap:4px;margin-top:4px;min-width:180px;">
                                        @csrf
                                        <input type="number" name="monto" step="0.01" required placeholder="Monto USD (negativo resta)" style="font-size:.78rem;padding:4px 6px;">
                                        <input type="text" name="motivo" required minlength="3" maxlength="255" placeholder="Motivo (obligatorio)" style="font-size:.78rem;padding:4px 6px;">
                                        <button type="submit" class="btn" style="font-size:.75rem;padding:4px 8px;">Guardar y recalcular</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td data-col="comision">${{ number_format($comisionSt, 2) }}</td>
                        <td data-col="bonos">
                            @include('nomina.partials.bono-rapido', [
                                'periodo' => $periodo,
                                'empleado' => $liq->empleado,
                                'monto' => $liq->bonoManual(),
                                'bonoMeta' => $liq->bonoProductosMeta(),
                                'puede' => ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true),
                            ])
                        </td>
                        <td data-col="retencion">
                            $0.00
                            <div class="muted" style="font-size:.72rem;">Sin retención</div>
                        </td>
                        <td data-col="descuentos">
                            @include('nomina.partials.descuento-comision-celda', [
                                'periodo' => $periodo,
                                'empleado' => $liq->empleado,
                                'monto' => $descuentosSt,
                                'lineas' => $liq->lineasDescuento(),
                                'titulo' => 'Desc. / préstamos',
                                'puede' => ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true),
                            ])
                        </td>
                        <td data-col="pagar"><strong>${{ number_format($pagarSt, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">Sin liquidaciones de servicio técnico en esta quincena.</td></tr>
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
    $puedeAjustar = ! in_array($periodo->estado, ['PAGADO', 'CERRADO'], true);
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
    @include('nomina.comisiones._modal_txt')
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

    (function initTxtBanco() {
        const form = document.getElementById('form-txt-banco');
        if (!form) return;

        const filtroSede = document.getElementById('txt-filtro-sede');
        const filtroCargo = document.getElementById('txt-filtro-cargo');
        const contador = document.getElementById('txt-contador');
        const filas = Array.from(form.querySelectorAll('[data-txt-fila]'));
        const accionTodo = form.getAttribute('action');

        function actualizarContador() {
            const n = form.querySelectorAll('[data-txt-check]:checked:not(:disabled)').length;
            if (contador) contador.textContent = n + ' seleccionado' + (n === 1 ? '' : 's');
        }

        function aplicarFiltros() {
            const sede = (filtroSede && filtroSede.value) || '';
            const cargo = (filtroCargo && filtroCargo.value) || '';
            filas.forEach(function (fila) {
                const okSede = !sede || fila.getAttribute('data-sede') === sede;
                const okCargo = !cargo || fila.getAttribute('data-cargo') === cargo;
                fila.hidden = !(okSede && okCargo);
            });
        }

        function checksVisibles(marcar) {
            filas.forEach(function (fila) {
                if (fila.hidden) return;
                const check = fila.querySelector('[data-txt-check]');
                if (check && !check.disabled) check.checked = marcar;
            });
            actualizarContador();
        }

        function idsSeleccionados(empresaId) {
            const ids = [];
            filas.forEach(function (fila) {
                const check = fila.querySelector('[data-txt-check]');
                if (!check || check.disabled || !check.checked) return;
                if (empresaId != null && String(fila.getAttribute('data-empresa-id') || '0') !== String(empresaId)) return;
                ids.push(check.value);
            });
            return ids;
        }

        function enviarSeleccion(ids) {
            if (!ids.length) {
                alert('No hay personas marcadas para esa descarga.');
                return;
            }
            const tmp = document.createElement('form');
            tmp.method = 'POST';
            tmp.action = accionTodo;
            tmp.style.display = 'none';
            const csrf = form.querySelector('input[name="_token"]');
            if (csrf) {
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = csrf.value;
                tmp.appendChild(token);
            }
            ids.forEach(function (id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'empleados[]';
                input.value = id;
                tmp.appendChild(input);
            });
            document.body.appendChild(tmp);
            tmp.submit();
            tmp.remove();
        }

        if (filtroSede) filtroSede.addEventListener('change', aplicarFiltros);
        if (filtroCargo) filtroCargo.addEventListener('change', aplicarFiltros);
        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[data-txt-check]')) actualizarContador();
        });
        const btnMarcar = document.getElementById('txt-marcar-visibles');
        const btnDesmarcar = document.getElementById('txt-desmarcar-visibles');
        if (btnMarcar) btnMarcar.addEventListener('click', function () { checksVisibles(true); });
        if (btnDesmarcar) btnDesmarcar.addEventListener('click', function () { checksVisibles(false); });
        form.addEventListener('submit', function (event) {
            const destino = (event.submitter && event.submitter.getAttribute('formaction')) || form.getAttribute('action') || '';
            if (destino.indexOf('enviar-finanzas') !== -1) {
                return;
            }
            const n = form.querySelectorAll('[data-txt-check]:checked:not(:disabled)').length;
            if (n < 1) {
                event.preventDefault();
                alert('Selecciona al menos una persona para el TXT.');
            }
        });
        document.querySelectorAll('[data-txt-empresa]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                enviarSeleccion(idsSeleccionados(boton.getAttribute('data-txt-empresa')));
            });
        });
        actualizarContador();
    })();

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

    const params = new URLSearchParams(window.location.search);
    const tabInicial = params.get('ajuste');
    const abrir = params.get('abrir');
    if ((tabInicial || abrir === 'ajustes') && document.getElementById('modal-ajustes')) {
        document.getElementById('modal-ajustes').hidden = false;
        activarAjuste(tabInicial || 'bonos');
    }
    if (abrir === 'txt' && document.getElementById('modal-txt')) {
        document.getElementById('modal-txt').hidden = false;
    }
    if (abrir === 'sedes' && document.getElementById('modal-sedes')) {
        document.getElementById('modal-sedes').hidden = false;
    }

    function fmtUsd(n) {
        return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function actualizarKpi(selector, delta) {
        if (!delta) return;
        const el = document.querySelector(selector);
        if (!el) return;
        const actual = parseFloat(String(el.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
        el.textContent = fmtUsd(actual + delta);
    }

    document.querySelectorAll('form.com-bono-rapido').forEach(function (form) {
        const boton = form.querySelector('.com-bono-valor');
        const input = form.querySelector('.com-bono-input');
        if (!boton || !input) return;
        let actual = form.getAttribute('data-monto-actual') || '0';
        let bonoMeta = 0;
        const metaHintInicial = form.parentElement?.querySelector('.muted');
        if (metaHintInicial && /Meta\s*\$/.test(metaHintInicial.textContent || '')) {
            bonoMeta = parseFloat(String(metaHintInicial.textContent).replace(/[^0-9.-]/g, '')) || 0;
        }
        let guardando = false;

        function pintarMeta(meta) {
            bonoMeta = Number(meta || 0);
            let hint = form.parentElement?.querySelector('[data-bono-meta]');
            if (bonoMeta > 0.009) {
                if (!hint) {
                    hint = document.createElement('div');
                    hint.className = 'muted';
                    hint.setAttribute('data-bono-meta', '1');
                    hint.style.cssText = 'font-size:.72rem;color:#0f766e;margin-top:2px;';
                    form.parentElement?.appendChild(hint);
                }
                hint.textContent = 'Meta ' + fmtUsd(bonoMeta);
                hint.title = 'Valor de productos meta × unidades vendidas';
            } else if (hint) {
                hint.remove();
            }
        }

        function mostrarTexto(montoManual, meta) {
            actual = Number(montoManual || 0).toFixed(2);
            if (meta != null) {
                pintarMeta(meta);
            }
            form.setAttribute('data-monto-actual', actual);
            boton.textContent = fmtUsd(Number(actual) + Number(bonoMeta || 0));
            input.value = actual;
            input.hidden = true;
            boton.hidden = false;
        }

        function abrirEdicion() {
            if (guardando) return;
            boton.hidden = true;
            input.hidden = false;
            input.value = actual;
            input.focus();
            input.select();
        }

        function cancelarEdicion() {
            mostrarTexto(actual);
        }

        function aplicarFila(data) {
            const fila = form.closest('tr');
            if (!fila) return;
            if (data.bono_manual != null) {
                mostrarTexto(data.bono_manual, data.bono_meta);
            } else if (data.abonos != null) {
                mostrarTexto(data.abonos, data.bono_meta);
            }
            if (data.retencion != null) {
                const celda = fila.querySelector('[data-col="retencion"]');
                if (celda) {
                    const hint = celda.querySelector('.muted');
                    celda.textContent = '';
                    celda.appendChild(document.createTextNode(fmtUsd(data.retencion)));
                    if (hint) celda.appendChild(hint);
                }
            }
            if (data.total_pagar != null) {
                const celda = fila.querySelector('[data-col="pagar"]');
                if (celda) {
                    const fuerte = celda.querySelector('strong');
                    if (fuerte) fuerte.textContent = fmtUsd(data.total_pagar);
                    else celda.textContent = fmtUsd(data.total_pagar);
                }
            }
            actualizarKpi('.nomina-kpi span + strong', 0);
            const kpis = document.querySelectorAll('.nomina-kpi');
            kpis.forEach(function (kpi) {
                const label = (kpi.querySelector('span')?.textContent || '').toLowerCase();
                const strong = kpi.querySelector('strong');
                if (!strong) return;
                if (label.includes('bonos')) {
                    const actualKpi = parseFloat(String(strong.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
                    strong.textContent = fmtUsd(actualKpi + (data.delta_abonos || 0));
                }
                if (label.includes('retención') || label.includes('retencion')) {
                    const actualKpi = parseFloat(String(strong.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
                    strong.textContent = fmtUsd(actualKpi + (data.delta_retencion || 0));
                }
                if (label.includes('a pagar')) {
                    const actualKpi = parseFloat(String(strong.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
                    strong.textContent = fmtUsd(actualKpi + (data.delta_pagar || 0));
                }
            });
        }

        function guardarSiCambio() {
            if (guardando) return;
            const previo = actual;
            const nuevo = (parseFloat(String(input.value).replace(',', '.')) || 0).toFixed(2);
            if (nuevo === Number(previo).toFixed(2)) {
                cancelarEdicion();
                return;
            }

            guardando = true;
            mostrarTexto(nuevo);
            boton.textContent = '…';
            boton.disabled = true;

            const cuerpo = new FormData(form);
            cuerpo.set('monto', nuevo);

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: cuerpo,
                credentials: 'same-origin',
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    }).catch(function () {
                        return { ok: false, data: { message: 'No se pudo guardar el bono.' } };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.ok === false) {
                        throw new Error((result.data && result.data.message) || 'No se pudo guardar el bono.');
                    }
                    aplicarFila(result.data);
                })
                .catch(function (err) {
                    mostrarTexto(previo);
                    alert(err.message || 'No se pudo guardar el bono.');
                })
                .finally(function () {
                    guardando = false;
                    boton.disabled = false;
                    if (boton.textContent === '…') boton.textContent = fmtUsd(actual);
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            guardarSiCambio();
        });
        boton.addEventListener('click', abrirEdicion);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.blur();
                guardarSiCambio();
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelarEdicion();
            }
        });
        input.addEventListener('blur', function () {
            setTimeout(function () {
                if (guardando || document.activeElement === input || input.hidden) return;
                guardarSiCambio();
            }, 80);
        });
    });

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
