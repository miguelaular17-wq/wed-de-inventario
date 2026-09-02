<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Relación de comisiones {{ $periodo->etiqueta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 120px; }
        .header-logo img { height: 52px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
        h2 { font-size: 12px; color: #1e3a8a; margin: 16px 0 6px; }
        .sub { color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
        .empty { color: #94a3b8; font-style: italic; padding: 8px 0; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Relación de comisiones</h1>
            @if(!empty($grupoTitulo))
                <div class="sub" style="font-weight:bold;color:#1e3a8a;">{{ $grupoTitulo }}</div>
            @endif
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · Pago {{ $periodo->fecha_pago_comision?->format('d/m/Y') ?: '—' }}
                · Tasa BCV {{ number_format($tasaBcv, 2) }}
                · {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <h2>Supervisores y vendedores</h2>
    @if(count($filasVentas) > 0)
        <table>
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th>Venta neta</th>
                    <th>Base tel.</th>
                    <th>Base otros</th>
                    <th>Comisión</th>
                    <th>Abonos</th>
                    <th>Retención</th>
                    <th>Desc. / prést.</th>
                    <th>A pagar USD</th>
                    <th>A pagar BCV</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filasVentas as $fila)
                    <tr>
                        <td>{{ $fila['cedula'] }}</td>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['sede'] }}</td>
                        <td class="num">{{ number_format($fila['ventas'], 2) }}</td>
                        <td class="num">{{ $fila['es_supervisor'] ? '—' : number_format($fila['base_telefonia'], 2) }}</td>
                        <td class="num">{{ $fila['es_supervisor'] ? '—' : number_format($fila['base_otros'], 2) }}</td>
                        <td class="num">{{ number_format($fila['comision'], 2) }}</td>
                        <td class="num">{{ number_format($fila['abonos'], 2) }}</td>
                        <td class="num">{{ number_format($fila['retencion'], 2) }}</td>
                        <td class="num">{{ number_format($fila['descuentos'], 2) }}</td>
                        <td class="num">{{ number_format($fila['pagar_usd'], 2) }}</td>
                        <td class="num">{{ number_format($fila['pagar_bs'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td colspan="3">Totales ({{ count($filasVentas) }} empleados)</td>
                    <td class="num">{{ number_format($totalesVentas['ventas'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['base_telefonia'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['base_otros'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['comision'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['abonos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['retencion'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['descuentos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['pagar_usd'], 2) }}</td>
                    <td class="num">{{ number_format($totalesVentas['pagar_bs'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="empty">Sin liquidaciones de supervisores o vendedores.</p>
    @endif

    <h2>Servicio técnico</h2>
    @if(count($filasSt) > 0)
        <table>
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Empleado</th>
                    <th>Sede</th>
                    <th>Facturas ST</th>
                    <th>Egresos 058</th>
                    <th>Otros productos</th>
                    <th>Comisión</th>
                    <th>Abonos</th>
                    <th>Retención</th>
                    <th>Desc. / prést.</th>
                    <th>A pagar USD</th>
                    <th>A pagar BCV</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filasSt as $fila)
                    <tr>
                        <td>{{ $fila['cedula'] }}</td>
                        <td>{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['sede'] }}</td>
                        <td class="num">{{ number_format($fila['facturas_st'], 2) }}</td>
                        <td class="num">{{ number_format($fila['egresos_058'], 2) }}</td>
                        <td class="num">{{ number_format($fila['otros_productos'], 2) }}</td>
                        <td class="num">{{ number_format($fila['comision'], 2) }}</td>
                        <td class="num">{{ number_format($fila['abonos'], 2) }}</td>
                        <td class="num">{{ number_format($fila['retencion'], 2) }}</td>
                        <td class="num">{{ number_format($fila['descuentos'], 2) }}</td>
                        <td class="num">{{ number_format($fila['pagar_usd'], 2) }}</td>
                        <td class="num">{{ number_format($fila['pagar_bs'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td colspan="3">Totales ({{ count($filasSt) }} empleados)</td>
                    <td class="num">{{ number_format($totalesSt['facturas_st'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['egresos_058'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['otros_productos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['comision'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['abonos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['retencion'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['descuentos'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['pagar_usd'], 2) }}</td>
                    <td class="num">{{ number_format($totalesSt['pagar_bs'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p class="empty">Sin liquidaciones de servicio técnico.</p>
    @endif

    <p class="note">El sueldo de nómina se paga aparte y no aparece en este documento.</p>
</body>
</html>
