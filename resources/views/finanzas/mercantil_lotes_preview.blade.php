@extends('layouts.app')

@section('title', 'Lotes Mercantil · Balance')

@section('content')
@php
    $bs = fn ($v) => 'Bs. ' . number_format((float) $v, 2, ',', '.');
    $todas = collect($planes)->flatMap(fn ($p) => $p['filas']);
    $nuevas = $todas->whereIn('accion', ['ligar', 'sin_lote']);
@endphp
<div class="panel" style="max-width: 1100px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;">Lotes Mercantil · Balance del banco</h1>
            <p class="muted" style="margin:6px 0 0;">
                Mercantil · {{ $titular }} · {{ count($planes) }} archivo(s). Cada lote del Excel entra como liquidación del banco (LIQ)
                por su importe bruto. Si el lote está en Tesorería con el mismo número y monto, queda conciliado; si no, va a
                «LIQ del banco sin lote». La comisión es bruto − abonado.
            </p>
        </div>
        <a href="{{ route($volver ?? 'finanzas.conciliaciones') }}" class="btn secondary">Cancelar</a>
    </div>

    @foreach($planes as $p)
        @php $filas = collect($p['filas']); @endphp
        <div class="panel" style="margin-top:16px; padding:16px; border-left:4px solid {{ $p['error'] ? '#dc2626' : '#16a34a' }};">
            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <strong>{{ $p['archivo'] }}</strong>
                @if(! $p['error'])
                    <span class="muted">{{ $filas->count() }} lote(s) · Bruto: <strong>{{ $bs($filas->sum('bruto')) }}</strong> ·
                        Abonado: <strong>{{ $bs($filas->sum('abonado')) }}</strong> · Comisión: <strong>{{ $bs($filas->sum('comision')) }}</strong></span>
                @endif
            </div>

            @if($p['error'])
                <p style="color:#b91c1c; margin:10px 0 0;">{{ $p['error'] }}</p>
            @else
                <table class="table" style="width:100%; margin-top:8px; font-size:.88rem;">
                    <thead>
                        <tr><th>Fecha</th><th>Lote</th><th style="text-align:right;">Bruto</th><th style="text-align:right;">Abonado</th><th style="text-align:right;">Comisión</th><th>Qué pasa</th></tr>
                    </thead>
                    <tbody>
                        @foreach($filas as $f)
                            <tr>
                                <td>
                                    {{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}
                                    @if($f['fecha_estimada'])<span class="muted" title="El Excel no trae fecha: se toma la del cierre de las filas vecinas."> (aprox.)</span>@endif
                                </td>
                                <td><strong>{{ $f['lote'] }}</strong></td>
                                <td style="text-align:right;">{{ $bs($f['bruto']) }}</td>
                                <td style="text-align:right;">{{ $bs($f['abonado']) }}</td>
                                <td style="text-align:right;">
                                    {{ $bs($f['comision']) }}
                                    @if($f['bruto'] > 0)<span class="muted">({{ number_format($f['comision'] / $f['bruto'] * 100, 2, ',', '.') }} %)</span>@endif
                                </td>
                                <td>
                                    @switch($f['accion'])
                                        @case('ligar')
                                            <span style="color:#16a34a;">Se concilia con el lote del {{ \Carbon\Carbon::parse($f['tesoreria']->fecha)->format('d/m/Y') }}{{ $f['tesoreria']->descripcion ? ' ('.$f['tesoreria']->descripcion.')' : '' }}</span>
                                            @break
                                        @case('sin_lote')
                                            <span style="color:#b45309;">No está en Tesorería: va a «LIQ del banco sin lote»</span>
                                            @break
                                        @case('conciliado')
                                            <span class="muted">El lote ya estaba conciliado: no se carga</span>
                                            @break
                                        @default
                                            <span class="muted">Ya se cargó antes: no se repite</span>
                                    @endswitch
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach

    @if($nuevas->isNotEmpty())
        <form method="POST" action="{{ route('finanzas.conciliaciones.mercantil_lotes.aplicar') }}"
              onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Cargando…';"
              style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:18px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px;">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div style="flex:1; min-width:260px;">
                Se cargarán <strong>{{ $nuevas->count() }} LIQ</strong>: <strong>{{ $nuevas->where('accion', 'ligar')->count() }}</strong> quedan conciliadas
                con su lote y <strong>{{ $nuevas->where('accion', 'sin_lote')->count() }}</strong> van a «LIQ del banco sin lote».
                Los archivos con error no se tocan.
            </div>
            <button type="submit" class="btn primary" style="padding:10px 18px; font-weight:700;">Confirmar y cargar</button>
        </form>
    @elseif($todas->isNotEmpty())
        <p class="muted" style="margin-top:18px;">No hay nada nuevo que cargar: esos lotes ya están.</p>
    @endif
</div>
@endsection
