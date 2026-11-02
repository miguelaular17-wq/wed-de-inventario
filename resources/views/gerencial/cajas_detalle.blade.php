@extends('layouts.app')

@section('title', 'Facturas y devoluciones · '.$nombre)

@php
    $dinero = fn ($n) => $n === null ? '—' : '$'.number_format((float) $n, 2, ',', '.');
    $numero = fn ($n) => number_format((float) $n, 0, ',', '.');
    $fecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
@endphp

@section('content')
<div class="page-header">
    <a href="{{ route('gerencial.cajas', ['desde' => $periodo['desde'], 'hasta' => $periodo['hasta']]) }}" class="btn secondary" style="margin-bottom:10px;">← Efectividad de caja</a>
    <h1>{{ $nombre }}</h1>
    <p class="lead">
        Facturas y devoluciones que la web le cuenta como cajera ({{ $cajera }}{{ $sede ? ' · '.$sede : '' }}),
        del {{ $fecha($periodo['desde']) }} al {{ $fecha($periodo['hasta']) }}. Compara el total de cada día con el cuadre de caja.
    </p>
</div>

<form method="GET" action="{{ route('gerencial.cajas.detalle') }}" class="nomina-card gerencial-filtros" style="margin-bottom:14px;">
    <input type="hidden" name="cajera" value="{{ $cajera }}">
    <input type="hidden" name="sede" value="{{ $sede }}">
    <input type="hidden" name="nombre" value="{{ $nombre }}">
    <div class="nomina-form-grid">
        <div class="field">
            <label for="det-desde">Desde</label>
            <input id="det-desde" type="date" name="desde" value="{{ $periodo['desde'] }}" required>
        </div>
        <div class="field">
            <label for="det-hasta">Hasta</label>
            <input id="det-hasta" type="date" name="hasta" value="{{ $periodo['hasta'] }}" required>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn" type="submit">Aplicar</button>
        </div>
    </div>
</form>

<div class="nomina-kpis" style="margin-bottom:14px;">
    <div class="nomina-kpi"><span>Facturas</span><strong>{{ $numero($total['facturas']) }}</strong></div>
    <div class="nomina-kpi"><span>Devoluciones</span><strong>{{ $numero($total['devoluciones']) }}</strong></div>
    <div class="nomina-kpi"><span>Fact. crédito (Ventas diarias)</span><strong>{{ $dinero($total['credito'] ?? 0) }}</strong></div>
    <div class="nomina-kpi"><span>Venta real</span><strong>{{ $dinero($total['monto']) }}</strong></div>
</div>

<div class="panel" style="margin-bottom:16px;">
    <h3 style="margin-top:0;">Por día</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th style="text-align:right;">Facturas</th>
                    <th style="text-align:right;">Monto facturas</th>
                    <th style="text-align:right;">Devoluciones</th>
                    <th style="text-align:right;">Monto devoluciones</th>
                    <th style="text-align:right;" title="Fact. crédito anotado en Ventas diarias: no se cobra en caja">Fact. crédito</th>
                    <th style="text-align:right;">Total del día</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dias as $dia)
                    <tr>
                        <td>{{ $fecha($dia['fecha']) }}</td>
                        <td style="text-align:right;">{{ $numero($dia['facturas']) }}</td>
                        <td style="text-align:right;">{{ $dinero($dia['monto_facturas']) }}</td>
                        <td style="text-align:right;">{{ $numero($dia['devoluciones']) }}</td>
                        <td style="text-align:right;color:{{ $dia['monto_devoluciones'] < 0 ? '#b91c1c' : 'inherit' }};">{{ $dinero($dia['monto_devoluciones']) }}</td>
                        <td style="text-align:right;color:{{ $dia['credito'] > 0 ? '#b91c1c' : 'inherit' }};">{{ $dia['credito'] > 0 ? '-'.$dinero($dia['credito']) : '—' }}</td>
                        <td style="text-align:right;font-weight:700;">{{ $dinero($dia['monto']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay facturas ni devoluciones en el período.</td></tr>
                @endforelse
            </tbody>
            @if($dias)
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th style="text-align:right;">{{ $numero($total['facturas']) }}</th>
                        <th></th>
                        <th style="text-align:right;">{{ $numero($total['devoluciones']) }}</th>
                        <th></th>
                        <th style="text-align:right;">{{ ($total['credito'] ?? 0) > 0 ? '-'.$dinero($total['credito']) : '—' }}</th>
                        <th style="text-align:right;">{{ $dinero($total['monto']) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="panel">
    <h3 style="margin-top:0;">Documentos</h3>
    <div class="table-wrap" style="max-height:60vh;overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Factura original</th>
                    <th style="text-align:right;">Unidades</th>
                    <th style="text-align:right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documentos as $doc)
                    <tr>
                        <td>{{ $fecha($doc['fecha']) }}</td>
                        <td>
                            @if($doc['tipo'] === 'DEV')
                                <span style="color:#b91c1c;font-weight:700;">Devolución</span>
                                @if($doc['cajera_de_origen'])
                                    <div class="muted" style="font-size:.72rem;">Sin cajera: tomada de la factura original</div>
                                @endif
                            @else
                                Factura
                            @endif
                        </td>
                        <td style="font-family:monospace;">{{ $doc['numero'] }}</td>
                        <td>{{ $doc['cliente'] ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $doc['factura_origen'] ?: '—' }}</td>
                        <td style="text-align:right;">{{ $numero($doc['unidades']) }}</td>
                        <td style="text-align:right;color:{{ $doc['monto'] < 0 ? '#b91c1c' : 'inherit' }};">{{ $dinero($doc['monto']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay documentos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
