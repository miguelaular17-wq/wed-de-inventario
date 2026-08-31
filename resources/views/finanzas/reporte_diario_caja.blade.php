<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Consolidado - Flujo de Caja</title>
    <style>
        @page { size: landscape; margin: 5mm; }
        body { font-family: Arial, sans-serif; font-size: 8px; margin: 0; padding: 0; background: #fff; color: #000; }
        .report-wrapper { width: 100%; box-sizing: border-box; }
        
        /* Header */
        .header-table { width: 100%; border: 2px solid black; border-collapse: collapse; margin-bottom: 5px; }
        .header-table td { padding: 5px; text-align: center; font-weight: bold; }
        .header-title { font-size: 14px; text-transform: uppercase; }
        
        /* General Tables */
        table { border-collapse: collapse; width: 100%; margin-bottom: 5px; }
        th, td { border: 1px solid black; padding: 2px; }
        th { text-align: center; font-weight: bold; }
        
        /* Specific borders */
        .thick-border { border: 2px solid black !important; }
        .thick-border-left { border-left: 2px solid black !important; }
        .thick-border-right { border-right: 2px solid black !important; }
        .thick-border-top { border-top: 2px solid black !important; }
        .thick-border-bottom { border-bottom: 2px solid black !important; }

        /* Colors */
        .bg-black { background-color: #000; color: #fff; }
        .bg-light-gray { background-color: #e5e7eb; }
        .bg-green { background-color: #dcfce7; }
        .bg-lime { background-color: #c6efce; }
        .bg-white { background-color: #ffffff; }
        
        /* Legend Colors */
        .color-orange { background-color: #f4b183; }
        .color-red { background-color: #ff0000; }
        .color-yellow { background-color: #ffff00; }
        .color-blue { background-color: #0070c0; }
        .color-white { background-color: #ffffff; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: red; font-weight: bold; }
        .text-success { color: green; font-weight: bold; }

        /* Grid */
        .main-grid { display: grid; grid-template-columns: 4fr 1.5fr 4.5fr; gap: 5px; }
        
        /* Hide UI buttons on print */
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px; padding: 10px; background: #f8fafc; border-bottom: 1px solid #cbd5e1;">
        <a href="{{ route('finanzas.flujo_caja') }}" style="padding: 8px 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; text-decoration: none; color: #334155; font-weight: bold;">&larr; Volver al Flujo de Caja</a>
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; float: right; font-weight: bold;">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="report-wrapper">
        <!-- HEADER -->
        <table class="header-table" style="border: none; margin-bottom: 15px;">
            <tr style="border: none;">
                <td style="width: 25%; text-align: left; border: none;">
                    <img src="{{ asset('images/logo_izq.png') }}" alt="Logos Izquierda" style="height: 60px; object-fit: contain;">
                </td>
                <td style="width: 50%; text-align: center; border: none;">
                    <div class="header-title" style="font-weight: bold; font-size: 16px;">DISPONIBILIDAD BANCARIA EN TIEMPO REAL Y EGRESOS REALIZADOS DEL DÍA {{ \Carbon\Carbon::parse($resumen->fecha)->format('d/m/Y') }}</div>
                    <div class="header-title" style="margin-top: 4px; font-size: 12px; font-weight: bold;">GRUPO PALACIO DE LOS DETALLES - GRUPO JENU - NUNES STORE, C.A. - EURONISSI, C.A.</div>
                </td>
                <td style="width: 25%; text-align: right; border: none;">
                    <img src="{{ asset('images/logo_der.png') }}" alt="Logos Derecha" style="height: 60px; object-fit: contain;">
                </td>
            </tr>
        </table>

        <!-- MAIN CONTENT -->
        <div class="main-grid">
            
            <!-- LEFT COLUMN: DISPONIBILIDAD BANCARIA -->
            <div>
                <table class="thick-border">
                    <thead class="bg-light-gray thick-border-bottom">
                        <tr>
                            <th colspan="7">DISPONIBILIDAD EN TIEMPO REAL</th>
                        </tr>
                        <tr style="font-size: 7px;">
                            <th style="width: 2%;">TC</th>
                            <th style="width: 15%;">BANCO</th>
                            <th style="width: 23%;">TITULAR</th>
                            <th style="width: 15%;">BS TC</th>
                            <th style="width: 15%;">BS DISPONIBLES</th>
                            <th style="width: 15%;">USD TC</th>
                            <th style="width: 15%;">USD DISP.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $tot_bs_tc = 0;
                            $tot_bs_disp = 0;
                            $tot_usd_tc = 0;
                            $tot_usd_disp = 0;
                        @endphp
                        @foreach($cuentasBancarias as $c)
                            @php
                                $tot_bs_tc += $c->bs_tc;
                                $tot_bs_disp += $c->bs_disponibles;
                                $tot_usd_tc += $c->usd_tc;
                                $tot_usd_disp += $c->usd_disp;
                                
                                $bgColor = $c->color_tc ? $c->color_tc : '#ffffff';
                            @endphp
                            <tr>
                                <td class="thick-border-left thick-border-right" style="background-color: {{ $bgColor }}; -webkit-print-color-adjust: exact; print-color-adjust: exact;"></td>
                                <td>{{ $c->banco }}</td>
                                <td class="thick-border-right">{{ $c->titular }}</td>
                                <td class="text-right">Bs. {{ number_format($c->bs_tc, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-right">Bs. {{ number_format($c->bs_disponibles, 2, ',', '.') }}</td>
                                <td class="text-right">$ {{ number_format($c->usd_tc, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-right">$ {{ number_format($c->usd_disp, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="thick-border-top">
                        <tr>
                            <td colspan="3" class="text-center bg-light-gray" style="font-weight: bold;">TOTALES</td>
                            <td class="text-right bg-black" style="font-weight: bold;">Bs. {{ number_format($tot_bs_tc, 2, ',', '.') }}</td>
                            <td class="text-right bg-black" style="font-weight: bold;">Bs. {{ number_format($tot_bs_disp, 2, ',', '.') }}</td>
                            <td class="text-right bg-black" style="font-weight: bold; color: #a3e635;">$ {{ number_format($tot_usd_tc, 2, ',', '.') }}</td>
                            <td class="text-right bg-black thick-border-right" style="font-weight: bold; color: #a3e635;">$ {{ number_format($tot_usd_disp, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- MIDDLE COLUMN: TASA, LEYENDA, RESUMEN -->
            <div>
                <!-- TASA -->
                <table class="thick-border">
                    <tr class="bg-light-gray">
                        <td class="text-center" style="font-weight: bold; font-size: 9px; width: 50%;">TASA BCV USD</td>
                        <td class="text-center bg-white" style="font-weight: bold; font-size: 9px; width: 50%;">{{ number_format($resumen->tasa_bcv_usd, 2, ',', '.') }}</td>
                    </tr>
                </table>

                <!-- TIPO DE CUENTA LEYENDA -->
                <table class="thick-border" style="margin-top: 15px;">
                    <tr class="bg-light-gray"><th colspan="2">TIPO DE CUENTA</th></tr>
                    <tr><td class="text-center bg-white" style="width: 80%;">P.V/TRANSF/P.M</td><td class="color-orange" style="width: 20%;"></td></tr>
                    <tr><td class="text-center bg-white">TERCEROS</td><td class="color-red"></td></tr>
                    <tr><td class="text-center bg-white">CASHEA</td><td class="color-yellow"></td></tr>
                    <tr><td class="text-center bg-white">AVANCES</td><td class="color-blue"></td></tr>
                </table>

                <!-- RESUMEN FINANCIERO -->
                <table class="thick-border" style="margin-top: 15px;">
                    <tr class="bg-light-gray"><th class="thick-border-bottom">SALDO INICIAL</th></tr>
                    <tr><td class="bg-green text-center" style="font-weight: bold; font-size: 10px;">$ {{ number_format($resumen->saldo_inicial, 2, ',', '.') }}</td></tr>
                    
                    <tr class="bg-light-gray"><th class="thick-border-top thick-border-bottom">TOTAL SALIDAS BS</th></tr>
                    @php $total_salidas_usd = $resumen->tasa_bcv_usd > 0 ? ($total_salidas_bs / $resumen->tasa_bcv_usd) : 0; @endphp
                    <tr><td class="bg-green text-center" style="font-weight: bold; font-size: 10px;">$ {{ number_format($total_salidas_usd, 2, ',', '.') }}</td></tr>

                    <tr class="bg-light-gray"><th class="thick-border-top thick-border-bottom">QUEDA DEL DIA ANTERIOR</th></tr>
                    @php $queda_calculada = $resumen->saldo_inicial - $total_salidas_usd; @endphp
                    <tr><td class="bg-green text-center" style="font-weight: bold; font-size: 10px;">$ {{ number_format($queda_calculada, 2, ',', '.') }}</td></tr>

                    <tr class="bg-light-gray"><th class="text-danger thick-border-top thick-border-bottom">TOTAL DIFERENCIAL<br>CAMBIARIO</th></tr>
                    <tr><td class="bg-green text-center" style="font-weight: bold; font-size: 10px;">$ {{ number_format($total_diferencial_cambiario, 2, ',', '.') }}</td></tr>

                    <tr class="bg-light-gray"><th class="text-danger thick-border-top thick-border-bottom">% TOTAL DIFERENCIAL<br>CAMBIARIO</th></tr>
                    @php $pct = $resumen->saldo_inicial > 0 ? ($total_diferencial_cambiario / $resumen->saldo_inicial) * 100 : 0; @endphp
                    <tr><td class="bg-green text-center" style="font-weight: bold; font-size: 10px;">{{ number_format($pct, 2, ',', '.') }} %</td></tr>
                </table>
            </div>

            <!-- RIGHT COLUMN: EGRESOS & OTROS EGRESOS -->
            <div style="display: flex; flex-direction: column; gap: 5px;">
                
                <!-- EGRESOS REALIZADOS -->
                <table class="thick-border">
                    <thead class="bg-light-gray thick-border-bottom">
                        <tr><th colspan="6">EGRESOS REALIZADOS</th></tr>
                        <tr style="font-size: 7px;">
                            <th style="width: 10%;">USD</th>
                            <th style="width: 10%;">TASA CAMBIO</th>
                            <th class="text-danger" style="width: 12%;">DIF. CAMBIARIO</th>
                            <th style="width: 15%;">BS</th>
                            <th style="width: 12%;">COMISION</th>
                            <th style="width: 41%;">MOTIVO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $e_usd = 0;
                            $e_dif = 0;
                            $e_bs = 0;
                            $e_comision = 0;
                        @endphp
                        @foreach($egresos_realizados as $e)
                            @php
                                $e_usd += $e->monto_usd;
                                $e_dif += $e->diferencial_cambiario;
                                $e_bs += $e->monto_bs;
                                $e_comision += $e->comision;
                            @endphp
                            <tr>
                                <td class="text-right thick-border-left thick-border-right">$ {{ number_format($e->monto_usd, 2, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($e->tasa_cambio, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-left thick-border-right">{{ number_format($e->diferencial_cambiario, 2, ',', '.') }}</td>
                                <td class="text-right">Bs. {{ number_format($e->monto_bs, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-left thick-border-right">Bs. {{ number_format($e->comision, 2, ',', '.') }}</td>
                                <td>{{ $e->motivo }}</td>
                            </tr>
                        @endforeach
                        <!-- Llenar filas vacías si hay pocos para mantener la estructura visual (opcional) -->
                        @for($i = count($egresos_realizados); $i < 15; $i++)
                            <tr>
                                <td class="text-right thick-border-left thick-border-right">$ -</td>
                                <td class="text-center">-</td>
                                <td class="text-right thick-border-left thick-border-right">-</td>
                                <td class="text-right">Bs. -</td>
                                <td class="text-right thick-border-left thick-border-right">Bs. -</td>
                                <td>&nbsp;</td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot class="thick-border-top bg-light-gray">
                        <tr>
                            <td class="text-right thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($e_usd, 2, ',', '.') }}</td>
                            <td class="text-center text-danger" style="font-weight: bold;">TOTAL</td>
                            <td class="text-danger text-right thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($e_dif, 2, ',', '.') }}</td>
                            <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($e_bs, 2, ',', '.') }}</td>
                            <td class="text-right thick-border-left thick-border-right" style="font-weight: bold;">Bs. {{ number_format($e_comision, 2, ',', '.') }}</td>
                            <td class="bg-white"></td>
                        </tr>
                        @php $pct_e = $e_usd > 0 ? ($e_dif / $e_usd) * 100 : 0; @endphp
                        <tr>
                            <td class="text-danger text-center thick-border-left thick-border-right" style="font-weight: bold;">% D.C: {{ number_format($pct_e, 2, ',', '.') }}%</td>
                            <td class="text-center text-danger" style="font-weight: bold; font-size: 8px;">EN USD</td>
                            <td class="bg-white thick-border-left thick-border-right"></td>
                            <td class="text-right text-success" style="font-weight: bold;">$ {{ number_format($e_bs / ($resumen->tasa_bcv_usd ?: 1), 2, ',', '.') }}</td>
                            <td class="text-right text-success thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($e_comision / ($resumen->tasa_bcv_usd ?: 1), 2, ',', '.') }}</td>
                            <td class="bg-white"></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- OTROS EGRESOS -->
                <table class="thick-border" style="margin-top: 5px;">
                    <thead class="bg-light-gray thick-border-bottom">
                        <tr><th colspan="6">OTROS EGRESOS (AVANCES Y CAMBIOS)</th></tr>
                        <tr style="font-size: 7px;">
                            <th style="width: 10%;">USD</th>
                            <th style="width: 10%;">TASA CAMBIO</th>
                            <th class="text-danger" style="width: 12%;">DIF. CAMBIARIO</th>
                            <th style="width: 15%;">BS</th>
                            <th style="width: 12%;">COMISION</th>
                            <th style="width: 41%;">INVERSION PARA COMPRA DE DIVISAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $o_usd = 0;
                            $o_dif = 0;
                            $o_bs = 0;
                            $o_comision = 0;
                        @endphp
                        @foreach($otros_egresos as $o)
                            @php
                                $o_usd += $o->monto_usd;
                                $o_dif += $o->diferencial_cambiario;
                                $o_bs += $o->monto_bs;
                                $o_comision += $o->comision;
                            @endphp
                            <tr>
                                <td class="text-right thick-border-left thick-border-right">$ {{ number_format($o->monto_usd, 2, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($o->tasa_cambio, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-left thick-border-right">{{ number_format($o->diferencial_cambiario, 2, ',', '.') }}</td>
                                <td class="text-right">Bs. {{ number_format($o->monto_bs, 2, ',', '.') }}</td>
                                <td class="text-right thick-border-left thick-border-right">Bs. {{ number_format($o->comision, 2, ',', '.') }}</td>
                                <td>{{ $o->motivo }}</td>
                            </tr>
                        @endforeach
                        @for($i = count($otros_egresos); $i < 3; $i++)
                            <tr>
                                <td class="text-right thick-border-left thick-border-right">$ -</td>
                                <td class="text-center">-</td>
                                <td class="text-right thick-border-left thick-border-right">-</td>
                                <td class="text-right">Bs. -</td>
                                <td class="text-right thick-border-left thick-border-right">Bs. -</td>
                                <td>&nbsp;</td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot class="thick-border-top bg-light-gray">
                        <tr>
                            <td class="text-right thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($o_usd, 2, ',', '.') }}</td>
                            <td class="text-center text-danger" style="font-weight: bold;">TOTAL</td>
                            <td class="text-danger text-right thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($o_dif, 2, ',', '.') }}</td>
                            <td class="text-right" style="font-weight: bold;">Bs. {{ number_format($o_bs, 2, ',', '.') }}</td>
                            <td class="text-right thick-border-left thick-border-right" style="font-weight: bold;">Bs. {{ number_format($o_comision, 2, ',', '.') }}</td>
                            <td class="bg-white"></td>
                        </tr>
                        @php $pct_o = $o_usd > 0 ? ($o_dif / $o_usd) * 100 : 0; @endphp
                        <tr>
                            <td class="text-danger text-center thick-border-left thick-border-right" style="font-weight: bold;">% D.C: {{ number_format($pct_o, 2, ',', '.') }}%</td>
                            <td class="text-center text-danger" style="font-weight: bold; font-size: 8px;">EN USD</td>
                            <td class="bg-white thick-border-left thick-border-right"></td>
                            <td class="text-right text-success" style="font-weight: bold;">$ {{ number_format($o_bs / ($resumen->tasa_bcv_usd ?: 1), 2, ',', '.') }}</td>
                            <td class="text-right text-success thick-border-left thick-border-right" style="font-weight: bold;">$ {{ number_format($o_comision / ($resumen->tasa_bcv_usd ?: 1), 2, ',', '.') }}</td>
                            <td class="bg-white"></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- EGRESOS EN DIVISAS -->
                <table class="thick-border" style="margin-top: 5px;">
                    <thead>
                        <tr class="bg-light-gray thick-border-bottom">
                            <th colspan="4">EGRESOS EN DIVISAS</th>
                        </tr>
                        <tr style="font-size: 7px;">
                            <th class="bg-lime" style="width: 18%;">USD</th>
                            <th class="bg-light-gray" style="width: 22%;">BANCO</th>
                            <th class="bg-light-gray" style="width: 22%;">TITULAR</th>
                            <th class="bg-light-gray" style="width: 38%;">MOTIVO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $d_usd = 0; @endphp
                        @foreach($egresos_divisas as $d)
                            @php $d_usd += $d->monto_usd; @endphp
                            <tr>
                                <td class="text-right bg-lime thick-border-left">$ {{ number_format($d->monto_usd, 2, ',', '.') }}</td>
                                <td>{{ $d->banco }}</td>
                                <td>{{ $d->titular }}</td>
                                <td class="thick-border-right">{{ $d->motivo }}</td>
                            </tr>
                        @endforeach
                        @for($i = count($egresos_divisas); $i < 3; $i++)
                            <tr>
                                <td class="text-right bg-lime thick-border-left">$ -</td>
                                <td></td>
                                <td></td>
                                <td class="thick-border-right">&nbsp;</td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot class="thick-border-top">
                        <tr>
                            <td class="text-right bg-lime thick-border-left" style="font-weight: bold;">$ {{ $d_usd > 0 ? number_format($d_usd, 2, ',', '.') : '-' }}</td>
                            <td colspan="3" class="bg-white thick-border-right"></td>
                        </tr>
                    </tfoot>
                </table>

            </div>
        </div>
    </div>
</body>
</html>
