@extends('layouts.app')

@section('title', $empleado->nombre())

@php
    $tabs = [
        'personal' => 'Información personal',
        'laboral' => 'Información laboral',
        'ventas' => 'Ventas',
    ];
    if ($empleado->generaComision()) {
        $tabs['comisiones'] = 'Comisiones';
    } else {
        $tabs['mercancia'] = 'Mercancía';
    }
    $tabs += [
        'nomina' => 'Nómina',
        'prestamos' => 'Préstamos',
        'abonos' => 'Adelantos',
        'ajustes' => 'Deducciones y bonos',
    ];
    $proxima = $resumenPrestamos['proxima_cuota'] ?? null;
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="nomina-ficha-head">
        <div>
            <a href="{{ route('nomina.empleados.index') }}" class="muted" style="font-size:.82rem;">← Volver</a>
            <h1 style="margin:4px 0 0;">{{ $empleado->nombre() }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                Cargo: {{ $empleado->nombreCargo() }}  · Supervisor: {{ $empleado->nombreSupervisor() }}
            </p>
        </div>
        <div class="nomina-ficha-meta">
            <div><span>Salario mensual</span><strong>${{ number_format($empleado->salario_base, 2) }}</strong></div>
            <div><span>Estado</span><strong>{{ $empleado->estado }}</strong></div>
            <a href="{{ route('nomina.empleados.edit', $empleado) }}" class="btn secondary">Editar</a>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Ventas del período</span><strong>${{ number_format($ventasResumen['total'] ?? 0, 2) }}</strong></div>
        @if($empleado->generaComision())
            <div class="nomina-kpi"><span>Comisiones tienda</span><strong>${{ number_format($comisionQuincena, 2) }}</strong></div>
        @else
            <div class="nomina-kpi"><span>Mercancía pendiente</span><strong>${{ number_format($resumenMercancia['pendiente'] ?? 0, 2) }}</strong></div>
        @endif
        <div class="nomina-kpi"><span>Préstamos activos</span><strong>{{ $resumenPrestamos['cantidad'] }}</strong></div>
        <div class="nomina-kpi"><span>Saldo préstamos</span><strong>${{ number_format($resumenPrestamos['saldo'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Adelantos acumulado</span><strong>${{ number_format($resumenAdelantos['acumulado'], 2) }}</strong></div>
        <div class="nomina-kpi"><span>Próxima cuota</span><strong>{{ $proxima ? $proxima->fecha_programada->format('d/m/Y') : '—' }}</strong></div>
    </div>

    <nav class="nomina-tabs">
        @foreach($tabs as $key => $label)
            <a href="{{ route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($tab === 'personal')
        <div class="nomina-form-grid nomina-ficha-datos" style="margin-top:16px;">
            <div><span class="muted">Cédula</span><div>{{ $empleado->cedula() }}</div></div>
            <div><span class="muted">Nombre</span><div>{{ $empleado->nombre() }}</div></div>
            <div><span class="muted">Email</span><div>{{ $empleado->email ?: '—' }}</div></div>
            <div><span class="muted">Teléfono</span><div>{{ $empleado->telefono ?: '—' }}</div></div>
        </div>
    @endif

    @if($tab === 'laboral')
        <div class="nomina-form-grid nomina-ficha-datos" style="margin-top:16px;">
            <div><span class="muted">Sede</span><div>{{ $empleado->nombreSede() }}</div></div>
            <div><span class="muted">Empresa</span><div>{{ $empleado->nombreEmpresa() }}</div></div>
            <div><span class="muted">Cargo</span><div>{{ $empleado->nombreCargo() }}</div></div>
            <div><span class="muted">Supervisor</span><div>{{ $empleado->nombreSupervisor() }}</div></div>
            <div><span class="muted">Ingreso</span><div>{{ optional($empleado->fecha_ingreso)->format('d/m/Y') ?: '—' }}</div></div>
            <div><span class="muted">Es supervisor</span><div>{{ $empleado->es_supervisor ? 'Sí' : 'No' }}</div></div>
            <div><span class="muted">Servicio Técnico</span><div>{{ $empleado->es_servicio_tecnico ? 'Sí' : 'No' }}</div></div>
            <div><span class="muted">Código de vendedor</span><div>{{ $empleado->codigo_vendedor ?: '—' }}</div></div>
            <div class="field-wide"><span class="muted">Modo de comisión</span><div>{{ \App\Models\Nomina\NominaEmpleado::modosComision()[$empleado->modo_comision] ?? $empleado->modo_comision }}</div></div>
        </div>

        @if($empleado->subordinados->isNotEmpty())
            <h3>Personal a cargo</h3>
            <ul>
                @foreach($empleado->subordinados as $sub)
                    <li><a href="{{ route('nomina.empleados.show', $sub) }}">{{ $sub->nombre() }}</a> · {{ $sub->nombreCargo() }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    @if($tab === 'ventas')
        <p class="muted" style="margin-top:16px;">
            Quincena {{ $quincenaActual['etiqueta'] }}.
        </p>
        <p>
            <strong>{{ $ventasResumen['facturas'] }} facturas</strong>
            · {{ $ventasResumen['lineas'] }} líneas
            · Facturado ${{ number_format($ventasResumen['total'], 2) }}
        </p>

        @if(!empty($ventaDetalle['factura']))
            @php $det = $ventaDetalle['factura']; @endphp
            <div class="nomina-card nomina-factura-detalle" style="margin-top:16px;">
                <div class="panel-header-flex">
                    <div>
                        <h3 style="margin:0;">{{ $det->tipo_documento }} {{ $det->numero_documento }}</h3>
                        <p class="muted" style="margin:4px 0 0;">
                            {{ \Carbon\Carbon::parse($det->fecha)->format('d/m/Y') }}
                            · {{ $det->sede }}
                            · Vendedor {{ $det->vendedor }}
                            · Cliente {{ $det->cliente ?: '—' }}
                        </p>
                    </div>
                    <a class="btn secondary" href="{{ route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ventas']) }}">Cerrar</a>
                </div>
                <table class="data-table" style="margin-top:12px;">
                    <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead>
                    <tbody>
                        @foreach($ventaDetalle['lineas'] as $linea)
                            <tr>
                                <td>
                                    {{ $linea->nombre_producto ?: ($linea->codigo_producto ?: '—') }}
                                    @if($linea->codigo_producto)
                                        <div class="muted" style="font-size:.78rem;">{{ $linea->codigo_producto }}</div>
                                    @endif
                                </td>
                                <td>{{ $linea->cantidad }}</td>
                                <td>${{ number_format($linea->precio_venta, 2) }}</td>
                                <td>${{ number_format($linea->importe, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="nomina-recibo" style="margin-top:12px;">
                    <ul>
                        <li>Total facturado: ${{ number_format($det->total, 2) }}</li>
                        @if($det->descuento_pct > 0)
                            <li>Descuento ({{ number_format($det->descuento_pct, 0) }}%): −${{ number_format($det->descuento, 2) }}</li>
                            <li><strong>Neto cobrado: ${{ number_format($det->neto, 2) }}</strong></li>
                        @endif
                    </ul>
                </div>
            </div>
        @endif

        <table class="data-table" style="margin-top:12px;">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Sede</th>
                    <th>Documento</th>
                    <th>Cliente</th>
                    <th>Líneas</th>
                    <th>Facturado</th>
                    @if(($ventasResumen['descuento_pct'] ?? 0) > 0)
                        <th>Descuento</th>
                        <th>Neto</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($ventasFacturas as $fac)
                    @php
                        $facFecha = \Carbon\Carbon::parse($fac->fecha)->toDateString();
                        $activa = !empty($ventaDetalle['factura'])
                            && $ventaDetalle['factura']->numero_documento == $fac->numero_documento
                            && $ventaDetalle['factura']->sede == $fac->sede;
                    @endphp
                    <tr class="{{ $activa ? 'is-active' : '' }}">
                        <td>{{ \Carbon\Carbon::parse($fac->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $fac->sede }}</td>
                        <td>
                            <a class="nomina-doc-link" href="{{ route('nomina.empleados.show', [
                                'empleado' => $empleado,
                                'tab' => 'ventas',
                                'fac_sede' => $fac->sede,
                                'fac_tipo' => $fac->tipo_documento,
                                'fac_numero' => $fac->numero_documento,
                                'fac_fecha' => $facFecha,
                            ]) }}">{{ $fac->tipo_documento }} {{ $fac->numero_documento }}</a>
                        </td>
                        <td>{{ $fac->cliente ?: '—' }}</td>
                        <td>{{ $fac->lineas }}</td>
                        <td>${{ number_format($fac->total, 2) }}</td>
                        @if(($ventasResumen['descuento_pct'] ?? 0) > 0)
                            <td>${{ number_format($fac->descuento, 2) }}</td>
                            <td>${{ number_format($fac->neto, 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No hay ventas en esta quincena para ese código de vendedor.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($tab === 'mercancia')
        <div class="nomina-card" style="margin-top:16px;">
            <h3>Descuentos de mercancía</h3>
            <p class="muted">Registra lo que el empleado se lleva de la tienda. El monto se descuenta del sueldo al calcular esa quincena. Puedes cargar varios movimientos o un parcial.</p>
            <form method="POST" action="{{ route('nomina.mercancia.store', $empleado) }}" class="nomina-form-grid">
                @csrf
                <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
                <div class="field"><label>Monto a descontar (USD)</label><input type="number" name="monto" step="0.01" min="0.01" required></div>
                <div class="field field-wide"><label>Detalle</label><input name="motivo" placeholder="Ej. celular, accesorios, abono parcial"></div>
                <div class="field" style="display:flex;align-items:flex-end;"><button class="btn primary" type="submit">Registrar descuento</button></div>
            </form>
        </div>
        <table class="data-table" style="margin-top:12px;">
            <thead><tr><th>Fecha</th><th>Quincena</th><th>Monto</th><th>Estado</th><th>Usuario</th><th>Detalle</th><th></th></tr></thead>
            <tbody>
                @forelse($empleado->descuentosMercancia as $item)
                    <tr>
                        <td>{{ $item->fecha->format('d/m/Y') }}</td>
                        <td>{{ $item->etiqueta }}</td>
                        <td>${{ number_format($item->monto, 2) }}</td>
                        <td>{{ $item->estado }}{{ $item->nomina_periodo_id ? ' · nómina #'.$item->nomina_periodo_id : '' }}</td>
                        <td>{{ $item->creador?->name ?: '—' }}</td>
                        <td>{{ $item->motivo ?: '—' }}</td>
                        <td>
                            @if($item->isPendiente())
                                <form method="POST" action="{{ route('nomina.mercancia.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este descuento de mercancía?')">
                                    @csrf
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin descuentos de mercancía.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="muted" style="margin-top:8px;">Pendiente esta quincena: <strong>${{ number_format($mercanciaPendiente, 2) }}</strong>. Al calcular la nómina se aplica una sola vez.</p>
    @endif

    @if($tab === 'comisiones')
        <div class="nomina-card" style="margin-top:16px;">
            <h3>Liquidaciones</h3>
            <p class="muted">
                Modo: <strong>{{ \App\Models\Nomina\NominaEmpleado::modosComision()[$empleado->modo_comision] ?? $empleado->modo_comision }}</strong>.
            </p>
            <table class="data-table">
                <thead><tr><th>Período</th><th>Pago</th><th>Comisión</th><th>Abonos</th><th>Retención</th><th>Desc.</th><th>A pagar</th></tr></thead>
                <tbody>
                    @forelse($liquidaciones as $liq)
                        <tr>
                            <td>{{ $liq->periodo?->etiqueta ?: '—' }}</td>
                            <td>{{ $liq->fecha_pago?->format('d/m/Y') ?: '—' }}</td>
                            <td>${{ number_format($liq->comision_total, 2) }}</td>
                            <td>${{ number_format($liq->abonos, 2) }}</td>
                            <td>${{ number_format($liq->retencion, 2) }}</td>
                            <td>${{ number_format((float) $liq->descuentos + (float) $liq->prestamos, 2) }}</td>
                            <td><strong>${{ number_format($liq->total_pagar, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">Todavía no hay liquidaciones. Se generan al calcular la quincena.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="muted" style="margin-top:16px;">
            Bonificaciones y descuentos se cargan en
            <a href="{{ route('nomina.empleados.show', ['empleado' => $empleado, 'tab' => 'ajustes']) }}">Deducciones y bonos</a>,
            eligiendo si van a nómina o a comisión.
        </p>

        <div class="nomina-card" style="margin-top:16px;">
            <h3>Detalle de líneas</h3>
            <table class="data-table">
                <thead><tr><th>Período</th><th>Fecha</th><th>Grupo</th><th>Base</th><th>%</th><th>Comisión</th></tr></thead>
                <tbody>
                    @forelse($comisiones as $comision)
                        <tr>
                            <td>{{ $comision->periodo?->etiqueta ?: '—' }}</td>
                            <td>{{ $comision->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $comision->regla_snapshot['grupo'] ?? $comision->regla_snapshot['modo'] ?? $comision->origen }}</td>
                            <td>${{ number_format($comision->base_monto, 2) }}</td>
                            <td>{{ number_format($comision->porcentaje, 4) }}%</td>
                            <td><strong>${{ number_format($comision->monto_comision, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">Todavía no hay líneas calculadas para este empleado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="nomina-card" style="margin-top:16px;">
            <h3>Comisiones de marca</h3>
            <p class="muted">Las paga la marca directamente al empleado y no se incluyen en el total a pagar por la tienda.</p>
        </div>
    @endif

    @if($tab === 'nomina')
        @php
            $horasTxt = $asistencia['horas'] > 0
                ? number_format($asistencia['horas'], 2).' h · $'.number_format($asistencia['monto_horas'], 2)
                : '—';
            $ausenciasTxt = $asistencia['dias'] > 0
                ? number_format($asistencia['dias'], 2).' día(s) · $'.number_format($asistencia['monto_ausencias'], 2)
                : '—';
        @endphp
        <div class="nomina-recibo" style="margin-top:16px;">
            <h3>Estructura del recibo</h3>
            <p class="muted">Quincena {{ $quincenaActual['etiqueta'] }}. Valor diario: ${{ number_format($asistencia['valor_dia'], 2) }} · hora extra: ${{ number_format($asistencia['valor_hora'], 2) }}{{ ! empty($asistencia['hora_supervisor']) ? ' (supervisor)' : ' (trabajador)' }}.</p>
            <div class="nomina-split">
                <div>
                    <h4>Ingresos</h4>
                    <ul>
                        <li>Salario mensual: ${{ number_format($empleado->salario_base, 2) }}</li>
                        <li>Horas extras: {{ $horasTxt }}</li>
                        <li>Bonificaciones de nómina: ${{ number_format($resumenAjustes['bonos'] ?? 0, 2) }}</li>
                    </ul>
                </div>
                <div>
                    <h4>Deducciones</h4>
                    <ul>
                        <li>Adelantos de quincena pendientes: ${{ number_format($abonosPendientes, 2) }}</li>
                        <li>Préstamos (cuota): se aplicará al cerrar la quincena</li>
                        <li>Ausencias: {{ $ausenciasTxt }}</li>
                        @if($empleado->generaComision())
                            <li>Otras deducciones: ${{ number_format(($deduccionesPendientes ?? 0) + ($resumenAjustes['pendiente_nomina'] ?? 0), 2) }}</li>
                        @else
                            <li>Mercancía pendiente: ${{ number_format($mercanciaPendiente, 2) }}</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <h3>Inasistencias (IAS)</h3>
        <div class="nomina-inline-form" style="margin-bottom:12px;">
            <form method="POST" action="{{ route('nomina.inasistencias.hoy', $empleado) }}">
                @csrf
                <button class="btn primary" type="submit" {{ $asistencia['ya_falto_hoy'] ? 'disabled' : '' }}>
                    {{ $asistencia['ya_falto_hoy'] ? 'Ya está marcado que faltó hoy' : 'Faltó hoy' }}
                </button>
            </form>
        </div>
        <form method="POST" action="{{ route('nomina.inasistencias.store', $empleado) }}" class="nomina-form-grid">
            @csrf
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field"><label>Cantidad de días (IAS)</label><input type="number" step="0.5" min="0.5" name="cantidad" value="1" required></div>
            <div class="field field-wide"><label>Motivo</label><input name="motivo" placeholder="Opcional"></div>
            <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Registrar inasistencia</button></div>
        </form>
        <table class="data-table" style="margin-top:12px;">
            <thead><tr><th>Fecha</th><th>Días</th><th>Valor/día</th><th>Monto</th><th>Estado</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
                @forelse($empleado->inasistencias as $falta)
                    <tr>
                        <td>{{ $falta->fecha->format('d/m/Y') }}</td>
                        <td>{{ number_format($falta->cantidad, 2) }}</td>
                        <td>${{ number_format($falta->valor_unitario, 2) }}</td>
                        <td>${{ number_format($falta->monto, 2) }}</td>
                        <td>{{ $falta->estado }}</td>
                        <td>{{ $falta->motivo ?: '—' }}</td>
                        <td>
                            @if($falta->isPendiente())
                                <form method="POST" action="{{ route('nomina.inasistencias.cancelar', $falta) }}" onsubmit="return confirm('¿Cancelar esta inasistencia?')">
                                    @csrf
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin inasistencias.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Horas o días extras</h3>
        <p class="muted" style="margin-top:0;">
            Horas: {{ ! empty($asistencia['hora_supervisor']) ? 'supervisor $'.number_format($asistencia['valor_hora'], 2) : 'trabajador $'.number_format($asistencia['valor_hora'], 2) }}.
            Días: salario ÷ 30 (${{ number_format($asistencia['valor_dia'] ?? 0, 2) }}).
            <a href="{{ route('nomina.horas_extras.index', ['sede_id' => $empleado->sede_id]) }}">Aplicar a varios de la sede</a>
        </p>
        <form method="POST" action="{{ route('nomina.horas_extras.store', $empleado) }}" class="nomina-form-grid" id="form-extras">
            @csrf
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field">
                <label>Tipo</label>
                <select name="unidad" id="extra-unidad" onchange="sincronizarExtraUnidad()">
                    <option value="HORAS">Horas</option>
                    <option value="DIAS">Días</option>
                </select>
            </div>
            <div class="field">
                <label id="extra-cantidad-label">Horas</label>
                <input type="number" step="0.25" min="0.25" name="horas" required>
            </div>
            <div class="field field-wide"><label>Motivo</label><input name="motivo" placeholder="Opcional"></div>
            <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit" id="extra-submit">Registrar extras</button></div>
        </form>
        <table class="data-table" style="margin-top:12px;">
            <thead><tr><th>Fecha</th><th>Cantidad</th><th>Tipo</th><th>Valor</th><th>Monto</th><th>Estado</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
                @forelse($empleado->horasExtras as $extra)
                    <tr>
                        <td>{{ $extra->fecha->format('d/m/Y') }}</td>
                        <td>{{ number_format($extra->horas, 2) }}</td>
                        <td>{{ $extra->etiquetaUnidad() }}</td>
                        <td>${{ number_format($extra->valor_unitario, 2) }}</td>
                        <td>${{ number_format($extra->monto, 2) }}</td>
                        <td>{{ $extra->estado }}</td>
                        <td>{{ $extra->motivo ?: '—' }}</td>
                        <td>
                            @if($extra->isPendiente())
                                <form method="POST" action="{{ route('nomina.horas_extras.cancelar', $extra) }}" onsubmit="return confirm('¿Cancelar este extra?')">
                                    @csrf
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin horas ni días extras.</td></tr>
                @endforelse
            </tbody>
        </table>
        <script>
        function sincronizarExtraUnidad() {
            const dias = document.getElementById('extra-unidad')?.value === 'DIAS';
            const label = document.getElementById('extra-cantidad-label');
            const btn = document.getElementById('extra-submit');
            if (label) label.textContent = dias ? 'Días' : 'Horas';
            if (btn) btn.textContent = dias ? 'Registrar días extras' : 'Registrar horas extras';
        }
        </script>
    @endif

    @if($tab === 'prestamos')
        <p>
            <strong>Quincena actual:</strong> {{ $quincenaActual['etiqueta'] }}
            · <a href="{{ route('nomina.prestamos.index') }}">Escritorio de préstamos</a>
        </p>
        @if(($planesPrestamo ?? collect())->isNotEmpty())
            <div class="nomina-card" style="margin-top:12px;">
                <h3 style="margin-top:0;">Descuento de esta quincena</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Préstamo</th>
                            <th>Cuota</th>
                            <th>Monto</th>
                            <th>Se aplica en</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($planesPrestamo as $plan)
                            <tr>
                                <td>#{{ $plan->prestamo_id }}</td>
                                <td>#{{ $plan->cuota_id }}</td>
                                <td>${{ number_format((float) $plan->monto, 2) }}</td>
                                <td>{{ $plan->etiquetaDestino() }}</td>
                                <td>{{ $plan->estado }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <h3 style="margin-top:16px;">Registrar préstamo</h3>
        <form method="POST" action="{{ route('nomina.prestamos.store', $empleado) }}" class="nomina-form-grid">
            @csrf
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field"><label>Monto original</label><input type="number" step="0.01" min="0.01" name="monto_original" required></div>
            <div class="field"><label>Número de cuotas</label><input type="number" min="1" max="120" name="numero_cuotas" value="20" required></div>
            <div class="field">
                <label>Frecuencia</label>
                <select name="frecuencia">
                    <option value="QUINCENAL">Quincenal</option>
                    <option value="SEMANAL">Semanal</option>
                    <option value="MENSUAL">Mensual</option>
                </select>
            </div>
            <div class="field"><label>Inicio de cobro</label><input type="date" name="fecha_inicio" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field field-wide"><label>Motivo</label><input name="motivo" placeholder="Observación"></div>
            <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Generar calendario</button></div>
        </form>

        @foreach($empleado->prestamos as $prestamo)
            <div class="nomina-card" style="margin-top:16px;">
                <div class="panel-header-flex">
                    <div>
                        <strong>Préstamo #{{ $prestamo->id }}</strong>
                        <span class="tag {{ $prestamo->estado === 'PAGADO' ? 'ok' : ($prestamo->estado === 'CANCELADO' ? 'no' : 'warn') }}">{{ $prestamo->estado }}</span>
                        <div class="muted">{{ $prestamo->fecha->format('d/m/Y') }} · {{ $prestamo->numero_cuotas }} cuotas {{ strtolower($prestamo->frecuencia) }} · cuota ${{ number_format($prestamo->valor_cuota, 2) }}</div>
                    </div>
                    <div>
                        Pagado ${{ number_format($prestamo->totalPagado(), 2) }} · Saldo ${{ number_format($prestamo->saldo_pendiente, 2) }}
                    </div>
                </div>
                <p class="muted">{{ $prestamo->motivo }}</p>
                @if(!in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true))
                    <form method="POST" action="{{ route('nomina.prestamos.abonar', $prestamo) }}" class="nomina-form-grid" style="margin:12px 0;">
                        @csrf
                        <div class="field field-wide"><strong>Pago extra a este préstamo</strong></div>
                        <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
                        <div class="field"><label>Monto</label><input type="number" step="0.01" min="0.01" max="{{ $prestamo->saldo_pendiente }}" name="monto" required></div>
                        <div class="field">
                            <label>Tipo</label>
                            <select name="tipo">
                                @foreach(\App\Models\Nomina\NominaPrestamoAbono::tipos() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Cuota (opcional)</label>
                            <select name="cuota_id">
                                <option value="">FIFO automático</option>
                                @foreach($prestamo->cuotas->whereIn('estado', ['PENDIENTE','VENCIDA','PARCIAL']) as $cuota)
                                    <option value="{{ $cuota->id }}">#{{ $cuota->numero }} · {{ $cuota->fecha_programada->format('d/m/Y') }} · ${{ number_format($cuota->saldo(), 2) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field"><label>Observación</label><input name="observacion"></div>
                        <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Registrar pago</button></div>
                    </form>
                @endif
                <table class="data-table">
                    <thead><tr><th>#</th><th>Fecha</th><th>Monto</th><th>Pagado</th><th>Estado</th><th>Esta quincena</th><th>Nómina</th></tr></thead>
                    <tbody>
                        @foreach($prestamo->cuotas as $cuota)
                            @php $plan = ($planesPrestamo ?? collect())->get($cuota->id); @endphp
                            <tr>
                                <td>{{ $cuota->numero }}</td>
                                <td>{{ $cuota->fecha_programada->format('d/m/Y') }}</td>
                                <td>${{ number_format($cuota->monto, 2) }}</td>
                                <td>${{ number_format($cuota->monto_pagado, 2) }}</td>
                                <td>{{ $cuota->estado }}</td>
                                <td>
                                    @if($plan)
                                        ${{ number_format((float) $plan->monto, 2) }} · {{ $plan->etiquetaDestino() }}
                                        <span class="muted">({{ $plan->estado }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $cuota->nomina_periodo_id ? '#'.$cuota->nomina_periodo_id : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(!in_array($prestamo->estado, ['PAGADO', 'CANCELADO'], true))
                    <form method="POST" action="{{ route('nomina.prestamos.cancelar', $prestamo) }}" onsubmit="return confirm('¿Cancelar este préstamo? El historial se conserva.')" style="margin-top:8px;">
                        @csrf
                        <button class="btn secondary" type="submit">Cancelar préstamo</button>
                    </form>
                @endif
            </div>
        @endforeach
    @endif

    @if($tab === 'abonos')
        <h3 style="margin-top:16px;">Registrar adelanto de quincena</h3>
        <p>
            <strong>Quincena actual:</strong> {{ $quincenaActual['etiqueta'] }} · Pendiente por descontar: ${{ number_format($abonosPendientes, 2) }}
            · <a href="{{ route('nomina.adelantos.index') }}">Escritorio de adelantos</a>
        </p>
        <div class="nomina-kpis" style="margin:12px 0;">
            <div class="nomina-kpi"><span>Acumulado</span><strong>${{ number_format($resumenAdelantos['acumulado'], 2) }}</strong></div>
            <div class="nomina-kpi"><span>Pendiente</span><strong>${{ number_format($resumenAdelantos['pendiente'], 2) }}</strong></div>
            <div class="nomina-kpi"><span>Ya descontado</span><strong>${{ number_format($resumenAdelantos['descontado'], 2) }}</strong></div>
            <div class="nomina-kpi"><span>Adelantos</span><strong>{{ $resumenAdelantos['cantidad'] }}</strong></div>
        </div>
        <form method="POST" action="{{ route('nomina.abonos_sueldo.store', $empleado) }}" class="nomina-form-grid">
            @csrf
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field"><label>Monto</label><input type="number" step="0.01" min="0.01" name="monto" required></div>
            <div class="field field-wide"><label>Motivo</label><input name="motivo" placeholder="Opcional"></div>
            <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Registrar adelanto</button></div>
        </form>

        <h3>Historial de adelantos</h3>
        <table class="data-table">
            <thead><tr><th>Fecha</th><th>Quincena</th><th>Monto</th><th>Estado</th><th>Usuario</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
                @forelse($empleado->abonosSueldo as $abono)
                    <tr>
                        <td>{{ $abono->fecha->format('d/m/Y') }}</td>
                        <td>{{ $abono->etiqueta }}</td>
                        <td>${{ number_format($abono->monto, 2) }}</td>
                        <td>{{ $abono->estado }}{{ $abono->nomina_periodo_id ? ' · nómina #'.$abono->nomina_periodo_id : '' }}</td>
                        <td>{{ $abono->creador?->name ?: '—' }}</td>
                        <td>{{ $abono->motivo ?: '—' }}</td>
                        <td>
                            @if($abono->isPendiente())
                                <form method="POST" action="{{ route('nomina.abonos_sueldo.cancelar', $abono) }}" onsubmit="return confirm('¿Cancelar este adelanto? El historial se conserva.')">
                                    @csrf
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin adelantos.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="muted" style="margin-top:8px;">Los adelantos no se eliminan. Al cerrar la quincena se descuentan del sueldo una sola vez.</p>
    @endif

    @if($tab === 'ajustes')
        <h3 style="margin-top:16px;">Deducciones y bonificaciones</h3>
        <p class="muted">
            <a href="{{ route('nomina.ajustes.index') }}">Deducciones y bonos</a>.
        </p>
        <div class="nomina-kpis" style="margin:12px 0;">
            <div class="nomina-kpi"><span>Deducciones quincena</span><strong>${{ number_format($resumenAjustes['deducciones'] ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Bonos quincena</span><strong>${{ number_format($resumenAjustes['bonos'] ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Pendiente nómina</span><strong>${{ number_format($resumenAjustes['pendiente_nomina'] ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Pendiente comisión</span><strong>${{ number_format($resumenAjustes['pendiente_comision'] ?? 0, 2) }}</strong></div>
        </div>
        <form method="POST" action="{{ route('nomina.ajustes.store', $empleado) }}" class="nomina-form-grid">
            @csrf
            <div class="field"><label>Fecha</label><input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
            <div class="field">
                <label>Qué es</label>
                <select name="tipo" required>
                    <option value="DEDUCCION">Deducción</option>
                    <option value="BONIFICACION">Bonificación</option>
                </select>
            </div>
            <div class="field">
                <label>Se aplica en</label>
                <select name="destino" required>
                    <option value="NOMINA">Nómina</option>
                    @if($empleado->generaComision())
                        <option value="COMISION">Comisión</option>
                    @endif
                </select>
            </div>
            <div class="field"><label>Monto</label><input type="number" step="0.01" min="0.01" name="monto" required></div>
            <div class="field field-wide"><label>Motivo</label><input name="motivo" placeholder="Meta, daño, acuerdo…" required></div>
            <div class="field" style="display:flex; align-items:flex-end;"><button class="btn primary" type="submit">Registrar</button></div>
        </form>

        <h3>Lo que lleva</h3>
        <table class="data-table">
            <thead><tr><th>Fecha</th><th>Quincena</th><th>Tipo</th><th>Destino</th><th>Monto</th><th>Motivo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($empleado->ajustes as $item)
                    <tr>
                        <td>{{ $item->fecha->format('d/m/Y') }}</td>
                        <td>{{ $item->etiqueta }}</td>
                        <td>{{ $item->etiquetaTipo() }}</td>
                        <td>{{ $item->etiquetaDestino() }}</td>
                        <td>${{ number_format((float) $item->monto, 2) }}</td>
                        <td>{{ $item->motivo }}</td>
                        <td>{{ $item->estado }}</td>
                        <td>
                            @if($item->isPendiente())
                                <form method="POST" action="{{ route('nomina.ajustes.cancelar', $item) }}" onsubmit="return confirm('¿Cancelar este ajuste? El historial se conserva.')">
                                    @csrf
                                    <button class="btn secondary" type="submit">Cancelar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin deducciones ni bonificaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>
@endsection
