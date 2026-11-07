<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22px 24px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.4px; color: #1e293b; }
    .cab { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .cab td { vertical-align: top; }
    .cab .empresa { font-size: 8px; line-height: 1.35; }
    .cab .datos { text-align: right; font-size: 8px; line-height: 1.5; }
    .cab .datos b { display: inline-block; min-width: 70px; }
    h1 { text-align: center; font-size: 13px; margin: 6px 0 8px; }
    table.rep { width: 100%; border-collapse: collapse; }
    table.rep th { background: #6b7280; color: #fff; padding: 4px 3px; font-size: 7px; text-transform: uppercase; text-align: center; }
    table.rep td { padding: 3px; border-bottom: 1px solid #e5e7eb; }
    table.rep td.r { text-align: right; white-space: nowrap; }
    table.rep tr.negativo td { color: #b91c1c; }
    table.rep tfoot td { font-weight: bold; background: #f3f4f6; border-top: 1.5px solid #6b7280; }
    .pie { position: fixed; bottom: -16px; left: 0; right: 0; font-size: 7px; color: #94a3b8; }
</style>
</head>
<body>
    <table class="cab">
        <tr>
            <td style="width:70px;">@if($logo)<img src="{{ $logo }}" style="width:58px;height:58px;">@endif</td>
            <td class="empresa">
                <b>{{ $filtros['sede'] ? ($etiquetas[$filtros['sede']] ?? $filtros['sede']) : 'Todas las sedes' }}</b><br>
                Datos de la facturadora (Suite)
            </td>
            <td class="datos">
                <b>FECHA EMISIÓN</b> : {{ now()->format('d/m/Y') }}<br>
                <b>FECHA DESDE</b> : {{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }}<br>
                <b>FECHA HASTA</b> : {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <h1>{{ mb_strtoupper($titulo, 'UTF-8') }}</h1>

    @if(! empty($resultado['aviso']))
        <p>{{ $resultado['aviso'] }}</p>
    @else
        <table class="rep">
            <thead>
                <tr>
                    @foreach($resultado['columnas'] as [$columna])
                        <th>{{ $columna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($resultado['filas'] as $fila)
                    <tr class="{{ ! empty($fila['negativo']) ? 'negativo' : '' }}">
                        @foreach($fila['celdas'] as $i => $celda)
                            <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">{{ $celda }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($resultado['columnas']) }}" style="text-align:center;padding:12px;">No hay datos con estos filtros.</td></tr>
                @endforelse
            </tbody>
            @if($resultado['filas'] && $resultado['totales'])
                <tfoot>
                    <tr>
                        @foreach($resultado['totales'] as $i => $total)
                            <td class="{{ $resultado['columnas'][$i][1] ?? 'l' }}">{{ $total }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    <div class="pie">Generado por {{ $generadoPor ?? '—' }} · Nexo PD</div>
</body>
</html>
