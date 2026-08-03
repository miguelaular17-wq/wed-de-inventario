<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Gráficos Q Pedir</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; text-align: center; }
        h1 { text-align: center; font-size: 20px; }
        .chart-container { margin: 20px auto; max-width: 600px; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1>Reporte Gráfico de Estados y Categorías</h1>
    <p><strong>Fecha:</strong> {{ date('d/m/Y') }}</p>
    
    <div class="chart-container">
        <h2>Distribución Global de Estados</h2>
        <img src="{{ $chartPie }}" alt="Gráfico de Torta">
    </div>

    <div class="chart-container" style="page-break-before: always;">
        <h2>Distribución por Categoría y Estado</h2>
        <img src="{{ $chartBar }}" alt="Gráfico de Barras">
    </div>
</body>
</html>
