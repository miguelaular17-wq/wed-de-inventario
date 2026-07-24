@extends('layouts.app')
@section('title', 'Flujo de Caja y Disponibilidad')
@section('content')

<style>
    /* Premium Modern Styles */
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
    }
    .modern-table-container {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .table-scroll-wrapper {
        max-height: 550px;
        overflow-y: auto;
        overflow-x: auto;
    }
    .modern-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8fafc; /* Fallback */
    }
    .modern-table .header-main, .modern-table .header-tasa, .modern-table .header-tasa-val {
        z-index: 11; /* Topmost header */
    }
    /* For the second row of the header, we need to push it down by the height of the first row */
    .modern-table thead tr:nth-child(2) th {
        top: 36px; /* Approx height of first row */
    }
    .modern-table tfoot td, .modern-table .summary-row td {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background-color: #f8fafc;
        border-top: 2px solid #e2e8f0;
        box-shadow: 0 -2px 5px rgba(0,0,0,0.02);
    }
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }
    .modern-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        padding: 8px 12px;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 11px;
    }
    .modern-table td {
        padding: 4px 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    .modern-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }
    .header-main {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        color: white !important;
        border-bottom: none !important;
    }
    .header-tasa {
        background: #f59e0b !important;
        color: white !important;
        border-bottom: none !important;
    }
    .header-tasa-val {
        background: #fffbeb !important;
        border-bottom: none !important;
    }
    
    .editable-input {
        width: 100%;
        border: 1px solid transparent;
        background: transparent;
        text-align: right;
        outline: none;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: inherit;
        font-size: inherit;
        color: inherit;
        transition: all 0.2s ease;
    }
    .editable-input:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .editable-input:focus {
        background: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59,130,246,0.1);
    }
    
    .color-indicator {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        display: inline-block;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.1);
    }

    .summary-row td {
        background-color: #f8fafc;
        font-weight: 600;
        color: #0f172a;
        border-top: 2px solid #e2e8f0;
    }
    
    /* Widget Cards */
    .widget-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .widget-header {
        background: #f8fafc;
        padding: 8px 12px;
        font-weight: 600;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
    }
    .widget-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .widget-row:last-child {
        border-bottom: none;
    }
    .widget-label {
        color: #64748b;
        font-weight: 500;
    }
    .widget-value {
        font-weight: 600;
        color: #334155;
    }
    
    .widget-block {
        text-align: center;
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .widget-block:last-child {
        border-bottom: none;
    }
    .widget-block-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .widget-block-value {
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }
    .widget-block-value.text-success { color: #10b981; }
    .widget-block-value.text-danger { color: #ef4444; }

    @media (min-width: 1200px) {
        .custom-row {
            display: flex;
            gap: 24px;
        }
        .custom-left {
            flex: 1;
            min-width: 0;
        }
        .custom-right {
            width: 320px;
            flex-shrink: 0;
        }
    }
</style>

<div class="dashboard-container py-4">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h2 style="margin: 0; font-size: 1.5rem; color: var(--blue); font-weight: 600;">Flujo de Caja y Disponibilidad</h2>
        <div style="display: flex; gap: 10px;">
            <form action="{{ route('finanzas.reset_daily') }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar TODOS los datos financieros y empezar el día completamente en blanco?');">
                @csrf
                <button type="submit" class="btn" style="background-color: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.background='#dc2626';" onmouseout="this.style.background='#ef4444';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    Limpiar Día
                </button>
            </form>
            <a href="{{ route('finanzas.reporte_diario_caja') }}" class="btn btn-secondary" style="background-color: #f1f5f9; color: #334155; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Reporte Diario
            </a>
            <a href="{{ route('finanzas.reporte_consolidado') }}" class="btn btn-secondary" style="background-color: #f1f5f9; color: #334155; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Generar Reporte Consolidado
            </a>
            <button class="btn btn-primary" style="background-color: #1a4273; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(26,66,115,0.2); transition: all 0.2s;" onclick="openNuevoEgresoModal()" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 6px rgba(26,66,115,0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(26,66,115,0.2)';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nuevo Egreso
            </button>
        </div>
    </div>

    <div class="custom-row">
        <!-- TABLA DISPONIBILIDAD (IZQUIERDA) -->
        <div class="custom-left mb-4">
            <div class="modern-table-container">
                <div class="table-scroll-wrapper">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th colspan="4" class="header-main" style="text-align: left; font-size: 13px;">DISPONIBILIDAD EN TIEMPO REAL</th>
                                <th class="header-tasa" style="text-align: right;">
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        TASA BCV USD
                                        <div id="bcv-api-status" style="font-size: 10px; color: #64748b; font-weight: normal; margin-top: 2px;"></div>
                                    </div>
                                </th>
                                <th colspan="2" class="header-tasa-val">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                                        <button type="button" id="btn-fetch-bcv" onclick="fetchBcvRate()" title="Consultar BCV API" style="background: none; border: none; cursor: pointer; color: #b45309; padding: 4px; border-radius: 4px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='none'">
                                            <svg id="bcv-icon-refresh" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.92-10.26l5.08 2.69"/></svg>
                                            <svg id="bcv-icon-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none; animation: spin 1s linear infinite;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                                        </button>
                                        <div style="display: flex; align-items: center;">
                                            <span style="color: #b45309; font-weight: 600; margin-right: 4px;">Bs.</span>
                                            <input type="number" step="0.01" id="tasa-bcv-input" class="editable-input" style="text-align: left; width: 100px; font-weight: 700; color: #b45309; background: #fef3c7; border-color: #fde68a;" 
                                                value="{{ $resumen->tasa_bcv_usd }}" data-type="resumen" data-id="{{ $resumen->id }}" data-field="tasa_bcv_usd">
                                        </div>
                                    </div>
                                </th>
                            </tr>
                            <tr>
                                <th style="width: 40px; text-align: center;">TC</th>
                                <th style="width: 15%;">BANCO</th>
                                <th style="width: 15%;">TITULAR</th>
                                <th style="text-align: right; width: 16%;">BS TC</th>
                                <th style="text-align: right; width: 16%;">BS DISPONIBLES</th>
                                <th style="text-align: right; width: 16%;">USD TC</th>
                                <th style="text-align: right; width: 16%;">USD DISP.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cuentasBancarias as $cb)
                            <tr>
                                <td style="text-align: center;">
                                    @if($cb->color_tc)
                                        <div class="color-indicator" style="background-color: {{ $cb->color_tc }};" title="Tipo: {{ $cb->color_tc }}"></div>
                                    @else
                                        <div class="color-indicator" style="background-color: #f1f5f9; border: 1px solid #e2e8f0;"></div>
                                    @endif
                                </td>
                                <td style="font-weight: 500; color: #0f172a;">{{ $cb->banco }}</td>
                                <td>{{ $cb->titular }}</td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <input type="number" step="0.01" class="editable-input" value="{{ $cb->bs_tc }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_tc">
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <input type="number" step="0.01" class="editable-input" value="{{ $cb->bs_disponibles }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_disponibles">
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <input type="number" step="0.01" class="editable-input" value="{{ $cb->usd_tc }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_tc">
                                    </div>
                                </td>
                                <td style="{{ $loop->last ? 'background-color: #f0fdf4;' : '' }}">
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <input type="number" step="0.01" class="editable-input" style="{{ $loop->last ? 'color: #166534; font-weight: 600;' : '' }}" value="{{ $cb->usd_disp }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_disp">
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                                    No hay cuentas bancarias. Ejecute el Seeder.
                                </td>
                            </tr>
                            @endforelse
                            
                            <!-- LAST ROW SUMMARY -->
                            <tr class="summary-row">
                                <td colspan="3" style="text-align: right; padding-right: 20px;">TOTALES</td>
                                <td>
                                    <div style="display: flex; align-items: center; justify-content: flex-end;">
                                        <span style="color: #64748b; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <span id="sum_bs_tc">0.00</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; justify-content: flex-end;">
                                        <span style="color: #64748b; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <span id="sum_bs_disp">0.00</span>
                                    </div>
                                </td>
                                <td style="background-color: #f0fdf4; color: #166534;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end;">
                                        <span style="color: #86efac; font-size: 11px; margin-right: 4px;">$</span>
                                        <span id="sum_usd_tc">0.00</span>
                                    </div>
                                </td>
                                <td style="background-color: #f0fdf4; color: #166534;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end;">
                                        <span style="color: #86efac; font-size: 11px; margin-right: 4px;">$</span>
                                        <span id="sum_usd_disp">0.00</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PANELES DERECHA (LEYENDA Y RESUMEN) -->
        <div class="custom-right mb-4">
            
            <!-- Leyenda -->
            <div class="widget-card">
                <div class="widget-header">TIPO DE CUENTA</div>
                <div class="widget-row">
                    <span class="widget-label">P.V/TRANSF/P.M</span>
                    <div class="color-indicator" style="background-color: #f4b183;"></div>
                </div>
                <div class="widget-row">
                    <span class="widget-label">TERCEROS P.V/P.M</span>
                    <div class="color-indicator" style="background-color: #ff0000;"></div>
                </div>
                <div class="widget-row">
                    <span class="widget-label">CASHEA</span>
                    <div class="color-indicator" style="background-color: #ffff00;"></div>
                </div>
                <div class="widget-row">
                    <span class="widget-label">AVANCES</span>
                    <div class="color-indicator" style="background-color: #0070c0;"></div>
                </div>
                <div class="widget-row">
                    <span class="widget-label">B/MOVIMIENTO</span>
                    <div class="color-indicator" style="background-color: #f1f5f9; border: 1px solid #e2e8f0;"></div>
                </div>
            </div>

            <!-- Resumen Financiero -->
            <div class="widget-card">
                <div class="widget-header">RESUMEN FINANCIERO</div>
                
                <div class="widget-block">
                    <div class="widget-block-label" title="Sincronizado automáticamente con la Disponibilidad Bancaria (Tasa BCV)">Saldo Inicial (Auto)</div>
                    <div style="display: flex; justify-content: center; align-items: center; gap: 4px;">
                        <span style="color: #94a3b8; font-size: 14px; font-weight: 500;">$</span>
                        <input type="number" step="0.01" class="widget-block-value" style="text-align: center; background: #e2e8f0; border-color: #cbd5e1; padding: 8px; max-width: 150px; color: #64748b; cursor: not-allowed;"
                            value="{{ $resumen->saldo_inicial }}" readonly>
                    </div>
                </div>

                <div class="widget-block" style="background-color: #f8fafc;">
                    <div class="widget-block-label">Total Salidas (USD)</div>
                    <div class="widget-block-value text-danger" style="font-size: 18px;">
                        @php $total_salidas_usd = $resumen->tasa_bcv_usd > 0 ? ($total_salidas_bs / $resumen->tasa_bcv_usd) : 0; @endphp
                        <span style="color: #fca5a5; font-size: 14px; font-weight: 500;">$</span>{{ number_format($total_salidas_usd, 2) }}
                    </div>
                </div>

                <div class="widget-block">
                    <div class="widget-block-label">Queda del día anterior</div>
                    <div class="widget-block-value" style="font-size: 18px; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px; border-radius: 6px;">
                        @php $queda_calculada = $resumen->saldo_inicial - $total_salidas_usd; @endphp
                        <span style="color: #6ee7b7; font-size: 14px; font-weight: 500;">$</span>{{ number_format($queda_calculada, 2) }}
                    </div>
                </div>

                <div class="widget-block" style="background-color: #fef2f2;">
                    <div class="widget-block-label" style="color: #dc2626;">Total Diferencial Cambiario</div>
                    <div class="widget-block-value text-danger">
                        <span style="color: #fca5a5; font-size: 14px; font-weight: 500;">$</span>{{ number_format($total_diferencial_cambiario, 2) }}
                    </div>
                </div>

                <div class="widget-row" style="background-color: #fff1f2;">
                    <span class="widget-label" style="color: #be123c; font-size: 11px; text-transform: uppercase; font-weight: 700;">% Diferencial</span>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        @php $pct_dif_global = $resumen->saldo_inicial > 0 ? ($total_diferencial_cambiario / $resumen->saldo_inicial) * 100 : 0; @endphp
                        <span style="font-weight: 700; color: #be123c; background: white; border: 1px solid #fda4af; padding: 4px 10px; border-radius: 4px; display: inline-block; min-width: 80px; text-align: right;">
                            {{ number_format($pct_dif_global, 2) }}
                        </span>
                        <span style="color: #be123c; font-weight: 700;">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- EGRESOS REALIZADOS -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 15px;">
        <h3 style="color: var(--blue); margin: 0;">EGRESOS REALIZADOS</h3>
        <form method="GET" action="{{ route('finanzas.flujo_caja') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Día a consultar:</label>
            <input type="date" name="fecha_filtro" value="{{ $fecha_filtro }}" style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;" onchange="this.form.submit()">
        </form>
    </div>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th>Banco y Titular</th>
                        <th>Tipo Gasto</th>
                        <th>Motivo</th>
                        <th class="col-number" style="text-align: right;">USD</th>
                        <th class="col-number" style="text-align: right;">Tasa Cambio</th>
                        <th class="col-number" style="text-align: right;">Dif. Cambiario</th>
                        <th class="col-number" style="text-align: right;">BS</th>
                        <th class="col-number" style="text-align: right;">Comisión</th>
                        <th style="text-align: center; width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($egresos_realizados as $mov)
                        <tr>
                            <td>{{ $mov->fecha }}</td>
                            <td>
                                <strong style="color: var(--blue);">{{ $mov->banco }}</strong><br>
                                <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                            </td>
                            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                            <td>
                                {{ $mov->motivo ?: '-' }}
                                @if($mov->desglose)
                                    <div style="margin-top: 5px;">
                                        <button type="button" onclick='verDesglose(@json($mov->desglose))' style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; cursor: pointer;">
                                            Ver Desglose ({{ count($mov->desglose) }})
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">{{ $mov->tasa_cambio ? number_format($mov->tasa_cambio, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; color: var(--danger);">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">
                                {{ $mov->comision ? number_format($mov->comision, 2) : '-' }}
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                @php
                                    $allComprobantes = array_filter(array_merge(
                                        $mov->comprobantes ?? [],
                                        ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
                                    ));
                                @endphp
                                {{-- Galería --}}
                                @if(count($allComprobantes) > 0)
                                    <button type="button" onclick='abrirGaleria(@json(array_values($allComprobantes)))'
                                        title="Ver comprobantes ({{ count($allComprobantes) }})"
                                        style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer; margin-right: 2px;">📎 {{ count($allComprobantes) }}</button>
                                @endif
                                {{-- Editar --}}
                                <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                    title="Editar egreso"
                                    style="background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px; color: var(--muted);">No hay egresos realizados.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php
                        $tot_egresos_bs = $egresos_realizados->sum('monto_bs');
                        $tot_egresos_com = $egresos_realizados->sum('comision');
                        $tot_egresos_dif = $egresos_realizados->sum('diferencial_cambiario');
                        $tasa = $resumen->tasa_bcv_usd > 0 ? $resumen->tasa_bcv_usd : 1;
                        $tot_egresos_bs_usd = $tot_egresos_bs / $tasa;
                        $tot_egresos_com_usd = $tot_egresos_com / $tasa;
                        $porcentaje_dc = $tot_egresos_bs_usd > 0 ? ($tot_egresos_dif / $tot_egresos_bs_usd) * 100 : 0;
                    @endphp
                    <tr style="background-color: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: bold;">
                        <td colspan="3" style="text-align: right; color: var(--blue);">
                            <span style="color: #be123c; margin-right: 15px; font-weight: 700;">% D.C: {{ number_format($porcentaje_dc, 2) }}%</span>
                            TOTALES
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            $ {{ number_format($egresos_realizados->sum('monto_usd'), 2) }}
                        </td>
                        <td></td>
                        <td class="col-number" style="text-align: right; color: var(--danger);">
                            $ {{ number_format($tot_egresos_dif, 2) }}
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            Bs. {{ number_format($tot_egresos_bs, 2) }}<br>
                            <span style="font-size: 0.85rem; color: #166534;">$ {{ number_format($tot_egresos_bs_usd, 2) }}</span>
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            Bs. {{ number_format($tot_egresos_com, 2) }}<br>
                            <span style="font-size: 0.85rem; color: #166534;">$ {{ number_format($tot_egresos_com_usd, 2) }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- OTROS EGRESOS (AVANCES Y CAMBIOS) -->
    <h3 style="margin-bottom: 15px; color: var(--blue);">OTROS EGRESOS (AVANCES Y CAMBIOS)</h3>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th>Banco y Titular</th>
                        <th>Tipo Gasto</th>
                        <th>Motivo</th>
                        <th class="col-number" style="text-align: right;">USD</th>
                        <th class="col-number" style="text-align: right;">Tasa Cambio</th>
                        <th class="col-number" style="text-align: right;">Dif. Cambiario</th>
                        <th class="col-number" style="text-align: right;">BS</th>
                        <th class="col-number" style="text-align: right;">Comisión</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($otros_egresos as $mov)
                        <tr>
                            <td>{{ $mov->fecha }}</td>
                            <td>
                                <strong style="color: var(--blue);">{{ $mov->banco }}</strong><br>
                                <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                            </td>
                            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                            <td>{{ $mov->motivo ?: '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">{{ $mov->tasa_cambio ? number_format($mov->tasa_cambio, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; color: var(--danger);">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--muted);">No hay otros egresos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php
                        $tot_otros_bs = $otros_egresos->sum('monto_bs');
                        $tot_otros_com = $otros_egresos->sum('comision');
                        $tot_otros_dif = $otros_egresos->sum('diferencial_cambiario');
                        $tasa = $resumen->tasa_bcv_usd > 0 ? $resumen->tasa_bcv_usd : 1;
                        $tot_otros_bs_usd = $tot_otros_bs / $tasa;
                        $tot_otros_com_usd = $tot_otros_com / $tasa;
                        $porcentaje_otros_dc = $tot_otros_bs_usd > 0 ? ($tot_otros_dif / $tot_otros_bs_usd) * 100 : 0;
                    @endphp
                    <tr style="background-color: #f8fafc; border-top: 2px solid #e2e8f0; font-weight: bold;">
                        <td colspan="3" style="text-align: right; color: var(--blue);">
                            <span style="color: #be123c; margin-right: 15px; font-weight: 700;">% D.C: {{ number_format($porcentaje_otros_dc, 2) }}%</span>
                            TOTALES
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            $ {{ number_format($otros_egresos->sum('monto_usd'), 2) }}
                        </td>
                        <td></td>
                        <td class="col-number" style="text-align: right; color: var(--danger);">
                            $ {{ number_format($tot_otros_dif, 2) }}
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            Bs. {{ number_format($tot_otros_bs, 2) }}<br>
                            <span style="font-size: 0.85rem; color: #166534;">$ {{ number_format($tot_otros_bs_usd, 2) }}</span>
                        </td>
                        <td class="col-number" style="text-align: right; color: var(--blue);">
                            Bs. {{ number_format($tot_otros_com, 2) }}<br>
                            <span style="font-size: 0.85rem; color: #166534;">$ {{ number_format($tot_otros_com_usd, 2) }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- TRASLADOS (no aparece en reportes) -->
<div class="dashboard-container" style="margin-top: 10px;">
    <h3 style="margin-bottom: 15px; color: #7c3aed; display: flex; align-items: center; gap: 8px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 16V4m0 0L3 8m4-4l4 4"/><path d="M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
        TRASLADOS BANCARIOS
        <span style="font-size: 0.75rem; font-weight: 400; background: #ede9fe; color: #6d28d9; padding: 2px 10px; border-radius: 20px; margin-left: 6px;">No incluidos en el reporte</span>
    </h3>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1.5px solid #ede9fe;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr style="background: #faf5ff;">
                        <th style="width: 100px;">Fecha</th>
                        <th>Banco Emisor y Titular</th>
                        <th>Banco Receptor y Titular</th>
                        <th>Motivo</th>
                        <th class="col-number" style="text-align: right;">Monto BS</th>
                        <th style="text-align: center; width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($traslados as $mov)
                        <tr>
                            <td>{{ $mov->fecha }}</td>
                            <td>
                                <strong style="color: #7c3aed;">{{ $mov->banco }}</strong><br>
                                <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                            </td>
                            <td>
                                @if($mov->banco_receptor)
                                    <strong style="color: #059669;">{{ $mov->banco_receptor }}</strong><br>
                                    <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular_receptor }}</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>{{ $mov->motivo ?: '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">
                                {{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                @php
                                    $allComprobantesT = array_filter(array_merge(
                                        $mov->comprobantes ?? [],
                                        ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
                                    ));
                                @endphp
                                @if(count($allComprobantesT) > 0)
                                    <button type="button" onclick='abrirGaleria(@json(array_values($allComprobantesT)))'
                                        title="Ver comprobantes ({{ count($allComprobantesT) }})"
                                        style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer; margin-right: 2px;">📎 {{ count($allComprobantesT) }}</button>
                                @endif
                                <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                    title="Editar traslado"
                                    style="background: #ede9fe; color: #7c3aed; border: 1px solid #ddd6fe; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);">No hay traslados registrados para este día.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php
                        $tot_traslados_bs = $traslados->sum('monto_bs');
                    @endphp
                    <tr style="background-color: #faf5ff; border-top: 2px solid #ede9fe; font-weight: bold;">
                        <td colspan="4" style="text-align: right; color: #7c3aed;">TOTAL TRASLADADO</td>
                        <td class="col-number" style="text-align: right; color: #7c3aed;">
                            Bs. {{ number_format($tot_traslados_bs, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>


<!-- Modal Nuevo Egreso -->
<div id="nuevoEgresoModal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="panel modal-box" style="width: 95%; max-width: 600px; position: relative; padding: 15px 20px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 95vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeNuevoEgresoModal()" aria-label="Cerrar" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin: 0 0 15px; font-size: 1.1rem; color: var(--blue); display: flex; justify-content: space-between; align-items: center;">
            <span>Nuevo Egreso</span>
            <button type="button" id="btn-ocr" onclick="document.getElementById('ocr-upload').click()" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span id="ocr-btn-text">Escanear Recibo</span>
            </button>
            <input type="file" id="ocr-upload" accept="image/*" style="display: none;" onchange="handleOcrUpload(event)">
        </h3>
        
        <form id="formNuevoEgreso" method="POST" action="{{ route('finanzas.store_egreso') }}" enctype="multipart/form-data" onsubmit="return validarDesglose(event)">
            @csrf
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tipo de Egreso</label>
                    <select name="categoria_egreso" id="categoria_egreso" onchange="toggleTraslados()" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="egreso_realizado">EGRESOS REALIZADOS</option>
                        <option value="otros_egresos">OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                        <option value="traslados">TRASLADOS</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Fecha</label>
                    <input type="date" name="fecha" value="{{ $fecha_filtro }}" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 2;">
                    <label id="lbl_banco_titular" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco y Titular</label>
                    <select name="banco_titular" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccione --</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1;" id="col_referencia">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Ref.</label>
                    <input type="text" name="referencia" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;" placeholder="# Referencia">
                </div>
            </div>

            <div id="row_receptor" style="display: none; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco Receptor y Titular Receptor</label>
                    <select name="banco_titular_receptor" id="banco_titular_receptor" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccione Receptor --</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;" id="col_monto_usd">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto USD</label>
                    <input type="number" step="0.01" name="monto_usd" id="monto_usd" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="col_tasa_cambio">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tasa de Cambio</label>
                    <input type="number" step="0.01" name="tasa_cambio" id="tasa_cambio" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;" id="lbl_monto_bs">Monto BS</label>
                    <input type="number" step="0.01" name="monto_bs" id="monto_bs" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div id="row_diferencial" style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Dif. Cambiario</label>
                    <input type="number" step="0.01" name="diferencial_cambiario" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comisión</label>
                    <input type="number" step="0.01" name="comision" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div id="row_tipo_gasto" style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tipo de Gasto</label>
                <select name="tipo_gasto" id="tipo_gasto" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <option value="">-- Seleccione un tipo de gasto --</option>
                    <option value="083 - GASTOS MEDICOS EMPLEADOS">083 - GASTOS MEDICOS EMPLEADOS</option>
                    <option value="002 - IMPUESTO MUNICIPAL (ALCALDIAS)">002 - IMPUESTO MUNICIPAL (ALCALDIAS)</option>
                    <option value="003 - ALQUILERES">003 - ALQUILERES</option>
                    <option value="004 - AYUDA FAMILIAR">004 - AYUDA FAMILIAR</option>
                    <option value="005 - COLABORACIONES">005 - COLABORACIONES</option>
                    <option value="006 - DONACIONES (LEGALES)">006 - DONACIONES (LEGALES)</option>
                    <option value="007 - INSUMOS ARREGLOS">007 - INSUMOS ARREGLOS</option>
                    <option value="008 - CONSUMIBLES TIENDA">008 - CONSUMIBLES TIENDA</option>
                    <option value="009 - ARTICULOS DE PAPELERIA">009 - ARTICULOS DE PAPELERIA</option>
                    <option value="010 - CONDOMINIO TIENDAS">010 - CONDOMINIO TIENDAS</option>
                    <option value="011 - ENVIOS Y ENCOMIENDAS">011 - ENVIOS Y ENCOMIENDAS</option>
                    <option value="012 - GASTOS NOTARIA/LEGALES">012 - GASTOS NOTARIA/LEGALES</option>
                    <option value="013 - GASTOS NOTARIA/LEGALES (EMPEÑO)">013 - GASTOS NOTARIA/LEGALES (EMPEÑO)</option>
                    <option value="014 - GASTOS INTERNET TIENDAS">014 - GASTOS INTERNET TIENDAS</option>
                    <option value="0141 - GASTOS TELEFONICOS">0141 - GASTOS TELEFONICOS</option>
                    <option value="015 - MANT. Y REP. VEHICULOS TIENDAS">015 - MANT. Y REP. VEHICULOS TIENDAS</option>
                    <option value="017 - MANT. Y REP. VEHICULOS EMPEÑOS">017 - MANT. Y REP. VEHICULOS EMPEÑOS</option>
                    <option value="016 - MANT. Y REP. VEHICULOS DIRECTIVO">016 - MANT. Y REP. VEHICULOS DIRECTIVO</option>
                    <option value="018 - FLETE COMPRA MERCANCIA">018 - FLETE COMPRA MERCANCIA</option>
                    <option value="019 - IMASEO TIENDA">019 - IMASEO TIENDA</option>
                    <option value="020 - IMPUESTOS Y TASAS ( SENIAT)">020 - IMPUESTOS Y TASAS ( SENIAT)</option>
                    <option value="021 - INCES">021 - INCES</option>
                    <option value="022 - FAOV">022 - FAOV</option>
                    <option value="023 - IVSS">023 - IVSS</option>
                    <option value="024 - AGAZAGOS PERSONAL">024 - AGAZAGOS PERSONAL</option>
                    <option value="025 - UTILIDADES PERSONAL">025 - UTILIDADES PERSONAL</option>
                    <option value="026 - VACACIONES PERSONAL">026 - VACACIONES PERSONAL</option>
                    <option value="027 - NOMINA LEGAL">027 - NOMINA LEGAL</option>
                    <option value="028 - NOMINA ESPECIAL">028 - NOMINA ESPECIAL</option>
                    <option value="029 - PRESTACIONES SOCIALES">029 - PRESTACIONES SOCIALES</option>
                    <option value="030 - UNIFORME PERSONAL">030 - UNIFORME PERSONAL</option>
                    <option value="031 - ARTICULOS DE LIMPIEZA">031 - ARTICULOS DE LIMPIEZA</option>
                    <option value="032 - COMBUSTIBLE PLANTAS ELECTRICAS">032 - COMBUSTIBLE PLANTAS ELECTRICAS</option>
                    <option value="033 - COMBUSTIBLE VEHICULOS TIENDAS">033 - COMBUSTIBLE VEHICULOS TIENDAS</option>
                    <option value="034 - COMBUSTIBLE VEHICULOS DIRECTIVO">034 - COMBUSTIBLE VEHICULOS DIRECTIVO</option>
                    <option value="035 - MANT. Y REP. LOCALES TIENDA">035 - MANT. Y REP. LOCALES TIENDA</option>
                    <option value="036 - MANT. Y REP. LOCALES/CASAS EMPEÑO">036 - MANT. Y REP. LOCALES/CASAS EMPEÑO</option>
                    <option value="037 - CONDOMINIOS EMPEÑOS">037 - CONDOMINIOS EMPEÑOS</option>
                    <option value="038 - CONDOMINIOS DIRECTIVO">038 - CONDOMINIOS DIRECTIVO</option>
                    <option value="039 - GASTOS INTERNET DIRECTIVO">039 - GASTOS INTERNET DIRECTIVO</option>
                    <option value="040 - SUELDOS Y SALARIO DIRECTIVO">040 - SUELDOS Y SALARIO DIRECTIVO</option>
                    <option value="041 - SUELDO Y SALARIO MARIA NUÑEZ">041 - SUELDO Y SALARIO MARIA NUÑEZ</option>
                    <option value="042 - GASTOS CAFETIN">042 - GASTOS CAFETIN</option>
                    <option value="043 - CORPOELEC TIENDAS">043 - CORPOELEC TIENDAS</option>
                    <option value="044 - HIDROFALCON TIENDAS">044 - HIDROFALCON TIENDAS</option>
                    <option value="045 - GASTOS AGUA CISTERNA">045 - GASTOS AGUA CISTERNA</option>
                    <option value="046 - REPUESTOS VEHICULOS TIENDA">046 - REPUESTOS VEHICULOS TIENDA</option>
                    <option value="047 - REPUESTOS VEHICULOS DIRECTIVO">047 - REPUESTOS VEHICULOS DIRECTIVO</option>
                    <option value="048 - REPUESTOS VEHICULOS EMPEÑO">048 - REPUESTOS VEHICULOS EMPEÑO</option>
                    <option value="049 - INSUMOS MANTENIMIENTO">049 - INSUMOS MANTENIMIENTO</option>
                    <option value="050 - GASTOS BOUTIQUIN MEDICINAS">050 - GASTOS BOUTIQUIN MEDICINAS</option>
                    <option value="051 - ACTIVOS (EQUIPOS-MOBILIARIOS)">051 - ACTIVOS (EQUIPOS-MOBILIARIOS)</option>
                    <option value="052 - GASTOS SISTEMA INTEGRA/UNIX">052 - GASTOS SISTEMA INTEGRA/UNIX</option>
                    <option value="053 - GASTOS ASESORIAS EXTERNAS">053 - GASTOS ASESORIAS EXTERNAS</option>
                    <option value="054 - GASTOS PERMISOLOGIAS">054 - GASTOS PERMISOLOGIAS</option>
                    <option value="055 - PUBLICIDAD">055 - PUBLICIDAD</option>
                    <option value="056 - SERVICIO COULD PAGO">056 - SERVICIO COULD PAGO</option>
                    <option value="057 - REPUESTOS TELEFONIA (CAJA CHICA)">057 - REPUESTOS TELEFONIA (CAJA CHICA)</option>
                    <option value="058 - SERVICIO TECNICO (GARANTIAS)">058 - SERVICIO TECNICO (GARANTIAS)</option>
                    <option value="059 - COMISIONES EMPEÑO">059 - COMISIONES EMPEÑO</option>
                    <option value="060 - SUSCRIPCIONES">060 - SUSCRIPCIONES</option>
                    <option value="061 - MANTENIMIENTO MOBILIARIOS Y EQUIPOS">061 - MANTENIMIENTO MOBILIARIOS Y EQUIPOS</option>
                    <option value="062 - SERVICIO PAGINA WEB">062 - SERVICIO PAGINA WEB</option>
                    <option value="063 - GASTOS SISTEMA PREMIUM">063 - GASTOS SISTEMA PREMIUM</option>
                    <option value="064 - SERVICIO INTEGRA">064 - SERVICIO INTEGRA</option>
                    <option value="065 - FONACIT">065 - FONACIT</option>
                    <option value="066 - CURSOS Y ADIESTRAMIENTOS">066 - CURSOS Y ADIESTRAMIENTOS</option>
                    <option value="067 - INGRESO VENTAS">067 - INGRESO VENTAS</option>
                    <option value="068 - CXC GRUPO JENU (AVANCES)">068 - CXC GRUPO JENU (AVANCES)</option>
                    <option value="069 - CXC/CXP DIRECTIVO JOSE JEREZ">069 - CXC/CXP DIRECTIVO JOSE JEREZ</option>
                    <option value="070 - DESEMBOLSO NOMINA DIVISAS">070 - DESEMBOLSO NOMINA DIVISAS</option>
                    <option value="071 - DESEMBOLSO NOMINA BOLIVARES">071 - DESEMBOLSO NOMINA BOLIVARES</option>
                    <option value="072 - ANTICIPO NOMINAS DIVISAS">072 - ANTICIPO NOMINAS DIVISAS</option>
                    <option value="073 - ANTICIPO NOMINAS BOLIVARES">073 - ANTICIPO NOMINAS BOLIVARES</option>
                    <option value="096 - PRESTAMO PERSONAL DIVISAS">096 - PRESTAMO PERSONAL DIVISAS</option>
                    <option value="074 - PRESTAMO PERSONAL BOLIVARES (TRANSF)">074 - PRESTAMO PERSONAL BOLIVARES (TRANSF)</option>
                    <option value="075 - COMISION HONOR">075 - COMISION HONOR</option>
                    <option value="076 - EMPEÑOS">076 - EMPEÑOS</option>
                    <option value="077 - COMPRA DIVISAS BANCOS">077 - COMPRA DIVISAS BANCOS</option>
                    <option value="078 - DEPOSITO BANCARIO">078 - DEPOSITO BANCARIO</option>
                    <option value="079 - COMISION EMPEÑO">079 - COMISION EMPEÑO</option>
                    <option value="080 - INGRESO ALQUILERES">080 - INGRESO ALQUILERES</option>
                    <option value="081 - COMPRA DIVISAS TERCEROS">081 - COMPRA DIVISAS TERCEROS</option>
                    <option value="082 - FONDO BOLIVARES">082 - FONDO BOLIVARES</option>
                    <option value="084 - ABONO TIENDA EMPLEADOS">084 - ABONO TIENDA EMPLEADOS</option>
                    <option value="085 - PEAJES">085 - PEAJES</option>
                    <option value="086 - COMISION ZTE">086 - COMISION ZTE</option>
                    <option value="087 - PAGO PROVEEDORES">087 - PAGO PROVEEDORES</option>
                    <option value="088 - INCENTIVOS PERSONAL">088 - INCENTIVOS PERSONAL</option>
                    <option value="089 - INSUMOS TIENDA">089 - INSUMOS TIENDA</option>
                    <option value="090 - PROTECCION PRECIO HONOR">090 - PROTECCION PRECIO HONOR</option>
                    <option value="091 - HONORARIOS PROFESIONALES">091 - HONORARIOS PROFESIONALES</option>
                    <option value="092 - GASTOS DELIVERY">092 - GASTOS DELIVERY</option>
                    <option value="093 - GASTOS DIRECTIVO">093 - GASTOS DIRECTIVO</option>
                    <option value="094 - SALDO MOVISTAR">094 - SALDO MOVISTAR</option>
                    <option value="095 - TASAS Y CONTRIBUCIONES">095 - TASAS Y CONTRIBUCIONES</option>
                    <option value="097 - INSTALACIONES Y MEJORAS GALPON Y DEPOSITO">097 - INSTALACIONES Y MEJORAS GALPON Y DEPOSITO</option>
                    <option value="098 - MEJORAS INSTALACIONES TIENDAS">098 - MEJORAS INSTALACIONES TIENDAS</option>
                    <option value="099 - DEVOLUCIONES CLIENTES">099 - DEVOLUCIONES CLIENTES</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Sede (Opcional)</label>
                    <select name="sede" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                        <option value="">-- Seleccione una sede --</option>
                        @foreach(config('inventario.sedes_locales') as $sedeLocal)
                            <option value="{{ $sedeLocal }}">{{ $sedeLocal }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Placa del vehículo</label>
                    <input type="text" name="placa_vehiculo" placeholder="Ej. ABC-123" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Motivo (Breve descripción)</label>
                <input type="text" name="motivo" placeholder="Ej. Pago de internet mensual..." style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="margin-bottom: 15px; margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; font-size: 0.95rem;">
                    <input type="checkbox" id="chk_desglose" onchange="toggleDesglose()" style="width: 16px; height: 16px;">
                    Este pago es general y requiere desglose por beneficiarios
                </label>
            </div>

            <div id="container_desglose" style="display: none; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px;">
                <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--blue);">Desglose del Pago</h4>
                <div id="lista_desglose">
                    <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
                        <input type="text" name="desglose_beneficiario[]" placeholder="Beneficiario" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <input type="text" name="desglose_cedula[]" placeholder="Cédula" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <input type="number" step="0.01" name="desglose_monto[]" placeholder="Monto Bs" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <button type="button" onclick="this.parentElement.remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">&times;</button>
                    </div>
                </div>
                <button type="button" onclick="agregarDesglose()" style="margin-top: 5px; padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+ Añadir persona</button>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comprobante de Pago</label>
                <div id="paste-area" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s;" onmouseover="this.style.borderColor='#3b82f6'; this.style.backgroundColor='#eff6ff';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.backgroundColor='#f8fafc';">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <span id="paste-text" style="display: block; color: #64748b; font-size: 13px;">Haz clic aquí y presiona <b>Ctrl+V</b> para pegar una imagen.</span>
                    <img id="preview-image" src="" style="max-width: 100%; max-height: 100px; display: none; margin: 10px auto 0; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" />
                </div>
                <input type="file" name="comprobante" id="comprobante-input" accept="image/*" style="display: none;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeNuevoEgresoModal(); sessionStorage.removeItem('pending_ocr_txs'); document.querySelector('#nuevoEgresoModal button[type=\'submit\']').innerText='Guardar Egreso'; document.querySelector('#nuevoEgresoModal button[type=\'submit\']').style.backgroundColor='#1a4273';" style="padding: 10px 20px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancelar</button>
                <button type="submit" style="padding: 10px 20px; background-color: #1a4273; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">Guardar Egreso</button>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
function toggleTraslados() {
    const cat = document.getElementById('categoria_egreso').value;
    const isTraslado = (cat === 'traslados');

    document.getElementById('row_receptor').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor').required = isTraslado;
    
    document.getElementById('lbl_banco_titular').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs').innerText = isTraslado ? 'Monto' : 'Monto BS';
    
    document.getElementById('col_monto_usd').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio').style.display = isTraslado ? 'none' : 'block';
    
    document.getElementById('row_diferencial').style.display = isTraslado ? 'none' : 'flex';
    document.getElementById('row_tipo_gasto').style.display = isTraslado ? 'none' : 'block';
    
    document.getElementById('tipo_gasto').required = !isTraslado;
}

function fetchBcvRate() {
    const btn = document.getElementById('btn-fetch-bcv');
    const iconRefresh = document.getElementById('bcv-icon-refresh');
    const iconSpinner = document.getElementById('bcv-icon-spinner');
    const statusDiv = document.getElementById('bcv-api-status');
    const input = document.getElementById('tasa-bcv-input');

    if (btn) btn.disabled = true;
    if (iconRefresh) iconRefresh.style.display = 'none';
    if (iconSpinner) iconSpinner.style.display = 'block';
    if (statusDiv) statusDiv.innerHTML = 'Consultando...';

    fetch('{{ route("finanzas.api_bcv") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (statusDiv) statusDiv.innerHTML = 'Actualizado: ' + data.fecha_actualizacion;
                if (input) {
                    input.value = data.tasa;
                    // Disparar evento change para que el backend lo guarde automáticamente
                    input.dispatchEvent(new Event('change'));
                }
            } else {
                if (statusDiv) statusDiv.innerHTML = '<span style="color: #ef4444;">Error: ' + data.message + '</span>';
            }
        })
        .catch(err => {
            console.error('Error fetching BCV rate:', err);
            if (statusDiv) statusDiv.innerHTML = '<span style="color: #ef4444;">Sin conexión a API</span>';
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (iconSpinner) iconSpinner.style.display = 'none';
            if (iconRefresh) iconRefresh.style.display = 'block';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto fetch BCV API on load
    fetchBcvRate();

    // Initialize TomSelect for tipo_gasto
    const srcTG = document.getElementById('tipo_gasto');
    const dstTG = document.getElementById('edit_tipo_gasto');
    if (srcTG && dstTG) {
        dstTG.innerHTML = srcTG.innerHTML;
    }
    const tsSettings = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: '-- Seleccione un tipo de gasto --',
        maxOptions: null
    };
    if (srcTG) window.tsTipoGasto = new TomSelect("#tipo_gasto", tsSettings);
    if (dstTG) window.tsEditTipoGasto = new TomSelect("#edit_tipo_gasto", tsSettings);

    // Modal functions
    window.openNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };
    window.closeNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'none';
        // Limpiar el comprobante
        document.getElementById('comprobante-input').value = '';
        document.getElementById('preview-image').src = '';
        document.getElementById('preview-image').style.display = 'none';
        document.getElementById('paste-text').style.display = 'block';
    };

    // Pegar imagen (Comprobante)
    const pasteArea = document.getElementById('paste-area');
    const fileInput = document.getElementById('comprobante-input');
    const preview = document.getElementById('preview-image');
    const pasteText = document.getElementById('paste-text');

    pasteArea.addEventListener('click', () => {
        fileInput.click();
    });

    document.getElementById('nuevoEgresoModal').addEventListener('paste', (e) => {
        handlePaste(e);
    });

    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            showPreview(this.files[0]);
        }
    });

    function handlePaste(e) {
        let items = e.clipboardData || e.originalEvent.clipboardData;
        if (!items) return;

        let file = null;
        for (let i = 0; i < items.items.length; i++) {
            if (items.items[i].type.indexOf("image") !== -1) {
                file = items.items[i].getAsFile();
                break;
            }
        }

        if (file) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            showPreview(file);
        }
    }

    function showPreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            pasteText.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }

    // Calculadora Egreso
    const usdInput = document.getElementById('monto_usd');
    const tasaInput = document.getElementById('tasa_cambio');
    const bsInput = document.getElementById('monto_bs');
    const difInput = document.querySelector('input[name="diferencial_cambiario"]');

    let lastEditedAmount = 'usd';

    function calcular() {
        const usd = parseFloat(usdInput.value) || 0;
        const bs = parseFloat(bsInput.value) || 0;
        const tasa = parseFloat(tasaInput.value) || 0;
        
        if (tasa > 0) {
            if (lastEditedAmount === 'usd') {
                bsInput.value = (usd * tasa).toFixed(2);
            } else if (lastEditedAmount === 'bs') {
                usdInput.value = (bs / tasa).toFixed(2);
            }
        }
        
        const bcvTasaInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
        if (difInput && bcvTasaInput) {
            const bcv = parseFloat(bcvTasaInput.value) || 1;
            const finalUsd = parseFloat(usdInput.value) || 0;
            const finalBs = parseFloat(bsInput.value) || 0;
            if (bcv > 0) {
                difInput.value = (((finalUsd * bcv) - finalBs) / bcv).toFixed(2);
            }
        }
    }

    usdInput.addEventListener('input', function() {
        lastEditedAmount = 'usd';
        calcular();
    });
    
    bsInput.addEventListener('input', function() {
        lastEditedAmount = 'bs';
        calcular();
    });
    
    tasaInput.addEventListener('input', calcular);

    // AJAX Guardado en Vivo
    const editables = document.querySelectorAll('.editable-input');
    
    function updateSums() {
        let bsTc = 0, bsDisp = 0, usdTc = 0, usdDisp = 0;
        document.querySelectorAll('input[data-field="bs_tc"]').forEach(i => bsTc += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(i => bsDisp += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="usd_tc"]').forEach(i => usdTc += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="usd_disp"]').forEach(i => usdDisp += parseFloat(i.value)||0);
        
        // Sumar campos fijos si es necesario, o solo mostrarlos
        document.getElementById('sum_bs_tc').textContent = bsTc.toFixed(2);
        document.getElementById('sum_bs_disp').textContent = bsDisp.toFixed(2);
        document.getElementById('sum_usd_tc').textContent = usdTc.toFixed(2);
        document.getElementById('sum_usd_disp').textContent = usdDisp.toFixed(2);
    }
    
    updateSums(); // Init sums

    editables.forEach(input => {
        input.addEventListener('change', function() {
            // Auto calc USD DISP if BS DISPONIBLES changed
            if (this.getAttribute('data-field') === 'bs_disponibles') {
                const tr = this.closest('tr');
                const usdDispInput = tr.querySelector('input[data-field="usd_disp"]');
                const tasaBcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
                if (usdDispInput && tasaBcvInput) {
                    const bsDisp = parseFloat(this.value) || 0;
                    const tasa = parseFloat(tasaBcvInput.value) || 1;
                    const usdDisp = bsDisp / tasa;
                    usdDispInput.value = usdDisp.toFixed(2);
                    // Trigger change manually to save usd_disp
                    usdDispInput.dispatchEvent(new Event('change'));
                }
            }

            // Auto calc USD TC if BS TC changed
            if (this.getAttribute('data-field') === 'bs_tc') {
                const tr = this.closest('tr');
                const usdTcInput = tr.querySelector('input[data-field="usd_tc"]');
                const tasaBcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
                if (usdTcInput && tasaBcvInput) {
                    const bsTc = parseFloat(this.value) || 0;
                    const tasa = parseFloat(tasaBcvInput.value) || 1;
                    const usdTc = bsTc / tasa;
                    usdTcInput.value = usdTc.toFixed(2);
                    usdTcInput.dispatchEvent(new Event('change'));
                }
            }

            // Auto calc all USD if TASA BCV changed
            if (this.getAttribute('data-field') === 'tasa_bcv_usd') {
                const tasa = parseFloat(this.value) || 1;
                document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdDispInput = tr.querySelector('input[data-field="usd_disp"]');
                    if (usdDispInput) {
                        usdDispInput.value = (parseFloat(bsInput.value || 0) / tasa).toFixed(2);
                        usdDispInput.dispatchEvent(new Event('change'));
                    }
                });
                document.querySelectorAll('input[data-field="bs_tc"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdTcInput = tr.querySelector('input[data-field="usd_tc"]');
                    if (usdTcInput) {
                        usdTcInput.value = (parseFloat(bsInput.value || 0) / tasa).toFixed(2);
                        usdTcInput.dispatchEvent(new Event('change'));
                    }
                });
            }

            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const field = this.getAttribute('data-field');
            const value = this.value;
            
            updateSums();

            let url = '';
            if (type === 'cuenta') {
                url = `/finanzas/flujo-caja/cuenta/${id}`;
            } else {
                url = `/finanzas/flujo-caja/resumen/${id}`;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ field: field, value: value })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.style.backgroundColor = '#d1e7dd';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                } else {
                    alert('Error guardando el campo');
                }
            })
            .catch(err => console.error(err));
        });
    });
});

function handleOcrUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const btn = document.getElementById('btn-ocr');
    const btnText = document.getElementById('ocr-btn-text');
    const originalText = btnText.innerText;
    
    btn.disabled = true;
    btnText.innerText = "Analizando...";
    btn.style.opacity = "0.7";

    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("finanzas.ocr_receipt") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btnText.innerText = originalText;
        btn.style.opacity = "1";
        
        if (data.error) {
            alert('Error al leer recibo: ' + (data.error || 'Desconocido'));
            return;
        }

        // Fill form fields
        if (data.fecha) {
            const dateInput = document.querySelector('input[name="fecha"]');
            if (dateInput) dateInput.value = data.fecha;
        }
        
        if (data.referencia) {
            const refInput = document.querySelector('input[name="referencia"]');
            if (refInput) refInput.value = data.referencia;
        }

        // The user specifically requested amounts in BS
        const amount = data.monto_bs ? data.monto_bs : (data.monto_usd ? data.monto_usd : null);
        const montoBsInput = document.querySelector('input[name="monto_bs"]');
        
        if (amount && montoBsInput) {
            montoBsInput.value = Math.abs(amount);
            montoBsInput.dispatchEvent(new Event('input')); // trigger calculations if any
        }
        
        if (data.motivo) {
            const motivoInput = document.querySelector('input[name="motivo"]');
            if (motivoInput) motivoInput.value = data.motivo;
        }

        // Try to pre-select Banco
        if (data.banco_titular_hint) {
            const bancoSelect = document.querySelector('select[name="banco_titular"]');
            if (bancoSelect) {
                const hint = data.banco_titular_hint.toLowerCase();
                Array.from(bancoSelect.options).forEach(opt => {
                    if (opt.text.toLowerCase().includes(hint)) {
                        bancoSelect.value = opt.value;
                    }
                });
            }
        }
        
        // Abrir el modal original
        openNuevoEgresoModal();

        // Reset file input
        event.target.value = '';
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btnText.innerText = originalText;
        btn.style.opacity = "1";
        alert('Error de conexión o timeout al analizar la imagen.');
    });
}

function handleOcrSaldosUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    const textSpan = document.getElementById('ocr-saldos-text');
    const originalText = textSpan.innerText;
    textSpan.innerText = "Analizando Reporte...";

    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("finanzas.ocr_saldos") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        textSpan.innerText = originalText;
        if (data.error) {
            alert('Error al leer reporte: ' + data.error);
            event.target.value = '';
            return;
        }

        if (!Array.isArray(data) || data.length === 0) {
            alert('No se encontraron cuentas o saldos en el reporte.');
            event.target.value = '';
            return;
        }

        let updatedCount = 0;
        const tableRows = document.querySelectorAll('.modern-table tbody tr');
        
        data.forEach(item => {
            if (!item.banco || !item.titular || item.bs === undefined || item.bs === null) return;
            
            const bankStr = item.banco.toUpperCase().trim();
            const titStr = item.titular.toUpperCase().trim();
            
            tableRows.forEach(tr => {
                const tdBanco = tr.querySelector('td:nth-child(2)');
                const tdTit = tr.querySelector('td:nth-child(3)');
                if (!tdBanco || !tdTit) return;
                
                // Fuzzy match just in case
                const rowBank = tdBanco.innerText.toUpperCase().trim();
                const rowTit = tdTit.innerText.toUpperCase().trim();
                
                if (rowBank.includes(bankStr) && rowTit.includes(titStr)) {
                    const bsDispInput = tr.querySelector('input[data-field="bs_disponibles"]');
                    if (bsDispInput) {
                        bsDispInput.value = item.bs;
                        // Dispatch event to save via AJAX and trigger USD auto-calc
                        bsDispInput.dispatchEvent(new Event('change')); 
                        updatedCount++;
                    }
                }
            });
        });

        alert(`Se actualizaron exitosamente ${updatedCount} cuentas bancarias.`);
        event.target.value = '';
    })
    .catch(err => {
        console.error(err);
        textSpan.innerText = originalText;
        alert('Error de conexión al analizar el reporte.');
        event.target.value = '';
    });
}

function validarDesglose(event) {
    const chk = document.getElementById('chk_desglose');
    if (chk && chk.checked) {
        const montoBsInput = document.getElementById('monto_bs');
        const montoTotal = parseFloat(montoBsInput.value) || 0;
        
        const montosDesglose = document.querySelectorAll('input[name="desglose_monto[]"]');
        let sumaDesglose = 0;
        montosDesglose.forEach(input => {
            sumaDesglose += parseFloat(input.value) || 0;
        });

        if (Math.abs(montoTotal - sumaDesglose) > 0.05) {
            alert(`Error: La suma del desglose (Bs. ${sumaDesglose.toLocaleString('es-VE', {minimumFractionDigits:2})}) no coincide con el Monto Bs total (Bs. ${montoTotal.toLocaleString('es-VE', {minimumFractionDigits:2})}).`);
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function toggleDesglose() {
    const chk = document.getElementById('chk_desglose');
    const container = document.getElementById('container_desglose');
    container.style.display = chk.checked ? 'block' : 'none';
}

function agregarDesglose() {
    const lista = document.getElementById('lista_desglose');
    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
            <input type="text" name="desglose_beneficiario[]" placeholder="Beneficiario" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" name="desglose_cedula[]" placeholder="Cédula" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="number" step="0.01" name="desglose_monto[]" placeholder="Monto Bs" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <button type="button" onclick="this.parentElement.remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">&times;</button>
        </div>
    `;
    lista.insertAdjacentHTML('beforeend', html);
}

function verDesglose(desglose) {
    let tbodyHtml = '';
    let total = 0;
    desglose.forEach(item => {
        const monto = parseFloat(item.monto) || 0;
        total += monto;
        tbodyHtml += `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${item.cedula || '-'}</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${item.beneficiario || '-'}</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 500;">Bs. ${monto.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
            </tr>
        `;
    });
    
    document.getElementById('modalDesgloseBody').innerHTML = tbodyHtml;
    document.getElementById('modalDesgloseTotal').innerText = `Bs. ${total.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    document.getElementById('modalDesglose').style.display = 'flex';
}

function closeDesgloseModal() {
    document.getElementById('modalDesglose').style.display = 'none';
}

// ===== GALERÍA DE COMPROBANTES =====
function abrirGaleria(urls) {
    const grid = document.getElementById('galeriaGrid');
    grid.innerHTML = '';
    urls.forEach((url, i) => {
        const isImage = /\.(jpg|jpeg|png|gif|webp|bmp)(\?|$)/i.test(url);
        const item = document.createElement('div');
        item.style.cssText = 'position:relative;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#f8fafc;display:flex;flex-direction:column;align-items:center;';
        if (isImage) {
            item.innerHTML = `
                <img src="${url}" style="max-width:100%;max-height:200px;object-fit:contain;cursor:zoom-in;" onclick="window.open('${url}','_blank')">
                <div style="padding:6px;display:flex;gap:6px;">
                    <a href="${url}" download target="_blank" style="font-size:0.8rem;background:#3b82f6;color:white;padding:4px 10px;border-radius:4px;text-decoration:none;">⬇ Descargar</a>
                    <a href="${url}" target="_blank" style="font-size:0.8rem;background:#e0f2fe;color:#0284c7;padding:4px 10px;border-radius:4px;text-decoration:none;">🔍 Ver</a>
                </div>`;
        } else {
            item.innerHTML = `
                <div style="padding:20px;text-align:center;font-size:0.85rem;color:#64748b;">📄 Documento ${i+1}</div>
                <div style="padding:6px;display:flex;gap:6px;">
                    <a href="${url}" download target="_blank" style="font-size:0.8rem;background:#3b82f6;color:white;padding:4px 10px;border-radius:4px;text-decoration:none;">⬇ Descargar</a>
                    <a href="${url}" target="_blank" style="font-size:0.8rem;background:#e0f2fe;color:#0284c7;padding:4px 10px;border-radius:4px;text-decoration:none;">🔍 Ver</a>
                </div>`;
        }
        grid.appendChild(item);
    });
    document.getElementById('modalGaleria').style.display = 'flex';
}

function cerrarGaleria() {
    document.getElementById('modalGaleria').style.display = 'none';
}

// ===== EDITAR EGRESO =====
function abrirEditarEgreso(mov) {
    const m = document.getElementById('modalEditarEgreso');
    const f = document.getElementById('formEditarEgreso');

    // Set action URL
    f.action = '/finanzas/flujo-caja/egreso/' + mov.id;

    // Fill fields
    f.querySelector('[name="fecha"]').value          = mov.fecha || '';
    f.querySelector('[name="referencia"]').value     = mov.referencia || '';
    f.querySelector('[name="monto_usd"]').value      = mov.monto_usd || '';
    f.querySelector('[name="tasa_cambio"]').value    = mov.tasa_cambio || '';
    f.querySelector('[name="monto_bs"]').value       = mov.monto_bs || '';
    f.querySelector('[name="comision"]').value       = mov.comision || '';
    f.querySelector('[name="motivo"]').value         = mov.motivo || '';
    f.querySelector('[name="sede"]').value           = mov.sede || '';
    f.querySelector('[name="placa_vehiculo"]').value = mov.placa_vehiculo || '';
    
    // Set value for TomSelect
    if (window.tsEditTipoGasto) {
        window.tsEditTipoGasto.setValue(mov.tipo_gasto || '');
    } else {
        const dstTG = document.getElementById('edit_tipo_gasto');
        if (dstTG) dstTG.value = mov.tipo_gasto || '';
    }

    // Banco titular
    const bancoVal = (mov.banco || '') + '|' + (mov.titular || '') + '|' + (mov.categoria_cuenta || '');
    const bancoSelect = f.querySelector('[name="banco_titular"]');
    if (bancoSelect) {
        // Try exact match first
        let found = false;
        for (let opt of bancoSelect.options) {
            if (opt.value === bancoVal) { opt.selected = true; found = true; break; }
        }
        if (!found) {
            // fallback: search by banco+titular partial
            for (let opt of bancoSelect.options) {
                const parts = opt.value.split('|');
                if (parts[0] === mov.banco && parts[1] === mov.titular) { opt.selected = true; break; }
            }
        }
    }

    // Lógica para Traslados
    const isTraslado = (mov.categoria_egreso === 'traslados');
    
    document.getElementById('row_receptor_edit').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor_edit').required = isTraslado;
    
    document.getElementById('lbl_banco_titular_edit').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs_edit').innerText = isTraslado ? 'Monto' : 'Monto BS';
    
    document.getElementById('col_monto_usd_edit').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio_edit').style.display = isTraslado ? 'none' : 'block';
    
    document.getElementById('row_tipo_gasto_edit').style.display = isTraslado ? 'none' : 'block';
    // Para no dar error con TomSelect requerimos el elemento base
    const dstTG = document.getElementById('edit_tipo_gasto');
    if (dstTG) dstTG.required = !isTraslado;

    // Banco receptor
    if (isTraslado && mov.banco_receptor) {
        const bancoReceptorSelect = document.getElementById('banco_titular_receptor_edit');
        if (bancoReceptorSelect) {
            // Se busca match parcial con banco y titular receptor
            for (let opt of bancoReceptorSelect.options) {
                const parts = opt.value.split('|');
                if (parts[0] === mov.banco_receptor && parts[1] === mov.titular_receptor) { 
                    opt.selected = true; 
                    break; 
                }
            }
        }
    } else {
        document.getElementById('banco_titular_receptor_edit').value = '';
    }

    // Existing comprobantes gallery
    const compSection = document.getElementById('editComprobantesActuales');
    compSection.innerHTML = '';
    const allComps = [];
    if (mov.comprobantes && mov.comprobantes.length) {
        mov.comprobantes.forEach(u => allComps.push(u));
    } else if (mov.comprobante_url) {
        allComps.push(mov.comprobante_url);
    }

    allComps.forEach((url, i) => {
        const isImg = /\.(jpg|jpeg|png|gif|webp|bmp)(\?|$)/i.test(url);
        const div = document.createElement('div');
        div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;';
        div.innerHTML = `
            ${isImg ? `<img src="${url}" style="width:60px;height:50px;object-fit:cover;border-radius:4px;cursor:pointer;" onclick="window.open('${url}','_blank')">` : `<span style="font-size:1.5rem;">📄</span>`}
            <div style="flex:1;font-size:0.8rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${url.split('/').pop()}</div>
            <a href="${url}" target="_blank" style="font-size:0.75rem;color:#0284c7;text-decoration:none;">Ver</a>
            <label style="font-size:0.75rem;color:#dc2626;cursor:pointer;">
                <input type="checkbox" name="comprobantes_eliminar[]" value="${url}"> Eliminar
            </label>`;
        compSection.appendChild(div);
    });
    if (allComps.length === 0) {
        compSection.innerHTML = '<p style="color:#94a3b8;font-size:0.85rem;">Sin comprobantes adjuntos</p>';
    }

    // Desglose
    const chk = document.getElementById('chk_desglose_edit');
    const listaDes = document.getElementById('lista_desglose_edit');
    const containerDes = document.getElementById('container_desglose_edit');
    listaDes.innerHTML = '';
    if (mov.desglose && mov.desglose.length) {
        chk.checked = true;
        containerDes.style.display = 'block';
        mov.desglose.forEach(item => {
            agregarDesgloseEdit(item.beneficiario, item.cedula, item.monto);
        });
    } else {
        chk.checked = false;
        containerDes.style.display = 'none';
    }

    m.style.display = 'flex';
}

function cerrarEditarEgreso() {
    document.getElementById('modalEditarEgreso').style.display = 'none';
}

function toggleDesgloseEdit() {
    const chk = document.getElementById('chk_desglose_edit');
    document.getElementById('container_desglose_edit').style.display = chk.checked ? 'block' : 'none';
}

function agregarDesgloseEdit(benef, ced, monto) {
    const lista = document.getElementById('lista_desglose_edit');
    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
            <input type="text" name="desglose_beneficiario[]" placeholder="Beneficiario" value="${benef || ''}" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" name="desglose_cedula[]" placeholder="Cédula" value="${ced || ''}" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="number" step="0.01" name="desglose_monto[]" placeholder="Monto Bs" value="${monto || ''}" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            <button type="button" onclick="this.parentElement.remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0;">&times;</button>
        </div>`;
    lista.insertAdjacentHTML('beforeend', html);
}

function validarDesgloseEdit(event) {
    const chk = document.getElementById('chk_desglose_edit');
    if (chk && chk.checked) {
        const montoBsInput = document.querySelector('#formEditarEgreso [name="monto_bs"]');
        const montoTotal = parseFloat(montoBsInput ? montoBsInput.value : 0) || 0;
        let sumaDesglose = 0;
        document.querySelectorAll('#lista_desglose_edit input[name="desglose_monto[]"]').forEach(inp => {
            sumaDesglose += parseFloat(inp.value) || 0;
        });
        if (Math.abs(montoTotal - sumaDesglose) > 0.05) {
            alert(`Error: La suma del desglose (Bs. ${sumaDesglose.toFixed(2)}) no coincide con el Monto Bs (Bs. ${montoTotal.toFixed(2)}).`);
            event.preventDefault();
            return false;
        }
    }
    return true;
}

</script>

<!-- Modal Ver Desglose -->
<div id="modalDesglose" class="modal-overlay" style="display: none; z-index: 1200; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="modal-box" style="background: white; width: 95%; max-width: 500px; padding: 20px; border-radius: 12px; position: relative;">
        <button type="button" onclick="closeDesgloseModal()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: var(--blue);">Desglose de Beneficiarios</h3>
        
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background: #f8fafc; text-align: left;">
                    <th style="padding: 8px; border-bottom: 2px solid #e2e8f0;">Cédula</th>
                    <th style="padding: 8px; border-bottom: 2px solid #e2e8f0;">Beneficiario</th>
                    <th style="padding: 8px; border-bottom: 2px solid #e2e8f0; text-align: right;">Monto</th>
                </tr>
            </thead>
            <tbody id="modalDesgloseBody">
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="padding: 10px 8px; text-align: right; font-weight: bold; color: var(--blue);">Total:</td>
                    <td id="modalDesgloseTotal" style="padding: 10px 8px; text-align: right; font-weight: bold; color: var(--blue);">Bs. 0,00</td>
                </tr>
            </tfoot>
        </table>
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" onclick="closeDesgloseModal()" style="padding: 8px 16px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Galería de Comprobantes -->
<div id="modalGaleria" class="modal-overlay" style="display: none; z-index: 1300; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
    <div style="background: white; width: 95%; max-width: 700px; max-height: 90vh; overflow-y: auto; padding: 24px; border-radius: 14px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <button type="button" onclick="cerrarGaleria()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 22px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: var(--blue); display:flex; align-items:center; gap:8px;">📎 Comprobantes de Pago</h3>
        <div id="galeriaGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-top: 15px;"></div>
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" onclick="cerrarGaleria()" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Editar Egreso -->
<div id="modalEditarEgreso" class="modal-overlay" style="display: none; z-index: 1300; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 650px; position: relative; padding: 20px; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); max-height: 92vh; overflow-y: auto; background: white;">
        <button type="button" onclick="cerrarEditarEgreso()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 22px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: var(--blue);">✏️ Editar Egreso</h3>

        <form id="formEditarEgreso" method="POST" action="" enctype="multipart/form-data" onsubmit="return validarDesgloseEdit(event)">
            @csrf

            <!-- Banco + Referencia -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 2;">
                    <label id="lbl_banco_titular_edit" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco y Titular</label>
                    <select name="banco_titular" id="banco_titular_edit" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccione --</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Ref.</label>
                    <input type="text" name="referencia" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <!-- Receptor Traslado -->
            <div id="row_receptor_edit" style="display: none; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco Receptor y Titular Receptor</label>
                    <select name="banco_titular_receptor" id="banco_titular_receptor_edit" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccione Receptor --</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Fecha -->
            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Fecha</label>
                <input type="date" name="fecha" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <!-- Montos -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;" id="col_monto_usd_edit">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto USD</label>
                    <input type="number" step="0.01" name="monto_usd" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="col_tasa_cambio_edit">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tasa Cambio</label>
                    <input type="number" step="0.01" name="tasa_cambio" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label id="lbl_monto_bs_edit" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto BS</label>
                    <input type="number" step="0.01" name="monto_bs" id="monto_bs_edit" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <!-- Comisión -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comisión</label>
                    <input type="number" step="0.01" name="comision" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Sede</label>
                    <input type="text" name="sede" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Placa Veh.</label>
                    <input type="text" name="placa_vehiculo" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <!-- Tipo gasto -->
            <div id="row_tipo_gasto_edit" style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tipo de Gasto</label>
                <select id="edit_tipo_gasto" name="tipo_gasto" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <option value="">-- Seleccione --</option>
                </select>
            </div>

            <!-- Motivo -->
            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Motivo</label>
                <input type="text" name="motivo" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <!-- Comprobantes actuales -->
            <div style="margin-bottom: 15px; border-top: 1px dashed #cbd5e1; padding-top: 12px;">
                <h4 style="margin: 0 0 8px; font-size: 0.95rem; color: #475569;">Comprobantes actuales</h4>
                <div id="editComprobantesActuales"></div>
            </div>

            <!-- Agregar nuevos comprobantes -->
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Agregar nuevos comprobantes</label>
                <input type="file" name="comprobantes_nuevos[]" multiple accept="image/*,.pdf" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: #f8fafc;">
                <p style="margin: 4px 0 0; font-size: 0.8rem; color: #94a3b8;">Puedes seleccionar varias imágenes a la vez</p>
            </div>

            <!-- Desglose -->
            <div style="margin-bottom: 15px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; font-size: 0.95rem;">
                    <input type="checkbox" id="chk_desglose_edit" onchange="toggleDesgloseEdit()" style="width: 16px; height: 16px;">
                    Este pago requiere desglose por beneficiarios
                </label>
            </div>
            <div id="container_desglose_edit" style="display: none; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px;">
                <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--blue);">Desglose del Pago</h4>
                <div id="lista_desglose_edit"></div>
                <button type="button" onclick="agregarDesgloseEdit('','','')" style="margin-top: 5px; padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+ Añadir persona</button>
            </div>

            <!-- Buttons -->
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                <button type="button" onclick="cerrarEditarEgreso()" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 20px; background: var(--blue); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
