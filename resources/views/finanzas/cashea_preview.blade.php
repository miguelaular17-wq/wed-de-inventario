@extends('layouts.app')

@section('title', 'Cashea · Verificación')

@section('content')
@php
    $pagos = collect($pagos);
    $porEstado = $pagos->groupBy('estado');
    $grupos = [
        'conciliar' => ['Se concilian al confirmar', '#16a34a'],
        'ya_conciliado' => ['Ya conciliados', '#0369a1'],
        'revisar' => ['Para revisar', '#d97706'],
        'no_encontrado' => ['Sin abono en el banco', '#dc2626'],
    ];
    $bs = fn ($v) => 'Bs. ' . number_format((float) $v, 2, ',', '.');
    $fmtFecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '—';
    $porDia = $porEstado->get('conciliar', collect())->groupBy(fn ($p) => $p['linea']->fecha)->sortKeys();
    $limite = 300;
@endphp
<div class="panel" style="max-width: 1280px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Pagos Cashea · Verificación en el banco</h1>
            <p class="muted" style="margin:6px 0 0;">
                {{ $plan['archivo'] }} · {{ number_format($filas) }} cuotas en {{ number_format($pagos->count()) }} pagos móviles ·
                abonos del {{ $fmtFecha($desde) }} al {{ $fmtFecha($hasta) }} en {{ collect($cuentas)->map(fn ($n, $c) => $c . ' (' . number_format($n) . ')')->implode(', ') ?: 'ninguna cuenta de Cashea' }}
            </p>
        </div>
        <a href="{{ route('finanzas.conciliaciones') }}" class="btn secondary">Cancelar</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin:18px 0;">
        @foreach($grupos as $estado => [$titulo, $color])
            @php $g = $porEstado->get($estado, collect()); @endphp
            <div class="panel" style="padding:14px; border-left:4px solid {{ $color }};">
                <div class="muted" style="font-size:.78rem; text-transform:uppercase; font-weight:700;">{{ $titulo }}</div>
                <div style="font-size:1.6rem; font-weight:800; color:{{ $color }};">{{ number_format($g->count()) }}</div>
                <div class="muted">{{ $bs($g->sum('bs')) }} · {{ number_format($g->sum('filas')) }} cuotas</div>
            </div>
        @endforeach
    </div>

    @if($copias > 0)
        <div style="margin-bottom:14px; padding:12px 14px; background:#fff7ed; border:1px solid #fdba74; border-radius:10px; color:#9a3412; font-size:.88rem;">
            ⚠ El extracto de estas cuentas tiene <strong>{{ number_format($copias) }} líneas repetidas</strong> (el mismo movimiento cargado más de una vez).
            Se concilia una sola copia de cada movimiento; las demás quedan en «Movimientos en el banco» hasta depurarlas.
        </div>
    @endif

    @if($porEstado->has('conciliar'))
        <form method="POST" action="{{ route('finanzas.conciliaciones.cashea.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Conciliando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $plan['token'] }}">
            <div style="flex:1; min-width:260px;">
                Se conciliarán <strong>{{ number_format($porEstado['conciliar']->sum(fn ($p) => count($p['lineas']))) }} abonos</strong> de {{ number_format($porEstado['conciliar']->count()) }} pagos ({{ $bs($porEstado['conciliar']->sum('monto_banco')) }}):
                un ingreso de Tesorería por día ({{ $porDia->count() }} días) ligado a sus abonos. Lo demás no se toca.
            </div>
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Confirmar y conciliar</button>
        </form>

        <details style="margin-bottom:14px;">
            <summary style="cursor:pointer; font-weight:700;">Por día ({{ $porDia->count() }})</summary>
            <div class="table-wrap" style="margin-top:8px;">
                <table class="data-table" style="font-size:.82rem;">
                    <thead><tr><th>Día del abono</th><th style="text-align:right;">Pagos</th><th style="text-align:right;">Monto en el banco</th><th style="text-align:right;">Con nota (ajuste, diferencia, cola de ref.)</th></tr></thead>
                    <tbody>
                        @foreach($porDia as $dia => $g)
                            <tr>
                                <td>{{ $fmtFecha($dia) }}</td>
                                <td style="text-align:right;">{{ number_format($g->count()) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ $bs($g->sum('monto_banco')) }}</td>
                                <td style="text-align:right;">{{ number_format($g->whereNotNull('nota')->count()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @php $conNota = $porEstado->get('conciliar', collect())->filter(fn ($p) => $p['nota'] && ! str_starts_with($p['nota'], 'sin las')); @endphp
    @if($conNota->isNotEmpty())
        <details style="margin-bottom:14px;">
            <summary style="cursor:pointer; font-weight:700; color:#0f766e;">Se concilian con una nota ({{ number_format($conNota->count()) }}): diferencia pequeña, varios pagos con la misma referencia o cola de la referencia</summary>
            <div class="table-wrap" style="margin-top:8px;">
                <table class="data-table" style="font-size:.8rem;">
                    <thead><tr><th>Fecha</th><th>Referencia Cashea</th><th style="text-align:right;">Cashea</th><th style="text-align:right;">Banco</th><th>Nota</th></tr></thead>
                    <tbody>
                        @foreach($conNota->take($limite) as $p)
                            <tr>
                                <td>{{ $fmtFecha($p['fecha']) }}</td>
                                <td><code>{{ $p['ref'] }}</code></td>
                                <td style="text-align:right;">{{ $bs($p['bs']) }}</td>
                                <td style="text-align:right; font-weight:600;">{{ $bs($p['monto_banco']) }}</td>
                                <td style="font-size:.74rem;">{{ $p['nota'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @php $sobran = collect($abonos_sin_cashea ?? []); @endphp
    @if($sobran->isNotEmpty())
        <h3 style="margin:20px 0 4px; color:#7c3aed;">Abonos del banco sin pago en Cashea ({{ number_format($sobran->count()) }} · {{ $bs($sobran->sum(fn ($l) => abs((float) $l->monto))) }})</h3>
        <p class="muted" style="margin:0 0 8px; font-size:.83rem;">
            Están en el extracto en las fechas del reporte, pero ningún pago de Cashea los explica: transferencias que no son cuotas,
            pagos que Cashea registró con otra referencia o que no salen en este export. Se quedan pendientes.
            @if($sobran->count() > $limite) Se muestran los primeros {{ $limite }}. @endif
        </p>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.8rem;">
                <thead><tr><th>Fecha</th><th>Referencia</th><th>Descripción</th><th style="text-align:right;">Monto</th></tr></thead>
                <tbody>
                    @foreach($sobran->take($limite) as $l)
                        <tr>
                            <td style="white-space:nowrap;">{{ $fmtFecha($l->fecha) }}</td>
                            <td><code>{{ $l->referencia }}</code></td>
                            <td style="font-size:.74rem;">{{ \Illuminate\Support\Str::limit((string) $l->descripcion, 70) }}</td>
                            <td style="text-align:right; font-weight:600;">{{ $bs(abs((float) $l->monto)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @foreach(['revisar', 'no_encontrado'] as $estado)
        @php [$titulo, $color] = $grupos[$estado]; $g = $porEstado->get($estado, collect()); @endphp
        @continue($g->isEmpty())
        <h3 style="margin:20px 0 4px; color:{{ $color }};">{{ $titulo }} ({{ number_format($g->count()) }})</h3>
        <p class="muted" style="margin:0 0 8px; font-size:.83rem;">
            @if($estado === 'revisar')
                La referencia está en el banco, pero el monto no cuadra ni sumando las cuotas ni quitando los ajustes de Cashea.
            @else
                La referencia no aparece en los abonos cargados de las cuentas de Cashea (¿falta cargar el extracto de esos días?).
            @endif
            @if($g->count() > $limite) Se muestran los primeros {{ $limite }}. @endif
        </p>
        <div class="table-wrap">
            <table class="data-table" style="font-size:.8rem;">
                <thead><tr><th>Fecha</th><th>Referencia</th><th style="text-align:right;">Cashea</th><th>Cuotas</th><th>En el banco</th></tr></thead>
                <tbody>
                    @foreach($g->take($limite) as $p)
                        <tr>
                            <td style="white-space:nowrap;">{{ $fmtFecha($p['fecha']) }}</td>
                            <td><code>{{ $p['ref'] }}</code></td>
                            <td style="text-align:right; font-weight:700;">{{ $bs($p['bs']) }}</td>
                            <td>
                                {{ $p['filas'] }} {{ $p['filas'] === 1 ? 'cuota' : 'cuotas' }}
                                @foreach($p['detalle'] as $d)<div class="muted" style="font-size:.72rem;">{{ $d }}</div>@endforeach
                            </td>
                            <td>
                                @if($p['linea'])
                                    {{ $p['linea']->banco }} · {{ $p['linea']->titular }} · {{ $fmtFecha($p['linea']->fecha) }} · {{ $bs(abs((float) $p['linea']->monto)) }}
                                @endif
                                @if($p['nota'])<div style="font-size:.74rem; color:{{ $color }}; font-weight:600;">{{ $p['nota'] }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
