@extends('layouts.app')

@section('title', 'Finanzas · Cuentas bancarias')

@section('content')
@php
    $puedeEditar = auth()->user()?->canAccess('finanzas.editar');
    $cortos = [
        'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO' => 'Nacional · alto/medio',
        'BANCA NACIONAL - BAJO MOVIMIENTO' => 'Nacional · bajo',
        'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA' => 'Tarjetas moneda extranjera',
        'BANCA INTERNACIONAL / BILLETERAS' => 'Internacional / billeteras',
        'TARJETAS INTERNACIONALES DE TERCEROS' => 'Tarjetas de terceros',
    ];
    $colorSeccion = fn ($s) => str_contains($s, 'NACIONAL - ') ? '#0f766e' : (str_contains($s, 'MONEDA EXTRANJERA - ') ? '#b45309' : (str_contains($s, 'INTERNACIONAL') ? '#7c3aed' : '#64748b'));
@endphp
<style>
    .cb-tabs { display:flex; gap:6px; border-bottom:1px solid #e2e8f0; margin:16px 0 14px; }
    .cb-tab { padding:9px 16px; border-radius:8px 8px 0 0; font-weight:700; font-size:.88rem; color:#475569; text-decoration:none; border:1px solid transparent; margin-bottom:-1px; }
    .cb-tab.activa { background:white; color:#1d4ed8; border-color:#e2e8f0 #e2e8f0 white; }
    .cb-stats { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
    .cb-stat { background:white; border:1px solid #e2e8f0; border-radius:10px; padding:8px 14px; font-size:.8rem; color:#64748b; }
    .cb-stat strong { display:block; font-size:1.2rem; color:#0f172a; }
    .cb-tools { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
    .cb-in { padding:7px 10px; border:1px solid #cbd5e1; border-radius:7px; background:white; font-size:.85rem; }
    .cb-sec { background:white; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:14px; overflow:hidden; }
    .cb-sec-head { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 14px; border-left:5px solid; background:#f8fafc; }
    .cb-sec-head h3 { margin:0; font-size:.95rem; }
    .cb-badge { font-size:.72rem; font-weight:700; padding:3px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; white-space:nowrap; }
    .cb-table { width:100%; border-collapse:collapse; font-size:.84rem; }
    .cb-table th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.3px; color:#64748b; padding:7px 10px; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
    .cb-table td { padding:6px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .cb-table tr:hover td { background:#fafcff; }
    .cb-table input[type=text], .cb-table input[type=number], .cb-table select { padding:5px 7px; border:1px solid #cbd5e1; border-radius:6px; font-size:.8rem; background:white; }
    .cb-btn { padding:5px 10px; border-radius:6px; font-size:.76rem; font-weight:700; cursor:pointer; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; white-space:nowrap; }
    .cb-btn.prim { background:#1d4ed8; border-color:#1d4ed8; color:white; }
    .cb-btn.peligro { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
    .cb-chip { display:inline-flex; align-items:center; gap:4px; margin:2px 6px 2px 0; padding:3px 8px; border:1px solid #e2e8f0; border-radius:999px; font-size:.74rem; cursor:pointer; background:white; }
    .cb-chip:has(input:checked) { background:#eff6ff; border-color:#93c5fd; color:#1e40af; font-weight:700; }
    .cb-nueva { background:white; border:1px dashed #93c5fd; border-radius:12px; padding:12px 14px; margin-bottom:14px; }
    .cb-nueva summary { cursor:pointer; font-weight:800; color:#1d4ed8; }
    .cb-form { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:10px; margin-top:12px; align-items:end; }
    .cb-form label { display:block; font-size:.74rem; font-weight:700; color:#475569; margin-bottom:3px; }
    .cb-form .cb-in { width:100%; }
    .cb-muted { color:#94a3b8; font-size:.76rem; }
</style>

<div style="max-width: 1280px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Cuentas bancarias</h1>
            <p class="muted" style="margin:4px 0 0;">
                Las cuentas de la empresa en un solo lugar: dónde salen en Flujo de Caja (Disponibilidad) y en el reporte consolidado,
                y en qué grupos aparecen en «Banco y Titular» al registrar un egreso.
            </p>
        </div>
        <a href="{{ route('finanzas.configuracion') }}" class="cb-btn">Tipos de gasto y beneficiarios →</a>
    </div>

    @if(session('success'))<div class="alert success" style="margin-top:12px;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert error" style="margin-top:12px;">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert error" style="margin-top:12px;">{{ $errors->first() }}</div>@endif

    <div class="cb-tabs">
        <a class="cb-tab {{ $tab === 'reporte' ? 'activa' : '' }}" href="{{ route('finanzas.cuentas', ['tab' => 'reporte']) }}">Disponibilidad y reporte</a>
        <a class="cb-tab {{ $tab === 'selector' ? 'activa' : '' }}" href="{{ route('finanzas.cuentas', ['tab' => 'selector']) }}">Selector de egresos («Banco y Titular»)</a>
    </div>

    <datalist id="cbBancos">@foreach($bancos as $b)<option value="{{ $b }}">@endforeach</datalist>
    <datalist id="cbEmpresas">@foreach($empresas as $e)<option value="{{ $e }}">@endforeach</datalist>

@if($tab === 'reporte')
    <div class="cb-stats">
        <div class="cb-stat"><strong>{{ $totalReporte }}</strong>cuentas con sección</div>
        <div class="cb-stat"><strong>{{ $enDisponibilidad }}</strong>en Disponibilidad (Flujo de Caja)</div>
        <div class="cb-stat"><strong>{{ $ocultas->count() }}</strong>quitadas</div>
    </div>

    @if($puedeEditar)
    <details class="cb-nueva" @if(old('categoria_reporte') && ! old('grupos')) open @endif>
        <summary>+ Agregar cuenta</summary>
        <form method="POST" action="{{ route('finanzas.cuentas.crear') }}" class="cb-form">
            @csrf
            <div><label>Banco</label><input class="cb-in" name="banco" list="cbBancos" required maxlength="120" value="{{ old('banco') }}" placeholder="BANESCO"></div>
            <div><label>Empresa / titular</label><input class="cb-in" name="titular" list="cbEmpresas" required maxlength="200" value="{{ old('titular') }}" placeholder="GRUPO JRZ"></div>
            <div style="grid-column: span 2;"><label>Sección</label>
                <select class="cb-in" name="categoria_reporte" required>
                    @foreach(\App\Http\Controllers\FinanzasCuentasController::SECCIONES as $s => [$etiqueta, $donde])
                        <option value="{{ $s }}" @selected(old('categoria_reporte') === $s)>{{ $etiqueta }} — {{ $donde }}</option>
                    @endforeach
                </select>
            </div>
            @if($tieneNumero)<div><label>Nº de cuenta</label><input class="cb-in" name="numero_cuenta" inputmode="numeric" maxlength="32" value="{{ old('numero_cuenta') }}" placeholder="20 dígitos"></div>@endif
            <div><label>Color TC</label><input type="color" name="color_tc" value="{{ old('color_tc', '#f4b183') }}" style="width:100%; height:36px; border:1px solid #cbd5e1; border-radius:7px;"></div>
            <div><label><input type="checkbox" name="mostrar_en_principal" value="1" @checked(old('mostrar_en_principal', true))> En Disponibilidad</label>
                <span class="cb-muted">Tabla de Flujo de Caja</span></div>
            <div><button class="cb-btn prim" style="padding:8px 16px;">Agregar</button></div>
        </form>
    </details>
    @endif

    <div class="cb-tools">
        <input type="search" class="cb-in" id="cbBuscar" placeholder="Buscar banco, empresa o número…" style="min-width:260px;" oninput="cbFiltrar()">
        <select class="cb-in" id="cbBanco" onchange="cbFiltrar()">
            <option value="">Todos los bancos</option>
            @foreach($bancos as $b)<option value="{{ mb_strtolower($b) }}">{{ $b }}</option>@endforeach
        </select>
        <label class="cb-muted"><input type="checkbox" id="cbSoloDisp" onchange="cbFiltrar()"> Solo las que salen en Disponibilidad</label>
    </div>

    @php
        $bloques = $porSeccion->map(fn ($l, $s) => [$s, $secciones[$s][0] ?? $s, $secciones[$s][1] ?? '', $l]);
        if ($sinSeccion->isNotEmpty()) { $bloques->put('_sin', ['', 'Sin sección', 'No sale en el reporte', $sinSeccion]); }
    @endphp
    @foreach($bloques as [$seccion, $titulo, $donde, $lista])
        <div class="cb-sec" data-sec>
            <div class="cb-sec-head" style="border-left-color: {{ $colorSeccion($seccion) }};">
                <h3>{{ $titulo }} <span class="cb-muted">({{ $lista->count() }})</span></h3>
                <span class="cb-badge">{{ $donde }}</span>
            </div>
            @include('finanzas.partials.cuentas_tabla', ['lista' => $lista, 'quitadas' => false])
        </div>
    @endforeach

    @if($ocultas->isNotEmpty())
        <details class="cb-sec" style="padding:0;">
            <summary style="padding:10px 14px; cursor:pointer; font-weight:800; color:#64748b;">Quitadas del reporte ({{ $ocultas->count() }}) — elige una sección y guarda para volver a ponerlas</summary>
            @include('finanzas.partials.cuentas_tabla', ['lista' => $ocultas, 'quitadas' => true])
        </details>
    @endif
@else
    @php $activas = collect($selector)->where('activa', true)->count(); @endphp
    <div class="cb-stats">
        <div class="cb-stat"><strong>{{ $activas }}</strong>cuentas en «Banco y Titular»</div>
        <div class="cb-stat"><strong>{{ count($selector) - $activas }}</strong>desactivadas</div>
    </div>
    <p class="muted" style="margin:0 0 12px; font-size:.84rem;">
        Una fila por cuenta. Marca los grupos donde debe salir al registrar un egreso; sin ningún grupo deja de salir.
        Los egresos que ya la usan no cambian.
    </p>

    @if($puedeEditar)
    <details class="cb-nueva" @if(old('grupos')) open @endif>
        <summary>+ Agregar cuenta al selector</summary>
        <form method="POST" action="{{ route('finanzas.cuentas.grupos') }}" class="cb-form">
            @csrf
            <input type="hidden" name="nueva" value="1">
            <div><label>Banco</label><input class="cb-in" name="banco" list="cbBancos" required maxlength="120" value="{{ old('banco') }}" placeholder="Banesco"></div>
            <div><label>Empresa / titular</label><input class="cb-in" name="titular" list="cbEmpresas" required maxlength="200" value="{{ old('titular') }}" placeholder="Grupo JRZ"></div>
            <div style="grid-column: 1 / -1;"><label>Grupos</label>
                @foreach($grupos as $g)
                    <label class="cb-chip"><input type="checkbox" name="grupos[]" value="{{ $g }}" @checked(in_array($g, old('grupos', []), true))> {{ $cortos[$g] ?? $g }}</label>
                @endforeach
            </div>
            <div><button class="cb-btn prim" style="padding:8px 16px;">Agregar</button></div>
        </form>
    </details>
    @endif

    <div class="cb-tools">
        <input type="search" class="cb-in" id="cbBuscar" placeholder="Buscar banco o empresa…" style="min-width:260px;" oninput="cbFiltrar()">
        <select class="cb-in" id="cbBanco" onchange="cbFiltrar()">
            <option value="">Todos los bancos</option>
            @foreach(collect($selector)->pluck('banco')->map(fn ($b) => mb_strtoupper($b))->unique()->sort() as $b)<option value="{{ mb_strtolower($b) }}">{{ $b }}</option>@endforeach
        </select>
        <label class="cb-muted"><input type="checkbox" id="cbVerInactivas" onchange="cbFiltrar()"> Ver desactivadas</label>
    </div>

    <div class="cb-sec">
        <table class="cb-table">
            <thead><tr><th>Banco</th><th>Empresa / titular</th><th>Grupos en «Banco y Titular»</th><th style="text-align:right;">Egresos</th><th></th></tr></thead>
            <tbody>
            @foreach($selector as $i => $c)
                <tr data-fila data-banco="{{ mb_strtolower($c['banco']) }}" data-buscar="{{ mb_strtolower($c['banco'].' '.$c['titular']) }}" data-activa="{{ $c['activa'] ? 1 : 0 }}" style="{{ $c['activa'] ? '' : 'opacity:.55;' }}">
                    <td><strong>{{ $c['banco'] }}</strong></td>
                    <td>{{ $c['titular'] }} @unless($c['activa'])<span style="color:#b91c1c; font-size:.72rem; font-weight:700;"> · desactivada</span>@endunless</td>
                    <td>
                        @foreach($grupos as $g)
                            @php $estado = $c['grupos'][$g] ?? null; @endphp
                            @if($puedeEditar)
                                <label class="cb-chip" title="{{ $g }}"><input type="checkbox" form="sel-{{ $i }}" name="grupos[]" value="{{ $g }}" @checked($estado['activo'] ?? false)> {{ $cortos[$g] ?? $g }}</label>
                            @elseif($estado['activo'] ?? false)
                                <span class="cb-chip">{{ $cortos[$g] ?? $g }}</span>
                            @endif
                        @endforeach
                    </td>
                    <td style="text-align:right;">{{ number_format($c['egresos']) }}</td>
                    <td style="text-align:right;">
                        @if($puedeEditar)
                            <form id="sel-{{ $i }}" method="POST" action="{{ route('finanzas.cuentas.grupos') }}">
                                @csrf
                                <input type="hidden" name="banco" value="{{ $c['banco'] }}">
                                <input type="hidden" name="titular" value="{{ $c['titular'] }}">
                                <button class="cb-btn">Guardar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
</div>

<script>
function cbFiltrar() {
    const q = (document.getElementById('cbBuscar')?.value || '').toLowerCase().trim();
    const banco = document.getElementById('cbBanco')?.value || '';
    const soloDisp = document.getElementById('cbSoloDisp')?.checked;
    const verInactivas = document.getElementById('cbVerInactivas')?.checked;
    document.querySelectorAll('[data-fila]').forEach(tr => {
        const ok = (!q || tr.dataset.buscar.includes(q)) && (!banco || tr.dataset.banco === banco)
            && (!soloDisp || tr.dataset.disp === '1')
            && (tr.dataset.activa === undefined || verInactivas || tr.dataset.activa === '1' || q);
        tr.style.display = ok ? '' : 'none';
    });
    document.querySelectorAll('[data-sec]').forEach(sec => {
        sec.style.display = [...sec.querySelectorAll('[data-fila]')].some(tr => tr.style.display !== 'none') ? '' : 'none';
    });
}
document.addEventListener('DOMContentLoaded', cbFiltrar);
</script>
@endsection
