<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comisiones de marca</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 72px; }
        .header-logo img { height: 56px; width: 56px; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 16px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 9px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: left; }
        td { padding: 4px; border: 1px solid #e2e8f0; font-size: 10px; }
        .num { text-align: right; }
        .tot td { font-weight: bold; background: #f8fafc; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-top: 8px; }
        .kpis td { border: 1px solid #e2e8f0; width: 25%; }
        .kpis span { display: block; font-size: 8px; color: #64748b; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Palacio de los Detalles"></div>
        @endif
        <div class="header-titles">
            <h1>Comisiones de marca</h1>
            <div class="sub">
                {{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }}
                al {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}
                @if($filtros['marca']) · {{ $marcas[$filtros['marca']] ?? $filtros['marca'] }} @endif
                · {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <table class="kpis">
        <tr>
            <td><span>Total Bs</span><strong>Bs {{ number_format($totales['bs'], 2, ',', '.') }}</strong></td>
            <td><span>Total USD</span><strong>${{ number_format($totales['usd'], 2) }}</strong></td>
            <td><span>Samsung</span><strong>${{ number_format($totales['por_marca']['SAMSUNG']['usd'], 2) }}</strong></td>
            <td><span>Honor</span><strong>${{ number_format($totales['por_marca']['HONOR']['usd'], 2) }}</strong></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Persona</th>
                <th>Marca</th>
                <th class="num">Bs</th>
                <th class="num">Tasa</th>
                <th class="num">USD</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
            @forelse($registros as $fila)
                <tr>
                    <td>{{ $fila->fecha->format('d/m/Y') }}</td>
                    <td>{{ $fila->empleado?->nombre() ?? '—' }}</td>
                    <td>{{ $fila->nombreMarca() }}</td>
                    <td class="num">{{ number_format($fila->monto_bs, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($fila->tasa, 4, ',', '.') }}</td>
                    <td class="num">{{ number_format($fila->monto_usd, 2) }}</td>
                    <td>{{ $fila->nota }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Sin registros.</td></tr>
            @endforelse
            <tr class="tot">
                <td colspan="3">Total</td>
                <td class="num">{{ number_format($totales['bs'], 2, ',', '.') }}</td>
                <td></td>
                <td class="num">{{ number_format($totales['usd'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
