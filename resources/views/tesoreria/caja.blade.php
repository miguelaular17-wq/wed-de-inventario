@extends('layouts.app')

@section('title', 'Tesorería · Caja')

@php
    $fmt = function ($caja, $monto, $monedaDivisa = null) {
        $n = number_format((float) $monto, 2, ',', '.');
        if ($caja && $caja->esBolivares()) {
            return 'Bs. '.$n;
        }
        $m = strtoupper((string) $monedaDivisa);
        return $m === 'EUR' ? '€'.$n : '$'.$n;
    };
@endphp

@push('head')
<style>
.tc-dash { max-width: 1400px; margin: 0 auto; }
.tc-modern { background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.05); border:1px solid #e2e8f0; overflow:hidden; }
.tc-modern table { width:100%; border-collapse:collapse; font-size:13px; }
.tc-modern th { background:#f8fafc; color:#475569; font-weight:600; padding:8px 12px; border-bottom:2px solid #e2e8f0; text-transform:uppercase; letter-spacing:.5px; font-size:11px; text-align:left; }
.tc-modern td { padding:8px 12px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle; }
.tc-modern tbody tr:hover { background:#f8fafc; }
.tc-modern tfoot td { background:#f8fafc; font-weight:800; border-top:2px solid #e2e8f0; }
.tc-header-main { background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%) !important; color:#fff !important; border-bottom:none !important; text-align:left; font-size:13px; }
.tc-ingreso { color:#15803d; font-weight:700; }
.tc-egreso { color:#b91c1c; font-weight:700; }
.tc-modal { display:none; position:fixed; inset:0; z-index:1200; background:rgba(15,23,42,.45); align-items:center; justify-content:center; padding:16px; }
.tc-modal.abierto { display:flex; }
.tc-modal-box { background:#fff; border-radius:12px; width:95%; max-width:600px; padding:15px 20px; position:relative; box-shadow:0 10px 25px -5px rgba(0,0,0,.1); max-height:95vh; overflow-y:auto; }
.tc-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.tc-filter input, .tc-filter select { padding:6px 10px; border:1px solid #ccc; border-radius:6px; font:inherit; }
.tc-inp { width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; font:inherit; background:#fff; box-sizing:border-box; }
.tc-lbl { display:block; margin-bottom:3px; font-weight:500; font-size:.9rem; color:#334155; }
</style>
@endpush

@section('content')
<div class="tc-dash py-4">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:12px; flex-wrap:wrap;">
        <div>
            <h2 style="margin:0; font-size:1.5rem; color:var(--blue); font-weight:600;">Caja de Tesorería</h2>
            <p class="muted" style="margin:4px 0 0;">Disponibilidad · ingresos y egresos · Caja Principal Bolívares y Divisas</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <a href="{{ route('tesoreria.dashboard') }}" class="btn secondary" style="padding:10px 16px; border-radius:8px;">← Panel Tesorería</a>
            <a href="{{ route('tesoreria.dash') }}" class="btn secondary" style="padding:10px 16px; border-radius:8px;">Dashboard</a>
            <button type="button" class="btn btn-primary" style="background-color:#1a4273; color:#fff; padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:500; display:flex; align-items:center; gap:8px; box-shadow:0 2px 4px rgba(26,66,115,0.2); transition:all 0.2s;" onclick="tcAbrirModal('egreso')" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 6px rgba(26,66,115,0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(26,66,115,0.2)';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nuevo Egreso
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-bottom:14px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-bottom:14px;">⚠️ {{ $errors->first() }}</div>
    @endif

    {{-- DISPONIBILIDAD --}}
    <div class="tc-modern" style="margin-bottom:28px;">
        <table>
            <thead>
                <tr>
                    <th colspan="4" class="tc-header-main">DISPONIBILIDAD EN TIEMPO REAL</th>
                </tr>
                <tr>
                    <th style="width:28%;">BANCO</th>
                    <th style="width:28%;">TITULAR</th>
                    <th style="width:22%;">MONEDA</th>
                    <th style="text-align:right; width:22%;">DISPONIBLE</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cajas as $caja)
                    <tr>
                        <td style="font-weight:600; color:#0f172a;">CAJA PRINCIPAL</td>
                        <td>{{ $caja->esBolivares() ? 'BOLÍVARES' : 'DIVISAS' }}</td>
                        <td>{{ $caja->esBolivares() ? 'Bs' : 'USD / EUR' }}</td>
                        <td style="text-align:right; font-weight:800; font-size:1.05rem; {{ $caja->esBolivares() ? 'color:#1d4ed8;' : 'color:#15803d;' }}">
                            {{ $caja->esBolivares() ? $caja->formatoSaldo() : $caja->formatoSaldoDivisas() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;">Total cajas</td>
                    <td style="text-align:right;">
                        @foreach($cajas as $caja)
                            <div>{{ $caja->esBolivares() ? $caja->formatoSaldo() : $caja->formatoSaldoDivisas() }}</div>
                        @endforeach
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <form method="GET" action="{{ route('tesoreria.caja.index') }}" class="tc-filter" style="margin-bottom:14px;">
        <label style="font-weight:600; color:#4b5563; font-size:.9rem;">Desde:</label>
        <input type="date" name="desde" value="{{ $filtros['desde'] }}">
        <label style="font-weight:600; color:#4b5563; font-size:.9rem;">Hasta:</label>
        <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
        <select name="caja_id">
            <option value="">Todas las cajas</option>
            @foreach($cajas as $caja)
                <option value="{{ $caja->id }}" @selected((int) $filtros['caja_id'] === (int) $caja->id)>{{ $caja->nombre }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Buscar concepto o referencia…">
        <button type="submit" class="btn primary" style="padding:7px 14px;">Filtrar</button>
        <a href="{{ route('tesoreria.caja.index') }}" class="btn secondary" style="padding:7px 14px;">Limpiar</a>
    </form>

    {{-- EGRESOS --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin:24px 0 10px;">
        <h3 style="color:var(--blue); margin:0;">EGRESOS</h3>
    </div>
    <div class="tc-modern" style="margin-bottom:28px;">
        <table>
            <thead>
                <tr>
                    <th style="width:100px;">Fecha</th>
                    <th>Caja</th>
                    <th>Tipo de gasto</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
                    <th style="text-align:right;">Monto</th>
                    <th style="text-align:center; width:90px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($egresos as $m)
                    <tr>
                        <td>{{ $m->fecha?->format('d/m/Y') }}</td>
                        <td>
                            <strong style="color:var(--blue);">{{ $m->caja?->esBolivares() ? 'CAJA PRINCIPAL' : 'CAJA PRINCIPAL' }}</strong><br>
                            <span class="muted" style="font-size:.85rem;">{{ $m->caja?->esBolivares() ? 'BOLÍVARES' : 'DIVISAS' }}</span>
                        </td>
                        <td>{{ $m->tipo_gasto ?: '—' }}</td>
                        <td>
                            {{ $m->concepto }}
                            <div class="muted" style="font-size:.72rem;">{{ $m->usuario?->name }} · {{ $m->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>{{ $m->referencia ?: '—' }}</td>
                        <td class="tc-egreso" style="text-align:right;">−{{ $m->formatoMonto() }}</td>
                        <td style="text-align:center;">
                            <form method="POST" action="{{ route('tesoreria.caja.destroy', $m) }}" onsubmit="return confirm('¿Eliminar este egreso? Se ajustará el saldo.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:6px; padding:4px 8px; font-size:.75rem; cursor:pointer;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">
                            No hay egresos. Regístralos con «Nuevo Egreso».
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($egresos->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;">TOTAL EGRESOS</td>
                        <td class="tc-egreso" style="text-align:right;" colspan="2">
                            @foreach($totalesEgreso as $cajaId => $monto)
                                @php $c = $cajas->firstWhere('id', $cajaId); @endphp
                                @if($c)<div>{{ $fmt($c, $monto) }}</div>@endif
                            @endforeach
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- INGRESOS --}}
    <div style="display:flex; justify-content:space-between; align-items:center; margin:24px 0 10px;">
        <h3 style="color:#0f766e; margin:0;">INGRESOS</h3>
    </div>
    <div class="tc-modern">
        <table>
            <thead>
                <tr>
                    <th style="width:100px;">Fecha</th>
                    <th>Caja</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
                    <th style="text-align:right;">Monto</th>
                    <th style="text-align:center; width:90px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ingresos as $m)
                    @php
                        $esCuadre = $m->esDeCuadre();
                        $detalles = $esCuadre ? $m->detalles : collect();
                        $porSede = $detalles->groupBy(fn ($d) => $d->sede ?: 'SIN SEDE');
                    @endphp
                    <tr>
                        <td>{{ $m->fecha?->format('d/m/Y') }}</td>
                        <td>
                            <strong style="color:#0f766e;">CAJA PRINCIPAL</strong><br>
                            <span class="muted" style="font-size:.85rem;">{{ $m->caja?->esBolivares() ? 'BOLÍVARES' : 'DIVISAS' }}</span>
                        </td>
                        <td>
                            {{ $m->concepto }}
                            @if($esCuadre)
                                <span style="display:inline-block; margin-left:6px; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:999px; padding:1px 8px; font-size:.68rem; font-weight:700;">CUADRE</span>
                                <div class="muted" style="font-size:.72rem; margin-top:2px;">Ingreso del día · detalle por sede abajo</div>
                            @else
                                <div class="muted" style="font-size:.72rem;">{{ $m->usuario?->name }} · {{ $m->created_at?->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td>{{ $m->referencia ?: '—' }}</td>
                        <td class="tc-ingreso" style="text-align:right;">+{{ $m->formatoMonto() }}</td>
                        <td style="text-align:center;">
                            @if($esCuadre)
                                <span class="muted" style="font-size:.72rem;">Desde cuadre</span>
                            @else
                                <form method="POST" action="{{ route('tesoreria.caja.destroy', $m) }}" onsubmit="return confirm('¿Eliminar este ingreso? Se ajustará el saldo.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-radius:6px; padding:4px 8px; font-size:.75rem; cursor:pointer;">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @if($esCuadre && $porSede->isNotEmpty())
                        <tr>
                            <td colspan="6" style="background:#f0fdf4; padding:0;">
                                <details open style="padding:10px 14px;">
                                    <summary style="cursor:pointer; font-weight:700; color:#047857; font-size:.82rem;">
                                        Detalle por sede ({{ $porSede->count() }})
                                    </summary>
                                    <table style="width:100%; margin-top:8px; border-collapse:collapse; font-size:.8rem;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left; padding:4px 6px; color:#64748b;">Sede</th>
                                                <th style="text-align:left; padding:4px 6px; color:#64748b;">Caja</th>
                                                <th style="text-align:right; padding:4px 6px; color:#64748b;">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($porSede as $sedeNombre => $items)
                                                @php $sumaSede = round($items->sum('monto'), 2); @endphp
                                                <tr style="background:#fff;">
                                                    <td style="padding:5px 6px; font-weight:700;">{{ $etiquetas[$sedeNombre] ?? $sedeNombre }}</td>
                                                    <td style="padding:5px 6px;">
                                                        @foreach($items as $item)
                                                            <div>{{ $item->codigo_caja ?: '—' }}</div>
                                                        @endforeach
                                                    </td>
                                                    <td style="padding:5px 6px; text-align:right; font-weight:700; color:#15803d;">
                                                        @foreach($items as $item)
                                                            <div>+{{ $fmt($m->caja, $item->monto) }}</div>
                                                        @endforeach
                                                        @if($items->count() > 1)
                                                            <div class="muted" style="font-size:.72rem; margin-top:2px;">Σ sede {{ $fmt($m->caja, $sumaSede) }}</div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding:30px; color:var(--muted);">
                            No hay ingresos. El efectivo del cuadre entra solo al procesar/cerrar; también puedes usar «Nuevo Egreso» → Tipo: Ingreso.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($ingresos->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; color:#0f766e;">TOTAL INGRESOS</td>
                        <td class="tc-ingreso" style="text-align:right;" colspan="2">
                            @foreach($totalesIngreso as $cajaId => $monto)
                                @php $c = $cajas->firstWhere('id', $cajaId); @endphp
                                @if($c)<div>{{ $fmt($c, $monto) }}</div>@endif
                            @endforeach
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Modal Nuevo Egreso (estilo Finanzas) --}}
<div id="tc-modal" class="tc-modal" onclick="if(event.target===this) tcCerrarModal()">
    <div class="tc-modal-box">
        <button type="button" onclick="tcCerrarModal()" aria-label="Cerrar" style="position:absolute; right:15px; top:15px; background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        <h3 style="margin:0 0 15px; font-size:1.1rem; color:var(--blue);">
            <span id="tc-modal-titulo">Nuevo Egreso</span>
        </h3>
        <form method="POST" action="{{ route('tesoreria.caja.store') }}" id="tc-form">
            @csrf
            <input type="hidden" name="volver_desde" value="{{ $filtros['desde'] }}">
            <input type="hidden" name="volver_hasta" value="{{ $filtros['hasta'] }}">

            <div style="display:flex; gap:15px; margin-bottom:10px;">
                <div style="flex:1;">
                    <label class="tc-lbl">Tipo</label>
                    <select name="tipo" id="tc-tipo" required class="tc-inp" onchange="tcTitulo()">
                        <option value="egreso" @selected(old('tipo', 'egreso') === 'egreso')>Egreso</option>
                        <option value="ingreso" @selected(old('tipo') === 'ingreso')>Ingreso</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="tc-lbl">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="tc-inp">
                </div>
            </div>

            <div id="tc-row-tipo-gasto" style="margin-bottom:10px;">
                <label class="tc-lbl" id="tc-lbl-tipo-gasto">Tipo de gasto</label>
                <select name="tipo_gasto" id="tc-tipo-gasto" class="tc-inp">
                    <option value="">-- Seleccione --</option>
                    @foreach(($tiposGasto ?? []) as $tg)
                        <option value="{{ $tg }}" @selected(old('tipo_gasto') === $tg)>{{ $tg }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex; gap:15px; margin-bottom:10px;">
                <div style="flex:2;">
                    <label class="tc-lbl" id="tc-lbl-banco">Banco y Titular</label>
                    <select name="caja_id" id="tc-caja" required class="tc-inp" onchange="tcSyncMontos()">
                        <option value="">-- Seleccione --</option>
                        @foreach($cajas as $caja)
                            <option value="{{ $caja->id }}"
                                data-bs="{{ $caja->esBolivares() ? '1' : '0' }}"
                                @selected((string) old('caja_id') === (string) $caja->id)>
                                CAJA PRINCIPAL - {{ $caja->esBolivares() ? 'BOLÍVARES' : 'DIVISAS' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="tc-lbl">Ref.</label>
                    <input type="text" name="referencia" value="{{ old('referencia') }}" maxlength="120" class="tc-inp" placeholder="# Referencia">
                </div>
            </div>

            {{-- Bolívares: solo Monto BS --}}
            <div id="tc-row-bs" style="margin-bottom:10px;">
                <label class="tc-lbl">Monto BS</label>
                <input type="text" inputmode="decimal" name="monto_bs" id="tc-monto-bs" value="{{ old('monto_bs') }}" class="tc-inp" placeholder="0,00">
            </div>

            {{-- Divisas: moneda (USD/EUR) + monto, sin tasa --}}
            <div id="tc-row-divisa" style="display:none; gap:15px; margin-bottom:10px;">
                <div style="flex:1;">
                    <label class="tc-lbl">Moneda</label>
                    <select name="moneda_divisa" id="tc-moneda-divisa" class="tc-inp">
                        <option value="USD" @selected(old('moneda_divisa', 'USD') === 'USD')>Dólares (USD)</option>
                        <option value="EUR" @selected(old('moneda_divisa') === 'EUR')>Euros (EUR)</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="tc-lbl" id="tc-lbl-monto-divisa">Monto</label>
                    <input type="text" inputmode="decimal" name="monto_divisa" id="tc-monto-divisa" value="{{ old('monto_divisa', old('monto_usd')) }}" class="tc-inp" placeholder="0,00">
                </div>
            </div>

            <div style="margin-bottom:10px;">
                <label class="tc-lbl" id="tc-lbl-motivo">Motivo (Breve descripción)</label>
                <input type="text" name="concepto" id="tc-motivo" value="{{ old('concepto') }}" maxlength="300" required class="tc-inp" placeholder="Ej. Pago de internet mensual...">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="tcCerrarModal()" style="padding:10px 20px; background-color:#f1f5f9; color:#475569; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-weight:500;">Cancelar</button>
                <button type="submit" id="tc-submit" style="padding:10px 20px; background-color:#1a4273; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:500;">Guardar Egreso</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('tc-modal');
    const tipo = document.getElementById('tc-tipo');
    const tipoGasto = document.getElementById('tc-tipo-gasto');
    const rowTipoGasto = document.getElementById('tc-row-tipo-gasto');
    const titulo = document.getElementById('tc-modal-titulo');
    const submit = document.getElementById('tc-submit');
    const caja = document.getElementById('tc-caja');
    const rowBs = document.getElementById('tc-row-bs');
    const rowDivisa = document.getElementById('tc-row-divisa');
    const montoBs = document.getElementById('tc-monto-bs');
    const montoDivisa = document.getElementById('tc-monto-divisa');
    const monedaDivisa = document.getElementById('tc-moneda-divisa');
    const lblMontoDivisa = document.getElementById('tc-lbl-monto-divisa');

    function esBs() {
        const opt = caja.options[caja.selectedIndex];
        return !opt || opt.value === '' || opt.dataset.bs === '1';
    }

    function esIngreso() {
        return tipo.value === 'ingreso';
    }

    window.tcTitulo = function () {
        const ingreso = esIngreso();
        titulo.textContent = ingreso ? 'Nuevo Ingreso' : 'Nuevo Egreso';
        document.getElementById('tc-lbl-banco').textContent = ingreso ? 'Cuenta que recibe el dinero' : 'Banco y Titular';
        document.getElementById('tc-lbl-tipo-gasto').textContent = ingreso ? 'Tipo de ingreso (opcional)' : 'Tipo de gasto';
        document.getElementById('tc-motivo').placeholder = ingreso ? 'Ej. Cobro de alquiler local 3...' : 'Ej. Pago de internet mensual...';
        submit.textContent = ingreso ? 'Guardar Ingreso' : 'Guardar Egreso';
        submit.style.backgroundColor = ingreso ? '#0f766e' : '#1a4273';
        rowTipoGasto.style.display = 'block';
        tipoGasto.required = !ingreso;
        if (ingreso) tipoGasto.value = tipoGasto.value; // keep optional selection
    };

    function actualizarLblDivisa() {
        lblMontoDivisa.textContent = monedaDivisa.value === 'EUR' ? 'Monto EUR' : 'Monto USD';
    }

    window.tcSyncMontos = function () {
        const bolivares = esBs();
        rowBs.style.display = bolivares ? 'block' : 'none';
        rowDivisa.style.display = bolivares ? 'none' : 'flex';
        montoBs.disabled = !bolivares;
        montoDivisa.disabled = bolivares;
        monedaDivisa.disabled = bolivares;
        if (!bolivares) actualizarLblDivisa();
    };

    window.tcAbrirModal = function (t) {
        tipo.value = (t === 'ingreso' || t === 'ingresos') ? 'ingreso' : 'egreso';
        tcTitulo();
        tcSyncMontos();
        modal.classList.add('abierto');
        (esBs() ? montoBs : montoDivisa).focus();
    };
    window.tcCerrarModal = function () {
        modal.classList.remove('abierto');
    };
    monedaDivisa.addEventListener('change', actualizarLblDivisa);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') tcCerrarModal(); });
    @if($errors->any())
        tcAbrirModal(@json(old('tipo', 'egreso')));
    @endif
})();
</script>
@endsection
