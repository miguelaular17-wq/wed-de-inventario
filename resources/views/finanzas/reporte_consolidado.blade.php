@extends('layouts.app')
@section('title', 'Reporte Consolidado')
@section('content')

<style>
    .report-container {
        font-family: 'Inter', 'Roboto', Arial, sans-serif;
        background: #fff;
        padding: 10px;
        min-width: 1200px;
        color: #000;
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .report-title-box {
        text-align: center;
        flex-grow: 1;
    }
    .report-title-box h1 { margin: 0; font-size: 22px; font-weight: 900; color: #002060; }
    .report-title-box h2 { margin: 4px 0; font-size: 16px; font-weight: bold; }
    .report-title-box h3 { margin: 0; font-size: 12px; font-weight: bold; color: #444; }
    
    .tasa-box { 
        display: flex; 
        border: 2px solid #002060; 
        width: fit-content; 
        margin-bottom: 10px; 
        border-radius: 4px;
        overflow: hidden;
    }
    .tasa-cell { display: flex; align-items: center; border-right: 2px solid #002060; }
    .tasa-cell:last-child { border-right: none; }
    .tasa-label { background-color: #d9e1f2; padding: 4px 12px; font-weight: bold; font-size: 12px; border-right: 1px solid #002060; text-align: center; color: #002060; }
    .tasa-value { padding: 4px 8px; font-weight: bold; font-size: 14px; background: #fff; }
    
    .report-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 8px;
    }
    
    .report-table { 
        width: 100%; 
        table-layout: fixed; 
        border-collapse: collapse; 
        border: 2px solid #002060; 
        font-size: 10px; 
        margin-bottom: 8px; 
        background: #fff;
    }
    .report-table th, .report-table td { 
        border: 1px solid #999; 
        padding: 3px 4px; 
        word-wrap: break-word; 
        overflow: hidden; 
        vertical-align: middle; 
    }
    .report-table th { font-weight: bold; text-align: center; }
    .report-table thead tr:first-child th { 
        background-color: #002060; 
        color: white; 
        font-size: 11px; 
        padding: 5px; 
        border: 1px solid #002060; 
        text-transform: uppercase;
    }
    .report-table thead tr:nth-child(2) th { 
        background-color: #d9e1f2; 
        color: #002060; 
        font-size: 9px; 
        border: 1px solid #999; 
    }
    
    .report-table tfoot td { 
        background-color: #e2efda; 
        font-weight: bold; 
        font-size: 10px;
        border-top: 2px solid #002060;
    }
    
    /* Input Styling */
    .report-input { 
        border: 1px solid transparent !important; 
        background: transparent !important; 
        width: 100% !important; 
        box-sizing: border-box !important; 
        font-size: 10px; 
        margin: 0; 
        padding: 1px 2px;
        font-family: inherit;
        color: #000;
        border-radius: 2px;
    }
    .report-input:focus {
        border: 1px solid #3b82f6 !important;
        outline: none;
        background: #f8fafc !important;
    }
    
    /* Hide arrows on number inputs */
    input[type=number]::-webkit-inner-spin-button, 
    input[type=number]::-webkit-outer-spin-button { 
        -webkit-appearance: none; 
        margin: 0; 
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
    
    .text-right { text-align: right !important; }
    .text-center { text-align: center !important; }
    
    .currency-wrap { display: flex; align-items: center; justify-content: space-between; }
    .currency-symbol { margin-right: 4px; font-weight: bold; color: #555; font-size: 9px; }
    .currency-input { flex-grow: 1; min-width: 0; text-align: right; font-weight: bold; }
    
    .bg-dark-blue { background-color: #002060 !important; color: white !important; }
    .bg-light-blue { background-color: #d9e1f2 !important; color: #002060 !important; }
    .bg-green { background-color: #e2efda !important; color: #000 !important; }
    
    @media print {
        @page { size: landscape; margin: 5mm; }
        html, body { margin: 0; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body * { visibility: hidden; }
        .report-container, .report-container * { visibility: visible; }
        .report-container { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; zoom: 0.52; }
        .btn { display: none !important; }
    }
</style>

<div class="report-container">
    <div style="margin-bottom: 10px;" class="no-print btn-container">
        <a href="{{ route('finanzas.flujo_caja') }}" class="btn btn-secondary" style="background-color: #f1f5f9; color: #334155; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; font-weight: bold;">&larr; Volver</a>
        
        <div style="display: inline-block; margin-left: 30px;">
            <button id="btn-inicio" class="btn" style="background-color: #1a4273; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-right: 5px;">☀️ Inicio de Día</button>
            <button id="btn-fin" class="btn" style="background-color: #f1f5f9; color: #334155; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: bold;">🌙 Fin de Día</button>
        </div>

        <button onclick="window.print()" class="btn btn-primary" style="background-color: #1a4273; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; float: right; display: flex; align-items: center; gap: 8px; font-weight: bold;">
            🖨️ Descargar PDF (Imprimir)
        </button>
    </div>

    <div class="report-header">
        <div>
            <img src="{{ asset('images/logo_izq.png') }}" alt="Logos Izquierda" style="height: 60px; object-fit: contain;">
        </div>
        <div class="report-title-box">
            <h1>FLUJO DE CAJA AL {{ \Carbon\Carbon::parse($resumen->fecha)->format('d/m/Y') }}</h1>
            <h2 id="report-title-text">CONSOLIDADO DISPONIBILIDAD BANCARIA (INICIO DE DÍA)</h2>
            <h3>GRUPO PALACIO DE LOS DETALLES - GRUPO JENU - NUNES STORE, C.A. - EURONISSI, C.A.</h3>
        </div>
        <div>
            <img src="{{ asset('images/logo_der.png') }}" alt="Logos Derecha" style="height: 60px; object-fit: contain;">
        </div>
    </div>

    <div class="tasa-box">
        <div class="tasa-cell">
            <div class="tasa-label">TASA<br>BCV</div>
            <div class="tasa-value"><input type="number" step="0.01" class="report-input save-resumen" data-field="tasa_bcv_usd" value="{{ $resumen->tasa_bcv_usd }}" style="font-size: 14px; width: 80px !important; text-align: center;" id="tasa_bcv"></div>
        </div>
        <div class="tasa-cell">
            <div class="tasa-label">TASA<br>PARALELO</div>
            <div class="tasa-value"><input type="number" step="0.01" class="report-input save-resumen" data-field="tasa_paralelo" value="{{ $resumen->tasa_paralelo }}" style="font-size: 14px; width: 80px !important; text-align: center;" id="tasa_paralelo"></div>
        </div>
    </div>
    
    <div class="report-grid-master" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px; align-items: start;">
        
        <!-- LEFT GROUP (Col 1 & 2 + Planificacion + Disp BS/USD) -->
        <div style="display: flex; flex-direction: column; gap: 8px;">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <!-- COLUMN 1: ALTO Y MEDIO -->
                <div>
                    <table class="report-table" id="table-alto">
                        <thead>
                            <tr><th colspan="4">BANCA NACIONAL<br>ALTO Y MEDIANO MOVIMIENTO</th></tr>
                            <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">BS</th><th style="width: 20%">USD</th></tr>
                        </thead>
                        <tbody>
                            @php $alto = isset($cuentas['BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO']) ? $cuentas['BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'] : []; @endphp
                            @foreach($alto as $c)
                            <tr>
                                <td>{{ $c->banco }}</td>
                                <td>{{ $c->titular }}</td>
                                <td><div class="currency-wrap"><span class="currency-symbol">Bs.</span><input type="number" step="0.01" class="report-input save-cuenta calc-alto-bs currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                                <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-alto-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-center bg-light-blue" style="font-weight: bold;">TOTALES</td>
                                <td class="text-right bg-green"><div class="currency-wrap"><span class="currency-symbol">Bs.</span><span id="tot_alto_bs_sum">0.00</span></div></td>
                                <td class="text-right bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_alto_usd_sum">0.00</span></div></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- COLUMN 2: BAJO MOVIMIENTO -->
                <div>
                    <table class="report-table" id="table-bajo">
                        <thead>
                            <tr><th colspan="4">BANCA NACIONAL<br>BAJO MOVIMIENTO</th></tr>
                            <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">BS</th><th style="width: 20%">USD</th></tr>
                        </thead>
                        <tbody>
                            @php $bajo = isset($cuentas['BANCA NACIONAL - BAJO MOVIMIENTO']) ? $cuentas['BANCA NACIONAL - BAJO MOVIMIENTO'] : []; @endphp
                            @foreach($bajo as $c)
                            <tr>
                                <td>{{ $c->banco }}</td>
                                <td>{{ $c->titular }}</td>
                                <td><div class="currency-wrap"><span class="currency-symbol">Bs.</span><input type="number" step="0.01" class="report-input save-cuenta calc-bajo-bs currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                                <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-bajo-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-center bg-light-blue" style="font-weight: bold;">TOTALES</td>
                                <td class="text-right bg-green"><div class="currency-wrap"><span class="currency-symbol">Bs.</span><span id="tot_bajo_bs_sum">0.00</span></div></td>
                                <td class="text-right bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_bajo_usd_sum">0.00</span></div></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- PLANIFICACION -->
            <div>
                <table class="report-table" id="table-planificacion">
                    <thead>
                        <tr><th colspan="6">PLANIFICACION DE PAGOS A EJECUTAR EN EL DIA</th></tr>
                        <tr class="bg-dark-blue">
                            <th style="width: 25%">RAZON SOCIAL</th><th style="width: 15%">TOTAL BS</th><th style="width: 10%">TASA</th><th style="width: 15%">TOTAL $</th><th style="width: 15%">FACTURA</th><th style="width: 20%">CONCEPTO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($planificacion as $p)
                        <tr>
                            <td><input type="text" class="report-input save-plan text-center" data-id="{{ $p->id }}" data-field="razon_social" value="{{ $p->razon_social }}" style="text-align: left !important;"></td>
                            <td class="bg-light-blue"><div class="currency-wrap"><span class="currency-symbol">Bs.</span><input type="number" step="0.01" class="report-input save-plan calc-plan-bs currency-input" data-id="{{ $p->id }}" data-field="total_bs" value="{{ $p->total_bs }}"></div></td>
                            <td class="text-center"><input type="number" step="0.01" class="report-input save-plan text-center" data-id="{{ $p->id }}" data-field="tasa" value="{{ $p->tasa }}"></td>
                            <td class="bg-light-blue"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-plan calc-plan-usd currency-input" data-id="{{ $p->id }}" data-field="total_usd" value="{{ $p->total_usd }}"></div></td>
                            <td><input type="text" class="report-input save-plan text-center" data-id="{{ $p->id }}" data-field="factura" value="{{ $p->factura }}"></td>
                            <td><input type="text" class="report-input save-plan text-center" data-id="{{ $p->id }}" data-field="concepto" value="{{ $p->concepto }}" style="text-align: left !important;"></td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-light-blue">
                            <td class="text-center" style="font-weight: bold; font-size: 11px;">TOTALES</td>
                            <td style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">Bs.</span><span id="tot_plan_bs">-</span></div></td>
                            <td class="text-center">-</td>
                            <td class="bg-green" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_plan_usd">-</span></div></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <!-- DISPONIBILIDAD BOLIVARES -->
                <table class="report-table">
                    <thead>
                        <tr><th colspan="2">DISPONIBILIDAD BOLIVARES</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>BANCA ALTO Y MEDIO MOVIMIENTO</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_bs_alto">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td>BANCA BAJO MOVIMIENTO</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_bs_bajo">0.00</span></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">TOTAL DISPONIBILIDAD (TASA BCV)</td>
                            <td class="text-right" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_bs_tot_bcv">0.00</span></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">TOTAL DISPONIBILIDAD<br>(TASA PARALELO)</td>
                            <td class="text-right" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_bs_tot_par">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td>BLOQUEADO PARA COMPRA DE DIVISAS</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-resumen currency-input" data-field="bloqueado_compra_divisas" value="{{ $resumen->bloqueado_compra_divisas }}"></div></td>
                        </tr>
                        <tr>
                            <td>FONDOS NO DISPONIBLES<br>(PROBLEMAS CON LA CUENTA BANCARIA)</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-resumen currency-input" data-field="fondos_no_disponibles" value="{{ $resumen->fondos_no_disponibles }}"></div></td>
                        </tr>
                        <tr>
                            <td>SOLICITUDES DE TITULOS DE COBERTURA<br>(EN ESPERA DE APROBACION)</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-resumen currency-input" data-field="solicitudes_cobertura" value="{{ $resumen->solicitudes_cobertura }}"></div></td>
                        </tr>
                        <tr>
                            <td>TITULOS DE COBERTURA / PLAZO FIJO<br>(APROBADOS)</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-resumen currency-input" data-field="titulos_cobertura_aprobados" value="{{ $resumen->titulos_cobertura_aprobados }}"></div></td>
                        </tr>
                        <tr>
                            <td>RETENIDO PARA PAGOS PLANIFICADOS</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-resumen currency-input" data-field="retenido_pagos" value="{{ $resumen->retenido_pagos }}"></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">DISPONIBLE PARA USO (TASA BCV)</td>
                            <td class="text-right" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_uso_bcv">0.00</span></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">DISPONIBLE PARA USO<br>(TASA PARALELO)</td>
                            <td class="text-right" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_uso_par">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">PÉRDIDA POR DIFERENCIAL CAMBIARIO</td>
                            <td class="text-right" style="color: red; font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="perdida_dif">-0.00</span></div></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">DIFERENCIAL CAMBIARIO (%)</td>
                            <td class="text-center" style="color: red; font-weight: bold;"><span id="dif_porc">-0%</span></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">BRECHA CAMBIARIA</td>
                            <td class="text-center" style="color: red; font-weight: bold;"><span id="brecha_porc">0.00%</span></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- DISPONIBILIDAD USD -->
                <table class="report-table" style="height: fit-content;">
                    <thead>
                        <tr><th colspan="2">DISPONIBILIDAD USD</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>BANCA NACIONAL MONEDA EXTRANJERA</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_nacional">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td>BANCA INTERNACIONAL</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_inter">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td>CUENTAS NO OPERATIVAS</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_noop">0.00</span></div></td>
                        </tr>
                        <tr>
                            <td>TARJETAS INTERNACIONALES DE TERCEROS</td>
                            <td class="text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_terceros">0.00</span></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">TOTAL DISPONIBILIDAD USD</td>
                            <td class="text-right" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_tot">0.00</span></div></td>
                        </tr>
                        <tr class="bg-light-blue">
                            <td style="font-weight: bold;">DISPONIBLE USD PARA USO</td>
                            <td class="text-right bg-green" style="font-weight: bold;"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="disp_usd_uso">0.00</span></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MIDDLE GROUP (Col 3: Moneda Extranjera) -->
        <div>
            <table class="report-table" id="table-extranjera">
                <thead>
                    <tr><th colspan="4">BANCA NACIONAL / TARJETAS<br>MONEDA EXTRANJERA</th></tr>
                    <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">USD</th><th style="width: 20%">USD</th></tr>
                </thead>
                <tbody>
                    @php $extranjera = isset($cuentas['BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA']) ? $cuentas['BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'] : []; @endphp
                    @foreach($extranjera as $c)
                    <tr>
                        <td>{{ $c->banco }}</td>
                        <td>{{ $c->titular }}</td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-extranjera-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-center bg-light-blue" style="font-weight: bold;">TOTALES</td>
                        <td class="bg-green text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span>0.00</span></div></td>
                        <td class="bg-green text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_extranjera_usd_sum">0.00</span></div></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- RIGHT GROUP (Col 4: Billeteras, No Operativas, Terceros, Proyeccion) -->
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <!-- BILLETERAS -->
            <table class="report-table" id="table-billeteras">
                <thead>
                    <tr><th colspan="4">BANCA INTERNACIONAL /<br>BILLETERAS</th></tr>
                    <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">USD</th><th style="width: 20%">USD</th></tr>
                </thead>
                <tbody>
                    @php $billeteras = isset($cuentas['BANCA INTERNACIONAL / BILLETERAS']) ? $cuentas['BANCA INTERNACIONAL / BILLETERAS'] : []; @endphp
                    @foreach($billeteras as $c)
                    <tr>
                        <td>{{ $c->banco }}</td>
                        <td>{{ $c->titular }}</td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-billeteras-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="bg-light-blue"></td>
                        <td class="bg-green"></td>
                        <td class="bg-green text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_billeteras_usd_sum">0.00</span></div></td>
                    </tr>
                </tfoot>
            </table>

            <!-- NO OPERATIVAS -->
            <table class="report-table" id="table-no-operativas">
                <thead>
                    <tr><th colspan="4">BANCA INTERNACIONAL<br>CUENTAS NO OPERATIVAS</th></tr>
                    <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">USD</th><th style="width: 20%">USD</th></tr>
                </thead>
                <tbody>
                    @php $no_op = isset($cuentas['BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS']) ? $cuentas['BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS'] : []; @endphp
                    @foreach($no_op as $c)
                    <tr>
                        <td>{{ $c->banco }}</td>
                        <td>{{ $c->titular }}</td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-noop-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="bg-light-blue"></td>
                        <td class="bg-green"></td>
                        <td class="bg-green text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_noop_usd_sum">0.00</span></div></td>
                    </tr>
                </tfoot>
            </table>

            <!-- TERCEROS -->
            <table class="report-table" id="table-terceros">
                <thead>
                    <tr><th colspan="4">TARJETAS INTERNACIONALES<br>DE TERCEROS</th></tr>
                    <tr><th style="width: 25%">BANCO</th><th style="width: 35%">TITULAR</th><th style="width: 20%">USD</th><th style="width: 20%">USD</th></tr>
                </thead>
                <tbody>
                    @php $terceros = isset($cuentas['TARJETAS INTERNACIONALES DE TERCEROS']) ? $cuentas['TARJETAS INTERNACIONALES DE TERCEROS'] : []; @endphp
                    @foreach($terceros as $c)
                    <tr>
                        <td>{{ $c->banco }}</td>
                        <td>{{ $c->titular }}</td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta currency-input" data-id="{{ $c->id }}" data-field="reporte_bs" value="{{ $c->reporte_bs }}"></div></td>
                        <td><div class="currency-wrap"><span class="currency-symbol">$</span><input type="number" step="0.01" class="report-input save-cuenta calc-terceros-usd currency-input" data-id="{{ $c->id }}" data-field="reporte_usd" value="{{ $c->reporte_usd }}"></div></td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="bg-light-blue"></td>
                        <td class="bg-green"></td>
                        <td class="bg-green text-right"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="tot_terceros_usd_sum">0.00</span></div></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- TOTALES GENERALES ROW -->
            <table class="report-table" style="border-width: 3px;">
                <tr class="bg-light-blue">
                    <td class="text-center" style="font-weight: bold; width: 50%; font-size: 12px; padding: 5px;">TOTALES</td>
                    <td class="text-center" style="font-weight: bold; width: 50%; font-size: 11px;">Bs. <span id="totales_gran_bs">-</span></td>
                </tr>
                <tr class="bg-light-blue">
                    <td class="text-center" style="font-weight: bold;"></td>
                    <td class="text-center bg-green" style="font-weight: bold; font-size: 11px;">$ <span id="totales_gran_usd">0.00</span></td>
                </tr>
            </table>

            <!-- PROYECCION -->
            <table class="report-table">
                <thead>
                    <tr><th colspan="6">PROYECCION DISPONIBILIDAD VS CUENTAS POR PAGAR</th></tr>
                    <tr class="bg-light-blue">
                        <th style="width: 15%"></th><th style="width: 20%">FONDOS PARA USO</th><th style="width: 20%">COMPROMISOS DE PAGO</th><th style="width: 15%">LIQUIDEZ</th><th style="width: 15%">% CUBIERTO</th><th style="width: 15%">% POR CUBRIR</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="font-weight: bold;">BOLIVARES</td>
                        <td class="bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="proy_bs_fondos">0.00</span></div></td>
                        <td class="text-center">PROV. BS &nbsp;&nbsp;&nbsp; $ <span id="proy_bs_comp">0.00</span></td>
                        <td class="bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="proy_bs_liq">0.00</span></div></td>
                        <td class="text-center">100%</td>
                        <td class="text-center">0%</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold;">DOLARES</td>
                        <td class="bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="proy_usd_fondos">0.00</span></div></td>
                        <td class="text-center">PROV. USD &nbsp;&nbsp;&nbsp; $ <span id="proy_usd_comp">0.00</span></td>
                        <td class="bg-green"><div class="currency-wrap"><span class="currency-symbol">$</span><span id="proy_usd_liq">0.00</span></div></td>
                        <td class="text-center">100%</td>
                        <td class="text-center">0%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentMode = 'inicio';
    
    const cuentasData = @json(collect($cuentas)->flatten());
    const cuentasMap = {};
    cuentasData.forEach(c => {
        cuentasMap[c.id] = c;
    });

    document.getElementById('btn-inicio').addEventListener('click', () => switchMode('inicio'));
    document.getElementById('btn-fin').addEventListener('click', () => switchMode('fin'));

    function switchMode(mode) {
        currentMode = mode;
        
        // Update Title and UI
        document.getElementById('report-title-text').innerText = mode === 'inicio' ? "CONSOLIDADO DISPONIBILIDAD BANCARIA (INICIO DE DÍA)" : "CONSOLIDADO DISPONIBILIDAD BANCARIA (FIN DE DÍA)";
        document.getElementById('btn-inicio').style.backgroundColor = mode === 'inicio' ? '#1a4273' : '#f1f5f9';
        document.getElementById('btn-inicio').style.color = mode === 'inicio' ? 'white' : '#334155';
        document.getElementById('btn-fin').style.backgroundColor = mode === 'fin' ? '#1a4273' : '#f1f5f9';
        document.getElementById('btn-fin').style.color = mode === 'fin' ? 'white' : '#334155';

        // Update inputs
        document.querySelectorAll('.save-cuenta').forEach(input => {
            let baseField = input.getAttribute('data-field').replace('_fin', '');
            let id = input.getAttribute('data-id');
            let data = cuentasMap[id];
            
            if (data) {
                if (mode === 'inicio') {
                    input.setAttribute('data-field', baseField);
                    input.value = data[baseField];
                } else {
                    input.setAttribute('data-field', baseField + '_fin');
                    input.value = data[baseField + '_fin'] || 0;
                }
            }
        });

        calculateTotals();
    }

    function calculateTotals() {
        let bcv = parseFloat(document.getElementById('tasa_bcv').value) || 1;
        
        // Sums
        let sum_alto_bs = 0, sum_alto_usd = 0;
        document.querySelectorAll('.calc-alto-bs').forEach(el => sum_alto_bs += parseFloat(el.value) || 0);
        document.querySelectorAll('.calc-alto-usd').forEach(el => sum_alto_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_alto_bs_sum').innerText = sum_alto_bs.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('tot_alto_usd_sum').innerText = sum_alto_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        let sum_bajo_bs = 0, sum_bajo_usd = 0;
        document.querySelectorAll('.calc-bajo-bs').forEach(el => sum_bajo_bs += parseFloat(el.value) || 0);
        document.querySelectorAll('.calc-bajo-usd').forEach(el => sum_bajo_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_bajo_bs_sum').innerText = sum_bajo_bs.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('tot_bajo_usd_sum').innerText = sum_bajo_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        let sum_ext_usd = 0;
        document.querySelectorAll('.calc-extranjera-usd').forEach(el => sum_ext_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_extranjera_usd_sum').innerText = sum_ext_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        let sum_bill_usd = 0;
        document.querySelectorAll('.calc-billeteras-usd').forEach(el => sum_bill_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_billeteras_usd_sum').innerText = sum_bill_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        let sum_noop_usd = 0;
        document.querySelectorAll('.calc-noop-usd').forEach(el => sum_noop_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_noop_usd_sum').innerText = sum_noop_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        let sum_terc_usd = 0;
        document.querySelectorAll('.calc-terceros-usd').forEach(el => sum_terc_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_terceros_usd_sum').innerText = sum_terc_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Update variables to display in blocks
        document.getElementById('disp_bs_alto').innerText = (sum_alto_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('disp_bs_bajo').innerText = (sum_bajo_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        document.getElementById('disp_usd_nacional').innerText = (sum_ext_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('disp_usd_inter').innerText = (sum_bill_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('disp_usd_noop').innerText = (sum_noop_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('disp_usd_terceros').innerText = (sum_terc_usd).toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        let sum_plan_bs = 0, sum_plan_usd = 0;
        document.querySelectorAll('.calc-plan-bs').forEach(el => sum_plan_bs += parseFloat(el.value) || 0);
        document.querySelectorAll('.calc-plan-usd').forEach(el => sum_plan_usd += parseFloat(el.value) || 0);
        document.getElementById('tot_plan_bs').innerText = sum_plan_bs.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('tot_plan_usd').innerText = sum_plan_usd.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    document.querySelectorAll('.report-input').forEach(input => {
        // Instant visual conversion when typing BS
        if (input.getAttribute('data-field') === 'reporte_bs' || input.getAttribute('data-field') === 'reporte_bs_fin') {
            input.addEventListener('input', function() {
                let val = parseFloat(this.value) || 0;
                let bcv = parseFloat(document.getElementById('tasa_bcv').value) || 1;
                let usdVal = bcv > 0 ? (val / bcv) : 0;
                
                let row = this.closest('tr');
                if (row) {
                    let usdField = currentMode === 'inicio' ? 'reporte_usd' : 'reporte_usd_fin';
                    let usdInput = row.querySelector(`input[data-field="${usdField}"]`);
                    if (usdInput) {
                        usdInput.value = usdVal.toFixed(2);
                    }
                }
                calculateTotals();
            });
        }

        input.addEventListener('change', function() {
            calculateTotals();
            
            // Auto save logic
            let route = '';
            let payload = {
                _token: '{{ csrf_token() }}',
                field: this.getAttribute('data-field'),
                value: this.value
            };
            
            if (this.classList.contains('save-cuenta')) {
                route = '{{ url("finanzas/flujo-caja/cuenta") }}/' + this.getAttribute('data-id');
            } else if (this.classList.contains('save-resumen')) {
                route = '{{ url("finanzas/flujo-caja/resumen") }}/{{ $resumen->id }}';
            } else if (this.classList.contains('save-plan')) {
                route = '{{ url("finanzas/flujo-caja/planificacion") }}/' + this.getAttribute('data-id');
            }

            if(route) {
                let currentInput = this;
                fetch(route, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                }).then(res => res.json()).then(data => {
                    // Update the local Map cache so switching back and forth keeps the unsaved state if any
                    if (currentInput.classList.contains('save-cuenta')) {
                        let id = currentInput.getAttribute('data-id');
                        if (cuentasMap[id]) {
                            cuentasMap[id][payload.field] = payload.value;
                            
                            // Mimic backend synchronization logic for local JS cache
                            if (payload.field === 'reporte_bs') {
                                cuentasMap[id]['reporte_bs_fin'] = payload.value;
                            }
                            
                            if (data.success) {
                                cuentasMap[id]['reporte_usd'] = data.reporte_usd;
                                cuentasMap[id]['reporte_usd_fin'] = data.reporte_usd_fin;
                            }
                        }
                    }

                    if (data.success && (payload.field === 'reporte_bs' || payload.field === 'reporte_bs_fin') && currentInput.classList.contains('save-cuenta')) {
                        // Find the USD input in the same row and update it visually
                        let row = currentInput.closest('tr');
                        if (row) {
                            let usdField = currentMode === 'inicio' ? 'reporte_usd' : 'reporte_usd_fin';
                            let usdInput = row.querySelector(`input[data-field="${usdField}"]`);
                            if (usdInput) {
                                usdInput.value = currentMode === 'inicio' ? data.reporte_usd : data.reporte_usd_fin;
                                calculateTotals();
                            }
                        }
                    }
                });
            }
        });
    });

    calculateTotals();
});
</script>
@endsection
