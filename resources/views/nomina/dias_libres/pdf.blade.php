<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Días libres</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1e293b; }
        .header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 70px; }
        .header-logo img { height: 52px; width: 52px; }
        .header-titles { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; color: #1e3a8a; text-transform: uppercase; }
        .sub { color: #64748b; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #dbeafe; color: #1e3a8a; font-size: 7px; text-transform: uppercase; padding: 4px 2px; border: 1px solid #bfdbfe; text-align: center; }
        td { padding: 3px 2px; border: 1px solid #e2e8f0; font-size: 8px; text-align: center; }
        td.nombre { text-align: left; white-space: nowrap; }
        .l { background: #bbf7d0; color: #14532d; font-weight: bold; }
        .sup { color: #1d4ed8; font-size: 7px; }
        .total { background: #f0fdf4; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h1>Calendario de días libres</h1>
            <div class="sub">
                {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
                @if(!empty($sedeNombre)) · {{ $sedeNombre }} @endif
                · {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="text-align:left;">Trabajador</th>
                @foreach($fechas as $f)
                    <th>{{ $f->format('d/m') }}<br>{{ ['D','L','M','X','J','V','S'][(int) $f->format('w')] }}</th>
                @endforeach
                <th>Días</th>
            </tr>
        </thead>
        <tbody>
            @foreach($empleados as $emp)
                @php $n = 0; @endphp
                <tr>
                    <td class="nombre">
                        {{ $emp->nombre() }}
                        @if($emp->es_supervisor)<div class="sup">Supervisor</div>@endif
                        <div class="sup">{{ $emp->nombreCargo() }}</div>
                    </td>
                    @foreach($fechas as $f)
                        @php
                            $dia = $mapa[$emp->id.'|'.$f->toDateString()] ?? null;
                            if ($dia) { $n++; }
                        @endphp
                        <td class="{{ $dia ? 'l' : '' }}">{{ $dia ? 'L' : '' }}</td>
                    @endforeach
                    <td class="total">{{ $n }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
