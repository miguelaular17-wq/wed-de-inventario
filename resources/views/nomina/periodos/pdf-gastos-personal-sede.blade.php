<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gastos de personal por sede · {{ $periodo->etiqueta }}</title>
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
            <h1>{{ $titulo ?? 'Gastos de personal por sede' }}</h1>
            <div class="sub">
                Quincena {{ $periodo->etiqueta }}
                · {{ $periodo->fecha_inicio?->format('d/m/Y') }} al {{ $periodo->fecha_fin?->format('d/m/Y') }}
                · Tasa BCV {{ number_format($tasaBcv, 2) }}
            </div>
            <div class="sub">Áreas: Call Center y Digital Manage aparte; el resto de áreas en Administración. Sin sedes con venta neta en 0.</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sede</th>
                <th>Personal</th>
                <th>Paquete salarial</th>
                <th>Total nómina</th>
                <th>Total comisiones</th>
                <th>Total gastos del personal</th>
                <th>Venta neta quincena</th>
                <th>% gastos / venta</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $fila)
                <tr>
                    <td>
                        <span class="badge">{{ $fila['etiqueta'] }}</span>
                        <strong>{{ $fila['nombre'] }}</strong>
                    </td>
                    <td class="num">{{ $fila['empleados'] }}</td>
                    <td class="num">{{ number_format($fila['paquete_salarial'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($fila['total_nomina'] ?? 0, 2) }}</td>
                    <td class="num">{{ number_format($fila['total_comisiones'] ?? 0, 2) }}</td>
                    <td class="num"><strong>{{ number_format($fila['total_gastos'] ?? 0, 2) }}</strong></td>
                    <td class="num">{{ ($fila['venta_neta'] ?? 0) > 0 ? number_format($fila['venta_neta'], 2) : '—' }}</td>
                    <td class="num">
                        @if(($fila['pct_nomina_sobre_venta'] ?? null) !== null)
                            <strong>{{ number_format($fila['pct_nomina_sobre_venta'], 2) }}%</strong>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:16px;color:#64748b;">
                        No hay sedes o áreas con venta neta mayor a cero en esta quincena.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($filas->isNotEmpty())
            <tfoot>
                <tr class="tot">
                    <td>Total</td>
                    <td class="num">{{ $filas->sum('empleados') }}</td>
                    <td class="num">{{ number_format($filas->sum('paquete_salarial'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('total_nomina'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('total_comisiones'), 2) }}</td>
                    <td class="num">{{ number_format($filas->sum('total_gastos'), 2) }}</td>
                    @php
                        // La venta de las áreas (por vendedor) ya está dentro de la venta de las sedes.
                        $ventaTotal = (float) $filas->where('tipo', 'SEDE')->sum('venta_neta');
                        if ($ventaTotal <= 0) {
                            $ventaTotal = (float) $filas->sum('venta_neta');
                        }
                        $gastosTotal = (float) $filas->sum('total_gastos');
                    @endphp
                    <td class="num">{{ $ventaTotal > 0 ? number_format($ventaTotal, 2) : '—' }}</td>
                    <td class="num">{{ $ventaTotal > 0 ? number_format(($gastosTotal / $ventaTotal) * 100, 2).'%' : '—' }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="note">
        Generado {{ now()->format('d/m/Y H:i') }}.
        Montos en USD.
        Paquete salarial = total de asignaciones de nómina.
        Total nómina = neto a pagar de nómina.
        Total comisiones = neto a pagar de comisiones (luego de bonos y retenciones).
        Total gastos del personal = total nómina + total comisiones.
        Venta neta sedes = facturas − devoluciones (USD) de la quincena; Call Center y Digital Manage = ventas de sus vendedores.
        % = total gastos del personal ÷ venta neta × 100. El % del total usa solo la venta de las sedes.
    </div>
</body>
</html>
