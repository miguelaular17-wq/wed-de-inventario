@extends('layouts.app')

@section('title', 'Transferencias en divisas · Vista previa')

@section('content')
@php
    $pares = collect($pares);
    $porComo = $pares->countBy('como');
    $usdPares = $pares->sum(fn ($p) => $p['t']['monto_usd']);
    $bsPares = $pares->sum(fn ($p) => abs((float) $p['linea']->monto) + array_sum(array_map(fn ($x) => abs((float) $x->monto), $p['extras'] ?? [])));
    $usdTotal = array_sum(array_column($transferencias, 'monto_usd'));
    $comoLabel = [
        'referencia' => 'Referencia igual',
        'referencia_final' => 'Últimos dígitos de la ref. + monto',
        'monto_fecha' => 'Monto exacto + fecha',
        'referencia_otra_tasa' => 'Misma ref., otra tasa',
        'pago_partido' => 'Pagado en varios débitos',
    ];
    $montoPar = fn ($p) => abs((float) $p['linea']->monto) + array_sum(array_map(fn ($x) => abs((float) $x->monto), $p['extras'] ?? []));
    $fmtFecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
@endphp
<div class="panel" style="max-width: 1200px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Transferencias en divisas · Vista previa</h1>
            <p class="muted" style="margin:6px 0 0;">
                {{ $plan['archivo'] }} · {{ $plan['banco'] }} · {{ $plan['titular'] }}
                · débitos del {{ $fmtFecha($desde) }} al {{ $fmtFecha($hasta) }}
            </p>
        </div>
        <a href="{{ route('finanzas.conciliaciones', array_filter(['banco_filtro' => $plan['banco'], 'titular_filtro' => $plan['titular'], 'fecha_desde' => $plan['fecha_desde'], 'fecha_hasta' => $plan['fecha_hasta']])) }}" class="btn secondary">Cancelar</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin:18px 0;">
        <div class="panel" style="padding:14px; border-left:4px solid #6366f1;">
            <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">En el PDF</div>
            <div style="font-size:1.6rem; font-weight:800;">{{ count($transferencias) }}</div>
            <div class="muted">${{ number_format($usdTotal, 2) }}</div>
        </div>
        <div class="panel" style="padding:14px; border-left:4px solid #16a34a;">
            <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">Con pareja en el banco</div>
            <div style="font-size:1.6rem; font-weight:800; color:#16a34a;">{{ $pares->count() }}</div>
            <div class="muted">${{ number_format($usdPares, 2) }} · Bs. {{ number_format($bsPares, 2) }}</div>
        </div>
        @if(! empty($ya_registradas))
            <div class="panel" style="padding:14px; border-left:4px solid #0ea5e9;">
                <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">Ya registradas antes</div>
                <div style="font-size:1.6rem; font-weight:800; color:#0369a1;">{{ count($ya_registradas) }}</div>
                <div class="muted">${{ number_format(array_sum(array_column($ya_registradas, 'monto_usd')), 2) }} · no se repiten</div>
            </div>
        @endif
        <div class="panel" style="padding:14px; border-left:4px solid #f59e0b;">
            <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">Sin pareja</div>
            <div style="font-size:1.6rem; font-weight:800; color:#b45309;">{{ count($sin_pareja) }}</div>
            <div class="muted">Se quedan como están</div>
        </div>
        <div class="panel" style="padding:14px; border-left:4px solid #94a3b8;">
            <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">Débitos pendientes revisados</div>
            <div style="font-size:1.6rem; font-weight:800;">{{ $lineas_pendientes }}</div>
            <div class="muted">
                @foreach($comoLabel as $k => $label)
                    {{ $label }}: {{ $porComo[$k] ?? 0 }}@if(! $loop->last) · @endif
                @endforeach
            </div>
        </div>
    </div>

    @if($pares->isNotEmpty())
        <form method="POST" action="{{ route('finanzas.conciliaciones.divisas.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Conciliando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <div style="flex:1; min-width:260px;">
                Se registrarán <strong>{{ $pares->count() }} compras de divisas</strong> y sus débitos pasarán a <strong>consolidados</strong>.
                Las comisiones por transferencia no se tocan.
            </div>
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Confirmar y conciliar</button>
        </form>
    @endif

    @if($sin_pareja !== [])
        <h3 style="margin:18px 0 8px;">Sin pareja en el banco ({{ count($sin_pareja) }})</h3>
        <p class="muted" style="margin:0 0 8px; font-size:.85rem;">
            Suelen ser envíos de otro mes (su débito está en otro extracto), operaciones "cruzado" o en efectivo, o débitos que ya estaban conciliados con otro egreso.
        </p>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.85rem;">
                <thead><tr><th>#</th><th>Fecha</th><th>Referencia</th><th>Banco destino</th><th>Titular</th><th style="text-align:right;">USD</th><th style="text-align:right;">Tasa</th><th style="text-align:right;">Bs. esperado</th></tr></thead>
                <tbody>
                    @foreach($sin_pareja as $t)
                        <tr>
                            <td>{{ $t['nro'] }}</td>
                            <td>{{ $fmtFecha($t['fecha']) }}</td>
                            <td><code>{{ $t['referencia'] }}</code></td>
                            <td>{{ $t['banco'] }}</td>
                            <td>{{ $t['titular'] }}</td>
                            <td style="text-align:right;">${{ number_format($t['monto_usd'], 2) }}</td>
                            <td style="text-align:right;">{{ rtrim(rtrim(number_format($t['tasa'], 2), '0'), '.') }}</td>
                            <td style="text-align:right;">Bs. {{ number_format($t['monto_bs'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($pares->isNotEmpty())
        <h3 style="margin:22px 0 8px;">Emparejadas ({{ $pares->count() }})</h3>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.82rem;">
                <thead><tr><th>#</th><th>Fecha banco</th><th>Ref. banco</th><th>Ref. PDF</th><th>Destino · titular</th><th style="text-align:right;">USD × tasa</th><th style="text-align:right;">Débito banco</th><th>Cómo</th></tr></thead>
                <tbody>
                    @foreach($pares as $p)
                        <tr>
                            <td>{{ $p['t']['nro'] }}</td>
                            <td>{{ $p['linea']->fecha->format('d/m/Y') }}</td>
                            <td>
                                <code>{{ $p['linea']->referencia }}</code>
                                @foreach($p['extras'] ?? [] as $x)
                                    <br><code>{{ $x->referencia }}</code>
                                @endforeach
                            </td>
                            <td><code>{{ $p['t']['referencia'] }}</code></td>
                            <td>{{ $p['t']['banco'] }} · {{ $p['t']['titular'] }}</td>
                            <td style="text-align:right;">${{ number_format($p['t']['monto_usd'], 2) }} × {{ rtrim(rtrim(number_format($p['t']['tasa'], 2), '0'), '.') }}</td>
                            <td style="text-align:right; font-weight:600;">
                                Bs. {{ number_format($montoPar($p), 2) }}
                                @if(! empty($p['extras']))
                                    <div class="muted" style="font-weight:400; font-size:.75rem;">
                                        {{ collect([$p['linea'], ...$p['extras']])->map(fn ($x) => number_format(abs((float) $x->monto), 2))->implode(' + ') }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="tag" style="font-size:.72rem;">{{ $comoLabel[$p['como']] ?? $p['como'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
