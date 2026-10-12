<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1a4480; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 90px; }
        .header-logo img { height: 58px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; padding-left: 12px; }
        h1 { font-size: 15px; color: #1a4480; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        .kpis { display: table; width: 100%; margin: 8px 0 12px; border-collapse: separate; border-spacing: 6px 0; }
        .kpi { display: table-cell; width: 25%; background: #eef4fc; border: 1px solid #bfdbfe; border-radius: 6px; padding: 7px 9px; }
        .kpi span { display: block; font-size: 7px; text-transform: uppercase; color: #1a4480; font-weight: 700; }
        .kpi strong { display: block; margin-top: 2px; font-size: 13px; }
        .marca-block { margin-top: 12px; page-break-inside: avoid; }
        .marca-title { background: #1a4480; color: #fff; padding: 6px 9px; font-size: 11px; font-weight: 700; }
        .marca-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 0; padding: 5px 9px; color: #475569; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #eef4fc; color: #1a4480; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: left; }
        td { padding: 4px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #64748b; font-size: 8px; }
        .tot td { font-weight: bold; background: #f8fafc; }
        .note { margin-top: 12px; color: #64748b; font-size: 8px; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
        .resumen { margin-bottom: 10px; }
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
                {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
                @if(!empty($filtroSede)) · Sede {{ $filtroSede }} @endif
                @if(!empty($tipoFiltro)) · {{ $tiposGestion[$tipoFiltro] ?? $tipoFiltro }} @endif
            </div>
            <div class="sub">Palacio de los Detalles · Servicio técnico / Garantías / Reparaciones internas</div>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><span>Total órdenes</span><strong>{{ $totales['total'] }}</strong></div>
        <div class="kpi"><span>Servicio técnico</span><strong>{{ $totales['st'] }}</strong></div>
        <div class="kpi"><span>Garantías</span><strong>{{ $totales['garantia'] }}</strong></div>
        <div class="kpi"><span>Reparaciones internas</span><strong>{{ $totales['interna'] }}</strong></div>
    </div>

    <div class="resumen">
        <table>
            <thead>
                <tr>
                    <th>Marca</th>
                    <th class="num">Total</th>
                    <th class="num">Servicio técnico</th>
                    <th class="num">Garantías</th>
                    <th class="num">Internas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($porMarca as $bloque)
                    <tr>
                        <td><strong>{{ $bloque['marca'] }}</strong></td>
                        <td class="num">{{ $bloque['total'] }}</td>
                        <td class="num">{{ $bloque['st'] }}</td>
                        <td class="num">{{ $bloque['garantia'] }}</td>
                        <td class="num">{{ $bloque['interna'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No hay órdenes en el período.</td></tr>
                @endforelse
                @if(count($porMarca) > 0)
                    <tr class="tot">
                        <td>Total</td>
                        <td class="num">{{ $totales['total'] }}</td>
                        <td class="num">{{ $totales['st'] }}</td>
                        <td class="num">{{ $totales['garantia'] }}</td>
                        <td class="num">{{ $totales['interna'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @foreach($porMarca as $bloque)
        <div class="marca-block">
            <div class="marca-title">{{ $bloque['marca'] }}</div>
            <div class="marca-summary">
                {{ $bloque['total'] }} órdenes
                · ST {{ $bloque['st'] }}
                · Garantía {{ $bloque['garantia'] }}
                · Interna {{ $bloque['interna'] }}
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:10%;">Orden</th>
                        <th style="width:9%;">Fecha</th>
                        <th style="width:14%;">Tipo</th>
                        <th style="width:22%;">Equipo</th>
                        <th style="width:8%;">Sede</th>
                        <th style="width:12%;">Estado</th>
                        <th>Cliente / Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bloque['ordenes'] as $orden)
                        <tr>
                            <td>{{ $orden->codigo() }}</td>
                            <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $orden->etiquetaTipoGestion() }}</td>
                            <td>
                                {{ $orden->equipo ?: '—' }}
                                @if($orden->serial || $orden->imei)
                                    <div class="muted">{{ $orden->serial ?: $orden->imei }}</div>
                                @endif
                            </td>
                            <td>{{ $orden->sede }}</td>
                            <td>{{ $orden->etiquetaEstado() }}</td>
                            <td>{{ $orden->esReparacionInterna() ? ($orden->falla ?: '—') : ($orden->cliente_nombre ?: '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="note">
        Generado {{ now()->format('d/m/Y H:i') }}
        @if(!empty($generadoPor)) · por {{ $generadoPor }} @endif.
    </div>
</body>
</html>
