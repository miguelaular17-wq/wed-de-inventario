<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas diarias {{ $reporte->sede }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 70px; }
        .header-logo img { height: 52px; width: 52px; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        h2 { font-size: 12px; margin: 12px 0 2px; color: #334155; text-align: center; }
        .desglose-sub { text-align: center; color: #64748b; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 8px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: left; }
        td { padding: 5px 6px; border: 1px solid #e2e8f0; }
        td.lab, .venta td:first-child { white-space: nowrap; }
        .num { text-align: right; white-space: nowrap; }
        .usd { color: #047857; font-weight: bold; text-align: right; }
        .bs { text-align: right; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .venta td { font-weight: bold; background: #d1fae5; border-top: 2px solid #047857; border-bottom: 2px solid #047857; }
        .vd-unidades .usd { text-align: center; color: #047857; font-weight: bold; font-size: 13px; }
        .vd-credito .lab { color: #dc2626; font-weight: bold; }
        th.bs, th.usd { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Reporte cierre de venta diaria: {{ config('inventario.display.'.$reporte->sede, $reporte->sede) }}</h1>
            <div class="sub">
                {{ $reporte->fecha->format('d/m/Y') }}
                · Tasa {{ number_format((float) $reporte->tasa, 2) }}
                · Meta venta ${{ number_format($metaCtx['meta_venta'], 2) }}
                · Meta productos {{ number_format($metaCtx['meta_productos'], 2) }}
                · {{ $metaCtx['es_domingo'] ? 'Domingo' : 'Lunes a sábado' }}
            </div>
        </div>
    </div>

    <h2>DESGLOSE VENTAS DEL DIA</h2>
    <p class="desglose-sub">{{ $reporte->fecha->format('d/m/Y') }} · Tasa {{ number_format((float) $reporte->tasa, 2) }}</p>
    @include('ventas_diarias._desglose', ['tablaClass' => 'desglose'])

    @if($reporte->observaciones)
        <p style="margin-top:8px;"><strong>Observaciones:</strong> {{ $reporte->observaciones }}</p>
    @endif

    <h2>Cajas</h2>
    @include('ventas_diarias._cajas', ['tablaClass' => 'cajas'])
</body>
</html>
