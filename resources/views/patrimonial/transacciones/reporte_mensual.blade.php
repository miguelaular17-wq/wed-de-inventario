@extends('layouts.app')
@section('title', 'Reporte Mensual Patrimonial')
@section('content')

@php
    $nombreMes = \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y');
    $mesPrev = \Carbon\Carbon::create($anio, $mes)->subMonth();
    $mesSig = \Carbon\Carbon::create($anio, $mes)->addMonth();
    $ingresosPorPropiedad = $ingresosPorPropiedad ?? [];
    $gastosPorCategoria = $gastosPorCategoria ?? [];
    $comisionesPorCategoria = $comisionesPorCategoria ?? [];
@endphp

<div style="max-width:980px; margin:0 auto; padding:32px 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:28px;">
        <div>
            <a href="{{ route('patrimonial.transacciones.index') }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">← Transacciones</a>
            <h1 style="font-size:1.5rem; font-weight:700; color:#1e293b; margin:8px 0 0;">Reporte mensual patrimonial</h1>
            <p style="margin:4px 0 0; color:#64748b; font-size:0.9rem;">{{ $nombreMes }} · recibido → gastos / comisión → neto</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="?mes={{ $mesPrev->month }}&anio={{ $mesPrev->year }}" style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">← Anterior</a>
            <a href="?mes={{ $mesSig->month }}&anio={{ $mesSig->year }}" style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">Siguiente →</a>
            <a href="{{ route('patrimonial.reportes.mensual.pdf', ['mes' => $mes, 'anio' => $anio]) }}" target="_blank" style="padding:8px 16px; background:#dc2626; color:#fff; border-radius:8px; font-weight:600; font-size:0.85rem; text-decoration:none;">Descargar PDF</a>
        </div>
    </div>

    {{-- RESULTADO --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <h2 style="margin:0 0 14px; font-size:0.95rem; font-weight:700; color:#1e3a8a; text-transform:uppercase; letter-spacing:0.4px;">Resultado del mes</h2>
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
            @foreach([
                ['Total recibido', $totales['ingresos'], '#059669'],
                ['Gastos', $totales['gastos'], '#dc2626'],
                ['Comisiones', $totales['comisiones'], '#d97706'],
                ['Neto', $totales['balance'], $totales['balance'] >= 0 ? '#059669' : '#dc2626'],
            ] as [$lbl, $val, $color])
            <div style="text-align:center; padding:12px; background:#f8fafc; border-radius:10px;">
                <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.4px; font-weight:600;">{{ $lbl }}</div>
                <div style="font-size:1.25rem; font-weight:700; color:{{ $color }}; margin-top:4px;">${{ number_format($val, 2) }}</div>
            </div>
            @endforeach
        </div>
        <p style="margin:12px 0 0; font-size:0.85rem; color:#475569;">
            ${{ number_format($totales['ingresos'], 2) }} recibido
            − ${{ number_format($totales['gastos'], 2) }} gastos
            − ${{ number_format($totales['comisiones'], 2) }} comisiones
            = <strong style="color:{{ $totales['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($totales['balance'], 2) }} neto</strong>
        </p>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
        {{-- INGRESOS --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:12px 16px; background:#ecfdf5; color:#047857; font-weight:700; font-size:0.85rem; text-transform:uppercase;">Ingresos por alquileres</div>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <tbody>
                    @forelse($ingresosPorPropiedad as $linea)
                        <tr>
                            <td style="padding:10px 16px; border-bottom:1px solid #f1f5f9;">
                                {{ $linea['nombre'] }}
                                <div style="font-size:0.75rem; color:#94a3b8;">{{ $linea['codigo'] }}</div>
                            </td>
                            <td style="padding:10px 16px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:600; color:{{ $linea['monto'] > 0 ? '#059669' : '#94a3b8' }};">
                                ${{ number_format($linea['monto'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" style="padding:16px; color:#94a3b8;">Sin ingresos en este mes.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="background:#f0fdf4;">
                        <td style="padding:10px 16px; font-weight:700;">Total recibido</td>
                        <td style="padding:10px 16px; text-align:right; font-weight:700; color:#059669;">${{ number_format($totales['ingresos'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
            {{-- GASTOS --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding:12px 16px; background:#fef2f2; color:#b91c1c; font-weight:700; font-size:0.85rem; text-transform:uppercase;">Gastos por concepto</div>
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tbody>
                        @forelse($gastosPorCategoria as $linea)
                            <tr>
                                <td style="padding:9px 16px; border-bottom:1px solid #f1f5f9;">{{ $linea['categoria'] }}</td>
                                <td style="padding:9px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:#dc2626; font-weight:600;">${{ number_format($linea['monto'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" style="padding:16px; color:#94a3b8;">Sin gastos registrados.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:#fef2f2;">
                            <td style="padding:10px 16px; font-weight:700;">Total gastos</td>
                            <td style="padding:10px 16px; text-align:right; font-weight:700; color:#dc2626;">${{ number_format($totales['gastos'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- COMISIONES --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding:12px 16px; background:#fffbeb; color:#b45309; font-weight:700; font-size:0.85rem; text-transform:uppercase;">Comisiones</div>
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tbody>
                        @forelse($comisionesPorCategoria as $linea)
                            <tr>
                                <td style="padding:9px 16px; border-bottom:1px solid #f1f5f9;">{{ $linea['categoria'] }}</td>
                                <td style="padding:9px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:#d97706; font-weight:600;">${{ number_format($linea['monto'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" style="padding:16px; color:#94a3b8;">Sin comisiones registradas.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:#fffbeb;">
                            <td style="padding:10px 16px; font-weight:700;">Total comisiones</td>
                            <td style="padding:10px 16px; text-align:right; font-weight:700; color:#d97706;">${{ number_format($totales['comisiones'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- CIERRE POR PROPIEDAD --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
            <h2 style="margin:0; font-size:1rem; font-weight:700; color:#334155;">Cierre por propiedad</h2>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase;">Propiedad</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#059669; text-transform:uppercase;">Ingreso bruto</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#dc2626; text-transform:uppercase;">Gastos</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#d97706; text-transform:uppercase;">Comisión</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#334155; text-transform:uppercase;">Neto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte as $row)
                    @php $tiene = ($row['ingresos'] > 0 || $row['gastos'] > 0 || $row['comisiones'] > 0); @endphp
                    @if($tiene)
                    <tr>
                        <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; font-weight:600; color:#334155; vertical-align:top;">
                            {{ $row['propiedad'] }}
                            <div style="font-size:0.75rem; font-weight:400; color:#94a3b8;">{{ $row['codigo'] }} · {{ ucfirst($row['tipo']) }}</div>
                            @if(!empty($row['gastosPorCategoria']) || !empty($row['comisionesPorCategoria']) || !empty($row['ingresosPorCategoria']))
                                <div style="margin-top:6px; font-size:0.75rem; font-weight:400; color:#64748b; line-height:1.45;">
                                    @foreach($row['ingresosPorCategoria'] ?? [] as $c)
                                        <div>{{ $c['categoria'] }}: ${{ number_format($c['monto'], 2) }}</div>
                                    @endforeach
                                    @foreach($row['gastosPorCategoria'] ?? [] as $c)
                                        <div>{{ $c['categoria'] }}: −${{ number_format($c['monto'], 2) }}</div>
                                    @endforeach
                                    @foreach($row['comisionesPorCategoria'] ?? [] as $c)
                                        <div>{{ $c['categoria'] }}: −${{ number_format($c['monto'], 2) }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['ingresos'] > 0 ? '#059669' : '#94a3b8' }}; font-weight:600;">${{ number_format($row['ingresos'], 2) }}</td>
                        <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['gastos'] > 0 ? '#dc2626' : '#94a3b8' }}; font-weight:600;">${{ number_format($row['gastos'], 2) }}</td>
                        <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['comisiones'] > 0 ? '#d97706' : '#94a3b8' }};">${{ number_format($row['comisiones'], 2) }}</td>
                        <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:700; color:{{ $row['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($row['balance'], 2) }}</td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc; border-top:2px solid #e2e8f0;">
                    <td style="padding:12px 16px; font-weight:700; color:#334155;">Neto del mes</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#059669;">${{ number_format($totales['ingresos'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#dc2626;">${{ number_format($totales['gastos'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#d97706;">${{ number_format($totales['comisiones'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; font-size:1rem; color:{{ $totales['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($totales['balance'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="text-align:center; margin-top:24px; color:#94a3b8; font-size:0.78rem;">
        Solo se listan conceptos con monto registrado. Generado: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
@endsection
