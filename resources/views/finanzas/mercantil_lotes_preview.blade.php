@extends('layouts.app')

@section('title', 'Lotes Mercantil · Verificación')

@section('content')
@php
    $bs = fn ($v) => 'Bs. ' . number_format((float) $v, 2, ',', '.');
    $listos = collect($planes)->filter(fn ($p) => ! $p['error'] && ! $p['plan']['error']);
@endphp
<div class="panel" style="max-width: 1100px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Lotes Mercantil · Verificación en el banco</h1>
            <p class="muted" style="margin:6px 0 0;">
                Mercantil · {{ $titular }} · {{ count($planes) }} captura(s). El total bruto de cada balance es el abono
                «CANCELACION RESUMEN DE VENTAS» del cierre; el descuento sale aparte como «DESCUENTO TARJETA».
            </p>
        </div>
        <a href="{{ route($volver ?? 'finanzas.conciliaciones') }}" class="btn secondary">Cancelar</a>
    </div>

    @foreach($planes as $p)
        @php $plan = $p['plan']; $error = $p['error'] ?: ($plan['error'] ?? null); @endphp
        <div class="panel" style="margin-top:16px; padding:16px; border-left:4px solid {{ $error ? '#dc2626' : '#16a34a' }};">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <strong>{{ $p['archivo'] }}</strong>
                @if($plan)
                    <span class="muted">Total bruto del balance: <strong>{{ $bs($plan['total']) }}</strong></span>
                @endif
            </div>

            @if($error)
                <p style="color:#b91c1c; margin:10px 0 0;">{{ $error }}</p>
            @elseif($plan['aviso'] ?? null)
                <p style="color:#b45309; margin:10px 0 0;">{{ $plan['aviso'] }}</p>
            @endif

            @if($plan && $plan['abono'])
                <p style="margin:10px 0 6px;">
                    Abono del banco: <strong>{{ $plan['abono']->fecha->format('d/m/Y') }}</strong> · ref. {{ $plan['abono']->referencia }} ·
                    {{ $plan['abono']->descripcion }} · <strong>{{ $bs($plan['abono']->monto) }}</strong>
                    @if($plan['descuento'])
                        <br><span class="muted">Descuento: {{ $plan['descuento']->descripcion }} ref. {{ $plan['descuento']->referencia }} · {{ $bs($plan['descuento']->monto) }} → pasa a Comisiones bancarias.</span>
                    @endif
                </p>
            @endif

            @if($plan && $plan['filas'] !== [])
                <table class="table" style="width:100%; margin-top:8px; font-size:.88rem;">
                    <thead>
                        <tr><th>Lote</th><th style="text-align:right;">Bruto</th><th style="text-align:right;">Descuento</th><th style="text-align:right;">Abonado</th><th>En Tesorería</th></tr>
                    </thead>
                    <tbody>
                        @foreach($plan['filas'] as $f)
                            <tr>
                                <td><strong>{{ $f['lote'] }}</strong></td>
                                <td style="text-align:right;">{{ $bs($f['bruto']) }}</td>
                                <td style="text-align:right;">{{ $bs($f['descuento']) }}</td>
                                <td style="text-align:right;">{{ $bs($f['abonado']) }}</td>
                                <td>
                                    @if($f['tesoreria'])
                                        <span style="color:#16a34a;">Lote #{{ $f['tesoreria']->id }} del {{ \Carbon\Carbon::parse($f['tesoreria']->fecha)->format('d/m/Y') }} ({{ $f['tesoreria']->descripcion }})</span>
                                    @elseif($f['conflicto'])
                                        <span style="color:#b91c1c;">Registrado por {{ $bs($f['conflicto']->monto) }}: no coincide</span>
                                    @else
                                        <span style="color:#b45309;">Falta en Tesorería: queda en «LIQ del banco sin lote» hasta registrarlo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    @if($listos->isNotEmpty())
        <form method="POST" action="{{ route('finanzas.conciliaciones.mercantil_lotes.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Conciliando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div style="flex:1; min-width:260px;">
                Se conciliarán <strong>{{ $listos->count() }} abono(s)</strong> con los lotes que están en Tesorería. Lo que falte
                queda en «LIQ del banco sin lote» y se completa solo al registrar el lote. Las capturas con error no se tocan.
            </div>
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Confirmar y conciliar</button>
        </form>
    @endif
</div>
@endsection
