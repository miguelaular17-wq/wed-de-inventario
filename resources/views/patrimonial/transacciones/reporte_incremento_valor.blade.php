@extends('layouts.app')
@section('title', 'Incremento de Valor de las Propiedades')
@section('content')

@php
    $nombreCorte = $fecha_corte->translatedFormat('F Y');
    $mesPrev = $fecha_corte->copy()->subMonth();
    $mesSig = $fecha_corte->copy()->addMonth();
@endphp

<div style="max-width:1100px; margin:0 auto; padding:32px 20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">← Gestión patrimonial</a>
            <h1 style="font-size:1.5rem; font-weight:700; color:#1e293b; margin:8px 0 0;">Incremento de valor de las propiedades</h1>
            <p style="margin:4px 0 0; color:#64748b; font-size:0.9rem;">Remodelaciones acumuladas hasta {{ $nombreCorte }}</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('patrimonial.reportes.incremento_valor', ['mes' => $mesPrev->month, 'anio' => $mesPrev->year]) }}" style="padding:8px 13px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155;">← Anterior</a>
            <a href="{{ route('patrimonial.reportes.incremento_valor', ['mes' => $mesSig->month, 'anio' => $mesSig->year]) }}" style="padding:8px 13px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155;">Siguiente →</a>
            <a href="{{ route('patrimonial.reportes.incremento_valor.pdf', ['mes' => $mes, 'anio' => $anio]) }}" target="_blank" style="padding:8px 16px; background:#dc2626; color:#fff; border-radius:8px; font-weight:600; text-decoration:none;">Descargar PDF</a>
        </div>
    </div>

    <div style="padding:12px 16px; margin-bottom:18px; border-radius:10px; background:#fffbeb; border:1px solid #fde68a; color:#92400e; font-size:0.86rem;">
        El valor aproximado se calcula como <strong>valor anterior + inversión registrada en remodelaciones</strong>. Es una referencia contable, no un avalúo comercial.
    </div>

    <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:22px;">
        @foreach([
            ['Valor anterior', $totales['valor_anterior'], '#1e3a8a'],
            ['Invertido en remodelaciones', $totales['inversion_remodelaciones'], '#d97706'],
            ['Valor aproximado actualizado', $totales['valor_aproximado'], '#059669'],
        ] as [$label, $value, $color])
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:17px; text-align:center;">
                <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; font-weight:700;">{{ $label }}</div>
                <div style="font-size:1.3rem; color:{{ $color }}; font-weight:800; margin-top:5px;">${{ number_format($value, 2) }}</div>
            </div>
        @endforeach
    </div>

    @forelse($filas as $fila)
        @php $propiedad = $fila['propiedad']; @endphp
        <section style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:18px;">
            <div style="padding:14px 18px; background:#1e3a8a; color:#fff; display:flex; justify-content:space-between; gap:12px;">
                <div>
                    <strong>{{ $propiedad->nombre }}</strong>
                    <div style="font-size:0.75rem; opacity:.8;">{{ $propiedad->codigo }} · {{ ucfirst($propiedad->tipo) }}</div>
                </div>
                <div style="text-align:right; font-size:0.82rem;">
                    Valor aproximado<br><strong style="font-size:1.05rem;">${{ number_format($fila['valor_aproximado'], 2) }}</strong>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <div style="padding:11px 16px;">Valor anterior<br><strong>${{ number_format($fila['valor_anterior'], 2) }}</strong></div>
                <div style="padding:11px 16px;">Remodelaciones<br><strong style="color:#d97706;">${{ number_format($fila['inversion_remodelaciones'], 2) }}</strong></div>
                <div style="padding:11px 16px;">Incremento registrado<br><strong>{{ $fila['valor_anterior'] > 0 ? number_format(($fila['inversion_remodelaciones'] / $fila['valor_anterior']) * 100, 2).'%' : '—' }}</strong></div>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%; min-width:650px; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc; color:#475569;">
                            <th style="padding:9px 14px; text-align:left;">Fecha</th>
                            <th style="padding:9px 14px; text-align:left;">Descripción</th>
                            <th style="padding:9px 14px; text-align:left;">Observaciones</th>
                            <th style="padding:9px 14px; text-align:right;">Inversión</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fila['remodelaciones'] as $remodelacion)
                            <tr>
                                <td style="padding:9px 14px; border-top:1px solid #f1f5f9;">{{ $remodelacion->fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td style="padding:9px 14px; border-top:1px solid #f1f5f9;">{{ $remodelacion->descripcion ?: 'Remodelación' }}</td>
                                <td style="padding:9px 14px; border-top:1px solid #f1f5f9; color:#64748b;">{{ $remodelacion->observaciones ?: '—' }}</td>
                                <td style="padding:9px 14px; border-top:1px solid #f1f5f9; text-align:right; color:#d97706; font-weight:700;">${{ number_format($remodelacion->monto, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div style="padding:30px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; text-align:center; color:#64748b;">
            No hay remodelaciones registradas hasta este período.
        </div>
    @endforelse
</div>
@endsection
