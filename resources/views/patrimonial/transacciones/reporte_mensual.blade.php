@extends('layouts.app')
@section('title', 'Reporte Mensual Patrimonial')
@section('content')

<div style="max-width:960px; margin:0 auto; padding:32px 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:28px;">
        <div>
            <a href="{{ route('patrimonial.transacciones.index') }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">← Transacciones</a>
            <h1 style="font-size:1.5rem; font-weight:700; color:#1e293b; margin:8px 0 0;">📄 Reporte Mensual Patrimonial</h1>
            <p style="margin:4px 0 0; color:#64748b; font-size:0.9rem;">{{ \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y') }}</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            @php $mesPrev = \Carbon\Carbon::create($anio,$mes)->subMonth(); $mesSig = \Carbon\Carbon::create($anio,$mes)->addMonth(); @endphp
            <a href="?mes={{ $mesPrev->month }}&anio={{ $mesPrev->year }}" style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">← Anterior</a>
            <a href="?mes={{ $mesSig->month }}&anio={{ $mesSig->year }}" style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">Siguiente →</a>
            <a href="{{ route('patrimonial.reportes.mensual.pdf', ['mes' => $mes, 'anio' => $anio]) }}" target="_blank" style="padding:8px 16px; background:#dc2626; color:#fff; border-radius:8px; font-weight:600; font-size:0.85rem; text-decoration:none;">📄 Descargar PDF</a>
            <button onclick="window.print()" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.85rem; cursor:pointer;">🖨️ Imprimir</button>
        </div>
    </div>

    {{-- TOTALES GENERALES --}}
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:28px;">
        @foreach([
            ['Ingresos Total', $totales['ingresos'], '#059669'],
            ['Gastos Total', $totales['gastos'], '#dc2626'],
            ['Comisiones', $totales['comisiones'], '#d97706'],
            ['Balance General', $totales['balance'], $totales['balance'] >= 0 ? '#059669' : '#dc2626'],
        ] as [$lbl, $val, $color])
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; margin-bottom:6px;">{{ $lbl }}</div>
            <div style="font-size:1.3rem; font-weight:700; color:{{ $color }};">${{ number_format($val, 2) }}</div>
        </div>
        @endforeach
    </div>

    {{-- TABLA POR PROPIEDAD --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.05);">
        <div style="padding:16px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
            <h2 style="margin:0; font-size:1rem; font-weight:700; color:#334155;">Balance por Propiedad</h2>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Propiedad</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Tipo</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#059669; text-transform:uppercase; letter-spacing:0.5px;">Ingresos</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#dc2626; text-transform:uppercase; letter-spacing:0.5px;">Gastos</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#d97706; text-transform:uppercase; letter-spacing:0.5px;">Comisiones</th>
                    <th style="padding:10px 16px; border-bottom:2px solid #e2e8f0; text-align:right; font-size:11px; color:#334155; text-transform:uppercase; letter-spacing:0.5px;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte as $row)
                <tr style="transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; font-weight:600; color:#334155;">
                        {{ $row['propiedad'] }}
                        <div style="font-size:0.75rem; font-weight:400; color:#94a3b8;">{{ $row['codigo'] }}</div>
                    </td>
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.85rem;">{{ ucfirst($row['tipo']) }}</td>
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['ingresos'] > 0 ? '#059669' : '#94a3b8' }}; font-weight:{{ $row['ingresos'] > 0 ? '600' : '400' }};">
                        ${{ number_format($row['ingresos'], 2) }}
                    </td>
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['gastos'] > 0 ? '#dc2626' : '#94a3b8' }}; font-weight:{{ $row['gastos'] > 0 ? '600' : '400' }};">
                        ${{ number_format($row['gastos'], 2) }}
                    </td>
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; color:{{ $row['comisiones'] > 0 ? '#d97706' : '#94a3b8' }};">
                        ${{ number_format($row['comisiones'], 2) }}
                    </td>
                    <td style="padding:11px 16px; border-bottom:1px solid #f1f5f9; text-align:right; font-weight:700; color:{{ $row['balance'] >= 0 ? '#059669' : '#dc2626' }};">
                        ${{ number_format($row['balance'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc; border-top:2px solid #e2e8f0;">
                    <td colspan="2" style="padding:12px 16px; font-weight:700; color:#334155;">TOTALES GENERALES</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#059669;">${{ number_format($totales['ingresos'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#dc2626;">${{ number_format($totales['gastos'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; color:#d97706;">${{ number_format($totales['comisiones'], 2) }}</td>
                    <td style="padding:12px 16px; text-align:right; font-weight:700; font-size:1rem; color:{{ $totales['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($totales['balance'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="text-align:center; margin-top:24px; color:#94a3b8; font-size:0.78rem;">
        Generado: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

<style>
@media print {
    header, .wrap > a, button { display: none !important; }
    body { background: white; }
}
</style>
@endsection
