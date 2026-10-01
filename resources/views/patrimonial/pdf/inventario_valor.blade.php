<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de valor de las propiedades</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1e293b; }
        .header { width: 100%; border-bottom: 3px solid #2f75b6; margin-bottom: 8px; }
        .header td { vertical-align: middle; }
        .header img { width: 52px; height: 52px; }
        h1 { margin: 0; color: #1e3a8a; font-size: 14px; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 2px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #2f75b6; color: #fff; padding: 4px 3px; text-align: left; font-size: 7px; text-transform: uppercase; }
        table.grid td { padding: 3px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .total td { background: #f1f5f9; font-weight: bold; border-top: 2px solid #2f75b6; }
        .orange { color: #c2410c; }
        .green { color: #047857; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:64px;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" alt="Palacio de los Detalles">
                @endif
            </td>
            <td>
                <h1>Inventario de valor de las propiedades</h1>
                <div class="sub">Documento digitalizado, total de remodelaciones y total del valor · {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th>Nro</th>
                <th>Propiedad</th>
                <th class="right">Valor</th>
                <th>Documento digitalizado</th>
                <th>Status</th>
                <th class="right">Total remodelaciones</th>
                <th class="right">Total del valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $fila)
                @php
                    $estado = $fila['propiedad']->estado;
                    $statusBg = match ($estado) {
                        'alquilado', 'reservado' => '#fef08a',
                        'disponible' => '#bbf7d0',
                        'remodelacion' => '#fecaca',
                        'uso_propio' => '#e0e7ff',
                        default => '#f8fafc',
                    };
                @endphp
                <tr>
                    <td>{{ $fila['nro'] }}</td>
                    <td>{{ $fila['propiedad']->nombre }}</td>
                    <td class="right">{{ $fila['valor'] === null ? '—' : '$ '.number_format($fila['valor'], 2, ',', '.') }}</td>
                    <td>{{ $fila['documento_digitalizado'] !== '' ? $fila['documento_digitalizado'] : '—' }}</td>
                    <td style="background:{{ $statusBg }};">{{ \App\Models\Patrimonial\Propiedad::estadoLabel($estado) }}</td>
                    <td class="right orange">$ {{ number_format($fila['total_remodelaciones'], 2, ',', '.') }}</td>
                    <td class="right"><strong>$ {{ number_format($fila['total_valor'], 2, ',', '.') }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="right">$ {{ number_format($totales['valor'], 2, ',', '.') }}</td>
                <td></td>
                <td></td>
                <td class="right orange">$ {{ number_format($totales['remodelaciones'], 2, ',', '.') }}</td>
                <td class="right green">$ {{ number_format($totales['total_valor'], 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
