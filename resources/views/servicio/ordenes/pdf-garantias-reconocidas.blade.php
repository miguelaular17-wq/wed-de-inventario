<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Garantías reconocidas por la marca</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        /* Dompdf toma el margen de html como margen de cada hoja; el * de arriba lo dejaría en 0. */
        @page { margin: 14mm 12mm; }
        html { margin: 14mm 12mm; }
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
        .bloque { margin-top: 12px; page-break-inside: avoid; }
        .bloque-title { background: #1a4480; color: #fff; padding: 6px 9px; font-size: 11px; font-weight: 700; }
        .bloque-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 0; padding: 5px 9px; color: #475569; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #eef4fc; color: #1a4480; font-size: 8px; text-transform: uppercase; padding: 5px 8px; border: 1px solid #bfdbfe; text-align: left; white-space: nowrap; }
        td { padding: 4px 8px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; white-space: nowrap; }
        /* La columna de texto largo toma el espacio que sobra; las demás miden lo que su contenido. */
        .col-libre { width: 100%; white-space: normal; }
        .muted { color: #64748b; font-size: 8px; }
        .motivo { display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .note { margin-top: 12px; color: #64748b; font-size: 8px; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Garantías reconocidas por la marca</h1>
            <div class="sub">
                Corte al {{ now()->format('d/m/Y') }}
                @if(!empty($filtroSede)) · Sede {{ $filtroSede }} @endif
                @if(!empty($empresaFiltro)) · {{ $empresasEnvio[$empresaFiltro] ?? 'Sin empresa de envío' }} @endif
                @if(!empty($motivoFiltro)) · {{ \App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA[$motivoFiltro] }} @endif
            </div>
            <div class="sub">Palacio de los Detalles · Servicio técnico / Garantías</div>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><span>Total reconocidas</span><strong>{{ $total }}</strong></div>
        @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
            <div class="kpi"><span>{{ $label }}</span><strong>{{ $porMotivo[$key] ?? 0 }}</strong></div>
        @endforeach
    </div>

    @forelse($porEmpresa as $bloque)
        <div class="bloque">
            <div class="bloque-title">{{ $bloque['empresa'] }}</div>
            <div class="bloque-summary">
                {{ count($bloque['ordenes']) }} órdenes
                @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
                    @if(!empty($bloque['motivos'][$key])) · {{ $label }} {{ $bloque['motivos'][$key] }} @endif
                @endforeach
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Fecha</th>
                        <th>Marca</th>
                        <th>Equipo</th>
                        <th>Sede origen</th>
                        <th>Creada por</th>
                        <th>Fecha de envío</th>
                        <th>Motivo</th>
                        <th class="col-libre">Cliente / Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bloque['ordenes'] as $orden)
                        @php [$fondo, $texto] = \App\Models\StOrden::COLORES_RECONOCIMIENTO_GARANTIA[$orden->estado_empresa_garantia] ?? ['#f1f5f9', '#475569']; @endphp
                        <tr>
                            <td>{{ $orden->codigo() }}</td>
                            <td>{{ optional($orden->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $orden->marca_reporte }}</td>
                            <td>
                                {{ $orden->equipo ?: '—' }}@if($orden->esLote()) <strong>× {{ $orden->unidades() }}</strong>@endif
                                @if($orden->serial || $orden->imei)
                                    <div class="muted">{{ $orden->serial ?: $orden->imei }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $orden->sedeOrigen() }}
                                @if($orden->sedeOrigen() !== strtoupper((string) $orden->sede))
                                    <div class="muted">Ahora en {{ $orden->sede }}</div>
                                @endif
                            </td>
                            <td>{{ $orden->creador?->name ?: '—' }}</td>
                            <td>{{ $orden->garantia_enviado_at?->format('d/m/Y') ?: '—' }}</td>
                            <td><span class="motivo" style="background:{{ $fondo }};color:{{ $texto }};">{{ $orden->etiquetaMotivoReconocimiento() }}</span></td>
                            <td class="col-libre">{{ $orden->cliente_nombre ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty">No hay garantías reconocidas por la marca.</div>
    @endforelse

    <div class="note">
        Generado {{ now()->format('d/m/Y H:i') }}
        @if(!empty($generadoPor)) · por {{ $generadoPor }} @endif.
    </div>
</body>
</html>
