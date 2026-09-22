<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Deudores únicos — Cobranza</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        .header {
            display: table; width: 100%; margin-bottom: 18px;
            border-bottom: 3px solid #1e3a8a; padding-bottom: 12px;
        }
        .header-logo { display: table-cell; vertical-align: middle; width: 110px; }
        .header-logo img { width: 90px; }
        .header-titles { display: table-cell; vertical-align: middle; text-align: center; }
        .header-titles h1 { font-size: 18px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; }
        .header-titles h2 { font-size: 11px; color: #64748b; margin-top: 3px; font-weight: 400; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 120px; color: #64748b; font-size: 10px; }
        .kpis { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .kpis td {
            width: 25%; text-align: center; padding: 10px 6px;
            border: 1px solid #bfdbfe; background: #eff6ff;
        }
        .kpis .label { font-size: 9px; color: #64748b; text-transform: uppercase; }
        .kpis .value { font-size: 15px; font-weight: 800; color: #1e3a8a; margin-top: 2px; }
        .section-title {
            font-size: 11px; font-weight: bold; color: #fff; background: #1e3a8a;
            padding: 6px 12px; text-transform: uppercase; margin-bottom: 0;
        }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .data-table th {
            background: #dbeafe; color: #1e3a8a; font-weight: bold; text-align: center;
            padding: 7px 5px; font-size: 9.5px; border: 1px solid #bfdbfe; text-transform: uppercase;
        }
        .data-table td { padding: 6px 7px; border: 1px solid #e2e8f0; font-size: 10px; }
        .data-table tr:nth-child(even) td { background: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge {
            display: inline-block; padding: 2px 7px; border-radius: 999px;
            font-size: 8.5px; font-weight: 700; text-transform: uppercase;
        }
        .badge-personal { background: #dbeafe; color: #1e40af; }
        .badge-critico { background: #fee2e2; color: #991b1b; }
        .badge-moroso { background: #fef3c7; color: #92400e; }
        .badge-reciente { background: #dcfce7; color: #166534; }
        .badge-apartado { background: #e2e8f0; color: #475569; }
        .footer {
            margin-top: 16px; font-size: 9px; color: #94a3b8;
            border-top: 1px solid #e2e8f0; padding-top: 8px;
        }
        .saldo { font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>
@php
    $fechaLabel = $ultimaFecha
        ? \Carbon\Carbon::parse($ultimaFecha)->format('d/m/Y')
        : now()->format('d/m/Y');
    $badgeEstatus = [
        'CRITICO' => 'badge-critico',
        'MOROSO' => 'badge-moroso',
        'RECIENTE' => 'badge-reciente',
        'APARTADO' => 'badge-apartado',
    ];
@endphp

<div class="header">
    <div class="header-logo">
        <img src="{{ public_path('logo.png') }}" alt="Logo">
    </div>
    <div class="header-titles">
        <h1>Deudores únicos</h1>
        <h2>{{ $alcanceLabel ?? 'Todos' }} · Cada cliente una sola vez · Saldo total e indicadores</h2>
    </div>
    <div class="header-right">
        {{ $fechaLabel }}<br>
        {{ $total_deudores }} deudor(es)
    </div>
</div>

<table class="kpis">
    <tr>
        <td>
            <div class="label">Total deudores</div>
            <div class="value">{{ number_format($total_deudores, 0, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Saldo total</div>
            <div class="value">${{ number_format($total_saldo, 2, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Personales</div>
            <div class="value">{{ number_format($personales, 0, ',', '.') }}</div>
        </td>
        <td>
            <div class="label">Crítico / Moroso</div>
            <div class="value">
                {{ ($por_estatus['CRITICO']['clientes'] ?? 0) + ($por_estatus['MOROSO']['clientes'] ?? 0) }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">Indicadores por estatus (deudor único)</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Estatus</th>
            <th>Deudores</th>
            <th>Saldo</th>
            <th>% Cartera</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['CRITICO','MOROSO','RECIENTE','APARTADO'] as $estatus)
            @php
                $c = (int) ($por_estatus[$estatus]['clientes'] ?? 0);
                $s = (float) ($por_estatus[$estatus]['saldo'] ?? 0);
                $pct = $total_saldo > 0 ? round(($s / $total_saldo) * 100) : 0;
            @endphp
            <tr>
                <td class="text-center">
                    <span class="badge {{ $badgeEstatus[$estatus] }}">{{ $estatus }}</span>
                </td>
                <td class="text-center">{{ $c }}</td>
                <td class="text-right">${{ number_format($s, 2, ',', '.') }}</td>
                <td class="text-center">{{ $pct }}%</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">Listado de deudores ({{ $total_deudores }})</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width:12%;">Código</th>
            <th style="width:28%;">Cliente</th>
            <th style="width:16%;">Sede(s)</th>
            <th style="width:8%;">Docs</th>
            <th style="width:14%;">Saldo (falta pagar)</th>
            <th style="width:12%;">Estatus</th>
            <th style="width:10%;">Tipo</th>
        </tr>
    </thead>
    <tbody>
        @forelse($deudores as $d)
            <tr>
                <td>{{ $d->codigo }}</td>
                <td>
                    {{ $d->cliente }}
                    @if(!empty($d->es_personal))
                        <span class="badge badge-personal">PERSONAL</span>
                    @endif
                </td>
                <td>{{ $d->sedes ?: '—' }}</td>
                <td class="text-center">{{ $d->documentos }}</td>
                <td class="text-right saldo">${{ number_format($d->saldo, 2, ',', '.') }}</td>
                <td class="text-center">
                    <span class="badge {{ $badgeEstatus[$d->estatus] ?? 'badge-reciente' }}">{{ $d->estatus }}</span>
                </td>
                <td class="text-center">{{ !empty($d->es_personal) ? 'Personal' : 'Regular' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No hay deudores con saldo pendiente.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} — Sistema de Inventario y Cobranza — Confidencial
    · Un cliente aparece una sola vez aunque tenga varias facturas o sedes.
</div>
</body>
</html>
