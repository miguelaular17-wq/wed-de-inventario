@extends('layouts.app')
@section('title', 'Inventario de valor')
@section('content')
@php
    $money = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');
    $estadoStyle = [
        'alquilado' => 'background:#fef08a;color:#713f12;',
        'reservado' => 'background:#fde68a;color:#713f12;',
        'disponible' => 'background:#bbf7d0;color:#14532d;',
        'remodelacion' => 'background:#fecaca;color:#991b1b;font-weight:700;',
        'uso_propio' => 'background:#e0e7ff;color:#312e81;',
        'no_disponible' => 'background:#e2e8f0;color:#334155;',
    ];
@endphp
<div style="max-width:1400px;margin:0 auto;padding:24px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:14px;">
            <img src="{{ asset('logo.png') }}" alt="Palacio de los Detalles" style="width:64px;height:64px;border-radius:50%;">
            <div>
                <div style="font-size:0.8rem;color:#64748b;margin-bottom:4px;">
                    <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb;text-decoration:none;">Patrimonial</a>
                    → Inventario de valor
                </div>
                <h1 style="margin:0;font-size:1.35rem;color:#1e293b;">Inventario de propiedades</h1>
                <div style="color:#64748b;font-size:0.85rem;">Valor, documento digitalizado, remodelaciones y valor total</div>
            </div>
        </div>
        <a href="{{ route('patrimonial.reportes.inventario_valor.pdf') }}" target="_blank" style="padding:9px 16px;background:#dc2626;color:#fff;border-radius:8px;font-weight:700;text-decoration:none;">Descargar PDF</a>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
        <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:10px;padding:12px 16px;min-width:180px;">
            <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;">Total valor</div>
            <strong>{{ $money($totales['valor']) }}</strong>
        </div>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 16px;min-width:180px;">
            <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;">Total remodelaciones</div>
            <strong style="color:#c2410c;">{{ $money($totales['remodelaciones']) }}</strong>
        </div>
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 16px;min-width:180px;">
            <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;">Total del valor</div>
            <strong style="color:#047857;">{{ $money($totales['total_valor']) }}</strong>
        </div>
    </div>

    <div style="overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
        <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
            <thead>
                <tr style="background:#2f75b6;color:#fff;">
                    <th style="padding:8px;text-align:left;">Nro</th>
                    <th style="padding:8px;text-align:left;">Propiedad</th>
                    <th style="padding:8px;text-align:right;">Valor</th>
                    <th style="padding:8px;text-align:left;">Documento digitalizado</th>
                    <th style="padding:8px;text-align:left;">Status</th>
                    <th style="padding:8px;text-align:right;">Total remodelaciones</th>
                    <th style="padding:8px;text-align:right;">Total del valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $fila)
                    @php $estado = $fila['propiedad']->estado; @endphp
                    <tr>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;">{{ $fila['nro'] }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;">{{ $fila['propiedad']->nombre }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;text-align:right;">{{ $fila['valor'] === null ? '—' : $money($fila['valor']) }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;">{{ $fila['documento_digitalizado'] !== '' ? $fila['documento_digitalizado'] : '—' }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;{{ $estadoStyle[$estado] ?? '' }}">{{ \App\Models\Patrimonial\Propiedad::estadoLabel($estado) }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;text-align:right;color:#c2410c;">{{ $money($fila['total_remodelaciones']) }}</td>
                        <td style="padding:7px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:700;">{{ $money($fila['total_valor']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;font-weight:700;">
                    <td colspan="2" style="padding:8px;">Total</td>
                    <td style="padding:8px;text-align:right;">{{ $money($totales['valor']) }}</td>
                    <td></td>
                    <td></td>
                    <td style="padding:8px;text-align:right;color:#c2410c;">{{ $money($totales['remodelaciones']) }}</td>
                    <td style="padding:8px;text-align:right;color:#047857;">{{ $money($totales['total_valor']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
