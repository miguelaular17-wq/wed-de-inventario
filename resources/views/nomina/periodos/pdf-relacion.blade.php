<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Relación de nómina {{ $periodo->etiqueta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; }
        .header { width: 100%; margin-bottom: 14px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        h1 { font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relación de nómina</h1>
        <div class="sub">
            Quincena {{ $periodo->etiqueta }}
            · Estado {{ $periodo->estado }}
            · Tasa BCV {{ number_format($tasaBcv, 2) }}
            · {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Solo sueldo (salario, horas extras y deducciones). No incluye comisiones.</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Empleado</th>
                <th>Empresa</th>
                <th>Sede</th>
                <th>Salario USD</th>
                <th>Horas extra</th>
                <th>IAS</th>
                <th>Adelantos</th>
                <th>Préstamos</th>
                <th>Deducciones</th>
                <th>A pagar USD</th>
                <th>A pagar Bs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $fila)
                <tr>
                    <td>{{ $fila['cedula'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['empresa'] }}</td>
                    <td>{{ $fila['sede'] }}</td>
                    <td class="num">{{ number_format($fila['salario'], 2) }}</td>
                    <td class="num">{{ number_format($fila['horas_extras'], 2) }}</td>
                    <td class="num">{{ number_format($fila['inasistencias'], 2) }}</td>
                    <td class="num">{{ number_format($fila['adelantos'], 2) }}</td>
                    <td class="num">{{ number_format($fila['prestamos'], 2) }}</td>
                    <td class="num">{{ number_format($fila['deducciones'], 2) }}</td>
                    <td class="num">{{ number_format($fila['pagar_usd'], 2) }}</td>
                    <td class="num">{{ number_format($fila['pagar_bs'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="4">Totales ({{ count($filas) }} trabajadores)</td>
                <td class="num">{{ number_format($totales['salario'], 2) }}</td>
                <td class="num">{{ number_format($totales['horas_extras'], 2) }}</td>
                <td class="num">{{ number_format($totales['inasistencias'], 2) }}</td>
                <td class="num">{{ number_format($totales['adelantos'], 2) }}</td>
                <td class="num">{{ number_format($totales['prestamos'], 2) }}</td>
                <td class="num">{{ number_format($totales['deducciones'], 2) }}</td>
                <td class="num">{{ number_format($totales['pagar_usd'], 2) }}</td>
                <td class="num">{{ number_format($totales['pagar_bs'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    <p class="note">Las comisiones se pagan aparte y no aparecen en este documento.</p>
</body>
</html>
