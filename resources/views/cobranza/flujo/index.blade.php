@extends('layouts.app')

@section('title', 'Flujo de caja · Cobranza')

@section('content')
@php
    $fmtUsd = fn ($v) => '$'.number_format((float) $v, 2);
    $fmtBs = fn ($v) => 'Bs. '.number_format((float) $v, 2);
    $filtrosOcultos = [
        'f_desde' => $filtros['desde'], 'f_hasta' => $filtros['hasta'], 'f_tipo' => $filtros['tipo'],
        'f_ambito' => $filtros['ambito'], 'f_cuenta' => $filtros['cuenta'],
    ];
    $netoUsd = $totales['ingresos_usd'] - $totales['egresos_usd'];
    $ingresos = $movimientos->where('tipo', 'ingreso');
    $egresos = $movimientos->where('tipo', 'egreso');
    $coloresAmbito = [
        'cobranza' => ['#eff6ff', '#1d4ed8'], 'contratos' => ['#f5f3ff', '#6d28d9'],
        'patrimonial' => ['#fff7ed', '#c2410c'], 'general' => ['#f1f5f9', '#475569'],
    ];
@endphp

<style>
    .dashboard-container { max-width: 1600px; margin: 0 auto; }
    .modern-table-container { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.05); border: 1px solid #e2e8f0; overflow: hidden; }
    .modern-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
    .modern-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 8px 12px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: .5px; font-size: 11px; }
    .modern-table td { padding: 6px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    .modern-table tbody tr:hover { background: #f8fafc; }
    .header-main { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; color: #fff !important; border-bottom: none !important; }
    .widget-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.05); border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; }
    .widget-header { background: #f8fafc; padding: 8px 12px; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; text-align: center; }
    .widget-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .widget-row:last-child { border-bottom: none; }
    .widget-label { color: #64748b; font-weight: 500; }
    .widget-value { font-weight: 600; color: #334155; }
    .widget-block { text-align: center; padding: 14px; border-bottom: 1px solid #f1f5f9; }
    .widget-block-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; font-weight: 600; }
    .widget-block-value { font-size: 20px; font-weight: 700; color: #0f172a; }
    .widget-block-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
    @media (min-width: 1200px) {
        .custom-row { display: flex; gap: 24px; }
        .custom-left { flex: 1; min-width: 0; }
        .custom-right { width: 320px; flex-shrink: 0; }
    }
    .cf-btn { padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; color: #fff; }
    .cf-btn-ingreso { background: #0f766e; box-shadow: 0 2px 4px rgba(15,118,110,.25); }
    .cf-btn-egreso { background: #1a4273; box-shadow: 0 2px 4px rgba(26,66,115,.25); }
    .cf-btn-asiento { background: #f59e0b; box-shadow: 0 2px 4px rgba(245,158,11,.25); }
    .cf-btn-gris { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
    .cf-section-title { margin: 0 0 12px; display: flex; align-items: center; gap: 8px; font-size: 1.05rem; }
    .cf-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .cf-acc { border-radius: 4px; padding: 3px 7px; font-size: .8rem; cursor: pointer; }
    .cf-acc-editar { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
    .cf-acc-borrar { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Modales */
    .cf-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.5); z-index: 1100; align-items: flex-start; justify-content: center; padding: 3vh 10px; overflow-y: auto; }
    .cf-modal.abierto { display: flex; }
    .cf-modal-box { background: #fff; width: 100%; max-width: 720px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,.15); padding: 18px 22px; position: relative; }
    .cf-modal-box h3 { margin: 0 0 14px; font-size: 1.1rem; color: var(--blue); }
    .cf-modal .ts-wrapper { width: 100%; }
    .ts-dropdown { z-index: 1200 !important; }
    .cf-cerrar { position: absolute; right: 15px; top: 12px; background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b; }
    .cf-row { display: flex; gap: 14px; margin-bottom: 10px; flex-wrap: wrap; }
    .cf-row > div { flex: 1; min-width: 150px; }
    .cf-row label, .cf-label { display: block; margin-bottom: 3px; font-weight: 500; font-size: .88rem; color: #334155; }
    .cf-row input, .cf-row select, .cf-row textarea, .cf-input { width: 100%; padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: .9rem; }
    .cf-paso { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin: 14px 0 8px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
    .cf-paso:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
    .cf-pills { display: flex; gap: 8px; flex-wrap: wrap; }
    .cf-pill { padding: 7px 14px; border-radius: 999px; border: 1.5px solid #cbd5e1; background: #fff; cursor: pointer; font-weight: 600; font-size: .85rem; color: #475569; }
    .cf-pill.activo { border-color: #1a4273; background: #1a4273; color: #fff; }
    .cf-lista { max-height: 230px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 6px; }
    .cf-opcion { display: flex; justify-content: space-between; gap: 10px; padding: 8px 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: .85rem; }
    .cf-opcion:last-child { border-bottom: none; }
    .cf-opcion:hover { background: #f8fafc; }
    .cf-opcion.activo { background: #eff6ff; box-shadow: inset 3px 0 0 #1d4ed8; }
    .cf-opcion small { color: #64748b; display: block; }
    .cf-debe { font-weight: 700; color: #b91c1c; white-space: nowrap; }
    .cf-resumen { margin-top: 10px; padding: 10px 12px; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: .88rem; display: none; }
    .cf-vacio { padding: 12px; color: #64748b; font-size: .85rem; text-align: center; }
    .cf-box-cruce { margin-top: 12px; padding: 12px; border: 1px dashed #cbd5e1; border-radius: 10px; background: #fafafa; }
</style>

<div class="dashboard-container py-4">
    {{-- ENCABEZADO --}}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0;font-size:1.5rem;color:var(--blue);font-weight:600;">Flujo de Caja · Cobranza</h2>
            <div class="muted" style="font-size:.85rem;margin-top:2px;">Cobranza, Contratos y Patrimonial. Lo que pasa por un banco real va a la conciliación bancaria.</div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;margin:0;">
                @foreach(['tipo', 'ambito', 'cuenta'] as $k)
                    @if($filtros[$k] !== '')<input type="hidden" name="{{ $k }}" value="{{ $filtros[$k] }}">@endif
                @endforeach
                <label style="font-weight:600;color:#4b5563;font-size:.9rem;">Desde:</label>
                <input type="date" name="desde" value="{{ $filtros['desde'] }}" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;" onchange="this.form.submit()">
                <label style="font-weight:600;color:#4b5563;font-size:.9rem;">Hasta:</label>
                <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;" onchange="this.form.submit()">
            </form>
            <button type="button" class="cf-btn cf-btn-ingreso" onclick="cfAbrir('ingreso')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5m-7 7l7-7 7 7"/></svg>
                Nuevo Ingreso
            </button>
            <button type="button" class="cf-btn cf-btn-egreso" onclick="cfAbrir('egreso')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14m7-7l-7 7-7-7"/></svg>
                Nuevo Egreso
            </button>
            <button type="button" class="cf-btn cf-btn-asiento" onclick="document.getElementById('cf-modal-asiento').classList.add('abierto')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                Asiento contable
            </button>
        </div>
    </div>

    @if(session('status'))
        <div style="margin-bottom:14px;padding:10px 14px;border-radius:8px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <span>{{ session('status') }}</span>
            @if(session('recibo_id'))
                <a href="{{ route('cobranza.flujo.recibo', session('recibo_id')) }}" target="_blank" rel="noopener" id="cf-recibo-nuevo"
                   style="padding:6px 14px;border-radius:7px;background:#065f46;color:#fff;font-weight:700;text-decoration:none;white-space:nowrap;">🧾 Recibo de pago</a>
                <script>window.addEventListener('load', () => { try { window.open(document.getElementById('cf-recibo-nuevo').href, '_blank'); } catch (e) {} });</script>
            @endif
        </div>
    @endif
    @if($errors->any())
        <div style="margin-bottom:14px;padding:10px 14px;border-radius:8px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    {{-- FILTROS --}}
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;">
        <input type="hidden" name="desde" value="{{ $filtros['desde'] }}">
        <input type="hidden" name="hasta" value="{{ $filtros['hasta'] }}">
        <div>
            <label class="cf-label">Apartado</label>
            <select name="ambito" class="cf-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($ambitos as $clave => $nombre)
                    <option value="{{ $clave }}" @selected($filtros['ambito'] === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="cf-label">Cuenta</label>
            <select name="cuenta" class="cf-input" onchange="this.form.submit()" style="min-width:240px;">
                <option value="">Todas</option>
                @foreach($cuentas as $grupo => $lista)
                    <optgroup label="{{ $grupo }}">
                        @foreach($lista as $c)
                            @php $valorFiltro = $c['banco'].'|'.$c['titular']; @endphp
                            <option value="{{ $valorFiltro }}" @selected($filtros['cuenta'] === $valorFiltro)>{{ $c['banco'] }} - {{ $c['titular'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <label style="font-weight:500;font-size:.88rem;display:flex;gap:6px;align-items:center;padding-bottom:8px;">
            <input type="checkbox" name="solo_cruces" value="1" @checked($filtros['solo_cruces']) onchange="this.form.submit()"> Solo cruces
        </label>
        <a href="{{ route('cobranza.flujo.excel', array_filter($filtros, fn ($v) => $v !== '' && $v !== false)) }}" class="cf-btn cf-btn-gris"
           style="text-decoration:none;margin-left:auto;">📊 Exportar Excel</a>
    </form>

    <div class="custom-row">
        <div class="custom-left">
            @foreach([['ingreso', 'INGRESOS', $ingresos, '#0f766e', '#99f6e4', '#f0fdfa'], ['egreso', 'EGRESOS', $egresos, '#b91c1c', '#fecaca', '#fef2f2']] as [$tipoTabla, $tituloTabla, $lista, $color, $borde, $fondo])
                <h3 class="cf-section-title" style="color:{{ $color }};">
                    @if($tipoTabla === 'ingreso')
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5m-7 7l7-7 7 7"/></svg>
                    @else
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14m7-7l-7 7-7-7"/></svg>
                    @endif
                    {{ $tituloTabla }}
                </h3>
                <div class="panel" style="padding:0;overflow:hidden;margin-bottom:28px;border:1.5px solid {{ $borde }};">
                    <div class="table-wrap">
                        <table class="data-table" style="width:100%;">
                            <thead>
                                <tr style="background:{{ $fondo }};">
                                    <th style="width:90px;">Fecha</th>
                                    <th>Cuenta</th>
                                    <th>Qué se paga</th>
                                    <th>Concepto</th>
                                    <th>Ref.</th>
                                    <th style="text-align:right;">Monto Bs</th>
                                    <th style="text-align:right;">Monto USD</th>
                                    <th style="text-align:center;">Banco</th>
                                    <th style="text-align:center;width:90px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lista as $m)
                                    @php
                                        $pago = $pagos[$m->id] ?? null;
                                        [$bgA, $fgA] = $coloresAmbito[$m->ambito] ?? $coloresAmbito['general'];
                                        $otra = $m->esCruce() ? $movimientos->first(fn ($x) => $x->cruce_id === $m->cruce_id && $x->id !== $m->id) : null;
                                        $edicion = [
                                            'fecha' => $m->fecha->toDateString(), 'ambito' => $m->ambito,
                                            'cuenta' => $m->banco.'|'.$m->titular.'|'.$m->categoria_cuenta,
                                            'monto_usd' => (float) $m->monto_usd, 'tasa_cambio' => (float) $m->tasa_cambio, 'monto_bs' => (float) $m->monto_bs,
                                            'referencia' => $m->referencia, 'concepto' => $m->concepto, 'contraparte' => $m->contraparte,
                                            'observaciones' => $m->observaciones, 'conciliado' => $m->estaConciliado(), 'tipo_gasto' => $m->tipo_gasto, 'comprobantes' => $m->comprobantes ?? [],
                                            'modulo' => \App\Services\Cobranza\CobranzaFlujoService::VINCULOS_MODULO[$m->vinculo_tipo] ?? null,
                                            'pago' => $pago ? $pago['titulo'].' · '.$pago['detalle'] : null,
                                            'tipo' => $m->tipo, 'url' => route('cobranza.flujo.update', $m),
                                        ];
                                    @endphp
                                    <tr>
                                        <td>{{ $m->fecha->format('d/m/Y') }}</td>
                                        <td>
                                            <strong style="color:{{ $color }};">{{ $m->banco }}</strong><br>
                                            <span class="muted" style="font-size:.82rem;">{{ $m->titular }}</span>
                                        </td>
                                        <td style="min-width:220px;">
                                            <span class="cf-badge" style="background:{{ $bgA }};color:{{ $fgA }};">{{ $m->nombreAmbito() }}</span>
                                            @if($m->esCruce())<span class="cf-badge" style="background:#eef2ff;color:#4338ca;">Cruce</span>@endif
                                            @if($pago)
                                                <div style="font-weight:600;margin-top:3px;">{{ $pago['titulo'] }}</div>
                                                <div class="muted" style="font-size:.8rem;">{{ $pago['detalle'] }}</div>
                                            @elseif($otra)
                                                <div class="muted" style="font-size:.8rem;margin-top:3px;">{{ $m->esIngreso() ? 'Se fue en' : 'Vino de' }}: {{ $otra->concepto }}</div>
                                            @else
                                                <div class="muted" style="font-size:.8rem;margin-top:3px;">Sin asignar a una factura, cuota o propiedad</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $m->concepto }}
                                            @if($m->contraparte)<div class="muted" style="font-size:.8rem;">{{ $m->esIngreso() ? 'Paga' : 'Para' }}: {{ $m->contraparte }}</div>@endif
                                            @if(! $m->esIngreso() && $m->tipo_gasto)<div style="font-size:.75rem;color:#b45309;">{{ $m->tipo_gasto }}</div>@endif
                                            @foreach($m->comprobantes ?? [] as $i => $url)
                                                <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:.75rem;margin-right:6px;" title="Comprobante {{ $i + 1 }}">📎 Comprobante{{ count($m->comprobantes) > 1 ? ' '.($i + 1) : '' }}</a>
                                            @endforeach
                                            @if($otra && $pago)<div class="muted" style="font-size:.8rem;">Se fue en: {{ $otra->concepto }}</div>@endif
                                            @if(! empty($asientoDeMovimiento[$m->id]))
                                                <div style="font-size:.75rem;color:#b45309;">📒 Asiento contra {{ $asientoDeMovimiento[$m->id] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $m->referencia ?: '-' }}</td>
                                        <td style="text-align:right;font-weight:500;color:{{ $color }};">{{ $fmtBs($m->monto_bs) }}</td>
                                        <td style="text-align:right;">{{ $fmtUsd($m->monto_usd) }}
                                            @if((float) $m->tasa_cambio > 0)<div class="muted" style="font-size:.72rem;">tasa {{ number_format((float) $m->tasa_cambio, 2) }}</div>@endif
                                        </td>
                                        <td style="text-align:center;font-size:.75rem;">
                                            @if(\App\Models\CobranzaMovimiento::esBancoCruce($m->banco))
                                                <span class="muted">Cruce</span>
                                            @elseif(! $m->flujo_caja_id)
                                                <span class="muted">Sin banco</span>
                                            @elseif($m->estaConciliado())
                                                <span style="color:#15803d;font-weight:700;">✓ en el banco</span>
                                            @else
                                                <span style="color:#b45309;font-weight:600;">En tránsito</span>
                                            @endif
                                        </td>
                                        <td style="text-align:center;white-space:nowrap;">
                                            @if($m->esIngreso())
                                                <a href="{{ route('cobranza.flujo.recibo', $m) }}" target="_blank" rel="noopener" class="cf-acc" title="Recibo de pago" style="text-decoration:none;">🧾</a>
                                            @endif
                                            <button type="button" class="cf-acc cf-acc-editar" title="Editar" onclick='cfEditar(@json($edicion))'>✏️</button>
                                            <form method="POST" action="{{ route('cobranza.flujo.destroy', $m) }}" style="display:inline;"
                                                  onsubmit="return confirm('{{ $m->esCruce() ? '¿Eliminar el cruce completo (ingreso y egreso)?' : '¿Eliminar este movimiento?' }}');">
                                                @csrf
                                                @method('DELETE')
                                                @foreach($filtrosOcultos as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                                <button type="submit" class="cf-acc cf-acc-borrar" title="Eliminar">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" style="text-align:center;padding:26px;color:var(--muted);">No hay {{ strtolower($tituloTabla) }} en este período.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr style="background:{{ $fondo }};border-top:2px solid {{ $borde }};font-weight:bold;">
                                    <td colspan="5" style="text-align:right;color:{{ $color }};">TOTAL {{ $tituloTabla }}</td>
                                    <td style="text-align:right;color:{{ $color }};">{{ $fmtBs($lista->sum('monto_bs')) }}</td>
                                    <td style="text-align:right;color:{{ $color }};">{{ $fmtUsd($lista->sum('monto_usd')) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- SALDOS POR CUENTA --}}
            @if($saldosCuentas)
                <div class="modern-table-container" style="margin-bottom:28px;">
                    <table class="modern-table">
                        <thead>
                            <tr><th colspan="7" class="header-main" style="font-size:13px;">SALDOS POR CUENTA DEL PERÍODO (FLUJO + ASIENTOS)</th></tr>
                            <tr>
                                <th>Cuenta</th>
                                <th style="text-align:right;">Entradas $</th>
                                <th style="text-align:right;">Salidas $</th>
                                <th style="text-align:right;">Saldo $</th>
                                <th style="text-align:right;">Entradas Bs</th>
                                <th style="text-align:right;">Salidas Bs</th>
                                <th style="text-align:right;">Saldo Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($saldosCuentas as $s)
                                <tr>
                                    <td style="font-weight:500;color:#0f172a;">{{ $s['cuenta'] }}</td>
                                    <td style="text-align:right;">{{ $fmtUsd($s['entradas_usd']) }}</td>
                                    <td style="text-align:right;">{{ $fmtUsd($s['salidas_usd']) }}</td>
                                    <td style="text-align:right;font-weight:700;color:{{ $s['saldo_usd'] < 0 ? '#b91c1c' : '#0f172a' }};">{{ $fmtUsd($s['saldo_usd']) }}</td>
                                    <td style="text-align:right;">{{ $fmtBs($s['entradas_bs']) }}</td>
                                    <td style="text-align:right;">{{ $fmtBs($s['salidas_bs']) }}</td>
                                    <td style="text-align:right;font-weight:700;color:{{ $s['saldo_bs'] < 0 ? '#b91c1c' : '#0f172a' }};">{{ $fmtBs($s['saldo_bs']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ASIENTOS CONTABLES --}}
            <h3 class="cf-section-title" style="color:#b45309;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                ASIENTOS CONTABLES
            </h3>
            <div class="panel" style="padding:0;overflow:hidden;margin-bottom:28px;border:1.5px solid #fde68a;">
                <div class="table-wrap">
                    <table class="data-table" style="width:100%;">
                        <thead>
                            <tr style="background:#fffbeb;">
                                <th style="width:90px;">Fecha</th><th>Asiento</th><th>Cuenta</th>
                                <th style="text-align:right;">Debe</th><th style="text-align:right;">Haber</th><th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($asientos as $a)
                                @foreach($a->lineas as $i => $l)
                                    <tr>
                                        @if($i === 0)
                                            <td rowspan="{{ $a->lineas->count() }}">{{ $a->fecha->format('d/m/Y') }}</td>
                                            <td rowspan="{{ $a->lineas->count() }}">
                                                <strong>{{ $a->descripcion }}</strong>
                                                <div class="muted" style="font-size:.78rem;">{{ $ambitos[$a->ambito] ?? $a->ambito }}{{ $a->usuario ? ' · '.$a->usuario->name : '' }}</div>
                                                @if($a->movimiento_id && $a->movimiento)
                                                    <div style="font-size:.75rem;color:#0f766e;">Del {{ $a->movimiento->esIngreso() ? 'ingreso' : 'egreso' }}: {{ $a->movimiento->concepto }}</div>
                                                @endif
                                            </td>
                                        @endif
                                        <td style="{{ (float) $l->haber_usd > 0 || (float) $l->haber_bs > 0 ? 'padding-left:28px;' : '' }}">
                                            {{ $l->cuenta }}
                                            @if($l->detalle)<div class="muted" style="font-size:.78rem;">{{ $l->detalle }}</div>@endif
                                        </td>
                                        <td style="text-align:right;">
                                            @if((float) $l->debe_usd > 0){{ $fmtUsd($l->debe_usd) }}@endif
                                            @if((float) $l->debe_bs > 0)<div>{{ $fmtBs($l->debe_bs) }}</div>@endif
                                        </td>
                                        <td style="text-align:right;">
                                            @if((float) $l->haber_usd > 0){{ $fmtUsd($l->haber_usd) }}@endif
                                            @if((float) $l->haber_bs > 0)<div>{{ $fmtBs($l->haber_bs) }}</div>@endif
                                        </td>
                                        @if($i === 0)
                                            <td rowspan="{{ $a->lineas->count() }}" style="text-align:center;">
                                                <form method="POST" action="{{ route('cobranza.flujo.asientos.destroy', $a) }}" onsubmit="return confirm('¿Eliminar este asiento?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    @foreach($filtrosOcultos as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                                    <button type="submit" class="cf-acc cf-acc-borrar" title="Eliminar">🗑️</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="6" style="text-align:center;padding:22px;color:var(--muted);">No hay asientos en este período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- RESUMEN --}}
        <div class="custom-right">
            <div class="widget-card">
                <div class="widget-header">Resumen del período</div>
                <div class="widget-block">
                    <div class="widget-block-label">Ingresos</div>
                    <div class="widget-block-value" style="color:#0f766e;">{{ $fmtUsd($totales['ingresos_usd']) }}</div>
                    <div class="widget-block-sub">{{ $fmtBs($totales['ingresos_bs']) }}</div>
                </div>
                <div class="widget-block">
                    <div class="widget-block-label">Egresos</div>
                    <div class="widget-block-value" style="color:#b91c1c;">{{ $fmtUsd($totales['egresos_usd']) }}</div>
                    <div class="widget-block-sub">{{ $fmtBs($totales['egresos_bs']) }}</div>
                </div>
                <div class="widget-block" style="border-bottom:none;">
                    <div class="widget-block-label">Neto</div>
                    <div class="widget-block-value" style="color:{{ $netoUsd < 0 ? '#b91c1c' : '#0f172a' }};">{{ $fmtUsd($netoUsd) }}</div>
                </div>
            </div>
            <div class="widget-card">
                <div class="widget-header">Por apartado (neto $)</div>
                @foreach($ambitos as $clave => $nombre)
                    @php $pa = $totales['por_ambito'][$clave]; @endphp
                    <div class="widget-row">
                        <span class="widget-label">{{ $nombre }}</span>
                        <span class="widget-value" title="+{{ $fmtUsd($pa['ingresos_usd']) }} / −{{ $fmtUsd($pa['egresos_usd']) }}">{{ $fmtUsd($pa['ingresos_usd'] - $pa['egresos_usd']) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="widget-card">
                <div class="widget-header">Otros datos</div>
                <div class="widget-row"><span class="widget-label">Cruces del período</span><span class="widget-value">{{ $totales['cruces'] }}</span></div>
                <div class="widget-row"><span class="widget-label">Movimientos</span><span class="widget-value">{{ $movimientos->count() }}</span></div>
                <div class="widget-row"><span class="widget-label">Tasa BCV hoy</span><span class="widget-value" style="color:#b45309;">Bs. {{ number_format((float) $tasaHoy, 2) }}</span></div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL NUEVO MOVIMIENTO --}}
<div id="cf-modal-nuevo" class="cf-modal">
    <div class="cf-modal-box">
        <button type="button" class="cf-cerrar" onclick="cfCerrar('cf-modal-nuevo')">&times;</button>
        <h3 id="cf-titulo">Nuevo Ingreso</h3>
        <form method="POST" action="{{ route('cobranza.flujo.store') }}" id="cf-form" enctype="multipart/form-data">
            @csrf
            @foreach($filtrosOcultos as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <input type="hidden" name="tipo" id="cf-tipo" value="{{ old('tipo', 'ingreso') }}">
            <input type="hidden" name="ambito" id="cf-ambito" value="{{ old('ambito', 'cobranza') }}">
            <input type="hidden" name="vinculo" id="cf-vinculo" value="{{ old('vinculo') }}">
            <input type="hidden" name="factura_sede" id="cf-factura-sede" value="{{ old('factura_sede', $factura['sede'] ?? '') }}">
            <input type="hidden" name="factura_doc" id="cf-factura-doc" value="{{ old('factura_doc', $factura['doc'] ?? '') }}">

            <div class="cf-paso">1 · ¿A qué apartado pertenece?</div>
            <div class="cf-pills" id="cf-pills">
                @foreach($ambitos as $clave => $nombre)
                    <button type="button" class="cf-pill" data-ambito="{{ $clave }}">{{ $nombre }}</button>
                @endforeach
            </div>

            <div class="cf-paso" id="cf-paso-que">2 · ¿Qué se está pagando?</div>
            {{-- Cobranza: factura del cliente --}}
            <div class="cf-que" data-ambito="cobranza">
                <div data-solo="ingreso">
                    <input type="text" id="cf-buscar-factura" class="cf-input" placeholder="Buscar cliente, código o número de factura…" autocomplete="off">
                    <div class="cf-lista" id="cf-facturas"><div class="cf-vacio">Escribe para buscar las facturas con deuda.</div></div>
                </div>
                <div data-solo="egreso" class="cf-vacio" style="text-align:left;">Egreso de Cobranza: escribe abajo en qué se usó el dinero.</div>
            </div>
            {{-- Contratos: contrato y cuota --}}
            <div class="cf-que" data-ambito="contratos">
                <div data-solo="ingreso">
                    <input type="text" id="cf-buscar-contrato" class="cf-input" placeholder="Buscar contrato o cliente…" autocomplete="off">
                    <div class="cf-lista" id="cf-contratos"></div>
                    <div id="cf-cuotas-wrap" style="display:none;">
                        <div class="cf-label" style="margin-top:10px;">Cuota que paga</div>
                        <div class="cf-lista" id="cf-cuotas"></div>
                    </div>
                </div>
                <div data-solo="egreso" class="cf-vacio" style="text-align:left;">Egreso de Contratos (por ejemplo, un préstamo entregado): escribe abajo el concepto.</div>
            </div>
            {{-- Patrimonial: propiedad y qué se paga --}}
            <div class="cf-que" data-ambito="patrimonial">
                <div class="cf-pills" style="margin-bottom:10px;">
                    <button type="button" class="cf-pill activo" id="cf-modo-una" style="font-size:.8rem;padding:5px 12px;">Una propiedad</button>
                    <button type="button" class="cf-pill" id="cf-modo-reparto" style="font-size:.8rem;padding:5px 12px;">Repartir entre varias propiedades</button>
                </div>
                <div id="cf-reparto" style="display:none;">
                    <table class="modern-table" style="border:1px solid #e2e8f0;border-radius:8px;">
                        <thead><tr><th>Propiedad</th><th id="cf-reparto-tipo-th">Tipo de gasto</th><th style="text-align:right;">Monto USD</th><th></th></tr></thead>
                        <tbody id="cf-reparto-filas"></tbody>
                    </table>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:8px;">
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="cf-btn cf-btn-gris" style="padding:5px 12px;" onclick="cfRepartoFila()">+ Agregar propiedad</button>
                            <button type="button" class="cf-btn cf-btn-gris" style="padding:5px 12px;" onclick="cfRepartoIguales()">Partes iguales</button>
                        </div>
                        <div id="cf-reparto-estado" style="font-weight:700;font-size:.88rem;"></div>
                    </div>
                </div>
                <div class="cf-row" id="cf-una-propiedad">
                    <div>
                        <label>Propiedad</label>
                        <select id="cf-propiedad">
                            <option value="">Elige la propiedad…</option>
                            @foreach($propiedades as $p)
                                <option value="{{ $p['id'] }}">{{ $p['nombre'] }}{{ $p['codigo'] ? ' ('.$p['codigo'].')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="cf-prop-opciones" style="display:none;">
                    <div class="cf-label">¿Qué se paga de esta propiedad?</div>
                    <div class="cf-lista" id="cf-prop-lista"></div>
                    <div id="cf-reserva-nueva" style="display:none;margin-top:10px;padding:10px 12px;border:1px solid #fed7aa;border-radius:8px;background:#fff7ed;">
                        <div class="cf-row">
                            <div style="flex:2;"><label>Cliente</label><input type="text" name="reserva_cliente" id="cf-res-cliente" maxlength="256" value="{{ old('reserva_cliente') }}" disabled></div>
                            <div><label>Contacto</label><input type="text" name="reserva_contacto" id="cf-res-contacto" maxlength="256" value="{{ old('reserva_contacto') }}" disabled></div>
                        </div>
                        <div class="cf-row" style="margin-bottom:4px;">
                            <div><label>Desde (entrada)</label><input type="date" name="reserva_entrada" id="cf-res-entrada" value="{{ old('reserva_entrada') }}" disabled></div>
                            <div><label>Hasta (salida)</label><input type="date" name="reserva_salida" id="cf-res-salida" value="{{ old('reserva_salida') }}" disabled></div>
                            <div><label>Precio por noche $</label><input type="number" step="0.01" min="0" name="reserva_precio_noche" id="cf-res-precio" value="{{ old('reserva_precio_noche') }}" disabled></div>
                            <div><label>Comisión $</label><input type="number" step="0.01" min="0" name="reserva_comision" id="cf-res-comision" value="{{ old('reserva_comision') }}" placeholder="0.00" disabled></div>
                        </div>
                        <div id="cf-res-info" class="muted" style="font-size:.82rem;"></div>
                    </div>
                    <div class="cf-row" id="cf-prop-categoria-wrap" style="margin-top:8px;display:none;">
                        <div>
                            <label id="cf-prop-categoria-label">Tipo de ingreso</label>
                            <select name="pat_categoria" id="cf-prop-categoria"></select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cf-que" data-ambito="general">
                <div class="cf-vacio" style="text-align:left;">Movimiento general: no se aplica a una factura, cuota ni propiedad.</div>
            </div>
            <div class="cf-resumen" id="cf-resumen"></div>

            <div id="cf-extra-pago" class="cf-row" style="margin-top:10px;display:none;">
                <div>
                    <label>Forma de pago</label>
                    <select name="forma_pago">
                        <option value="">Según la cuenta</option>
                        @foreach(\App\Models\CobranzaMovimiento::FORMAS_PAGO as $fp)
                            <option value="{{ $fp }}" @selected(old('forma_pago') === $fp)>{{ $fp === 'DESCUENTO_DIVISAS' ? 'DESCUENTO EN DIVISAS' : str_replace('_', ' ', $fp) }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="cf-abono-capital-wrap">
                    <label>De eso, abono a capital ($)</label>
                    <input type="number" name="abono_capital" step="0.01" min="0" value="{{ old('abono_capital') }}" placeholder="0.00">
                </div>
            </div>

            <div class="cf-paso">3 · Dinero</div>
            <div class="cf-row">
                <div><label>Fecha</label><input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                <div style="flex:2;">
                    <label id="cf-label-cuenta">Cuenta que recibe el dinero</label>
                    <select name="cuenta" id="cf-cuenta" required>
                        <option value="">-- Seleccione --</option>
                        @foreach($cuentas as $grupo => $lista)
                            <optgroup label="{{ $grupo }}">
                                @foreach($lista as $c)
                                    <option value="{{ $c['valor'] }}" @selected(old('cuenta') === $c['valor'])>{{ $c['banco'] }} - {{ $c['titular'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div><label>Ref.</label><input type="text" name="referencia" maxlength="120" value="{{ old('referencia') }}" placeholder="# Referencia"></div>
            </div>
            <div class="cf-row">
                <div><label>Monto USD</label><input type="number" name="monto_usd" id="cf-usd" step="0.01" min="0" value="{{ old('monto_usd', $factura['saldo'] ?? '') }}"></div>
                <div><label>Tasa de Cambio</label><input type="number" name="tasa_cambio" id="cf-tasa" step="0.0001" min="0" value="{{ old('tasa_cambio', $tasaHoy ?: '') }}"></div>
                <div><label>Monto BS</label><input type="number" name="monto_bs" id="cf-bs" step="0.01" min="0" value="{{ old('monto_bs') }}"></div>
            </div>

            <div class="cf-paso">4 · Detalle</div>
            <div class="cf-row">
                <div style="flex:2;"><label>Concepto</label><input type="text" name="concepto" id="cf-concepto" maxlength="255" required value="{{ old('concepto') }}"></div>
                <div><label id="cf-label-contraparte">Quién paga</label><input type="text" name="contraparte" id="cf-contraparte" maxlength="255" value="{{ old('contraparte') }}"></div>
            </div>
            <div class="cf-row" data-solo="egreso">
                <div>
                    <label>Tipo de gasto</label>
                    <select name="tipo_gasto" id="cf-tipo-gasto">
                        <option value="">-- Seleccione un tipo de gasto --</option>
                        @foreach($tiposGasto as $tg)<option value="{{ $tg }}" @selected(old('tipo_gasto') === $tg)>{{ $tg }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="cf-row">
                <div><label>Observaciones</label><textarea name="observaciones" rows="2" maxlength="2000">{{ old('observaciones') }}</textarea></div>
            </div>
            <div class="cf-row">
                <div>
                    <label>📎 Comprobante de pago <span class="muted" style="font-weight:400;">(imagen o PDF, hasta 6)</span></label>
                    <input type="file" name="comprobantes[]" multiple accept="image/*,.pdf">
                </div>
            </div>

            <div class="cf-box-cruce" id="cf-bloque-cruce">
                <label style="font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="cruce" value="1" id="cf-cruce" @checked(old('cruce'))>
                    Es un cruce: registrar también el egreso del mismo dinero
                </label>
                <div id="cf-campos-cruce" style="margin-top:10px;display:none;">
                    <div class="cf-row">
                        <div style="flex:2;"><label>¿En qué se fue el dinero?</label><input type="text" name="cruce_destino" id="cf-cruce-destino" maxlength="255" value="{{ old('cruce_destino') }}" placeholder="Ej.: Pago a proveedor X"></div>
                        <div>
                            <label>Apartado del egreso</label>
                            <select name="cruce_ambito">
                                <option value="">El mismo</option>
                                @foreach($ambitos as $clave => $nombre)<option value="{{ $clave }}" @selected(old('cruce_ambito') === $clave)>{{ $nombre }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="cf-row">
                        <div><label>A quién se le pagó</label><input type="text" name="cruce_beneficiario" maxlength="255" value="{{ old('cruce_beneficiario') }}"></div>
                        <div>
                            <label>Tipo de gasto del egreso</label>
                            <select name="cruce_tipo_gasto" id="cf-cruce-tipo-gasto">
                                <option value="">-- Seleccione un tipo de gasto --</option>
                                @foreach($tiposGasto as $tg)<option value="{{ $tg }}" @selected(old('cruce_tipo_gasto') === $tg)>{{ $tg }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="muted" style="font-size:.8rem;">El cruce va en <strong>Banco Cruce Cobranza</strong>: no pasa por el banco ni por la conciliación.</div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                <button type="button" class="cf-btn cf-btn-gris" onclick="cfCerrar('cf-modal-nuevo')">Cancelar</button>
                <button type="submit" class="cf-btn cf-btn-egreso" id="cf-guardar">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL EDITAR --}}
<div id="cf-modal-editar" class="cf-modal">
    <div class="cf-modal-box" style="max-width:640px;">
        <button type="button" class="cf-cerrar" onclick="cfCerrar('cf-modal-editar')">&times;</button>
        <h3 id="cfe-titulo">Editar movimiento</h3>
        <form method="POST" id="cf-edit-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @foreach($filtrosOcultos as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <div id="cfe-pago" class="cf-resumen" style="display:none;margin-top:0;margin-bottom:10px;"></div>
            <p id="cfe-aviso" class="muted" style="margin:0 0 10px;font-size:.82rem;display:none;"></p>
            <div class="cf-row">
                <div><label>Fecha</label><input type="date" name="fecha" id="cfe-fecha" required></div>
                <div>
                    <label>Apartado</label>
                    <select name="ambito" id="cfe-ambito">
                        @foreach($ambitos as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                    </select>
                </div>
                <div><label>Ref.</label><input type="text" maxlength="120" name="referencia" id="cfe-referencia"></div>
            </div>
            <div class="cf-row">
                <div>
                    <label>Cuenta</label>
                    <select name="cuenta" id="cfe-cuenta" required>
                        @foreach($cuentas as $grupo => $lista)
                            <optgroup label="{{ $grupo }}">
                                @foreach($lista as $c)<option value="{{ $c['valor'] }}">{{ $c['banco'] }} - {{ $c['titular'] }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="cf-row">
                <div><label>Monto USD</label><input type="number" step="0.01" min="0" name="monto_usd" id="cfe-usd"></div>
                <div><label>Tasa</label><input type="number" step="0.0001" min="0" name="tasa_cambio" id="cfe-tasa"></div>
                <div><label>Monto BS</label><input type="number" step="0.01" min="0" name="monto_bs" id="cfe-bs"></div>
            </div>
            <div class="cf-row">
                <div style="flex:2;"><label>Concepto</label><input type="text" maxlength="255" name="concepto" id="cfe-concepto" required></div>
                <div><label>Contraparte</label><input type="text" maxlength="255" name="contraparte" id="cfe-contraparte"></div>
            </div>
            <div class="cf-row" id="cfe-tipo-gasto-wrap">
                <div>
                    <label>Tipo de gasto</label>
                    <select name="tipo_gasto" id="cfe-tipo-gasto">
                        <option value="">-- Seleccione un tipo de gasto --</option>
                        @foreach($tiposGasto as $tg)<option value="{{ $tg }}">{{ $tg }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="cf-row"><div><label>Observaciones</label><textarea name="observaciones" id="cfe-observaciones" rows="2"></textarea></div></div>
            <div class="cf-row">
                <div>
                    <label>📎 Comprobantes</label>
                    <div id="cfe-comprobantes" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:6px;"></div>
                    <input type="file" name="comprobantes[]" multiple accept="image/*,.pdf">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
                <button type="button" class="cf-btn cf-btn-gris" onclick="cfCerrar('cf-modal-editar')">Cancelar</button>
                <button type="submit" class="cf-btn cf-btn-egreso">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL ASIENTO --}}
<div id="cf-modal-asiento" class="cf-modal">
    <div class="cf-modal-box" style="max-width:780px;">
        <button type="button" class="cf-cerrar" onclick="cfCerrar('cf-modal-asiento')">&times;</button>
        <h3>Asiento contable</h3>
        <p class="muted" style="margin:-6px 0 12px;font-size:.84rem;">Una cuenta al debe y una o varias al haber, en dólares. Los haberes tienen que sumar lo mismo que el debe.</p>
        <form method="POST" action="{{ route('cobranza.flujo.asientos.store') }}" id="cf-asiento-form">
            @csrf
            @foreach($filtrosOcultos as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <div class="cf-row">
                <div><label>Fecha</label><input type="date" name="fecha" value="{{ now()->toDateString() }}" required></div>
                <div>
                    <label>Apartado</label>
                    <select name="ambito">
                        @foreach($ambitos as $clave => $nombre)<option value="{{ $clave }}" @selected($clave === 'general')>{{ $nombre }}</option>@endforeach
                    </select>
                </div>
                <div style="flex:2;"><label>Descripción</label><input type="text" name="descripcion" maxlength="255" required placeholder="Ej.: Traspaso del cruce a Banesco"></div>
            </div>
            <datalist id="cf-cuentas-asiento">
                @foreach($cuentasAsiento as $c)<option value="{{ $c }}"></option>@endforeach
            </datalist>

            <div class="cf-paso" style="color:#0f766e;">Debe</div>
            <div class="cf-row" style="align-items:flex-end;">
                <div style="flex:3;"><label>Cuenta</label><input type="text" name="debe_cuenta" list="cf-cuentas-asiento" maxlength="255" required placeholder="Elige o escribe la cuenta"></div>
                <div style="flex:2;"><label>Detalle</label><input type="text" name="debe_detalle" maxlength="255"></div>
                <div style="flex:1;"><label>Monto $</label><input type="number" name="debe_monto" id="cf-debe-monto" step="0.01" min="0.01" required style="text-align:right;"></div>
                <div style="flex:0 0 34px;min-width:34px;"></div>
            </div>

            <div class="cf-paso" style="color:#b91c1c;">Haber</div>
            <div id="cf-haber-filas"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div style="display:flex;gap:8px;">
                    <button type="button" class="cf-btn cf-btn-gris" style="padding:5px 12px;" onclick="cfAgregarHaber()">+ Agregar haber</button>
                    <button type="button" class="cf-btn cf-btn-gris" style="padding:5px 12px;" onclick="cfHaberIguales()">Partes iguales</button>
                </div>
                <div id="cf-asiento-estado" style="font-weight:700;font-size:.9rem;"></div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                <button type="button" class="cf-btn cf-btn-gris" onclick="cfCerrar('cf-modal-asiento')">Cancelar</button>
                <button type="submit" class="cf-btn cf-btn-asiento" id="cf-asiento-guardar" disabled>Registrar asiento</button>
            </div>
        </form>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const contratos = @json($contratos);
    const propiedades = @json($propiedades);
    const categorias = @json($categoriasPropiedad);
    const facturaInicial = @json($factura);
    const urlFacturas = @json(route('cobranza.flujo.facturas'));
    const bancoCruce = @json($bancoCruce);
    const fmt = n => '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = id => document.getElementById(id);

    const estado = { tipo: $('cf-tipo').value || 'ingreso', ambito: $('cf-ambito').value || 'cobranza' };

    const tsSettings = {
        create: false,
        sortField: { field: 'text', direction: 'asc' },
        placeholder: '-- Seleccione un tipo de gasto --',
        maxOptions: null,
        allowEmptyOption: true,
    };
    const tsTipoGasto = document.getElementById('cf-tipo-gasto')
        ? new TomSelect('#cf-tipo-gasto', tsSettings) : null;
    const tsCruceTipoGasto = document.getElementById('cf-cruce-tipo-gasto')
        ? new TomSelect('#cf-cruce-tipo-gasto', tsSettings) : null;
    const tsEditTipoGasto = document.getElementById('cfe-tipo-gasto')
        ? new TomSelect('#cfe-tipo-gasto', Object.assign({}, tsSettings)) : null;

    function setTipoGasto(ts, value) {
        if (!ts) return;
        ts.setValue(value ?? '', true);
    }
    function enableTipoGasto(ts, on) {
        if (!ts) return;
        if (on) ts.enable(); else ts.disable();
    }

    // ── Montos Bs ↔ divisas con la tasa ─────────────────────────────
    function enlazarMontos(usd, tasa, bs) {
        let ultimo = null;
        const calcular = () => {
            const t = parseFloat(tasa.value) || 0;
            if (t <= 0) return;
            if (ultimo === 'bs') { const b = parseFloat(bs.value) || 0; usd.value = b > 0 ? (b / t).toFixed(2) : ''; }
            else if (ultimo === 'usd') { const u = parseFloat(usd.value) || 0; bs.value = u > 0 ? (u * t).toFixed(2) : ''; }
        };
        usd.addEventListener('input', () => { ultimo = 'usd'; calcular(); });
        bs.addEventListener('input', () => { ultimo = 'bs'; calcular(); });
        tasa.addEventListener('input', calcular);
        return v => { usd.value = v; ultimo = 'usd'; calcular(); };
    }
    const ponerUsd = enlazarMontos($('cf-usd'), $('cf-tasa'), $('cf-bs'));
    enlazarMontos($('cfe-usd'), $('cfe-tasa'), $('cfe-bs'));

    // ── Lo que se paga ───────────────────────────────────────────────
    function resumen(html) {
        const r = $('cf-resumen');
        r.style.display = html ? 'block' : 'none';
        r.innerHTML = html ? '✓ Se registrará como: ' + html : '';
    }
    function limpiarSeleccion() {
        $('cf-vinculo').value = '';
        $('cf-factura-sede').value = '';
        $('cf-factura-doc').value = '';
        document.querySelectorAll('#cf-form .cf-opcion.activo').forEach(o => o.classList.remove('activo'));
        $('cf-extra-pago').style.display = 'none';
        $('cf-prop-categoria-wrap').style.display = 'none';
        $('cf-prop-categoria').disabled = true;
        mostrarReservaNueva(false);
        resumen('');
    }

    // Reserva temporal nueva: fechas, precio por noche y comisión, como en Patrimonial.
    const camposReserva = ['cf-res-cliente', 'cf-res-contacto', 'cf-res-entrada', 'cf-res-salida', 'cf-res-precio', 'cf-res-comision'];
    let propiedadReserva = null;
    function mostrarReservaNueva(on) {
        $('cf-reserva-nueva').style.display = on ? '' : 'none';
        camposReserva.forEach(id => { $(id).disabled = !on; });
        ['cf-res-cliente', 'cf-res-entrada', 'cf-res-salida'].forEach(id => { $(id).required = on; });
        if (!on) propiedadReserva = null;
    }
    function nochesReserva() {
        const e = $('cf-res-entrada').value, s = $('cf-res-salida').value;
        if (!e || !s) return 0;
        return Math.max(0, Math.round((new Date(s) - new Date(e)) / 86400000));
    }
    function pintarReservaNueva() {
        if (!propiedadReserva) return;
        const noches = nochesReserva();
        const precio = parseFloat($('cf-res-precio').value) || 0;
        const monto = parseFloat($('cf-usd').value) || 0;
        const total = noches * precio;
        const cliente = $('cf-res-cliente').value.trim();
        const fechas = $('cf-res-entrada').value && $('cf-res-salida').value
            ? `${$('cf-res-entrada').value.split('-').reverse().join('/')} al ${$('cf-res-salida').value.split('-').reverse().join('/')}` : '';
        let info = noches > 0 ? `${noches} noche(s)` : 'Elige desde y hasta';
        if (noches > 0 && precio > 0) {
            info += ` · total ${fmt(total)}`;
            if (monto > 0) info += monto < total - 0.009 ? ` · este ingreso abona ${fmt(monto)} y queda debiendo ${fmt(total - monto)}` : ' · este ingreso la paga completa';
        } else if (noches > 0 && monto > 0) {
            info += ` · sin precio por noche se toma ${fmt(monto / noches)} (el monto entre las noches)`;
        }
        $('cf-res-info').textContent = info;
        resumen(`<strong>nueva reserva temporal</strong> en <strong>${esc(propiedadReserva.nombre)}</strong>${cliente ? ' de <strong>' + esc(cliente) + '</strong>' : ''}${fechas ? ' del ' + esc(fechas) : ''}`);
    }
    camposReserva.forEach(id => $(id).addEventListener('input', () => {
        if (id === 'cf-res-cliente' && propiedadReserva) {
            $('cf-contraparte').value = $('cf-res-cliente').value;
            $('cf-concepto').value = `Reserva ${propiedadReserva.nombre} · ${$('cf-res-cliente').value}`;
        }
        if ((id === 'cf-res-precio' || id === 'cf-res-entrada' || id === 'cf-res-salida') && !(parseFloat($('cf-usd').value) > 0)) {
            const total = nochesReserva() * (parseFloat($('cf-res-precio').value) || 0);
            if (total > 0) ponerUsd(total.toFixed(2));
        }
        pintarReservaNueva();
    }));
    $('cf-usd').addEventListener('input', pintarReservaNueva);
    function autollenar(concepto, contraparte, monto) {
        if (concepto) $('cf-concepto').value = concepto;
        if (contraparte) $('cf-contraparte').value = contraparte;
        if (monto && !(parseFloat($('cf-usd').value) > 0)) ponerUsd(Number(monto).toFixed(2));
    }

    // Cobranza: facturas con deuda
    function elegirFactura(f, el) {
        limpiarSeleccion();
        el?.classList.add('activo');
        $('cf-factura-sede').value = f.sede;
        $('cf-factura-doc').value = f.doc;
        resumen(`abono a <strong>${esc(f.numero)}</strong> de <strong>${esc(f.cliente)}</strong> (${esc(f.sede)}) · debe ${fmt(f.saldo)}`);
        autollenar('Abono ' + f.numero, f.cliente, f.saldo);
    }
    let temporizador = null;
    async function buscarFacturas() {
        const q = $('cf-buscar-factura').value.trim();
        const caja = $('cf-facturas');
        if (q.length < 2) { caja.innerHTML = '<div class="cf-vacio">Escribe al menos 2 letras del cliente o el número de factura.</div>'; return; }
        caja.innerHTML = '<div class="cf-vacio">Buscando…</div>';
        try {
            const r = await fetch(urlFacturas + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (!d.facturas.length) { caja.innerHTML = '<div class="cf-vacio">No hay facturas con deuda que coincidan.</div>'; return; }
            caja.innerHTML = '';
            d.facturas.forEach(f => {
                const el = document.createElement('div');
                el.className = 'cf-opcion';
                el.innerHTML = `<div><strong>${esc(f.cliente)}</strong> <span class="muted">${esc(f.codigo)}</span>
                    <small>${esc(f.numero)} · ${esc(f.sede)}${f.emision ? ' · emitida ' + esc(f.emision) : ''} · factura ${fmt(f.monto)}</small></div>
                    <div class="cf-debe">debe ${fmt(f.saldo)}</div>`;
                el.addEventListener('click', () => elegirFactura(f, el));
                caja.appendChild(el);
            });
        } catch (e) { caja.innerHTML = '<div class="cf-vacio">No se pudo buscar. Intenta de nuevo.</div>'; }
    }
    $('cf-buscar-factura').addEventListener('input', () => { clearTimeout(temporizador); temporizador = setTimeout(buscarFacturas, 300); });

    // Contratos: contrato → cuota
    function pintarContratos() {
        const q = $('cf-buscar-contrato').value.trim().toLowerCase();
        const caja = $('cf-contratos');
        const lista = contratos.filter(c => !q || (c.numero + ' ' + c.cliente).toLowerCase().includes(q)).slice(0, 60);
        if (!contratos.length) { caja.innerHTML = '<div class="cf-vacio">No hay contratos activos con cuotas por cobrar.</div>'; return; }
        caja.innerHTML = lista.length ? '' : '<div class="cf-vacio">Ningún contrato coincide.</div>';
        lista.forEach(c => {
            const debe = c.cuotas.reduce((s, q) => s + q.debe, 0);
            const el = document.createElement('div');
            el.className = 'cf-opcion';
            el.innerHTML = `<div><strong>${esc(c.numero)}</strong> · ${esc(c.cliente)}<small>${c.cuotas.length} cuota(s) abierta(s) · capital pendiente ${fmt(c.capital)}</small></div><div class="cf-debe">${fmt(debe)}</div>`;
            el.addEventListener('click', () => elegirContrato(c, el));
            caja.appendChild(el);
        });
    }
    function elegirContrato(c, el) {
        limpiarSeleccion();
        document.querySelectorAll('#cf-contratos .cf-opcion').forEach(o => o.classList.remove('activo'));
        el.classList.add('activo');
        $('cf-cuotas-wrap').style.display = '';
        const caja = $('cf-cuotas');
        caja.innerHTML = '';
        c.cuotas.forEach(q => {
            const o = document.createElement('div');
            o.className = 'cf-opcion';
            o.dataset.valor = q.valor;
            o.innerHTML = `<div><strong>Cuota #${q.numero}</strong> · vence ${esc(q.vence || '—')} <span class="cf-badge" style="background:${q.estatus === 'vencido' ? '#fee2e2;color:#b91c1c' : '#f1f5f9;color:#475569'};">${esc(q.estatus)}</span>
                <small>cuota ${fmt(q.monto)} · pagado ${fmt(q.pagado)}</small></div><div class="cf-debe">debe ${fmt(q.debe)}</div>`;
            o.addEventListener('click', () => {
                caja.querySelectorAll('.cf-opcion').forEach(x => x.classList.remove('activo'));
                o.classList.add('activo');
                $('cf-vinculo').value = q.valor;
                $('cf-extra-pago').style.display = '';
                $('cf-abono-capital-wrap').style.display = '';
                resumen(`pago de la <strong>cuota #${q.numero}</strong> del contrato <strong>${esc(c.numero)}</strong> de <strong>${esc(c.cliente)}</strong> · debe ${fmt(q.debe)}`);
                autollenar(`Cuota #${q.numero} contrato ${c.numero}`, c.cliente, q.debe);
            });
            caja.appendChild(o);
        });
    }
    $('cf-buscar-contrato').addEventListener('input', pintarContratos);

    // Patrimonial: propiedad → alquiler / reserva / otro ingreso o gasto
    function pintarPropiedad() {
        limpiarSeleccion();
        const p = propiedades.find(x => String(x.id) === $('cf-propiedad').value);
        $('cf-prop-opciones').style.display = p ? '' : 'none';
        if (!p) return;
        const caja = $('cf-prop-lista');
        caja.innerHTML = '';
        const agregar = (html, alElegir) => {
            const o = document.createElement('div');
            o.className = 'cf-opcion';
            o.innerHTML = html;
            o.addEventListener('click', () => { limpiarSeleccion(); o.classList.add('activo'); alElegir(); });
            caja.appendChild(o);
        };
        if (estado.tipo === 'ingreso') {
            p.alquileres.forEach(a => agregar(
                `<div><strong>Alquiler ${esc(a.periodo)}</strong> · ${esc(a.inquilino || '')}<small>vence ${esc(a.vence || '—')} · ${esc(a.estado)}</small></div><div class="cf-debe">debe ${fmt(a.debe)}</div>`,
                () => {
                    $('cf-vinculo').value = a.valor;
                    $('cf-extra-pago').style.display = '';
                    $('cf-abono-capital-wrap').style.display = 'none';
                    resumen(`pago del <strong>alquiler ${esc(a.periodo)}</strong> de <strong>${esc(p.nombre)}</strong> (${esc(a.inquilino || '')}) · debe ${fmt(a.debe)}`);
                    autollenar(`Alquiler ${a.periodo} · ${p.nombre}`, a.inquilino, a.debe);
                }));
            p.reservas.forEach(r => agregar(
                `<div><strong>Reserva</strong> · ${esc(r.cliente)}<small>${esc(r.fechas)}</small></div><div class="cf-debe">debe ${fmt(r.debe)}</div>`,
                () => {
                    $('cf-vinculo').value = r.valor;
                    $('cf-extra-pago').style.display = '';
                    $('cf-abono-capital-wrap').style.display = 'none';
                    resumen(`pago de la <strong>reserva</strong> de <strong>${esc(r.cliente)}</strong> en <strong>${esc(p.nombre)}</strong> (${esc(r.fechas)}) · debe ${fmt(r.debe)}`);
                    autollenar(`Reserva ${p.nombre} · ${r.cliente}`, r.cliente, r.debe);
                }));
        }
        const grupo = estado.tipo === 'ingreso' ? 'ingreso' : 'gasto';
        if (estado.tipo === 'ingreso') {
            agregar(`<div><strong>Nueva reserva temporal</strong><small>Desde y hasta, precio por noche: se crea en Patrimonial y este ingreso queda como su pago</small></div>`, () => {
                $('cf-vinculo').value = 'pat_reserva_nueva:' + p.id;
                propiedadReserva = p;
                mostrarReservaNueva(true);
                $('cf-extra-pago').style.display = '';
                $('cf-abono-capital-wrap').style.display = 'none';
                pintarReservaNueva();
            });
        }
        agregar(`<div><strong>${grupo === 'ingreso' ? 'Otro ingreso' : 'Gasto'} de la propiedad</strong><small>${grupo === 'ingreso' ? 'Depósito u otro ingreso' : 'Mantenimiento, servicios, condominio…'}: va al balance de Patrimonial</small></div>`, () => {
            $('cf-vinculo').value = 'pat_propiedad:' + p.id;
            const sel = $('cf-prop-categoria');
            // Alquiler y reserva tienen su propia opción arriba.
            const lista = categorias[grupo].filter(c => !['Alquiler', 'Reserva temporal'].includes(c));
            sel.innerHTML = lista.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
            sel.disabled = false;
            $('cf-prop-categoria-label').textContent = grupo === 'ingreso' ? 'Tipo de ingreso' : 'Tipo de gasto';
            $('cf-prop-categoria-wrap').style.display = '';
            const pintar = () => resumen(`<strong>${grupo === 'ingreso' ? 'ingreso' : 'gasto'}</strong> de <strong>${esc(p.nombre)}</strong>: ${esc(sel.value)}`);
            sel.onchange = pintar;
            pintar();
        });
        if (estado.tipo === 'ingreso' && !p.alquileres.length && !p.reservas.length) {
            caja.insertAdjacentHTML('afterbegin', '<div class="cf-vacio">Esta propiedad no tiene alquileres ni reservas por cobrar.</div>');
        }
    }
    $('cf-propiedad').addEventListener('change', pintarPropiedad);

    // Reparto: un gasto (o ingreso) dividido entre varias propiedades.
    let repartiendo = false;
    let nReparto = 0;
    const opcionesPropiedad = propiedades.map(p => `<option value="${p.id}">${esc(p.nombre)}${p.codigo ? ' (' + esc(p.codigo) + ')' : ''}</option>`).join('');
    const opcionesCategoria = () => categorias[estado.tipo === 'ingreso' ? 'ingreso' : 'gasto'].map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
    window.cfRepartoFila = function (monto) {
        const i = nReparto++;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select name="reparto[${i}][propiedad_id]" class="cf-input" required><option value="">Elige…</option>${opcionesPropiedad}</select></td>
            <td><select name="reparto[${i}][categoria]" class="cf-input cf-reparto-cat">${opcionesCategoria()}</select></td>
            <td style="text-align:right;"><input type="number" step="0.01" min="0" name="reparto[${i}][monto_usd]" class="cf-input cf-reparto-monto" style="width:120px;text-align:right;" value="${monto ?? ''}"></td>
            <td><button type="button" class="cf-acc cf-acc-borrar" onclick="this.closest('tr').remove(); cfRepartoEstado();">×</button></td>`;
        $('cf-reparto-filas').appendChild(tr);
        tr.querySelectorAll('select, input').forEach(el => el.addEventListener('input', cfRepartoEstado));
        tr.querySelectorAll('select').forEach(el => el.addEventListener('change', cfRepartoEstado));
        cfRepartoEstado();
    };
    window.cfRepartoIguales = function () {
        const montos = [...document.querySelectorAll('.cf-reparto-monto')];
        const total = parseFloat($('cf-usd').value) || 0;
        if (!montos.length || total <= 0) return;
        const parte = Math.floor(total / montos.length * 100) / 100;
        montos.forEach((el, i) => { el.value = (i === montos.length - 1 ? total - parte * (montos.length - 1) : parte).toFixed(2); });
        cfRepartoEstado();
    };
    function sumaReparto() {
        return [...document.querySelectorAll('.cf-reparto-monto')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
    }
    window.cfRepartoEstado = function () {
        if (!repartiendo) return;
        const total = parseFloat($('cf-usd').value) || 0;
        const suma = sumaReparto();
        const cuadra = total > 0 && Math.abs(suma - total) < 0.01;
        const e = $('cf-reparto-estado');
        e.textContent = `Repartido ${fmt(suma)} de ${fmt(total)}` + (cuadra ? ' ✓' : (suma > total ? ' · sobra ' + fmt(suma - total) : ' · falta ' + fmt(total - suma)));
        e.style.color = cuadra ? '#059669' : '#dc2626';
        const filas = [...$('cf-reparto-filas').querySelectorAll('tr')].map(tr => {
            const sel = tr.querySelector('select');
            const nombre = sel.value ? sel.options[sel.selectedIndex].text : '¿propiedad?';
            return `${esc(nombre)} ${fmt(tr.querySelector('.cf-reparto-monto').value)}`;
        });
        resumen(`<strong>${estado.tipo === 'ingreso' ? 'ingreso' : 'gasto'} repartido</strong> entre ${filas.length} propiedad(es): ${filas.join(' · ')}`);
    };
    function modoReparto(on) {
        repartiendo = on;
        limpiarSeleccion();
        $('cf-modo-una').classList.toggle('activo', !on);
        $('cf-modo-reparto').classList.toggle('activo', on);
        $('cf-reparto').style.display = on ? '' : 'none';
        $('cf-una-propiedad').style.display = on ? 'none' : '';
        $('cf-prop-opciones').style.display = on || !$('cf-propiedad').value ? 'none' : '';
        $('cf-reparto-filas').querySelectorAll('select, input').forEach(el => { el.disabled = !on; });
        $('cf-reparto-tipo-th').textContent = estado.tipo === 'ingreso' ? 'Tipo de ingreso' : 'Tipo de gasto';
        document.querySelectorAll('.cf-reparto-cat').forEach(sel => {
            const previo = sel.value;
            sel.innerHTML = opcionesCategoria();
            if ([...sel.options].some(o => o.value === previo)) sel.value = previo;
        });
        if (on) {
            $('cf-vinculo').value = 'pat_reparto:0';
            if (!$('cf-reparto-filas').children.length) { cfRepartoFila(); cfRepartoFila(); }
            cfRepartoEstado();
        } else if ($('cf-propiedad').value) {
            pintarPropiedad();
        }
    }
    $('cf-modo-una').addEventListener('click', () => modoReparto(false));
    $('cf-modo-reparto').addEventListener('click', () => modoReparto(true));
    ['cf-usd', 'cf-bs', 'cf-tasa'].forEach(id => $(id).addEventListener('input', () => cfRepartoEstado()));
    $('cf-form').addEventListener('submit', e => {
        if (!repartiendo || estado.ambito !== 'patrimonial') return;
        const total = parseFloat($('cf-usd').value) || 0;
        if (Math.abs(sumaReparto() - total) >= 0.01) {
            e.preventDefault();
            alert('El reparto entre propiedades tiene que sumar el monto USD del movimiento.');
        }
    });

    // ── Tipo y apartado ──────────────────────────────────────────────
    function sincronizar() {
        const ingreso = estado.tipo === 'ingreso';
        $('cf-tipo').value = estado.tipo;
        $('cf-ambito').value = estado.ambito;
        $('cf-titulo').textContent = ingreso ? 'Nuevo Ingreso' : 'Nuevo Egreso';
        $('cf-guardar').className = 'cf-btn ' + (ingreso ? 'cf-btn-ingreso' : 'cf-btn-egreso');
        $('cf-label-cuenta').textContent = ingreso ? 'Cuenta que recibe el dinero' : 'Cuenta de donde sale el dinero';
        $('cf-label-contraparte').textContent = ingreso ? 'Quién paga' : 'A quién se le paga';
        document.querySelectorAll('#cf-pills .cf-pill').forEach(b => b.classList.toggle('activo', b.dataset.ambito === estado.ambito));
        document.querySelectorAll('#cf-form .cf-que').forEach(b => { b.style.display = b.dataset.ambito === estado.ambito ? '' : 'none'; });
        document.querySelectorAll('#cf-form [data-solo]').forEach(b => { b.style.display = b.dataset.solo === estado.tipo ? '' : 'none'; });
        $('cf-bloque-cruce').style.display = ingreso ? '' : 'none';
        if (!ingreso) $('cf-cruce').checked = false;
        $('cf-tipo-gasto').required = !ingreso;
        enableTipoGasto(tsTipoGasto, !ingreso);
        if (ingreso) setTipoGasto(tsTipoGasto, '');
        sincronizarCruce();
    }
    function sincronizarCruce() {
        const on = $('cf-cruce').checked;
        $('cf-campos-cruce').style.display = on ? '' : 'none';
        $('cf-cruce-destino').required = on;
        $('cf-cruce-tipo-gasto').required = on;
        enableTipoGasto(tsCruceTipoGasto, on);
        if (!on) setTipoGasto(tsCruceTipoGasto, '');
        const cuenta = $('cf-cuenta');
        if (on && !cuenta.value.toUpperCase().startsWith(bancoCruce + '|')) {
            const op = [...cuenta.options].find(o => o.value.toUpperCase().startsWith(bancoCruce + '|'));
            if (op) cuenta.value = op.value;
        }
    }
    $('cf-cruce').addEventListener('change', sincronizarCruce);

    document.querySelectorAll('#cf-pills .cf-pill').forEach(b => b.addEventListener('click', () => {
        estado.ambito = b.dataset.ambito;
        if (repartiendo) modoReparto(false);
        limpiarSeleccion();
        $('cf-propiedad').value = '';
        $('cf-prop-opciones').style.display = 'none';
        $('cf-cuotas-wrap').style.display = 'none';
        sincronizar();
    }));

    window.cfAbrir = function (tipo) {
        if (estado.tipo !== tipo) {
            estado.tipo = tipo;
            limpiarSeleccion();
            if (repartiendo) modoReparto(true);
            else if ($('cf-propiedad').value) pintarPropiedad();
        }
        sincronizar();
        $('cf-modal-nuevo').classList.add('abierto');
    };
    window.cfCerrar = id => $(id).classList.remove('abierto');
    document.querySelectorAll('.cf-modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('abierto'); }));

    pintarContratos();
    sincronizar();
    // Viene de «Abonar» en Cobranza, o el formulario volvió con errores.
    if (facturaInicial) {
        estado.tipo = 'ingreso'; estado.ambito = 'cobranza';
        sincronizar();
        elegirFactura(facturaInicial, null);
        $('cf-modal-nuevo').classList.add('abierto');
    } else if (@json($errors->any() && old('tipo') !== null)) {
        if ($('cf-factura-doc').value) resumen('abono a la factura elegida (' + esc($('cf-factura-doc').value) + ')');
        $('cf-modal-nuevo').classList.add('abierto');
    }

    // ── Editar ───────────────────────────────────────────────────────
    window.cfEditar = function (m) {
        $('cf-edit-form').action = m.url;
        const set = (id, v) => { $(id).value = v ?? ''; };
        set('cfe-fecha', m.fecha); set('cfe-ambito', m.ambito); set('cfe-cuenta', m.cuenta);
        set('cfe-usd', m.monto_usd || ''); set('cfe-tasa', m.tasa_cambio || ''); set('cfe-bs', m.monto_bs || '');
        set('cfe-referencia', m.referencia); set('cfe-concepto', m.concepto); set('cfe-contraparte', m.contraparte); set('cfe-observaciones', m.observaciones);
        $('cfe-titulo').textContent = m.tipo === 'ingreso' ? 'Editar ingreso' : 'Editar egreso';
        const esEgreso = m.tipo !== 'ingreso';
        $('cfe-tipo-gasto-wrap').style.display = esEgreso ? '' : 'none';
        $('cfe-tipo-gasto').required = esEgreso;
        enableTipoGasto(tsEditTipoGasto, esEgreso);
        setTipoGasto(tsEditTipoGasto, esEgreso ? (m.tipo_gasto || '') : '');
        $('cfe-comprobantes').innerHTML = (m.comprobantes || []).map(url => `
            <label style="display:flex;align-items:center;gap:6px;padding:4px 8px;border:1px solid #e2e8f0;border-radius:8px;font-size:.8rem;">
                <a href="${esc(url)}" target="_blank" rel="noopener">${/\.pdf(\?|$)/i.test(url) ? '📄 PDF' : '🖼️ Imagen'}</a>
                <input type="checkbox" name="comprobantes_quitar[]" value="${esc(url)}"> Quitar
            </label>`).join('') || '<span class="muted" style="font-size:.8rem;">Sin comprobantes.</span>';
        const pago = $('cfe-pago');
        pago.style.display = m.pago ? 'block' : 'none';
        pago.innerHTML = m.pago ? 'Paga: <strong>' + esc(m.pago) + '</strong>' : '';
        const avisos = [];
        if (m.conciliado) avisos.push('Ya está conciliado con el banco: solo cambia concepto, apartado, contraparte y observaciones.');
        if (m.modulo) avisos.push(`Pago registrado en ${m.modulo}: el monto, la fecha y el apartado se corrigen allá.`);
        $('cfe-aviso').style.display = avisos.length ? '' : 'none';
        $('cfe-aviso').textContent = avisos.join(' ');
        const bloqueados = new Set();
        if (m.conciliado) ['cfe-fecha', 'cfe-cuenta', 'cfe-usd', 'cfe-tasa', 'cfe-bs', 'cfe-referencia'].forEach(id => bloqueados.add(id));
        if (m.modulo) ['cfe-fecha', 'cfe-usd', 'cfe-tasa', 'cfe-bs', 'cfe-ambito'].forEach(id => bloqueados.add(id));
        ['cfe-fecha', 'cfe-ambito', 'cfe-cuenta', 'cfe-usd', 'cfe-tasa', 'cfe-bs', 'cfe-referencia'].forEach(id => {
            const el = $(id), b = bloqueados.has(id);
            el.readOnly = b;
            el.style.background = b ? '#f1f5f9' : '';
            if (el.tagName === 'SELECT') el.style.pointerEvents = b ? 'none' : '';
        });
        $('cf-modal-editar').classList.add('abierto');
    };

    // ── Asiento: un debe y uno o varios haberes en $ que suman lo mismo ─
    const haberes = $('cf-haber-filas');
    let n = 0;
    window.cfAgregarHaber = function () {
        const i = n++;
        const fila = document.createElement('div');
        fila.className = 'cf-row';
        fila.style.alignItems = 'flex-end';
        fila.innerHTML = `<div style="flex:3;"><label>Cuenta</label><input type="text" name="haber[${i}][cuenta]" list="cf-cuentas-asiento" maxlength="255" required placeholder="Elige o escribe la cuenta"></div>
            <div style="flex:2;"><label>Detalle</label><input type="text" name="haber[${i}][detalle]" maxlength="255"></div>
            <div style="flex:1;"><label>Monto $</label><input type="number" name="haber[${i}][monto]" class="cf-haber-monto" step="0.01" min="0.01" required style="text-align:right;"></div>
            <div style="flex:0 0 34px;min-width:34px;"><button type="button" class="cf-acc cf-acc-borrar" title="Quitar" style="padding:7px 10px;">×</button></div>`;
        fila.querySelector('button').addEventListener('click', () => {
            if (haberes.children.length > 1) { fila.remove(); cfTotalesAsiento(); }
        });
        fila.querySelector('.cf-haber-monto').addEventListener('input', cfTotalesAsiento);
        haberes.appendChild(fila);
        cfTotalesAsiento();
    };
    window.cfHaberIguales = function () {
        const montos = [...haberes.querySelectorAll('.cf-haber-monto')];
        const total = parseFloat($('cf-debe-monto').value) || 0;
        if (!montos.length || total <= 0) return;
        const parte = Math.floor(total / montos.length * 100) / 100;
        montos.forEach((el, i) => { el.value = (i === montos.length - 1 ? total - parte * (montos.length - 1) : parte).toFixed(2); });
        cfTotalesAsiento();
    };
    window.cfTotalesAsiento = function () {
        const debe = parseFloat($('cf-debe-monto').value) || 0;
        const haber = [...haberes.querySelectorAll('.cf-haber-monto')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
        const cuadra = debe > 0 && Math.abs(debe - haber) < 0.01;
        const estadoAsiento = $('cf-asiento-estado');
        estadoAsiento.textContent = `Debe ${fmt(debe)} · Haber ${fmt(haber)}` + (cuadra ? ' ✓ Cuadra' : (haber > debe ? ' · sobra ' + fmt(haber - debe) : ' · falta ' + fmt(debe - haber)));
        estadoAsiento.style.color = cuadra ? '#059669' : '#dc2626';
        $('cf-asiento-guardar').disabled = !cuadra;
    };
    $('cf-debe-monto').addEventListener('input', cfTotalesAsiento);
    cfAgregarHaber();
})();
</script>
@endsection
