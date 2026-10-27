@extends('layouts.app')

@section('title', 'Pagos manuales · Verificación')

@section('content')
@php
    $items = collect($items);
    $grupos = [
        'conciliar' => ['Se concilian al confirmar', '#16a34a', 'Están en el banco con su referencia y monto, y el abono está pendiente.'],
        'revisar' => ['Para revisar', '#d97706', 'Hay un abono parecido, pero algo no cuadra (referencia distinta o monto ×100). No se tocan.'],
        'ya_conciliado' => ['Ya conciliados', '#0369a1', 'El abono existe en el banco, pero ya está ligado a otro registro (por ejemplo, el reporte de pago móvil).'],
        'no_encontrado' => ['Sin abono en ningún banco', '#dc2626', 'No aparecen en los extractos cargados de ninguna cuenta.'],
    ];
    $porEstado = $items->groupBy('estado');
    $fmt = fn ($p) => $p['moneda'] === 'usd' ? '$' . number_format($p['monto'], 2) : 'Bs. ' . number_format($p['monto'], 2, ',', '.');
    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '—';
    $usdSinExtracto = $abonos_usd === 0 && $items->where('moneda', 'usd')->isNotEmpty();
@endphp
<div class="panel" style="max-width: 1280px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Pagos manuales · Verificación en los bancos</h1>
            <p class="muted" style="margin:6px 0 0;">
                {{ $plan['archivo'] }} · {{ $items->count() }} pagos · se buscaron en {{ number_format($abonos_bs) }} abonos de {{ $cuentas_bs }} cuentas en bolívares
                y {{ number_format($abonos_usd) }} abonos de {{ $cuentas_usd }} cuentas en divisas ({{ $fmtFecha($desde) }} al {{ $fmtFecha($hasta) }})
            </p>
        </div>
        <a href="{{ route('finanzas.conciliaciones') }}" class="btn secondary">Cancelar</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin:18px 0;">
        @foreach($grupos as $estado => [$titulo, $color])
            @php $g = $porEstado->get($estado, collect()); @endphp
            <div class="panel" style="padding:14px; border-left:4px solid {{ $color }};">
                <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">{{ $titulo }}</div>
                <div style="font-size:1.6rem; font-weight:800; color:{{ $color }};">{{ $g->count() }}</div>
                <div class="muted">
                    Bs. {{ number_format($g->where('moneda', 'bs')->sum('monto'), 2, ',', '.') }} · ${{ number_format($g->where('moneda', 'usd')->sum('monto'), 2) }}
                </div>
            </div>
        @endforeach
    </div>

    @if($usdSinExtracto)
        <div style="margin-bottom:14px; padding:12px 14px; background:#fff7ed; border:1px solid #fdba74; border-radius:10px; color:#9a3412; font-size:.88rem;">
            Los pagos en divisas (Binance, Zelle, Mercantil Panamá) no se pueden verificar todavía: no hay extractos en divisas cargados para estas fechas.
            Súbelos en <a href="{{ route('finanzas.conciliacion_usd.index') }}">Conciliación en divisas</a> y vuelve a subir este reporte.
        </div>
    @endif

    @if($porEstado->has('conciliar'))
        <form method="POST" action="{{ route('finanzas.conciliaciones.pagos_manuales.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Conciliando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <div style="flex:1; min-width:260px;">
                Se conciliarán <strong>{{ $porEstado['conciliar']->count() }} pagos</strong>: los de bolívares quedan como ingresos de Tesorería ligados a su abono,
                y los de divisas como cobros de caja cruzados con su abono. Lo demás no se toca.
            </div>
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Confirmar y conciliar</button>
        </form>
    @endif

    @foreach($grupos as $estado => [$titulo, $color, $ayuda])
        @php $g = $porEstado->get($estado, collect()); @endphp
        @continue($g->isEmpty())
        <h3 style="margin:20px 0 4px; color:{{ $color }};">{{ $titulo }} ({{ $g->count() }})</h3>
        <p class="muted" style="margin:0 0 8px; font-size:.83rem;">{{ $ayuda }}</p>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.82rem;">
                <thead><tr>
                    <th>#</th><th>Fecha</th><th>Receptor · emisor</th><th>Caja · cajero</th><th>Ref. Cloud</th><th style="text-align:right;">Monto</th>
                    <th>En el banco</th>
                </tr></thead>
                <tbody>
                    @foreach($g as $p)
                        <tr>
                            <td>{{ $p['nro'] }}</td>
                            <td style="white-space:nowrap;">{{ $fmtFecha($p['fecha']) }}</td>
                            <td>{{ $p['receptor'] ?: '—' }}@if($p['emisor'] && $p['emisor'] !== 'Seleccione')<br><span class="muted">emisor {{ $p['emisor'] }}</span>@endif</td>
                            <td>{{ $p['caja'] }}<br><span class="muted">{{ $p['cajero'] }}</span></td>
                            <td>
                                <code>{{ $p['ref'] }}</code>
                                @foreach($p['avisos'] as $a)
                                    <div style="font-size:.72rem; color:#b45309; font-weight:600;">⚠ {{ $a }}</div>
                                @endforeach
                            </td>
                            <td style="text-align:right; font-weight:700;">{{ $fmt($p) }}</td>
                            <td>
                                @if($p['linea'])
                                    <strong>{{ $p['linea']->banco }} · {{ $p['linea']->titular }}</strong><br>
                                    {{ $p['linea']->fecha->format('d/m/Y') }} · ref <code>{{ $p['linea']->referencia }}</code>
                                    · {{ $p['moneda'] === 'usd' ? '$' . number_format(abs((float) $p['linea']->monto), 2) : 'Bs. ' . number_format(abs((float) $p['linea']->monto), 2, ',', '.') }}
                                    <div class="muted" style="font-size:.74rem;">{{ \Illuminate\Support\Str::limit((string) $p['linea']->descripcion, 60) }}</div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                                @if($p['nota'])
                                    <div style="font-size:.74rem; color:{{ $color }}; font-weight:600;">{{ $p['nota'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
