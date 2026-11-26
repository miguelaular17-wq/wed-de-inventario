@extends('layouts.app')

@section('title', 'Pago móvil Cloud · Verificación')

@section('content')
@php
    $items = collect($items);
    $grupos = [
        'conciliar' => ['Se concilian al confirmar', '#16a34a', 'Abono de pago móvil pendiente con la misma referencia y monto.'],
        'revisar' => ['Para revisar', '#d97706', 'Hay un abono del mismo monto en la ventana de fechas, pero la referencia no cuadra. No se tocan.'],
        'ya_conciliado' => ['Ya conciliados', '#0369a1', 'El abono existe, pero ya está ligado a otro registro.'],
        'no_encontrado' => ['Sin abono en el banco', '#dc2626', 'No aparecen en el extracto de esta cuenta (o aún no está cargado).'],
    ];
    $porEstado = $items->groupBy('estado');
    $fmt = fn ($p) => 'Bs. ' . number_format($p['monto'], 2, ',', '.');
    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '—';
@endphp
<div class="panel" style="max-width: 1280px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Pago móvil Cloud · Verificación</h1>
            <p class="muted" style="margin:6px 0 0;">
                {{ $plan['archivo'] }} · {{ $items->count() }} pagos · cuenta
                <strong>{{ $banco }} · {{ $titular }}</strong> ·
                {{ number_format($abonos) }} abonos del {{ $fmtFecha($desde) }} al {{ $fmtFecha($hasta) }}
            </p>
        </div>
        <a href="{{ route('finanzas.conciliaciones', ['banco_filtro' => $banco, 'titular_filtro' => $titular]) }}" class="btn secondary">Cancelar</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin:18px 0;">
        @foreach($grupos as $estado => [$titulo, $color])
            @php $g = $porEstado->get($estado, collect()); @endphp
            <div class="panel" style="padding:14px; border-left:4px solid {{ $color }};">
                <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">{{ $titulo }}</div>
                <div style="font-size:1.6rem; font-weight:800; color:{{ $color }};">{{ $g->count() }}</div>
                <div class="muted">Bs. {{ number_format($g->sum('monto'), 2, ',', '.') }}</div>
            </div>
        @endforeach
    </div>

    @if($porEstado->has('conciliar'))
        <form method="POST" action="{{ route('finanzas.conciliaciones.pagos_moviles.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Conciliando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <div style="flex:1; min-width:260px;">
                Se conciliarán <strong>{{ $porEstado['conciliar']->count() }} pagos</strong>
                (Bs. {{ number_format($porEstado['conciliar']->sum('monto'), 2, ',', '.') }})
                como ingresos de Tesorería ligados a su abono. Lo demás no se toca.
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
                    <th>#</th><th>Fecha</th><th>Caja · cajero</th><th>Ref. pago</th><th style="text-align:right;">Monto</th>
                    <th>En el banco</th>
                </tr></thead>
                <tbody>
                    @foreach($g as $p)
                        <tr>
                            <td>{{ $p['nro'] }}</td>
                            <td style="white-space:nowrap;">{{ $fmtFecha($p['fecha']) }}</td>
                            <td>{{ $p['caja'] ?: '—' }}<br><span class="muted">{{ $p['cajero'] }}</span></td>
                            <td><code>{{ $p['ref'] }}</code>@if($p['emisor'])<div class="muted" style="font-size:.72rem;">emisor {{ $p['emisor'] }}</div>@endif</td>
                            <td style="text-align:right; font-weight:700;">{{ $fmt($p) }}</td>
                            <td>
                                @if($p['linea'])
                                    <strong>{{ $p['linea']->banco }} · {{ $p['linea']->titular }}</strong><br>
                                    {{ $p['linea']->fecha->format('d/m/Y') }} · ref <code>{{ $p['linea']->referencia }}</code>
                                    · Bs. {{ number_format(abs((float) $p['linea']->monto), 2, ',', '.') }}
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
