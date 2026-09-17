<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #7c3aed; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 90px; }
        .header-logo img { height: 64px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; padding-left: 12px; }
        h1 { font-size: 15px; color: #5b21b6; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        .kpis { display: table; width: 100%; margin: 10px 0 14px; border-collapse: separate; border-spacing: 6px 0; }
        .kpi { display: table-cell; width: 25%; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 8px 10px; }
        .kpi span { display: block; font-size: 7px; text-transform: uppercase; color: #7c3aed; font-weight: 700; }
        .kpi strong { display: block; margin-top: 3px; font-size: 13px; color: #1e293b; }
        .sede-block { margin-top: 16px; page-break-inside: avoid; }
        .sede-title {
            background: #5b21b6; color: #fff; padding: 7px 10px; font-size: 11px; font-weight: 700;
            border-radius: 4px 4px 0 0;
        }
        .sede-summary { background: #faf5ff; border: 1px solid #e9d5ff; border-top: 0; padding: 6px 10px; color: #6b21a8; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #ede9fe; color: #5b21b6; font-size: 8px; text-transform: uppercase; padding: 6px 5px; border: 1px solid #ddd6fe; text-align: left; }
        td { padding: 5px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .codigo { font-family: Courier, monospace; font-size: 8px; color: #7c3aed; }
        .muted { color: #64748b; font-size: 8px; }
        .tot td { font-weight: bold; background: #f8fafc; }
        .note { margin-top: 14px; color: #64748b; font-size: 8px; }
        .bar-wrap { background: #e2e8f0; height: 7px; border-radius: 4px; overflow: hidden; margin-top: 3px; }
        .bar { height: 7px; background: #7c3aed; }
        .empty { text-align: center; padding: 24px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>{{ $titulo }}</h1>
            <div class="sub">
                Quincena {{ $quincena['etiqueta'] }}
                · {{ $quincena['inicio']->format('d/m/Y') }} al {{ $quincena['fin']->format('d/m/Y') }}
            </div>
            <div class="sub">Palacio de los Detalles · Avance de productos meta</div>
        </div>
    </div>

    @php
        $todas = collect($porSede);
        $iniGlobal = (float) $todas->sum(fn ($b) => $b['totales']['cantidad_inicial']);
        $venGlobal = (float) $todas->sum(fn ($b) => $b['totales']['vendido']);
        $avanceGlobal = $iniGlobal > 0
            ? round(min(100, ($venGlobal / $iniGlobal) * 100), 1)
            : ($venGlobal > 0 ? 100.0 : 0.0);
    @endphp

    <div class="kpis">
        <div class="kpi">
            <span>Productos meta</span>
            <strong>{{ $todas->sum(fn ($b) => $b['totales']['productos']) }}</strong>
        </div>
        <div class="kpi">
            <span>Stock inicial</span>
            <strong>{{ number_format($iniGlobal, 0) }} u.</strong>
        </div>
        <div class="kpi">
            <span>Vendido</span>
            <strong>{{ number_format($venGlobal, 0) }} u.</strong>
        </div>
        <div class="kpi">
            <span>Avance global</span>
            <strong>{{ number_format($avanceGlobal, 1) }}%</strong>
        </div>
    </div>

    @forelse($porSede as $bloque)
        <div class="sede-block">
            <div class="sede-title">Sede {{ $bloque['sede'] }}</div>
            <div class="sede-summary">
                {{ $bloque['totales']['productos'] }} productos
                · Inicial {{ number_format($bloque['totales']['cantidad_inicial'], 0) }} u.
                · Actual {{ number_format($bloque['totales']['cantidad_actual'], 0) }} u.
                · Vendido {{ number_format($bloque['totales']['vendido'], 0) }} u.
                · Avance {{ number_format($bloque['totales']['avance_pct'], 1) }}%
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:34%;">Producto</th>
                        <th class="num">Cantidad</th>
                        <th class="num">Cant. actual</th>
                        <th class="num">Vendido</th>
                        <th style="width:16%;">Avance</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bloque['productos'] as $fila)
                        <tr>
                            <td>
                                <div class="codigo">{{ $fila['codigo'] }}</div>
                                <strong>{{ $fila['producto'] }}</strong>
                                @if(!empty($fila['categoria']))
                                    <div class="muted">{{ $fila['categoria'] }}</div>
                                @endif
                            </td>
                            <td class="num">{{ number_format((float) $fila['cantidad_inicial'], 0) }} u.</td>
                            <td class="num">{{ number_format((float) $fila['cantidad_actual'], 0) }} u.</td>
                            <td class="num">{{ number_format((float) $fila['vendido'], 0) }} u.</td>
                            <td>
                                <strong>{{ number_format((float) $fila['avance_pct'], 1) }}%</strong>
                                <div class="bar-wrap">
                                    <div class="bar" style="width: {{ min(100, (float) $fila['avance_pct']) }}%;"></div>
                                </div>
                            </td>
                            <td>{{ $fila['responsable_nombre'] ?: 'Sin asignar' }}</td>
                        </tr>
                    @endforeach
                    <tr class="tot">
                        <td>Total {{ $bloque['sede'] }}</td>
                        <td class="num">{{ number_format($bloque['totales']['cantidad_inicial'], 0) }} u.</td>
                        <td class="num">{{ number_format($bloque['totales']['cantidad_actual'], 0) }} u.</td>
                        <td class="num">{{ number_format($bloque['totales']['vendido'], 0) }} u.</td>
                        <td><strong>{{ number_format($bloque['totales']['avance_pct'], 1) }}%</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty">No hay productos meta para esta quincena{{ $sedeFiltro ? ' en '.$sedeFiltro : '' }}.</div>
    @endforelse

    <div class="note">
        Generado {{ now()->format('d/m/Y H:i') }}
        @if(!empty($generadoPor)) · por {{ $generadoPor }} @endif.
        Avance = unidades vendidas (facturas) ÷ cantidad inicial × 100.
    </div>
</body>
</html>
