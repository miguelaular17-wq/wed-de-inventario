<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Diario Q Pedir</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; }
        h1 { text-align: center; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Reporte Diario de Q Pedir - Sede: {{ $sede }}</h1>
    <p><strong>Fecha:</strong> {{ date('d/m/Y') }}</p>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Código</th>
                <th>Categoría</th>
                <th>Frecuencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedidos as $pedido)
            <tr>
                <td>{{ $pedido->producto }}</td>
                <td>{{ $pedido->codigo }}</td>
                <td>{{ $pedido->categoria }}</td>
                <td>{{ $pedido->frecuencia }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
