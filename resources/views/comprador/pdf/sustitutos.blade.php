<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Análisis de Sustitutos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 20px;
        }
        .group-container {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .group-title {
            background-color: #f1f5f9;
            padding: 8px;
            font-weight: bold;
            font-size: 12px;
            border-left: 4px solid #3b82f6;
            margin-bottom: 5px;
            color: #0f172a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f8fafc;
            color: #475569;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-danger {
            color: #ef4444;
            font-weight: bold;
        }
        .text-success {
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Reporte de Análisis de Sustitutos y Compras</h2>
    <p style="text-align: center; margin-top: -15px; color: #64748b;">Agrupados por Subcategoría y Similitud de Nombre</p>

    @foreach($sustitutos as $key => $group)
        <div class="group-container">
            <div class="group-title">
                SUBCATEGORÍA: {{ $group['subcategoria'] }} | TIPO: {{ $group['keyword'] }}
            </div>
            <table>
                <thead>
                    <tr>
                        <th width="15%">Código</th>
                        <th width="35%">Producto</th>
                        <th width="20%">Proveedor</th>
                        <th width="15%" class="text-right">Stock Global</th>
                        <th width="15%" class="text-center">Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['productos'] as $prod)
                    <tr>
                        <td>{{ $prod->codigo }}</td>
                        <td>{{ $prod->nombre }}</td>
                        <td>{{ $prod->proveedor ?: 'N/A' }}</td>
                        <td class="text-right {{ $prod->stock_total == 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($prod->stock_total, 0) }}
                        </td>
                        <td class="text-center">
                            {{ $prod->ultima_compra ? \Carbon\Carbon::parse($prod->ultima_compra)->format('d/m/Y') : 'Nunca' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</body>
</html>
