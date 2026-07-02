<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas Históricas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #333;
        }
        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 5px;
        }
        p.subtitle {
            text-align: center;
            color: #64748b;
            margin-top: 0;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #0f172a;
        }
        .text-right {
            text-align: right;
        }
        .total-col {
            background-color: #f0fdf4;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Reporte de Ventas Históricas</h2>
    <p class="subtitle">Período: {{ $startMonth }} a {{ $endMonth }}</p>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Proveedor</th>
                <th class="text-right total-col">TOTAL</th>
                @foreach($months as $month)
                    <th class="text-right">{{ \Carbon\Carbon::parse($month.'-01')->format('M Y') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($pivoted as $row)
            <tr>
                <td>{{ $row['codigo'] }}</td>
                <td>{{ $row['producto'] }}</td>
                <td>{{ $row['categoria'] }}</td>
                <td>{{ $row['proveedor'] }}</td>
                <td class="text-right total-col">{{ number_format($row['total_general'], 0) }}</td>
                @foreach($months as $month)
                    <td class="text-right">{{ $row['meses'][$month] > 0 ? number_format($row['meses'][$month], 0) : '-' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
