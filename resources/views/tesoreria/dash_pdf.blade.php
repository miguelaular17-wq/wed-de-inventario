<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Tesorería {{ $desde }} — {{ $hasta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 100px; }
        .header-logo img { height: 44px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 7.5px; text-transform: uppercase; padding: 5px 4px; border: 1px solid #bfdbfe; text-align: center; }
        th.left { text-align: left; }
        td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 8.5px; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #94a3b8; }
        .pos { color: #15803d; font-weight: bold; }
        .neg { color: #b91c1c; font-weight: bold; }
        .ok { color: #334155; }
        .tot td { font-weight: bold; background: #f1f5f9; }
        .note { margin-top: 10px; color: #64748b; font-size: 8px; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
@php
    $usd = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $clsDif = function ($d) {
        $d = (float) $d;
        if (abs($d) < 0.005) {
            return 'ok';
        }

        return $d < 0 ? 'neg' : 'pos';
    };
@endphp
<div class="header">
    @if(!empty($logoPath))
        <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
    @endif
    <div class="header-titles">
        <h1>Dashboard Tesorería</h1>
        <div class="sub">
            Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            @if($sede !== '')
                · Sede {{ $etiquetas[$sede] ?? $sede }} (por caja)
            @else
                · Todas las sedes
            @endif
            · Generado {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">
            Filas = formas de pago.
            @if($modo === 'sedes')
                Columnas = sedes (Suite $) · Ingreso tesorería global · Diferencia (Tesorería − Suite).
            @else
                Columnas = cajas (Suite $) · Ingreso tesorería · Diferencia.
            @endif
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left">Forma de pago</th>
            @foreach($columnas as $col)
                <th>{{ $col['etiqueta'] }}</th>
            @endforeach
            <th>Ingreso tesorería {{ $modo === 'sedes' ? 'global' : '' }}</th>
            <th>Diferencia</th>
        </tr>
    </thead>
    <tbody>
        @forelse($filas as $fila)
            <tr>
                <td><strong>{{ $fila['forma'] }}</strong></td>
                @foreach($columnas as $col)
                    @php $v = $fila['por_columna'][$col['clave']] ?? 0; @endphp
                    <td class="num {{ abs($v) < 0.005 ? 'muted' : '' }}">{{ $usd($v) }}</td>
                @endforeach
                <td class="num">{{ $usd($fila['tesoreria']) }}</td>
                <td class="num {{ $clsDif($fila['diferencia']) }}">{{ $usd($fila['diferencia']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 3 + count($columnas) }}" class="empty">
                    No hay cierres de cuadre en ese rango{{ $sede ? ' para esa sede' : '' }}.
                </td>
            </tr>
        @endforelse
    </tbody>
    @if($filas)
        <tfoot>
            <tr class="tot">
                <td>Total</td>
                @foreach($columnas as $col)
                    <td class="num">{{ $usd($totales['por_columna'][$col['clave']] ?? 0) }}</td>
                @endforeach
                <td class="num">{{ $usd($totales['tesoreria']) }}</td>
                <td class="num {{ $clsDif($totales['diferencia']) }}">{{ $usd($totales['diferencia']) }}</td>
            </tr>
        </tfoot>
    @endif
</table>

<p class="note">
    Montos en USD. Columnas de sede/caja = declarado en Suite.
    Ingreso tesorería = contado en el cuadre.
    Diferencia = Tesorería − Suite (negativo = faltante, positivo = sobrante).
</p>
</body>
</html>
