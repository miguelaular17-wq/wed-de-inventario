<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arqueo de caja cerradas {{ $desde }} — {{ $hasta }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 12px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 100px; }
        .header-logo img { height: 44px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        .kpis { display: table; width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 6px 0; }
        .kpi { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; width: 20%; }
        .kpi span { display: block; font-size: 7px; font-weight: bold; color: #64748b; text-transform: uppercase; }
        .kpi strong { font-size: 11px; }
        .sede-block { margin-bottom: 16px; page-break-inside: avoid; }
        .sede-titulo {
            background: #1e3a8a; color: #fff; font-size: 11px; font-weight: bold;
            padding: 6px 10px; margin-bottom: 0; text-transform: uppercase; letter-spacing: .02em;
        }
        .sede-resumen { background: #eff6ff; border: 1px solid #bfdbfe; border-top: 0; padding: 5px 10px; color: #1e40af; font-size: 8px; margin-bottom: 0; }
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
        .cajera-sub { color: #64748b; font-size: 7.5px; margin-top: 1px; }
    </style>
</head>
<body>
@php
    $dinero = function ($n) {
        if ($n === null) {
            return '—';
        }
        $n = (float) $n;

        return ($n < -0.004 ? '-' : '').'$'.number_format(abs($n), 2, ',', '.');
    };
    $clsDif = function ($d) {
        if ($d === null) {
            return 'muted';
        }
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
        <h1>Arqueo de caja · Cerradas</h1>
        <div class="sub">
            Del {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            @if($sede !== '')
                · Sede {{ $etiquetas[$sede] ?? $sede }}
            @else
                · Todas las sedes
            @endif
            @if($cajera !== '')
                · Cajera «{{ $cajera }}»
            @endif
            · Solo cajas cerradas · Generado {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Diferencia = Tesorería − Suite (negativo = faltante, positivo = sobrante). Agrupado por sede.</div>
    </div>
</div>

<div class="kpis">
    <div class="kpi"><span>Cajas cerradas</span><strong>{{ $totales['cierres'] }}</strong></div>
    <div class="kpi"><span>Ingresos Suite</span><strong>{{ $dinero($totales['ingresos_usd']) }}</strong></div>
    <div class="kpi"><span>Faltantes</span><strong class="neg">{{ $dinero($totales['faltantes_usd']) }}</strong></div>
    <div class="kpi"><span>Sobrantes</span><strong class="pos">{{ $dinero($totales['sobrantes_usd']) }}</strong></div>
    <div class="kpi"><span>Diferencia neta</span><strong class="{{ $clsDif($totales['diferencia_usd']) }}">{{ $dinero($totales['diferencia_usd']) }}</strong></div>
</div>

@forelse($porSede as $bloque)
    <div class="sede-block">
        <div class="sede-titulo">{{ $bloque['etiqueta'] }}</div>
        <div class="sede-resumen">
            {{ $bloque['totales']['cierres'] }} caja(s)
            · Suite {{ $dinero($bloque['totales']['ingresos_usd']) }}
            · Tesorería {{ $dinero($bloque['totales']['tesoreria_usd']) }}
            · Diferencia {{ $dinero($bloque['totales']['diferencia_usd']) }}
        </div>
        <table>
            <thead>
                <tr>
                    <th class="left">Fecha</th>
                    <th class="left">Caja</th>
                    <th class="left">Cajera</th>
                    <th>Tasa</th>
                    <th>Ingresos Suite</th>
                    <th>Tesorería</th>
                    <th>Diferencia</th>
                    <th class="left">Motivo / observación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bloque['filas'] as $fila)
                    @php
                        $c = $fila['cierre'];
                        $nota = trim(implode(' · ', array_filter([
                            $c->motivoCierreTexto(),
                            $c->observacion_tesoreria,
                        ])));
                    @endphp
                    <tr>
                        <td>{{ $c->fecha_cierre?->format('d/m/Y') }}</td>
                        <td>{{ $c->descripcion_caja ?: $c->codigo_caja }}</td>
                        <td>
                            <strong>{{ $c->usuario_nombre ?: $c->usuario }}</strong>
                            @if($c->usuario_nombre)
                                <div class="cajera-sub">{{ $c->usuario }}</div>
                            @endif
                        </td>
                        <td class="num">{{ number_format((float) $c->factor_cambio, 2, ',', '.') }}</td>
                        <td class="num">{{ $dinero($fila['ingresos_usd']) }}</td>
                        <td class="num">{{ $dinero($fila['tesoreria_usd']) }}</td>
                        <td class="num {{ $clsDif($fila['diferencia_usd']) }}">{{ $dinero($fila['diferencia_usd']) }}</td>
                        <td>{{ $nota !== '' ? $nota : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="tot">
                    <td colspan="4">Total {{ $bloque['etiqueta'] }}</td>
                    <td class="num">{{ $dinero($bloque['totales']['ingresos_usd']) }}</td>
                    <td class="num">{{ $dinero($bloque['totales']['tesoreria_usd']) }}</td>
                    <td class="num {{ $clsDif($bloque['totales']['diferencia_usd']) }}">{{ $dinero($bloque['totales']['diferencia_usd']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@empty
    <p class="empty">No hay cajas cerradas en ese rango{{ $sede ? ' para esa sede' : '' }}{{ $cajera !== '' ? ' / cajera' : '' }}.</p>
@endforelse

@if(count($porSede) > 1)
    <table style="margin-top:8px;">
        <tfoot>
            <tr class="tot">
                <td colspan="4">Total general</td>
                <td class="num">{{ $dinero($totales['ingresos_usd']) }}</td>
                <td class="num">{{ $dinero($totales['tesoreria_usd']) }}</td>
                <td class="num {{ $clsDif($totales['diferencia_usd']) }}">{{ $dinero($totales['diferencia_usd']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@endif

<p class="note">Solo cajas cerradas, separadas por sede. Fechas, sede y cajera siguen los filtros de la pantalla.</p>
</body>
</html>
