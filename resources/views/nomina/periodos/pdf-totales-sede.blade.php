<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Totales por sede · {{ $periodo->etiqueta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 90px; }
        .header-logo img { height: 64px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; padding-left: 12px; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 6px 5px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 5px 5px; border: 1px solid #e2e8f0; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 12px; color: #64748b; font-size: 8px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; background: #e2e8f0; font-size: 8px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>{{ $titulo ?? 'Totales por sede y área' }}</h1>
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · {{ $periodo->fecha_inicio?->format('d/m/Y') }} al {{ $periodo->fecha_fin?->format('d/m/Y') }}
                · Tasa BCV {{ number_format($tasaBcv, 2) }}
            </div>
            <div class="sub">Incluye venta neta y % que representa el pago sobre esa venta. Sin sedes/áreas con venta neta en 0.</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Sede / área</th>
                <th>Personas</th>
                <th>Asignaciones</th>
                <th>Deducciones</th>
                <th>Total pagado USD</th>
                <th>Total pagado Bs</th>
                <th>Venta neta USD</th>
                <th>% nómina / venta</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $fila)
                <tr>
                    <td><span class="badge">{{ $fila['etiqueta'] }}</span></td>
                    <td><strong>{{ $fila['nombre'] }}</strong></td>
                    <td class="num">{{ $fila['empleados'] }}</td>
                    <td class="num">{{ number_format($fila['asignaciones'], 2) }}</td>
                    <td class="num">{{ number_format($fila['deducciones'], 2) }}</td>
                    <td class="num"><strong>{{ number_format($fila['pagar_usd'], 2) }}</strong></td>
                    <td class="num">{{ number_format($fila['pagar_bs'], 2) }}</td>
                    <td class="num">{{ number_format($fila['venta_neta'], 2) }}</td>
                    <td class="num"><strong>{{ number_format($fila['pct_nomina_sobre_venta'], 2) }}%</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:16px;color:#64748b;">
                        No hay sedes o áreas con venta neta mayor a cero en esta quincena.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($filas->isNotEmpty())
            <tfoot>
                <tr class="tot">
                    <td colspan="2">Total</td>
                    <td class="num">{{ $filas->sum('empleados') }}</td>
                    <td class="num">{{ number_format($filas->sum('asignaciones'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('deducciones'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('pagar_usd'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('pagar_bs'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('venta_neta'), 2) }}</td>
                    <td class="num">
                        @php
                            $ventaTotal = (float) $filas->sum('venta_neta');
                            $pagarTotal = (float) $filas->sum('pagar_usd');
                        @endphp
                        {{ $ventaTotal > 0 ? number_format(($pagarTotal / $ventaTotal) * 100, 2).'%' : '—' }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="note">
        Generado {{ now()->format('d/m/Y H:i') }}.
        Venta neta = facturas − devoluciones (USD) del mismo período.
        % = total pagado USD ÷ venta neta × 100.
    </div>
</body>
</html>
