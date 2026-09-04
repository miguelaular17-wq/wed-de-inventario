@extends('layouts.app')

@section('title', 'Comisiones equipo · '.$periodo->etiqueta)

@section('content')
@php
    $liquidacionesSt = $liquidaciones->filter(fn ($l) => $l->esServicioTecnico())->values();
    $liquidacionesVentas = $liquidaciones->values();
    $modosAgregados = array_merge(
        \App\Models\Nomina\NominaEmpleado::modosComisionAgregadosSede(),
        \App\Models\Nomina\NominaEmpleado::modosComisionAgregadosEquipo()
    );
    $totalComisiones = (float) $liquidaciones->sum(fn ($l) => (float) $l->comision_total + (float) $l->abonos);
    $totalRetencion = (float) $liquidaciones->sum(fn ($l) => $l->esServicioTecnico()
        ? $l->retencionOtrosProductos()
        : (float) $l->retencion);
    $totalPagar = (float) $liquidaciones->sum('total_pagar');
@endphp
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nomina.equipo.index') }}" class="muted" style="font-size:.82rem;">← Nómina del equipo</a>
            <h1 style="margin:4px 0 0;">Comisiones {{ $periodo->etiqueta }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Personal a tu cargo · Pago {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: $periodo->fecha_fin?->copy()->addDays(3)->format('d/m/Y') }}
            </p>
        </div>
        <div>
            <a class="btn secondary" href="{{ route('nomina.equipo.show', $periodo) }}">Ver nómina</a>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Personas</span><strong>{{ $liquidaciones->count() }}</strong></div>
        <div class="nomina-kpi"><span>Total comisiones</span><strong>${{ number_format($totalComisiones, 2) }}</strong></div>
        <div class="nomina-kpi warn"><span>Retención</span><strong>${{ number_format($totalRetencion, 2) }}</strong></div>
        <div class="nomina-kpi"><span>A pagar</span><strong>${{ number_format($totalPagar, 2) }}</strong></div>
    </div>

    <h3 style="margin:20px 0 0;">Supervisores y vendedores</h3>
    <div class="table-wrap" style="margin-top:8px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Empleado</th>
                    <th>Cargo</th>
                    <th>Venta neta</th>
                    <th>Ventas telefonía</th>
                    <th>Ventas otros</th>
                    <th>Bonos</th>
                    <th>Total comisiones</th>
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
                        $venta = $esSt ? $liq->ventasOtrosProductos() : $liq->totalVentas();
                        $comision = $esSt ? $liq->comisionOtrosProductos() : (float) $liq->comision_total;
                        $abonos = (float) $liq->abonos;
                        $retencion = $esSt ? $liq->retencionOtrosProductos() : (float) $liq->retencion;
                        $descuentos = $esSt ? 0.0 : ((float) $liq->descuentos + (float) $liq->prestamos);
                        $pagar = $esSt
                            ? round($comision + $abonos - $retencion, 2)
                            : (float) $liq->total_pagar;
                    @endphp
                    <tr>
                        <td>{{ $liq->empleado->cedula() ?: '—' }}</td>
                        <td>
                            <strong>{{ $liq->empleado->nombre() }}</strong>
                            @if($esSt)
                                <div class="muted" style="font-size:.72rem;">ST · Otros productos</div>
                            @endif
                        </td>
                        <td>{{ $liq->empleado->nombreCargo() }}</td>
                        <td>${{ number_format($venta, 2) }}</td>
                        @if($esAgregado)
                            <td class="muted">—</td>
                            <td class="muted">—</td>
                        @else
                            <td>${{ number_format($liq->base_telefonia, 2) }}</td>
                            <td>${{ number_format($liq->base_otros, 2) }}</td>
                        @endif
                        <td>${{ number_format($abonos, 2) }}</td>
                        <td>${{ number_format($comision + $abonos, 2) }}</td>
                        <td>${{ number_format($retencion, 2) }}</td>
                        <td>${{ number_format($descuentos, 2) }}</td>
                        <td><strong>${{ number_format($pagar, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">Sin comisiones de tu equipo en esta quincena.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($liquidacionesSt->isNotEmpty())
        <h3 style="margin:24px 0 0;">Servicio técnico</h3>
        <div class="table-wrap" style="margin-top:8px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Empleado</th>
                        <th>Facturas ST</th>
                        <th>Egresos</th>
                        <th>Comisión</th>
                        <th>Retención</th>
                        <th>Desc. / préstamos</th>
                        <th>A pagar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($liquidacionesSt as $liq)
                        @php
                            $comisionSt = $liq->comisionSt();
                            $descuentosSt = (float) $liq->descuentos + (float) $liq->prestamos;
                            $pagarSt = round($comisionSt - $descuentosSt, 2);
                        @endphp
                        <tr>
                            <td>{{ $liq->empleado->cedula() ?: '—' }}</td>
                            <td><strong>{{ $liq->empleado->nombre() }}</strong></td>
                            <td>${{ number_format($liq->ventasSt(), 2) }}</td>
                            <td>${{ number_format($liq->egresos058(), 2) }}</td>
                            <td>${{ number_format($comisionSt, 2) }}</td>
                            <td>$0.00</td>
                            <td>${{ number_format($descuentosSt, 2) }}</td>
                            <td><strong>${{ number_format($pagarSt, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
