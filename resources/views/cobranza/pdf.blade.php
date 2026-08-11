<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cobranza</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .page-break {
            page-break-before: always;
        }
        h2, h3, h4 {
            color: #1e3a8a;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #dbeafe;
            color: #1e3a8a;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        /* Colores de estatus */
        .critico { background-color: #ff0000; color: #fff; font-weight: bold; text-align: center; }
        .moroso { background-color: #ffff00; color: #000; font-weight: bold; text-align: center; }
        .reciente { background-color: #92d050; color: #000; font-weight: bold; text-align: center; }
        .apartado { background-color: #f3f4f6; color: #000; font-weight: bold; text-align: center; }
        
        .row-total { background-color: #dbeafe; font-weight: bold; }
    </style>
</head>
<body>

    <!-- PÁGINA 1: INDICADORES GLOBALES -->
    <h2>REPORTE GLOBAL DE COBRANZA</h2>
    <p class="text-center">Al {{ date('d/m/Y') }}</p>

    <div style="width: 100%;">
        <h3>INDICADORES DE COBRANZA POR SEDE</h3>
        <table>
            <thead>
                <tr>
                    <th>SEDE</th>
                    <th>CLIENTE</th>
                    <th>SALDO</th>
                    <th>% GLOBAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porSede as $sede)
                    @php
                        $porcentaje = $gran_total_saldo > 0 ? round(($sede->total_saldo / $gran_total_saldo) * 100) : 0;
                    @endphp
                    <tr>
                        <td>{{ $sede->sede_nombre }}</td>
                        <td class="text-center">{{ $sede->total_clientes }}</td>
                        <td class="text-right">{{ number_format($sede->total_saldo, 2, ',', '.') }}</td>
                        <td class="text-right">{{ $porcentaje }}%</td>
                    </tr>
                @endforeach
                <tr class="row-total">
                    <td>Total general</td>
                    <td class="text-center">{{ $gran_total_clientes }}</td>
                    <td class="text-right">{{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                    <td class="text-right">100%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="width: 100%; margin-top: 30px;">
        <h3>INDICADORES DE COBRANZA POR ESTATUS</h3>
        <table>
            <thead>
                <tr>
                    <th>ESTATUS</th>
                    <th>CLIENTE</th>
                    <th>SALDO</th>
                    <th>% GLOBAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($porEstatus as $estatus)
                    @php
                        $porcentaje = $gran_total_saldo > 0 ? round(($estatus->total_saldo / $gran_total_saldo) * 100) : 0;
                        $class = strtolower($estatus->estatus);
                    @endphp
                    <tr>
                        <td class="{{ $class }}">{{ $estatus->estatus }}</td>
                        <td class="{{ $class }}">{{ $estatus->total_clientes }}</td>
                        <td class="text-right {{ $class }}">{{ number_format($estatus->total_saldo, 2, ',', '.') }}</td>
                        <td class="text-right {{ $class }}">{{ $porcentaje }}%</td>
                    </tr>
                @endforeach
                <tr class="row-total">
                    <td>Total general</td>
                    <td class="text-center">{{ $gran_total_clientes }}</td>
                    <td class="text-right">{{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                    <td class="text-right">100%</td>
                </tr>
            </tbody>
        </table>
    </div>


    <!-- PÁGINAS POR SEDE -->
    @foreach($clientesPorSede as $sede => $clientes)
        <div class="page-break"></div>
        <h2>DETALLE DE CLIENTES - {{ mb_strtoupper($sede) }}</h2>
        <p class="text-center">Total Clientes: {{ count($clientes) }} | Total Saldo: ${{ number_format($clientes->sum('saldo'), 2, ',', '.') }}</p>
        
        <table>
            <thead>
                <tr>
                    <th>CÓDIGO</th>
                    <th>CLIENTE</th>
                    <th>MONTO NETO</th>
                    <th>SALDO (FALTA PAGAR)</th>
                    <th>ESTATUS</th>
                    <th>NOTA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clientes as $c)
                    @php
                        $class = strtolower($c->estatus);
                    @endphp
                    <tr>
                        <td>{{ $c->codigo_cliente ?? $c->codigo }}</td>
                        <td>
                            {{ $c->nombre_cliente ?? $c->cliente }}
                            @if(!empty($c->es_personal))
                                <span style="background:#2563eb; color:white; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:bold; margin-left:4px;">PERSONAL</span>
                            @endif
                        </td>
                        <td class="text-right">${{ number_format($c->monto_neto, 2, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($c->saldo, 2, ',', '.') }}</td>
                        <td class="{{ $class }}">{{ $c->estatus }}</td>
                        <td style="font-size:10px; color:#555;">{{ $c->nota_anclada ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach


    <!-- PÁGINA GLOBAL ORDENADA DE MAYOR A MENOR -->
    <div class="page-break"></div>
    <h2>RANKING GLOBAL DE CLIENTES DEUDORES</h2>
    <p class="text-center">Ordenado de mayor a menor saldo adeudado</p>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>SEDE</th>
                <th>CLIENTE</th>
                <th>SALDO ADEUDADO</th>
                <th>ESTATUS</th>
                <th>NOTA</th>
            </tr>
        </thead>
        <tbody>
            @php $rank = 1; @endphp
            @foreach($clientesGlobalDesc as $c)
                @php
                    $class = strtolower($c->estatus);
                @endphp
                <tr>
                    <td class="text-center">{{ $rank++ }}</td>
                    <td>{{ $c->sede_nombre }}</td>
                    <td>
                        {{ $c->nombre_cliente ?? $c->cliente }}
                        @if(!empty($c->es_personal))
                            <span style="background:#2563eb; color:white; padding:1px 5px; border-radius:3px; font-size:9px; font-weight:bold; margin-left:4px;">PERSONAL</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold;">${{ number_format($c->saldo, 2, ',', '.') }}</td>
                    <td class="{{ $class }}">{{ $c->estatus }}</td>
                    <td style="font-size:10px; color:#555;">{{ $c->nota_anclada ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
