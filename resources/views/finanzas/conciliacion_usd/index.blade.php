@extends('layouts.app')

@section('title', 'Conciliación en divisas')

@section('content')
@php
    $usd = fn ($v) => ($v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
    $cuentaSel = $banco !== '' ? $banco . '|' . $titular : '';
    $filtro = ['cuenta' => $cuentaSel, 'fecha_desde' => $desde, 'fecha_hasta' => $hasta];
    $fmt = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
    $accion = function (int $lineaId, string $accion, string $texto, string $clase = 'secondary') use ($filtro) {
        return '<form method="POST" action="' . route('finanzas.conciliacion_usd.accion') . '" style="display:inline;">'
            . csrf_field()
            . '<input type="hidden" name="linea_id" value="' . $lineaId . '"><input type="hidden" name="accion" value="' . $accion . '">'
            . '<button type="submit" class="btn ' . $clase . '" style="padding:3px 8px; font-size:.75rem;">' . e($texto) . '</button></form>';
    };
@endphp
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <div>
            <h1 style="margin:0;">Conciliación en divisas</h1>
            <p class="muted" style="margin:4px 0 0;">Binance, Mercantil Panamá, Wells Fargo y Citizens (Zelle)… Montos en USD contra los egresos del flujo de caja de cada cuenta.</p>
        </div>
        <a href="{{ route('finanzas.conciliaciones') }}" class="btn secondary">Conciliación en bolívares</a>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert error" style="margin-top:12px;">⚠️ {{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif
    @if(session('rechazadas'))
        <div class="alert" style="margin-top:12px; background:#fffbeb; border:1px solid #fde68a;">
            <strong>Filas que no se pudieron leer:</strong>
            <ul style="margin:6px 0 0; padding-left:18px; font-size:.85rem;">
                @foreach(session('rechazadas') as $r)<li>{{ $r }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:14px; margin-top:16px;">
        <form method="GET" action="{{ route('finanzas.conciliacion_usd.index') }}" class="panel" style="padding:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <label class="field" style="flex:1; min-width:220px;">Cuenta
                <select name="cuenta" required>
                    <option value="">— Selecciona la cuenta —</option>
                    @foreach($cuentas as $c)
                        @php $v = $c['banco'] . '|' . $c['titular']; @endphp
                        <option value="{{ $v }}" @selected($v === $cuentaSel)>{{ $c['banco'] }} · {{ $c['titular'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Desde <input type="date" name="fecha_desde" value="{{ $desde }}" required></label>
            <label class="field">Hasta <input type="date" name="fecha_hasta" value="{{ $hasta }}" required></label>
            <button type="submit" class="btn primary">Ver</button>
        </form>

        <form method="POST" action="{{ route('finanzas.conciliacion_usd.subir') }}" enctype="multipart/form-data" class="panel" style="padding:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <label class="field" style="flex:1; min-width:220px;">Subir extracto (Excel o CSV) de
                <select name="cuenta" required>
                    <option value="">— Cuenta —</option>
                    @foreach($cuentas as $c)
                        @php $v = $c['banco'] . '|' . $c['titular']; @endphp
                        <option value="{{ $v }}" @selected($v === $cuentaSel)>{{ $c['banco'] }} · {{ $c['titular'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">Archivo <input type="file" name="archivo" accept=".xlsx,.xls,.csv,.txt" required></label>
            <button type="submit" class="btn primary">Subir</button>
        </form>
    </div>

    @if($datos === null)
        <p class="muted" style="margin-top:18px;">Elige una cuenta y un período para ver la conciliación, o sube el extracto de la cuenta.</p>
    @else
        @php
            $tot = fn ($c) => $c->sum(fn ($l) => (float) $l->monto);
            $candidatos = function ($linea) use ($egresosManual) {
                $entrada = ! $linea->esCargo();
                return $egresosManual
                    ->filter(fn ($f) => ($f->categoria_egreso === 'traslados' && $entrada && strcasecmp(trim((string) $f->banco_receptor), trim($linea->banco)) === 0) || (! $entrada && strcasecmp(trim((string) $f->banco), trim($linea->banco)) === 0))
                    ->sortBy(fn ($f) => abs(abs((float) $f->monto_usd) - abs((float) $linea->monto)))
                    ->take(15);
            };
        @endphp
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:18px 0 6px;">
            <h2 style="margin:0; flex:1;">{{ $banco }} · {{ $titular }} <span class="muted" style="font-size:.9rem;">{{ $fmt($desde) }} al {{ $fmt($hasta) }}</span></h2>
            <form method="POST" action="{{ route('finanzas.conciliacion_usd.conciliar') }}">
                @csrf
                @foreach($filtro as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <button type="submit" class="btn primary">Match automático</button>
            </form>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:14px;">
            @foreach([
                ['Consolidados', $datos['consolidados'], '#16a34a'],
                ['Movimientos en el banco', $datos['pendientes'], '#dc2626'],
                ['Comisiones', $datos['comisiones'], '#7c3aed'],
            ] as [$t, $c, $color])
                <div class="panel" style="padding:12px; border-left:4px solid {{ $color }};">
                    <div class="muted" style="font-size:.75rem; text-transform:uppercase; font-weight:700;">{{ $t }} ({{ $c->count() }})</div>
                    <div style="font-size:1.25rem; font-weight:800;">{{ $usd($tot($c)) }}</div>
                </div>
            @endforeach
            <div class="panel" style="padding:12px; border-left:4px solid #f59e0b;">
                <div class="muted" style="font-size:.75rem; text-transform:uppercase; font-weight:700;">Egresos sin movimiento ({{ $datos['egresos_sin_pareja']->count() }})</div>
                <div style="font-size:1.25rem; font-weight:800;">{{ $usd(-$datos['egresos_sin_pareja']->sum(fn ($f) => abs((float) $f->monto_usd))) }}</div>
            </div>
        </div>

        <h3 style="margin:16px 0 6px;">Movimientos en el banco sin pareja ({{ $datos['pendientes']->count() }})</h3>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.82rem;">
                <thead><tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th style="text-align:right;">Monto</th><th>Ligar con egreso</th><th></th></tr></thead>
                <tbody>
                    @forelse($datos['pendientes'] as $l)
                        <tr>
                            <td>{{ $l->fecha->format('d/m/Y') }}</td>
                            <td><code>{{ $l->referencia }}</code></td>
                            <td style="max-width:320px;">{{ \Illuminate\Support\Str::limit($l->descripcion, 90) }}</td>
                            <td style="text-align:right; font-weight:700; color:{{ $l->monto < 0 ? '#dc2626' : '#16a34a' }};">{{ $usd($l->monto) }}</td>
                            <td>
                                @php $cands = $candidatos($l); @endphp
                                @if($cands->isNotEmpty())
                                    <form method="POST" action="{{ route('finanzas.conciliacion_usd.accion') }}" style="display:flex; gap:6px;">
                                        @csrf
                                        <input type="hidden" name="linea_id" value="{{ $l->id }}">
                                        <input type="hidden" name="accion" value="vincular">
                                        <select name="flujo_caja_id" style="max-width:300px; font-size:.78rem;">
                                            @foreach($cands as $f)
                                                <option value="{{ $f->id }}">{{ $fmt($f->fecha) }} · ${{ number_format(abs((float) $f->monto_usd), 2) }} · {{ \Illuminate\Support\Str::limit((string) $f->motivo, 40) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn primary" style="padding:3px 8px; font-size:.75rem;">Ligar</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:.78rem;">Sin egresos libres</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{!! $accion($l->id, 'comision', 'Comisión') !!} {!! $accion($l->id, 'ignorar', 'Ignorar') !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted" style="text-align:center;">Todo conciliado 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h3 style="margin:20px 0 6px;">Egresos registrados sin movimiento en el banco ({{ $datos['egresos_sin_pareja']->count() }})</h3>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.82rem;">
                <thead><tr><th>Fecha</th><th>Referencia</th><th>Motivo</th><th style="text-align:right;">USD</th></tr></thead>
                <tbody>
                    @forelse($datos['egresos_sin_pareja'] as $f)
                        <tr>
                            <td>{{ $fmt($f->fecha) }}</td>
                            <td><code>{{ $f->referencia }}</code></td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $f->motivo, 90) }}</td>
                            <td style="text-align:right; font-weight:700;">${{ number_format(abs((float) $f->monto_usd), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted" style="text-align:center;">Ninguno</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h3 style="margin:20px 0 6px;">Consolidados ({{ $datos['consolidados']->count() }})</h3>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.82rem;">
                <thead><tr><th>Fecha</th><th>Referencia</th><th>Descripción del banco</th><th>Egreso del sistema</th><th style="text-align:right;">Monto</th><th></th></tr></thead>
                <tbody>
                    @forelse($datos['consolidados'] as $l)
                        <tr>
                            <td>{{ $l->fecha->format('d/m/Y') }}</td>
                            <td><code>{{ $l->referencia }}</code></td>
                            <td style="max-width:280px;">{{ \Illuminate\Support\Str::limit($l->descripcion, 70) }}</td>
                            <td style="max-width:280px;">{{ \Illuminate\Support\Str::limit((string) $l->flujoCaja?->motivo, 70) }}</td>
                            <td style="text-align:right; font-weight:700;">{{ $usd($l->monto) }}</td>
                            <td>{!! $accion($l->id, 'desvincular', 'Desligar') !!}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted" style="text-align:center;">Aún no hay movimientos conciliados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @foreach([['Comisiones', $datos['comisiones'], 'Quitar de comisiones'], ['Ignorados', $datos['ignorados'], 'Restaurar']] as [$titulo, $lista, $boton])
            @if($lista->isNotEmpty())
                <h3 style="margin:20px 0 6px;">{{ $titulo }} ({{ $lista->count() }}) · {{ $usd($tot($lista)) }}</h3>
                <div class="table-wrap">
                    <table class="data-table" style="font-size:.82rem;">
                        <thead><tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th style="text-align:right;">Monto</th><th></th></tr></thead>
                        <tbody>
                            @foreach($lista as $l)
                                <tr>
                                    <td>{{ $l->fecha->format('d/m/Y') }}</td>
                                    <td><code>{{ $l->referencia }}</code></td>
                                    <td>{{ \Illuminate\Support\Str::limit($l->descripcion, 90) }}</td>
                                    <td style="text-align:right; font-weight:700;">{{ $usd($l->monto) }}</td>
                                    <td>{!! $accion($l->id, 'restaurar', $boton) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
    @endif
</div>
@endsection
