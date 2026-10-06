@extends('layouts.app')

@section('title', 'Traslados')

@php
    $fmtU = function ($n) {
        $n = (float) $n;
        $texto = number_format($n, 2);
        return $n > 0 ? '+'.$texto : $texto;
    };
    $fmtD = function ($n) {
        $n = (float) $n;
        $texto = number_format(abs($n), 2);
        return $n < 0 ? '−$'.$texto : '$'.$texto;
    };
@endphp

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Traslados</h1>
            @include('gerencial._tabs')
            <p class="muted" style="margin:8px 0 0;">Movimientos TRA de {{ $periodo['etiqueta'] }}. El signo se respeta: positivo sube la existencia (utilidad) y negativo la baja (pérdida).</p>
        </div>
    </div>

    @include('gerencial._filtros', ['modo' => 'operativo', 'action' => route('gerencial.traslados')])

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi">
            <span>Documentos</span>
            <strong>{{ number_format($kpis['documentos']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Unidades en más</span>
            <strong>{{ $fmtU($kpis['entrada']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Unidades en menos</span>
            <strong>{{ $fmtU($kpis['salida']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Neto unidades</span>
            <strong>{{ $fmtU($kpis['neto']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Utilidad</span>
            <strong>{{ $fmtD($kpis['utilidad']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Pérdida</span>
            <strong>{{ $fmtD($kpis['perdida']) }}</strong>
        </div>
        <div class="nomina-kpi">
            <span>Neto</span>
            <strong>{{ $fmtD($kpis['valor']) }}</strong>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Documentos</th>
                        <th>Unidades +</th>
                        <th>Unidades −</th>
                        <th>Neto unid.</th>
                        <th>Utilidad</th>
                        <th>Pérdida</th>
                        <th>Neto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($porSede as $fila)
                        <tr>
                            <td><strong>{{ $fila['sede'] }}</strong></td>
                            <td>{{ number_format($fila['documentos']) }}</td>
                            <td>{{ $fmtU($fila['entrada']) }}</td>
                            <td>{{ $fmtU($fila['salida']) }}</td>
                            <td>{{ $fmtU($fila['neto']) }}</td>
                            <td>{{ $fmtD($fila['utilidad']) }}</td>
                            <td>{{ $fmtD($fila['perdida']) }}</td>
                            <td>{{ $fmtD($fila['valor']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No hay traslados en el período.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($porSede !== [])
                    <tfoot>
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>{{ number_format($kpis['documentos']) }}</strong></td>
                            <td><strong>{{ $fmtU($kpis['entrada']) }}</strong></td>
                            <td><strong>{{ $fmtU($kpis['salida']) }}</strong></td>
                            <td><strong>{{ $fmtU($kpis['neto']) }}</strong></td>
                            <td><strong>{{ $fmtD($kpis['utilidad']) }}</strong></td>
                            <td><strong>{{ $fmtD($kpis['perdida']) }}</strong></td>
                            <td><strong>{{ $fmtD($kpis['valor']) }}</strong></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
