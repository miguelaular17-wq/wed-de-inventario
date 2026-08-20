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
        @if(!auth()->user()->isAuditor())
        <div style="display: flex; gap: 10px;">
            <form action="{{ route('finanzas.reset_daily') }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar TODOS los datos financieros y empezar el día completamente en blanco?');">
                @csrf
                <button type="submit" class="btn" style="background-color: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.background='#dc2626';" onmouseout="this.style.background='#ef4444';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    Limpiar Día
                </button>
            </form>
            <a href="{{ route('finanzas.reporte_diario_caja', ['fecha' => $fecha_filtro]) }}" class="btn btn-secondary" style="background-color: #f1f5f9; color: #334155; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f1f5f9';">
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
        @endif
    </div>

    @if(!auth()->user()->isAuditor())
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
                                    </div>
                                </th>
                                <th colspan="2" class="header-tasa-val">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                                        <div style="display: flex; align-items: center;">
                                            <span style="color: #b45309; font-weight: 600; margin-right: 4px;">Bs.</span>
                                            <input type="text" inputmode="decimal" id="tasa-bcv-input" class="editable-input" style="text-align: left; width: 100px; font-weight: 700; color: #b45309; background: #fef3c7; border-color: #fde68a;" 
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
                                        <input type="text" inputmode="decimal" class="editable-input" value="{{ $fecha_filtro == date('Y-m-d') ? $cb->bs_tc : '0.00' }}" {{ $fecha_filtro == date('Y-m-d') ? '' : 'readonly' }} data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_tc">
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <input type="text" inputmode="decimal" class="editable-input" value="{{ $fecha_filtro == date('Y-m-d') ? $cb->bs_disponibles : '0.00' }}" {{ $fecha_filtro == date('Y-m-d') ? '' : 'readonly' }} data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_disponibles">
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <input type="text" inputmode="decimal" class="editable-input" value="{{ $fecha_filtro == date('Y-m-d') ? $cb->usd_tc : '0.00' }}" {{ $fecha_filtro == date('Y-m-d') ? '' : 'readonly' }} data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_tc">
                                    </div>
                                </td>
                                <td style="{{ $loop->last ? 'background-color: #f0fdf4;' : '' }}">
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <input type="text" inputmode="decimal" class="editable-input" style="{{ $loop->last ? 'color: #166534; font-weight: 600;' : '' }}" value="{{ $fecha_filtro == date('Y-m-d') ? $cb->usd_disp : '0.00' }}" {{ $fecha_filtro == date('Y-m-d') ? '' : 'readonly' }} data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_disp">
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
                            
                            @if(count($cuentasBancarias) > 0)
                            @php
                                $tot_bs_tc = $fecha_filtro == date('Y-m-d') ? $cuentasBancarias->sum('bs_tc') : 0;
                                $tot_bs_disp = $fecha_filtro == date('Y-m-d') ? $cuentasBancarias->sum('bs_disponibles') : 0;
                                $tot_usd_tc = $fecha_filtro == date('Y-m-d') ? $cuentasBancarias->sum('usd_tc') : 0;
                                $tot_usd_disp = $fecha_filtro == date('Y-m-d') ? $cuentasBancarias->sum('usd_disp') : 0;
                            @endphp
                            <tr class="summary-row" style="background-color: #f8fafc; font-weight: bold; border-top: 2px solid #e2e8f0;">
                                <td colspan="3" style="text-align: right; color: var(--blue);">TOTALES</td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <span id="sum_bs_tc">{{ number_format($tot_bs_tc, 2, '.', '') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">Bs.</span>
                                        <span id="sum_bs_disp">{{ number_format($tot_bs_disp, 2, '.', '') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <span id="sum_usd_tc">{{ number_format($tot_usd_tc, 2, '.', '') }}</span>
                                    </div>
                                </td>
                                <td style="color: #166534;">
                                    <div style="display: flex; align-items: center;">
                                        <span style="color: #94a3b8; font-size: 11px; margin-right: 4px;">$</span>
                                        <span id="sum_usd_disp">{{ number_format($tot_usd_disp, 2, '.', '') }}</span>
                                    </div>
                                </td>
                            </tr>
                            @endif
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
                        <input type="text" inputmode="decimal" class="widget-block-value" style="text-align: center; background: #e2e8f0; border-color: #cbd5e1; padding: 8px; max-width: 150px; color: #64748b; cursor: not-allowed;"
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
    @endif

<!-- EGRESOS REALIZADOS -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 10px;">
        <h3 style="color: var(--blue); margin: 0;">EGRESOS REALIZADOS</h3>
        <form id="form-fechas-flujo" method="GET" action="{{ route('finanzas.flujo_caja') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Desde:</label>
            <input type="date" id="fecha_desde_input" name="fecha_desde" value="{{ $fecha_desde }}" style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;" onchange="this.form.submit()">
            
            <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Hasta:</label>
            <input type="date" id="fecha_hasta_input" name="fecha_hasta" value="{{ $fecha_hasta }}" style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;" onchange="this.form.submit()">
            
            <button type="button" onclick="descargarReporteBusqueda()" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 6px; padding: 6px 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px;">📥 Reporte</button>
        </form>
    </div>

    {{-- BARRA DE BÚSQUEDA / FILTROS --}}
    <div id="barra-filtros-egresos" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <input id="filtro-texto" type="text" placeholder="🔍 Buscar motivo o tipo de gasto..." oninput="aplicarFiltros()" style="flex:1; min-width:180px; padding:7px 12px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; outline:none;">
        <input id="filtro-banco" type="text" placeholder="🏦 Buscar banco..." oninput="aplicarFiltros()" style="flex:1; min-width:120px; padding:7px 12px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; outline:none;">
        <input id="filtro-beneficiario" type="text" placeholder="👤 Buscar beneficiario..." oninput="aplicarFiltros()" style="flex:1; min-width:120px; padding:7px 12px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; outline:none;">
        <select id="filtro-cat" onchange="aplicarFiltros()" style="flex:1; min-width:160px; padding:7px 10px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; background:#f8fafc;">
            <option value="">🏷 Todos los tipos</option>
            <option value="egresos">Egresos Realizados</option>
            <option value="otros">Otros Egresos</option>
            <option value="traslados">Traslados</option>
        </select>
        <button type="button" onclick="limpiarFiltros()" style="padding:7px 14px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:7px; font-size:0.85rem; cursor:pointer; font-weight:600;">Limpiar</button>
        <span id="filtro-contador" style="font-size:0.8rem; color:#64748b; margin-left:4px;"></span>
    </div>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th>Origen ➔ Destino (Beneficiario)</th>
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
                        <tr data-egreso-cat="egreso_realizado">
                            <td>{{ $mov->fecha }}</td>
                            <td>
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <div>
                                        <strong style="color: var(--blue);">{{ $mov->banco }}</strong><br>
                                        <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                    </div>
                                    @if($mov->banco_receptor || $mov->titular_receptor)
                                    <div style="color: #94a3b8; font-size: 1.2rem;">➔</div>
                                    <div>
                                        <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                                        <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular_receptor }}</span>
                                    </div>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                            <td>
                                {{ $mov->motivo ?: '-' }}
                                @if($mov->desglose)
                                    <div style="margin-top: 5px;">
                                        <button type="button" onclick='verDesglose(@json($mov->desglose))' style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; cursor: pointer;">
                                            Ver Desglose ({{ count($mov->desglose) }})
                                        </button>
                                        <span style="display: none;">
                                            @foreach($mov->desglose as $item)
                                                {{ $item['cedula'] ?? '' }}
                                            @endforeach
                                        </span>
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
                                {{-- Editar / Ver --}}
                                @if(auth()->user()->isAuditor())
                                <button type="button" onclick='abrirVerEgreso(@json($mov))'
                                    title="Ver detalle"
                                    style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">👁️</button>
                                @else
                                <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                    title="Editar egreso"
                                    style="background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                                @endif
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
                        <td colspan="4" style="text-align: right; color: var(--blue);">
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
                        <td></td>
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
                        <th>Origen ➔ Destino (Beneficiario)</th>
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
                    @forelse($otros_egresos as $mov)
                        <tr data-egreso-cat="otros_egresos">
                            <td>{{ $mov->fecha }}</td>
                            <td>
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <div>
                                        <strong style="color: var(--blue);">{{ $mov->banco }}</strong><br>
                                        <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                    </div>
                                    @if($mov->banco_receptor || $mov->titular_receptor)
                                    <div style="color: #94a3b8; font-size: 1.2rem;">➔</div>
                                    <div>
                                        <strong style="color: #10b981;">{{ $mov->banco_receptor }}</strong><br>
                                        <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular_receptor }}</span>
                                    </div>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                            <td>{{ $mov->motivo ?: '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">{{ $mov->tasa_cambio ? number_format($mov->tasa_cambio, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; color: var(--danger);">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
                            <td class="col-number" style="text-align: right;">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
                            <td style="text-align: center; white-space: nowrap;">
                                @php
                                    $allComprobantes = array_filter(array_merge(
                                        $mov->comprobantes ?? [],
                                        ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
                                    ));
                                @endphp
                                @if(count($allComprobantes) > 0)
                                    <button type="button" onclick='abrirGaleria(@json(array_values($allComprobantes)))'
                                        title="Ver comprobantes ({{ count($allComprobantes) }})"
                                        style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer; margin-right: 2px;">📎 {{ count($allComprobantes) }}</button>
                                @endif
                                @if(auth()->user()->isAuditor())
                                <button type="button" onclick='abrirVerEgreso(@json($mov))'
                                    title="Ver detalle"
                                    style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">👁️</button>
                                @else
                                <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                    title="Editar egreso"
                                    style="background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px; color: var(--muted);">No hay otros egresos registrados.</td>
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
                        <td colspan="4" style="text-align: right; color: var(--blue);">
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
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

    <!-- EGRESOS EN DIVISAS (no aparece en reportes) -->
    <div class="dashboard-container" style="margin-top: 10px;">
        <h3 style="margin-bottom: 15px; color: #166534; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20m-7-7h14m-14-6h14"/></svg>
            EGRESOS EN DIVISAS
            <span style="font-size: 0.75rem; font-weight: 400; background: #dcfce7; color: #166534; padding: 2px 10px; border-radius: 20px; margin-left: 6px;">No incluidos en el reporte</span>
        </h3>
        <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1.5px solid #dcfce7;">
            <div class="table-wrap">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr style="background: #f0fdf4;">
                            <th style="width: 100px;">Fecha</th>
                            <th>Banco y Titular</th>
                            <th>Tipo Gasto</th>
                            <th>Motivo</th>
                            <th class="col-number" style="text-align: right;">Monto USD</th>
                            <th style="text-align: center; width: 80px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($egresos_divisas as $mov)
                            <tr data-egreso-cat="egreso_divisas">
                                <td>{{ $mov->fecha }}</td>
                                <td>
                                    <strong style="color: #166534;">{{ $mov->banco }}</strong><br>
                                    <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                </td>
                                <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                                <td>{{ $mov->motivo ?: '-' }}</td>
                                <td class="col-number" style="text-align: right; font-weight: 500;">
                                    {{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    @php
                                        $allComprobantesDiv = array_filter(array_merge(
                                            $mov->comprobantes ?? [],
                                            ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
                                        ));
                                    @endphp
                                    @if(count($allComprobantesDiv) > 0)
                                        <button type="button" onclick='abrirGaleria(@json(array_values($allComprobantesDiv)))'
                                            title="Ver comprobantes ({{ count($allComprobantesDiv) }})"
                                            style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer; margin-right: 2px;">📎 {{ count($allComprobantesDiv) }}</button>
                                    @endif
                                    @if(auth()->user()->isAuditor())
                                    <button type="button" onclick='abrirVerEgreso(@json($mov))'
                                        title="Ver detalle"
                                        style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">👁️</button>
                                    @else
                                    <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                        title="Editar egreso"
                                        style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);">No hay egresos en divisas registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        @php
                            $tot_divisas_usd = $egresos_divisas->sum('monto_usd');
                        @endphp
                        <tr style="background-color: #f0fdf4; border-top: 2px solid #dcfce7; font-weight: bold;">
                            <td colspan="4" style="text-align: right; color: #166534;">TOTAL EGRESOS EN DIVISAS</td>
                            <td class="col-number" style="text-align: right; color: #166534;">
                                $ {{ number_format($tot_divisas_usd, 2) }}
                            </td>
                            <td></td>
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
                        <th class="col-number" style="text-align: right;">Comisión</th>
                        <th class="col-number" style="text-align: right;">Monto USD</th>
                        <th class="col-number" style="text-align: right;">Monto BS</th>
                        <th style="text-align: center; width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($traslados as $mov)
                        <tr data-egreso-cat="traslados">
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
                            @php
                                $trasUsd = $mov->monto_usd > 0
                                    ? $mov->monto_usd
                                    : ($mov->monto_bs > 0 && $resumen->tasa_bcv_usd > 0 ? round($mov->monto_bs / $resumen->tasa_bcv_usd, 2) : null);
                            @endphp
                            <td class="col-number" style="text-align: right;">
                                {{ $mov->comision ? number_format($mov->comision, 2) : '-' }}
                            </td>
                            <td class="col-number" style="text-align: right; font-weight: 500;">
                                {{ $trasUsd ? '$'.number_format($trasUsd, 2) : '-' }}
                            </td>
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
                                @if(auth()->user()->isAuditor())
                                <button type="button" onclick='abrirVerEgreso(@json($mov))'
                                    title="Ver detalle"
                                    style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">👁️</button>
                                @else
                                <button type="button" onclick='abrirEditarEgreso(@json($mov))'
                                    title="Editar traslado"
                                    style="background: #ede9fe; color: #7c3aed; border: 1px solid #ddd6fe; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--muted);">No hay traslados registrados para este día.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @php
                        $tot_traslados_bs  = $traslados->sum('monto_bs');
                        $tot_traslados_com = $traslados->sum('comision');
                        $tasa_t = $resumen->tasa_bcv_usd > 0 ? $resumen->tasa_bcv_usd : 1;
                        $tot_traslados_usd = $traslados->sum(fn($t) => $t->monto_usd > 0 ? $t->monto_usd : ($t->monto_bs / $tasa_t));
                        
                        $tot_traslados_com_usd = $traslados->sum(function($t) use ($tasa_t) {
                            if ($t->comision > 0) {
                                if ($t->monto_usd > 0 && empty($t->monto_bs)) {
                                    return $t->comision;
                                }
                                return $t->comision / $tasa_t;
                            }
                            return 0;
                        });
                    @endphp
                    <tr style="background-color: #faf5ff; border-top: 2px solid #ede9fe; font-weight: bold;">
                        <td colspan="4" style="text-align: right; color: #7c3aed;">TOTAL TRASLADADO</td>
                        <td class="col-number" style="text-align: right; color: #7c3aed;">
                            {{ $tot_traslados_com > 0 ? number_format($tot_traslados_com, 2) : '-' }}
                            @if($tot_traslados_com_usd > 0)
                                <br><span style="font-size: 0.8rem; color: #10b981;">$ {{ number_format($tot_traslados_com_usd, 2) }}</span>
                            @endif
                        </td>
                        <td class="col-number" style="text-align: right; color: #7c3aed;">
                            $ {{ number_format($tot_traslados_usd, 2) }}
                        </td>
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
                        <option value="egreso_divisas">EGRESOS EN DIVISAS</option>
                        <option value="otros_egresos">OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                        <option value="traslados">TRASLADOS</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Fecha</label>
                    <input type="date" name="fecha" value="{{ $fecha_filtro }}" max="{{ date('Y-m-d') }}" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 2;">
                    <label id="lbl_banco_titular" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco y Titular</label>
                    <select id="banco_titular" name="banco_titular" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                        <option value="">-- Seleccione --</option>
                        @php $cuentasAgrupadas = collect($cuentas)->groupBy('categoria'); @endphp
                        @foreach($cuentasAgrupadas as $categoria => $listaCuentas)
                            <optgroup label="{{ $categoria }}">
                                @foreach($listaCuentas as $cuenta)
                                    <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                        {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                                    </option>
                                @endforeach
                            </optgroup>
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
                    <select name="banco_titular_receptor" id="banco_titular_receptor" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                        <option value="">-- Seleccione Receptor --</option>
                        @foreach($cuentasAgrupadas as $categoria => $listaCuentas)
                            <optgroup label="{{ $categoria }}">
                                @foreach($listaCuentas as $cuenta)
                                    <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                        {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;" id="col_monto_usd">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto USD</label>
                    <input type="text" inputmode="decimal" name="monto_usd" id="monto_usd" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="col_tasa_cambio">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tasa de Cambio</label>
                    <input type="text" inputmode="decimal" name="tasa_cambio" id="tasa_cambio" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="col_monto_bs">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;" id="lbl_monto_bs">Monto BS</label>
                    <input type="text" inputmode="decimal" name="monto_bs" id="monto_bs" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            <div id="row_diferencial" style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Dif. Cambiario</label>
                    <input type="text" inputmode="decimal" name="diferencial_cambiario" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comisión</label>
                    <input type="text" inputmode="decimal" name="comision" id="comision" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
            </div>

            {{-- Fila exclusiva para TRASLADOS: Comisión + Monto USD calculado --}}
            <div id="row_traslado_extra" style="display: none; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comisión (Bs)</label>
                    <input type="text" inputmode="decimal" name="comision" id="comision_traslado"
                        oninput="calcTraslado()"
                        placeholder="0,00"
                        disabled
                        style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto USD <small style="color:#64748b;">(calculado)</small></label>
                    <input type="text" inputmode="decimal" name="monto_usd" id="monto_usd_traslado"
                        readonly
                        placeholder="Se calcula con la tasa del día"
                        style="width: 100%; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px; background:#f8fafc; color:#475569; cursor:default;">
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
                        @php
                            $sedesFinanzas = array_merge(config('inventario.sedes_locales', []), ['Nunes', 'Movistar', 'Depósito', 'Admon', 'Bella vista', 'Jenus']);
                        @endphp
                        @foreach($sedesFinanzas as $sedeLocal)
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
                <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Beneficiario</label>
                <select id="beneficiario" name="beneficiario" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <option value="">-- Seleccione un beneficiario --</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov }}">{{ $prov }}</option>
                    @endforeach
                </select>
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

            {{-- ── VINCULAR CON GASTO FIJO ── --}}
            <div style="margin-bottom: 10px; border-top: 1px dashed #bfdbfe; padding-top: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 0.95rem; color: #1e40af;">
                    <input type="checkbox" id="chk_gasto_fijo" onchange="toggleGastoFijoPanel()" style="width: 16px; height: 16px; accent-color: #3b82f6;">
                    <svg width="16" height="16" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
                    Este egreso es pago de un Gasto Fijo
                </label>
            </div>

            <div id="panel_gasto_fijo" style="display:none; background: linear-gradient(135deg,#eff6ff,#dbeafe); border: 1.5px solid #93c5fd; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;">
                <div style="margin-bottom: 10px;">
                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:5px;">
                        Gasto Fijo Pendiente
                    </label>
                    <select id="sel_gasto_fijo" onchange="onGastoFijoSelected()" style="width:100%; padding:8px 10px; border:1.5px solid #93c5fd; border-radius:7px; font-size:0.88rem; background:white; color:#0f172a;">
                        <option value="">-- Cargando gastos pendientes... --</option>
                    </select>
                </div>

                {{-- Info card del gasto seleccionado --}}
                <div id="gf_info_card" style="display:none; background:white; border-radius:8px; padding:12px 14px; border:1px solid #bfdbfe; margin-bottom:10px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; font-size:0.83rem;">
                        <div>
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Servicio / Tipo</span>
                            <span id="gf_info_servicio" style="font-weight:700; color:#0f172a;"></span>
                        </div>
                        <div>
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Empresa</span>
                            <span id="gf_info_empresa" style="font-weight:600; color:#334155;"></span>
                        </div>
                        <div id="gf_info_sede_row">
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Sede</span>
                            <span id="gf_info_sede" style="font-weight:600; color:#1d4ed8;"></span>
                        </div>
                        <div>
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Grupo</span>
                            <span id="gf_info_grupo" style="font-weight:600; color:#334155;"></span>
                        </div>
                        <div>
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Fecha pago</span>
                            <span id="gf_info_fecha" style="font-weight:600; color:#334155;"></span>
                        </div>
                        <div>
                            <span style="color:#64748b; font-weight:600; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.4px; display:block;">Costo estimado</span>
                            <span id="gf_info_costo" style="font-weight:800; color:#059669; font-size:1rem;"></span>
                        </div>
                    </div>
                </div>

                <div id="gf_monto_row" style="display:none;">
                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:4px;">
                        Monto real pagado (USD)
                    </label>
                    <input type="number" id="inp_monto_gf" step="0.01" min="0" placeholder="0.00"
                        style="width:100%; padding:8px 10px; border:1.5px solid #93c5fd; border-radius:7px; font-size:0.9rem; font-weight:700; color:#0f172a; box-sizing:border-box;"
                        oninput="syncGFMontoToForm()">
                    <p style="font-size:0.75rem; color:#3b82f6; margin:4px 0 0; font-style:italic;">
                        💡 Este monto se registrará en la tabla de Gastos Fijos del mes actual.
                    </p>
                </div>
            </div>
            {{-- Hidden fields enviados con el form --}}
            <input type="hidden" id="hid_gasto_fijo_id" name="gasto_fijo_id" value="">
            <input type="hidden" id="hid_monto_pagado_gf" name="monto_pagado_gf" value="">

            <div id="container_desglose" style="display: none; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 15px;">
                <h4 style="margin-top: 0; font-size: 0.95rem; color: var(--blue); display: flex; justify-content: space-between; align-items: center;">
                    Desglose del Pago
                    <div style="font-size: 0.85rem; font-weight: normal;">
                        <input type="file" id="archivo_desglose" accept=".xlsx, .xls, .csv, .xlsm, .txt" style="display: none;" onchange="cargarArchivoDesglose(this)">
                        <button type="button" onclick="document.getElementById('archivo_desglose').click()" style="padding: 4px 10px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            Cargar archivo (Excel/TXT)
                        </button>
                    </div>
                </h4>
                <div id="lista_desglose">
                    <!-- Filas de desglose se generarán en JS -->
                </div>
                <button type="button" onclick="agregarDesglose()" style="margin-top: 5px; padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+ Añadir persona</button>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem;">
                    Comprobantes de Pago
                    <span id="comp-counter" style="font-size:0.78rem; color:#64748b; margin-left:6px;">0 / 6</span>
                </label>
                {{-- Multi-image preview grid --}}
                <div id="comp-grid" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;"></div>
                {{-- Paste/click area --}}
                <div id="paste-area" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 14px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s;"
                    onmouseover="if(!pasteAreaDisabled())this.style.borderColor='#3b82f6';"
                    onmouseout="if(!pasteAreaDisabled())this.style.borderColor='#cbd5e1';">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <span id="paste-text" style="display: block; color: #64748b; font-size: 13px;">Haz clic o pega (<b>Ctrl+V</b>) para añadir imagen &bull; máx. 6</span>
                </div>
                <input type="file" name="comprobantes[]" id="comprobante-input" accept="image/*,application/pdf" multiple style="display: none;">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .swal2-container {
        z-index: 9999 !important;
    }
</style>

<script>
function descargarReporteBusqueda() {
    Swal.fire({
        title: 'Selecciona las tablas',
        html: `
            <div style="text-align: left; margin: 15px auto; width: fit-content; display: flex; flex-direction: column; gap: 10px;">
                <label style="cursor: pointer;"><input type="checkbox" id="rep_egresos" value="egreso_realizado" checked style="margin-right: 8px;"> Egresos Realizados</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_otros" value="otros_egresos" checked style="margin-right: 8px;"> Otros Egresos (Avances y Cambios)</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_traslados" value="traslados" checked style="margin-right: 8px;"> Traslados</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_divisas" value="egreso_divisas" checked style="margin-right: 8px;"> Egresos Divisas</label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Generar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            let selected = [];
            if(document.getElementById('rep_egresos').checked) selected.push('egreso_realizado');
            if(document.getElementById('rep_otros').checked) selected.push('otros_egresos');
            if(document.getElementById('rep_traslados').checked) selected.push('traslados');
            if(document.getElementById('rep_divisas').checked) selected.push('egreso_divisas');
            return selected;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const fDesde = document.getElementById('fecha_desde_input')?.value || '';
            const fHasta = document.getElementById('fecha_hasta_input')?.value || '';
            const txt = document.getElementById('filtro-texto')?.value || '';
            
            let selectedCats = result.value;
            if (selectedCats.length === 0) {
                Swal.fire('Atención', 'Debes seleccionar al menos una tabla', 'warning');
                return;
            }

            let url = '{{ route("finanzas.flujo_caja.reporte") }}?desde=' + encodeURIComponent(fDesde) + '&hasta=' + encodeURIComponent(fHasta);
            if(txt) url += '&q=' + encodeURIComponent(txt);
            url += '&cats=' + encodeURIComponent(selectedCats.join(','));
            
            window.open(url, '_blank');
        }
    });
}
function calcTraslado() {
    const bs = window.parseLocalNumber(document.getElementById('monto_bs')?.value) || 0;
    const bcvInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
    const tasa = window.parseLocalNumber(bcvInput?.value) || 0;
    const usdEl = document.getElementById('monto_usd_traslado');
    if (!usdEl) return;
    if (tasa > 0 && bs > 0) {
        usdEl.value = (bs / tasa).toFixed(2).replace('.', ',');
    } else {
        usdEl.value = '';
    }
}

function toggleTraslados() {
    const val = document.getElementById('categoria_egreso').value;
    const isTraslado = val === 'traslados';
    const isDivisas = val === 'egreso_divisas';
    
    document.getElementById('row_receptor').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor').required = isTraslado;
    
    document.getElementById('lbl_banco_titular').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs').innerText = isTraslado ? 'Monto BS' : 'Monto BS';
    
    document.getElementById('col_monto_usd').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio').style.display = (isTraslado || isDivisas) ? 'none' : 'block';
    document.getElementById('col_monto_bs').style.display = isDivisas ? 'none' : 'block';
    
    document.getElementById('row_diferencial').style.display = (isTraslado || isDivisas) ? 'none' : 'flex';
    document.getElementById('row_tipo_gasto').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('row_traslado_extra').style.display = isTraslado ? 'flex' : 'none';
    
    // Disable fields that would create duplicate POST keys
    const comisionNormal = document.getElementById('comision');
    const comisionTraslado = document.getElementById('comision_traslado');
    const usdNormal = document.getElementById('monto_usd');
    const usdTraslado = document.getElementById('monto_usd_traslado');
    if (comisionNormal) comisionNormal.disabled = (isTraslado || isDivisas);
    if (comisionTraslado) comisionTraslado.disabled = !isTraslado;
    if (usdNormal) usdNormal.disabled = isTraslado;
    if (usdTraslado) usdTraslado.disabled = !isTraslado;
    
    const bsNormal = document.getElementById('monto_bs');
    if (bsNormal) bsNormal.disabled = isDivisas;
    
    // When switching to traslado mode, auto-calc USD if monto_bs already has value
    if (isTraslado) calcTraslado();
    
    document.getElementById('tipo_gasto').required = !isTraslado;
}

// ── Gasto Fijo Linking Functions ──
let _gfPendientes = null; // cached list

async function toggleGastoFijoPanel() {
    const chk = document.getElementById('chk_gasto_fijo');
    const panel = document.getElementById('panel_gasto_fijo');
    const hidId = document.getElementById('hid_gasto_fijo_id');
    const hidMonto = document.getElementById('hid_monto_pagado_gf');

    if (!chk.checked) {
        panel.style.display = 'none';
        hidId.value = '';
        hidMonto.value = '';
        document.getElementById('gf_info_card').style.display = 'none';
        document.getElementById('gf_monto_row').style.display = 'none';
        return;
    }

    panel.style.display = 'block';

    // Lazy-load the list only once
    if (!_gfPendientes) {
        const sel = document.getElementById('sel_gasto_fijo');
        sel.innerHTML = '<option value="">Cargando...</option>';
        try {
            const res = await fetch('{{ \Illuminate\Support\Facades\Route::has("finanzas.gastos_fijos.pendientes") ? route("finanzas.gastos_fijos.pendientes") : url("/finanzas/gastos-fijos/pendientes") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            _gfPendientes = await res.json();
        } catch (e) {
            _gfPendientes = [];
        }
        buildGastoFijoSelect();
    } else {
        buildGastoFijoSelect();
    }
}

function buildGastoFijoSelect() {
    const sel = document.getElementById('sel_gasto_fijo');
    sel.innerHTML = '<option value="">-- Selecciona un gasto fijo --</option>';
    if (!_gfPendientes || _gfPendientes.length === 0) {
        sel.innerHTML = '<option value="">Sin gastos pendientes esta semana</option>';
        return;
    }
    _gfPendientes.forEach(gf => {
        const opt = document.createElement('option');
        opt.value = gf.id;
        const sedeStr = gf.sede ? ` · ${gf.sede.substring(0, 25)}` : '';
        const urgente = gf.urgente ? '⚡ ' : '';
        opt.textContent = `${urgente}${gf.servicio}${sedeStr} — $${parseFloat(gf.costo).toFixed(2)}`;
        sel.appendChild(opt);
    });
}

function onGastoFijoSelected() {
    const sel = document.getElementById('sel_gasto_fijo');
    const id = parseInt(sel.value);
    const hidId = document.getElementById('hid_gasto_fijo_id');
    const card = document.getElementById('gf_info_card');
    const montoRow = document.getElementById('gf_monto_row');

    if (!id || !_gfPendientes) {
        card.style.display = 'none';
        montoRow.style.display = 'none';
        hidId.value = '';
        return;
    }

    const gf = _gfPendientes.find(g => g.id === id);
    if (!gf) return;

    hidId.value = gf.id;

    document.getElementById('gf_info_servicio').textContent = gf.servicio;
    document.getElementById('gf_info_empresa').textContent  = gf.empresa || '—';
    document.getElementById('gf_info_grupo').textContent    = gf.tabla_label;
    document.getElementById('gf_info_fecha').textContent    = gf.fecha;
    document.getElementById('gf_info_costo').textContent    = `$ ${parseFloat(gf.costo).toFixed(2)}`;

    const sedeRow = document.getElementById('gf_info_sede_row');
    if (gf.sede) {
        document.getElementById('gf_info_sede').textContent = gf.sede;
        sedeRow.style.display = 'block';
    } else {
        sedeRow.style.display = 'none';
    }

    // Pre-fill monto real with costo estimado
    const montoInp = document.getElementById('inp_monto_gf');
    montoInp.value = parseFloat(gf.costo).toFixed(2);
    document.getElementById('hid_monto_pagado_gf').value = montoInp.value;

    card.style.display = 'block';
    montoRow.style.display = 'block';
}

function syncGFMontoToForm() {
    const val = document.getElementById('inp_monto_gf').value;
    document.getElementById('hid_monto_pagado_gf').value = val;
}


document.addEventListener('DOMContentLoaded', function() {

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

    const tsBankSettings = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: '-- Seleccione --',
        maxOptions: null
    };
    if (document.getElementById('banco_titular')) window.tsBancoTitular = new TomSelect("#banco_titular", tsBankSettings);
    if (document.getElementById('banco_titular_receptor')) window.tsBancoTitularReceptor = new TomSelect("#banco_titular_receptor", tsBankSettings);
    if (document.getElementById('beneficiario')) window.tsBeneficiario = new TomSelect("#beneficiario", tsBankSettings);

    // Modal functions
    window.openNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };
    window.closeNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'none';
        // Reset multi-comprobante
        multiFiles = [];
        renderCompGrid();
    };

    // ===== MULTI-COMPROBANTE (máx 6) =====
    const MAX_COMP = 6;
    let multiFiles = []; // array of File objects

    window.pasteAreaDisabled = function() {
        return multiFiles.length >= MAX_COMP;
    };

    function renderCompGrid() {
        const grid = document.getElementById('comp-grid');
        const counter = document.getElementById('comp-counter');
        const pasteArea = document.getElementById('paste-area');
        grid.innerHTML = '';
        multiFiles.forEach((f, i) => {
            const wrap = document.createElement('div');
            wrap.style = 'position:relative; width:80px; height:80px;';
            const img = document.createElement('img');
            img.style = 'width:80px; height:80px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1;';
            if (f.type.startsWith('image/')) {
                const url = URL.createObjectURL(f);
                img.src = url;
            } else {
                img.src = '';
                img.alt = '📄';
                img.style.background = '#f1f5f9';
                wrap.title = f.name;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.innerHTML = '&times;';
            btn.style = 'position:absolute; top:-6px; right:-6px; width:20px; height:20px; background:#ef4444; color:#fff; border:none; border-radius:50%; cursor:pointer; font-size:13px; line-height:1; display:flex; align-items:center; justify-content:center;';
            btn.onclick = () => { multiFiles.splice(i, 1); syncFileInput(); renderCompGrid(); };
            wrap.appendChild(img);
            wrap.appendChild(btn);
            grid.appendChild(wrap);
        });
        counter.textContent = `${multiFiles.length} / ${MAX_COMP}`;
        const full = multiFiles.length >= MAX_COMP;
        pasteArea.style.opacity = full ? '0.45' : '1';
        pasteArea.style.pointerEvents = full ? 'none' : 'auto';
        document.getElementById('paste-text').textContent = full ? `Límite de ${MAX_COMP} soportes alcanzado` : 'Haz clic o pega (Ctrl+V) para añadir imagen • máx. 6';
    }

    function addFilesToMulti(files) {
        const remaining = MAX_COMP - multiFiles.length;
        const toAdd = Array.from(files).slice(0, remaining);
        toAdd.forEach(f => multiFiles.push(f));
        syncFileInput();
        renderCompGrid();
    }

    function syncFileInput() {
        const dt = new DataTransfer();
        multiFiles.forEach(f => dt.items.add(f));
        document.getElementById('comprobante-input').files = dt.files;
    }

    const pasteArea = document.getElementById('paste-area');
    const fileInput = document.getElementById('comprobante-input');

    pasteArea.addEventListener('click', () => {
        if (!pasteAreaDisabled()) fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) addFilesToMulti(this.files);
    });

    document.getElementById('nuevoEgresoModal').addEventListener('paste', (e) => {
        const items = (e.clipboardData || e.originalEvent.clipboardData)?.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const file = items[i].getAsFile();
                if (file) { addFilesToMulti([file]); break; }
            }
        }
    });

    renderCompGrid(); // init counter

    // Calculadora Egreso
    const usdInput = document.getElementById('monto_usd');
    const tasaInput = document.getElementById('tasa_cambio');
    const bsInput = document.getElementById('monto_bs');
    const difInput = document.querySelector('input[name="diferencial_cambiario"]');

    let lastEditedAmount = 'usd';

    function calcular() {
        const usd = window.parseLocalNumber(usdInput.value) || 0;
        const bs = window.parseLocalNumber(bsInput.value) || 0;
        const tasa = window.parseLocalNumber(tasaInput.value) || 0;
        
        if (tasa > 0) {
            if (lastEditedAmount === 'usd') {
                bsInput.value = (usd * tasa).toFixed(2).replace(".", ",");
            } else if (lastEditedAmount === 'bs') {
                usdInput.value = (bs / tasa).toFixed(2).replace(".", ",");
            }
        }
        
        const bcvTasaInput = document.querySelector('input[data-field="tasa_bcv_usd"]');
        if (difInput && bcvTasaInput) {
            const bcv = window.parseLocalNumber(bcvTasaInput.value) || 1;
            const finalUsd = window.parseLocalNumber(usdInput.value) || 0;
            const finalBs = window.parseLocalNumber(bsInput.value) || 0;
            if (bcv > 0) {
                difInput.value = (((finalUsd * bcv) - finalBs) / bcv).toFixed(2).replace(".", ",");
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

    // Auto-calcular USD del traslado cuando cambia monto_bs
    bsInput.addEventListener('input', function() {
        const cat = document.getElementById('categoria_egreso')?.value;
        if (cat === 'traslados') calcTraslado();
    });

    // AJAX Guardado en Vivo
    const editables = document.querySelectorAll('.editable-input');
    
    function updateSums() {
        let bsTc = 0, bsDisp = 0, usdTc = 0, usdDisp = 0;
        document.querySelectorAll('input[data-field="bs_tc"]').forEach(i => bsTc += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(i => bsDisp += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="usd_tc"]').forEach(i => usdTc += window.parseLocalNumber(i.value)||0);
        document.querySelectorAll('input[data-field="usd_disp"]').forEach(i => usdDisp += window.parseLocalNumber(i.value)||0);
        
        const sumBsTc = document.getElementById('sum_bs_tc');
        if(sumBsTc) sumBsTc.textContent = bsTc.toFixed(2).replace(".", ",");
        
        const sumBsDisp = document.getElementById('sum_bs_disp');
        if(sumBsDisp) sumBsDisp.textContent = bsDisp.toFixed(2).replace(".", ",");
        
        const sumUsdTc = document.getElementById('sum_usd_tc');
        if(sumUsdTc) sumUsdTc.textContent = usdTc.toFixed(2).replace(".", ",");
        
        const sumUsdDisp = document.getElementById('sum_usd_disp');
        if(sumUsdDisp) sumUsdDisp.textContent = usdDisp.toFixed(2).replace(".", ",");
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
                    const bsDisp = window.parseLocalNumber(this.value) || 0;
                    const tasa = window.parseLocalNumber(tasaBcvInput.value) || 1;
                    const usdDisp = bsDisp / tasa;
                    usdDispInput.value = usdDisp.toFixed(2).replace(".", ",");
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
                    const bsTc = window.parseLocalNumber(this.value) || 0;
                    const tasa = window.parseLocalNumber(tasaBcvInput.value) || 1;
                    const usdTc = bsTc / tasa;
                    usdTcInput.value = usdTc.toFixed(2).replace(".", ",");
                    usdTcInput.dispatchEvent(new Event('change'));
                }
            }

            // Auto calc all USD if TASA BCV changed
            if (this.getAttribute('data-field') === 'tasa_bcv_usd') {
                const tasa = window.parseLocalNumber(this.value) || 1;
                document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdDispInput = tr.querySelector('input[data-field="usd_disp"]');
                    if (usdDispInput) {
                        usdDispInput.value = (window.parseLocalNumber(bsInput.value || 0) / tasa).toFixed(2).replace(".", ",");
                        usdDispInput.dispatchEvent(new Event('change'));
                    }
                });
                document.querySelectorAll('input[data-field="bs_tc"]').forEach(bsInput => {
                    const tr = bsInput.closest('tr');
                    const usdTcInput = tr.querySelector('input[data-field="usd_tc"]');
                    if (usdTcInput) {
                        usdTcInput.value = (window.parseLocalNumber(bsInput.value || 0) / tasa).toFixed(2).replace(".", ",");
                        usdTcInput.dispatchEvent(new Event('change'));
                    }
                });
            }

            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const field = this.getAttribute('data-field');
            // Enviar siempre el valor numérico limpio (sin puntos de miles ni coma decimal)
            const value = window.parseLocalNumber(this.value);
            
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
        const montoTotal = window.parseLocalNumber(montoBsInput.value) || 0;
        
        const montosDesglose = document.querySelectorAll('input[name="desglose_monto[]"]');
        let sumaDesglose = 0;
        montosDesglose.forEach(input => {
            sumaDesglose += window.parseLocalNumber(input.value) || 0;
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
    
    if (chk.checked && document.getElementById('lista_desglose').children.length === 0) {
        agregarDesglose();
    }
}

const proveedoresList = @json($proveedores);
// Variables para Desglose
const sedesList = @json($sedesFinanzas);

function buildOptions(list, selectedValue) {
    let options = '<option value="">-- Seleccione --</option>';
    list.forEach(item => {
        options += `<option value="${item}" ${item === selectedValue ? 'selected' : ''}>${item}</option>`;
    });
    return options;
}

const tiposGastoList = [
    "001 - COMPRA DE MERCANCIA", "002 - IMPUESTO MUNICIPAL (ALCALDIAS)", "003 - ALQUILERES", 
    "004 - AYUDA FAMILIAR", "005 - COLABORACIONES", "006 - DONACIONES (LEGALES)", "007 - INSUMOS ARREGLOS",
    "008 - CONSUMIBLES TIENDA", "009 - ARTICULOS DE PAPELERIA", "010 - CONDOMINIO TIENDAS",
    "011 - ENVIOS Y ENCOMIENDAS", "012 - GASTOS NOTARIA/LEGALES", "013 - GASTOS NOTARIA/LEGALES (EMPEÑO)",
    "014 - GASTOS INTERNET TIENDAS", "0141 - GASTOS TELEFONICOS", "015 - MANT. Y REP. VEHICULOS TIENDAS",
    "016 - MANT. Y REP. VEHICULOS DIRECTIVO", "017 - MANT. Y REP. VEHICULOS EMPEÑOS", "018 - FLETE COMPRA MERCANCIA",
    "019 - IMASEO TIENDA", "020 - IMPUESTOS Y TASAS ( SENIAT)", "021 - INCES", "022 - FAOV", "023 - IVSS",
    "024 - AGAZAGOS PERSONAL", "025 - UTILIDADES PERSONAL", "026 - VACACIONES PERSONAL", "027 - NOMINA LEGAL",
    "028 - NOMINA ESPECIAL", "029 - PRESTACIONES SOCIALES", "030 - UNIFORME PERSONAL", "031 - ARTICULOS DE LIMPIEZA",
    "032 - COMBUSTIBLE PLANTAS ELECTRICAS", "033 - COMBUSTIBLE VEHICULOS TIENDAS",
    "034 - COMBUSTIBLE VEHICULOS DIRECTIVO", "035 - MANT. Y REP. LOCALES TIENDA",
    "036 - MANT. Y REP. LOCALES/CASAS EMPEÑO", "037 - CONDOMINIOS EMPEÑOS", "038 - CONDOMINIOS DIRECTIVO",
    "039 - GASTOS INTERNET DIRECTIVO", "040 - SUELDOS Y SALARIO DIRECTIVO", "041 - SUELDO Y SALARIO MARIA NUÑEZ",
    "042 - GASTOS CAFETIN", "043 - CORPOELEC TIENDAS", "044 - HIDROFALCON TIENDAS",
    "045 - GASTOS AGUA CISTERNA", "046 - REPUESTOS VEHICULOS TIENDA", "047 - REPUESTOS VEHICULOS DIRECTIVO",
    "048 - REPUESTOS VEHICULOS EMPEÑO", "049 - INSUMOS MANTENIMIENTO", "050 - GASTOS BOUTIQUIN MEDICINAS",
    "051 - ACTIVOS (EQUIPOS-MOBILIARIOS)", "052 - GASTOS SISTEMA INTEGRA/UNIX",
    "053 - GASTOS ASESORIAS EXTERNAS", "054 - GASTOS PERMISOLOGIAS", "055 - PUBLICIDAD",
    "056 - SERVICIO COULD PAGO", "057 - REPUESTOS TELEFONIA (CAJA CHICA)", "058 - SERVICIO TECNICO (GARANTIAS)",
    "059 - COMISIONES EMPEÑO", "060 - SUSCRIPCIONES", "061 - MANTENIMIENTO MOBILIARIOS Y EQUIPOS",
    "062 - SERVICIO PAGINA WEB", "063 - GASTOS SISTEMA PREMIUM", "064 - SERVICIO INTEGRA",
    "065 - FONACIT", "066 - CURSOS Y ADIESTRAMIENTOS", "067 - INGRESO VENTAS",
    "068 - CXC GRUPO JENU (AVANCES)", "069 - CXC/CXP DIRECTIVO JOSE JEREZ", "070 - DESEMBOLSO NOMINA DIVISAS",
    "071 - DESEMBOLSO NOMINA BOLIVARES", "072 - ANTICIPO NOMINAS DIVISAS", "073 - ANTICIPO NOMINAS BOLIVARES",
    "074 - PRESTAMO PERSONAL BOLIVARES (TRANSF)", "075 - COMISION HONOR", "076 - EMPEÑOS",
    "077 - COMPRA DIVISAS BANCOS", "078 - DEPOSITO BANCARIO", "079 - COMISION EMPEÑO",
    "080 - INGRESO ALQUILERES", "081 - COMPRA DIVISAS TERCEROS", "082 - FONDO BOLIVARES",
    "083 - GASTOS MEDICOS EMPLEADOS", "084 - ABONO TIENDA EMPLEADOS", "085 - PEAJES", "086 - COMISION ZTE", 
    "087 - PAGO PROVEEDORES", "088 - INCENTIVOS PERSONAL", "089 - INSUMOS TIENDA", "090 - PROTECCION PRECIO HONOR",
    "091 - HONORARIOS PROFESIONALES", "092 - GASTOS DELIVERY", "093 - GASTOS DIRECTIVO",
    "094 - SALDO MOVISTAR", "095 - TASAS Y CONTRIBUCIONES", "096 - PRESTAMO PERSONAL DIVISAS",
    "097 - INSTALACIONES Y MEJORAS GALPON Y DEPOSITO", "098 - MEJORAS INSTALACIONES TIENDAS",
    "099 - DEVOLUCIONES CLIENTES"
];

function buildTipoGastoOptions(selectedValue) {
    let options = '<option value="">-- Tipo Gasto --</option>';
    tiposGastoList.forEach(item => {
        options += `<option value="${item}" ${item === selectedValue ? 'selected' : ''}>${item}</option>`;
    });
    return options;
}

window.cargarArchivoDesglose = async function(input) {
    if (!input.files || input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('_token', document.querySelector('input[name="_token"]').value);

    // native alert as fallback to ensure it fires
    // console.log("Procesando archivo...");
    Swal.fire({
        title: 'Procesando archivo',
        text: 'Leyendo datos y buscando clientes...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('{{ route("finanzas.parse_desglose") }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const res = await response.json();
        
        if (!res.ok) {
            Swal.fire('Error', res.error || 'Ocurrió un error al procesar el archivo', 'error');
            input.value = '';
            return;
        }

        const data = res.data;
        if (data.length === 0) {
            Swal.fire('Atención', 'No se encontraron registros válidos en el archivo', 'warning');
            input.value = '';
            return;
        }

        const inputForm = input.closest('form');
        let inputTasa = null;
        if (inputForm) {
            inputTasa = inputForm.querySelector('input[name="tasa_cambio"]') || inputForm.querySelector('#tasa_cambio');
        }
        if (!inputTasa) {
            inputTasa = document.getElementById('tasa_cambio') || document.querySelector('input[name="tasa_cambio"]');
        }
        
        let tasa = window.parseLocalNumber(inputTasa ? inputTasa.value : '0') || 0;
        if (tasa <= 0) {
            const bcvGlobal = document.getElementById('tasa-bcv-input') || document.querySelector('input[data-field="tasa_bcv_usd"]');
            tasa = window.parseLocalNumber(bcvGlobal ? bcvGlobal.value : '0') || 0;
        }

        const catEl = document.getElementById('categoria_egreso');
        const isDivisas = catEl && catEl.value === 'egreso_divisas';

        // Preguntar por sede, tipo de gasto, moneda y tasa
        const { value: formValues } = await Swal.fire({
            title: 'Configurar Archivo',
            html: `
                <p style="font-size: 14px; color: #475569; margin-bottom: 15px;">Se encontraron <b>${data.length}</b> registros. Configura los detalles:</p>
                <div style="text-align: left; display: flex; flex-direction: column; gap: 10px;">
                    <label>Sede (opcional)</label>
                    <select id="swal_sede" class="swal2-select" style="margin: 0; width: 100%; font-size: 14px; padding: 6px;">
                        <option value="">-- Ninguna --</option>
                        ${sedesList.map(s => `<option value="${s}">${s}</option>`).join('')}
                    </select>
                    
                    <label style="margin-top: 10px;">Tipo de Gasto (opcional)</label>
                    <select id="swal_tg" class="swal2-select" style="margin: 0; width: 100%; font-size: 14px; padding: 6px;">
                        <option value="">-- Ninguno --</option>
                        ${tiposGastoList.map(t => `<option value="${t}">${t}</option>`).join('')}
                    </select>
                    
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <div style="flex: 1;">
                            <label>Moneda del archivo</label>
                            <select id="swal_currency" class="swal2-select" style="margin: 0; width: 100%; font-size: 14px; padding: 6px;">
                                <option value="BS" ${!isDivisas ? 'selected' : ''}>Bolívares (Bs)</option>
                                <option value="USD" ${isDivisas ? 'selected' : ''}>Dólares (USD)</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label>Tasa de Cambio</label>
                            <input type="text" id="swal_tasa" class="swal2-input" placeholder="Ej. 41,50" value="${tasa > 0 ? tasa.toString().replace('.', ',') : ''}" style="margin: 0; width: 100%; font-size: 14px; padding: 6px; box-sizing: border-box;">
                        </div>
                    </div>
                    <small style="color: #64748b; font-size: 12px; margin-top: 5px;">* La tasa de cambio es necesaria para calcular el monto equivalente.</small>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Aplicar y añadir',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                return {
                    sede: document.getElementById('swal_sede').value,
                    tg: document.getElementById('swal_tg').value,
                    currency: document.getElementById('swal_currency').value,
                    tasaVal: document.getElementById('swal_tasa').value
                }
            }
        });

        if (formValues) {
            let tgSelected = formValues.tg === 'OTROS' ? '' : formValues.tg;
            
            tasa = window.parseLocalNumber(formValues.tasaVal) || 0;
            if (inputTasa && tasa > 0) {
                inputTasa.value = formValues.tasaVal;
            }

            data.forEach(row => {
                let montoUsd = '';
                let montoBsFormateado = '';
                let valNumerico = parseFloat(row.monto) || 0;

                if (formValues.currency === 'USD') {
                    // El archivo tiene montos en USD
                    montoUsd = valNumerico.toFixed(2).replace('.', ',');
                    if (tasa > 0) {
                        montoBsFormateado = (valNumerico * tasa).toFixed(2).replace('.', ',');
                    }
                } else {
                    // El archivo tiene montos en BS
                    montoBsFormateado = valNumerico.toFixed(2).replace('.', ',');
                    if (tasa > 0) {
                        montoUsd = (valNumerico / tasa).toFixed(2).replace('.', ',');
                    }
                }

                agregarDesglose(row.cedula, montoUsd, montoBsFormateado, formValues.sede, tgSelected);
            });
            
            Swal.fire('Éxito', `Se agregaron ${data.length} personas al desglose.`, 'success');
        }

    } catch (err) {
        console.error(err);
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
    
    // Limpiar input
    input.value = '';
}

function agregarDesglose(ced = '', usd = '', bs = '', sede = '', tipoGasto = '') {
    const lista = document.getElementById('lista_desglose');
    const sedeOptions = buildOptions(sedesList, sede);
    const tgOptions = buildTipoGastoOptions(tipoGasto);
    
    // Generar un ID único para los selects
    const uid = Date.now() + Math.floor(Math.random() * 1000);

    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; background: white; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
            <div style="flex: 1 1 100%; display: flex; gap: 8px;">
                <select id="sel_sede_${uid}" name="desglose_sede[]" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${sedeOptions}
                </select>
                <select id="sel_tg_${uid}" name="desglose_tipo_gasto[]" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${tgOptions}
                </select>
            </div>
            <div style="flex: 1 1 100%; display: flex; gap: 8px; margin-top: 4px; box-sizing: border-box;">
                <input type="text" name="desglose_cedula[]" placeholder="Cédula/RIF" value="${ced}" style="flex: 2 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto_usd[]" placeholder="Monto USD" value="${usd}" oninput="calcDesgloseRow(this, 'usd')" style="flex: 1 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto[]" placeholder="Monto Bs" value="${bs}" oninput="calcDesgloseRow(this, 'bs')" style="flex: 1 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" onclick="this.closest('.row-desglose').remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; box-sizing: border-box;">&times;</button>
            </div>
        </div>
    `;
    lista.insertAdjacentHTML('beforeend', html);
    
    // Inicializar TomSelect en los nuevos selects
    new TomSelect(`#sel_tg_${uid}`, { create: false, sortField: { field: "text", direction: "asc" }, placeholder: '-- Tipo Gasto --' });
}

function calcDesgloseRow(input, source) {
    const row = input.closest('.row-desglose');
    const inputBs = row.querySelector('input[name="desglose_monto[]"]');
    const inputUsd = row.querySelector('input[name="desglose_monto_usd[]"]');
    const inputTasa = document.getElementById('tasa_cambio') || document.querySelector('input[name="tasa_cambio"]');
    
    let tasa = window.parseLocalNumber(inputTasa.value) || 0;
    if (tasa <= 0) return;
    
    if (source === 'usd') {
        const usd = window.parseLocalNumber(inputUsd.value) || 0;
        inputBs.value = (usd * tasa).toFixed(2).replace('.', ',');
    } else {
        const bs = window.parseLocalNumber(inputBs.value) || 0;
        inputUsd.value = (bs / tasa).toFixed(2).replace('.', ',');
    }
}
function verDesglose(desglose) {
    let tbodyHtml = '';
    let totalBs = 0;
    let totalUsd = 0;
    let hasUsd = desglose.some(item => item.monto_usd > 0);
    desglose.forEach(item => {
        const monto = window.parseLocalNumber(item.monto) || 0;
        const montoUsd = parseFloat(item.monto_usd) || 0;
        totalBs += monto;
        totalUsd += montoUsd;
        tbodyHtml += `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${item.cedula || '-'}</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-weight: 500;">Sede: ${item.sede || '-'}</div>
                    <div style="font-size: 0.8rem; color: #64748b;">Gasto: ${item.tipo_gasto || '-'}</div>
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 500;">Bs. ${monto.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                ${hasUsd ? `<td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #0284c7;">$ ${montoUsd.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>` : ''}
            </tr>
        `;
    });

    // Add USD header if needed
    const thead = document.querySelector('#modalDesglose thead tr');
    const existingUsdTh = document.getElementById('th-desglose-usd');
    if (hasUsd && !existingUsdTh) {
        const th = document.createElement('th');
        th.id = 'th-desglose-usd';
        th.style = 'padding: 8px; border-bottom: 2px solid #e2e8f0; text-align: right;';
        th.textContent = 'USD';
        thead.appendChild(th);
    } else if (!hasUsd && existingUsdTh) {
        existingUsdTh.remove();
    }
    
    document.getElementById('modalDesgloseBody').innerHTML = tbodyHtml;
    document.getElementById('modalDesgloseTotal').innerText = `Bs. ${totalBs.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
    const usdTotalEl = document.getElementById('modalDesgloseTotalUsd');
    if (hasUsd && usdTotalEl) {
        usdTotalEl.style.display = '';
        usdTotalEl.innerText = `$ ${totalUsd.toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
        document.getElementById('row-desglose-usd-total').style.display = '';
    } else if (usdTotalEl) {
        usdTotalEl.style.display = 'none';
        document.getElementById('row-desglose-usd-total').style.display = 'none';
    }
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
    f.querySelector('[name="diferencial_cambiario"]').value = mov.diferencial_cambiario || '';
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
    const rowDifEdit = document.getElementById('row_diferencial_edit');
    if (rowDifEdit) rowDifEdit.style.display = isTraslado ? 'none' : 'block';
    
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
            agregarDesgloseEdit(item.cedula, item.monto_usd, item.monto, item.sede, item.tipo_gasto);
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

function agregarDesgloseEdit(ced, monto_usd, monto, sede = '', tipoGasto = '') {
    const lista = document.getElementById('lista_desglose_edit');
    const sedeOptions = buildOptions(sedesList, sede);
    const tgOptions = buildTipoGastoOptions(tipoGasto);

    const uid = Date.now() + Math.floor(Math.random() * 1000);

    const html = `
        <div class="row-desglose" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; background: white; padding: 10px; border-radius: 6px; border: 1px solid #ccc;">
            <div style="flex: 1 1 100%; display: flex; gap: 8px;">
                <select id="sel_sede_edit_${uid}" name="desglose_sede[]" style="flex: 1; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${sedeOptions}
                </select>
                <select id="sel_tg_edit_${uid}" name="desglose_tipo_gasto[]" style="flex: 2; min-width: 0; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    ${tgOptions}
                </select>
            </div>
            <div style="flex: 1 1 100%; display: flex; gap: 8px; margin-top: 4px; box-sizing: border-box;">
                <input type="text" name="desglose_cedula[]" placeholder="Cédula" value="${ced || ''}" style="flex: 2 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto_usd[]" placeholder="Monto USD" value="${monto_usd || ''}" oninput="calcDesgloseRow(this, 'usd')" style="flex: 1 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <input type="text" inputmode="decimal" name="desglose_monto[]" placeholder="Monto Bs" value="${monto || ''}" oninput="calcDesgloseRow(this, 'bs')" style="flex: 1 1 0%; min-width: 0; width: 100%; box-sizing: border-box; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" onclick="this.closest('.row-desglose').remove()" style="padding: 6px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; box-sizing: border-box;">&times;</button>
            </div>
        </div>`;
    lista.insertAdjacentHTML('beforeend', html);
    
    // Inicializar TomSelect en los nuevos selects
    new TomSelect(`#sel_tg_edit_${uid}`, { create: false, sortField: { field: "text", direction: "asc" }, placeholder: '-- Tipo Gasto --' });
}

function validarDesgloseEdit(event) {
    const chk = document.getElementById('chk_desglose_edit');
    if (chk && chk.checked) {
        const montoBsInput = document.querySelector('#formEditarEgreso [name="monto_bs"]');
        const montoTotal = window.parseLocalNumber(montoBsInput ? montoBsInput.value : 0) || 0;
        let sumaDesglose = 0;
        document.querySelectorAll('#lista_desglose_edit input[name="desglose_monto[]"]').forEach(inp => {
            sumaDesglose += window.parseLocalNumber(inp.value) || 0;
        });
        if (Math.abs(montoTotal - sumaDesglose) > 0.05) {
            alert(`Error: La suma del desglose (Bs. ${sumaDesglose.toFixed(2).replace(".", ",")}) no coincide con el Monto Bs (Bs. ${montoTotal.toFixed(2).replace(".", ",")}).`);
            event.preventDefault();
            return false;
        }
    }
    return true;
}

function abrirVerEgreso(mov) {
    document.getElementById('modalVerEgreso').style.display = 'flex';
    
    document.getElementById('ver_fecha').innerText = mov.fecha || '-';
    document.getElementById('ver_referencia').innerText = mov.referencia || '-';
    
    const bancoStr = (mov.banco || '') + ' - ' + (mov.titular || '');
    document.getElementById('ver_banco').innerText = bancoStr;

    if (mov.banco_receptor) {
        document.getElementById('ver_receptor_container').style.display = 'block';
        document.getElementById('ver_banco_receptor').innerText = (mov.banco_receptor || '') + ' - ' + (mov.titular_receptor || '');
    } else {
        document.getElementById('ver_receptor_container').style.display = 'none';
    }

    document.getElementById('ver_monto_usd').innerText = mov.monto_usd ? '$ ' + window.parseLocalNumber(mov.monto_usd).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_monto_bs').innerText = mov.monto_bs ? 'Bs. ' + window.parseLocalNumber(mov.monto_bs).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_tasa').innerText = mov.tasa_cambio ? window.parseLocalNumber(mov.tasa_cambio).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_dif').innerText = mov.diferencial_cambiario ? '$ ' + window.parseLocalNumber(mov.diferencial_cambiario).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_comision').innerText = mov.comision ? 'Bs. ' + window.parseLocalNumber(mov.comision).toFixed(2).replace(".", ",") : '-';
    document.getElementById('ver_tipo_gasto').innerText = mov.tipo_gasto || '-';
    document.getElementById('ver_motivo').innerText = mov.motivo || '-';
    document.getElementById('ver_sede').innerText = mov.sede || '-';
    document.getElementById('ver_placa').innerText = mov.placa_vehiculo || '-';

    // Desglose
    const dgCont = document.getElementById('ver_desglose_container');
    const dgLista = document.getElementById('ver_desglose_lista');
    dgLista.innerHTML = '';
    if (mov.desglose && Array.isArray(mov.desglose) && mov.desglose.length > 0) {
        dgCont.style.display = 'block';
        mov.desglose.forEach(item => {
            dgLista.innerHTML += `
                <div style="display: flex; gap: 10px; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid #bae6fd;">
                    <div style="flex: 2;"><strong>Beneficiario:</strong> ${item.beneficiario || ''}</div>
                    <div style="flex: 1;"><strong>Cédula:</strong> ${item.cedula || ''}</div>
                    <div style="flex: 1; text-align: right;"><strong>Monto:</strong> Bs. ${item.monto || ''}</div>
                </div>
            `;
        });
    } else {
        dgCont.style.display = 'none';
    }

    // Comprobantes
    const compCont = document.getElementById('ver_comprobantes');
    compCont.innerHTML = '';
    let allComps = [];
    if (mov.comprobantes) allComps = allComps.concat(mov.comprobantes);
    if (mov.comprobante_url && !allComps.includes(mov.comprobante_url)) allComps.push(mov.comprobante_url);
    
    if (allComps.length > 0) {
        allComps.forEach(url => {
            const ext = url.split('.').pop().toLowerCase();
            let el;
            if (ext === 'pdf') {
                el = `<a href="/storage/${url}" target="_blank" style="display:inline-block; padding:10px; background:#f1f5f9; border-radius:6px; border:1px solid #cbd5e1; text-decoration:none; color:#334155; font-weight:500;">📄 Ver PDF</a>`;
            } else {
                el = `<a href="/storage/${url}" target="_blank"><img src="/storage/${url}" style="width:100px; height:100px; object-fit:cover; border-radius:6px; border:1px solid #ccc;"></a>`;
            }
            compCont.innerHTML += el;
        });
    } else {
        compCont.innerHTML = '<span style="color:#94a3b8; font-size:0.85rem;">No hay comprobantes adjuntos</span>';
    }
}

function cerrarVerEgreso() {
    document.getElementById('modalVerEgreso').style.display = 'none';
}

// ===== FILTROS DE EGRESOS =====
function aplicarFiltros() {
    const texto = (document.getElementById('filtro-texto')?.value || '').toLowerCase().trim();
    const banco = (document.getElementById('filtro-banco')?.value || '').toLowerCase().trim();
    const beneficiario = (document.getElementById('filtro-beneficiario')?.value || '').toLowerCase().trim();
    const cat = document.getElementById('filtro-cat')?.value || '';

    // Map section identifiers
    const sectionMap = {
        'egresos': 'egreso_realizado',
        'otros': 'otros_egresos',
        'traslados': 'traslados',
    };

    // All egreso rows across all 3 tables are marked with data-egreso-cat
    const rows = document.querySelectorAll('tr[data-egreso-cat]');
    let visible = 0, total = rows.length;

    rows.forEach(tr => {
        const rowCat = tr.getAttribute('data-egreso-cat') || '';
        const rowText = tr.textContent.toLowerCase();
        
        const catMatch = !cat || sectionMap[cat] === rowCat;
        const textMatch = !texto || rowText.includes(texto);
        const bancoMatch = !banco || rowText.includes(banco);
        const beneficiarioMatch = !beneficiario || rowText.includes(beneficiario);
        
        const show = catMatch && textMatch && bancoMatch && beneficiarioMatch;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const contador = document.getElementById('filtro-contador');
    if (contador) {
        contador.textContent = texto || cat || banco || beneficiario
            ? `Mostrando ${visible} de ${total} registros`
            : '';
    }
}

function limpiarFiltros() {
    const txt = document.getElementById('filtro-texto');
    const txtBanco = document.getElementById('filtro-banco');
    const txtBeneficiario = document.getElementById('filtro-beneficiario');
    const cat = document.getElementById('filtro-cat');
    if (txt) txt.value = '';
    if (txtBanco) txtBanco.value = '';
    if (txtBeneficiario) txtBeneficiario.value = '';
    if (cat) cat.value = '';
    aplicarFiltros();
}

</script>

<!-- Modal Ver Desglose -->
<div id="modalDesglose" class="modal-overlay" style="display: none; z-index: 1200; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="modal-box" style="background: white; width: 95%; max-width: 500px; max-height: 90vh; overflow-y: auto; padding: 20px; border-radius: 12px; position: relative;">
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
                    <td colspan="2" style="padding: 10px 8px; text-align: right; font-weight: bold; color: var(--blue);">Total Bs:</td>
                    <td id="modalDesgloseTotal" style="padding: 10px 8px; text-align: right; font-weight: bold; color: var(--blue);">Bs. 0,00</td>
                </tr>
                <tr id="row-desglose-usd-total" style="display:none;">
                    <td colspan="2" style="padding: 4px 8px; text-align: right; font-weight: bold; color: #0284c7;">Total USD:</td>
                    <td id="modalDesgloseTotalUsd" style="padding: 4px 8px; text-align: right; font-weight: bold; color: #0284c7;">$ 0,00</td>
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
<!-- MODAL VER EGRESO (Solo Lectura) -->
<div id="modalVerEgreso" class="modal-overlay" style="display: none; z-index: 1300; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 700px; position: relative; padding: 20px; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); max-height: 92vh; overflow-y: auto; background: white;">
        <button type="button" onclick="cerrarVerEgreso()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 22px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: var(--blue); display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Detalles del Movimiento
        </h3>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Fecha:</span> <div id="ver_fecha" style="font-weight: 500; color: #0f172a;"></div></div>
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Ref:</span> <div id="ver_referencia" style="font-weight: 500; color: #0f172a;"></div></div>
            
            <div style="grid-column: span 2;"><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Banco Emisor y Titular:</span> <div id="ver_banco" style="font-weight: 500; color: var(--blue);"></div></div>
            <div id="ver_receptor_container" style="grid-column: span 2; display: none;"><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Banco Receptor y Titular:</span> <div id="ver_banco_receptor" style="font-weight: 500; color: #059669;"></div></div>

            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Monto USD:</span> <div id="ver_monto_usd" style="font-weight: 600; color: #0f172a;"></div></div>
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Monto BS:</span> <div id="ver_monto_bs" style="font-weight: 600; color: #0f172a;"></div></div>
            
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Tasa Cambio:</span> <div id="ver_tasa" style="font-weight: 500; color: #0f172a;"></div></div>
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Dif. Cambiario:</span> <div id="ver_dif" style="font-weight: 500; color: var(--danger);"></div></div>

            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Comisión:</span> <div id="ver_comision" style="font-weight: 500; color: #0f172a;"></div></div>
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Tipo de Gasto:</span> <div id="ver_tipo_gasto" style="font-weight: 500; color: #0f172a;"></div></div>

            <div style="grid-column: span 2;"><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Motivo:</span> <div id="ver_motivo" style="font-weight: 500; color: #0f172a;"></div></div>
            
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Sede:</span> <div id="ver_sede" style="font-weight: 500; color: #0f172a;"></div></div>
            <div><span style="color:#64748b; font-size: 0.85rem; font-weight: 600;">Placa Veh.:</span> <div id="ver_placa" style="font-weight: 500; color: #0f172a;"></div></div>
        </div>

        <div id="ver_desglose_container" style="display: none; background: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #bae6fd; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: #0284c7; font-size: 0.95rem;">Desglose por Beneficiarios</h4>
            <div id="ver_desglose_lista"></div>
        </div>

        <div style="margin-bottom: 15px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
            <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; color: #475569;">Comprobantes Adjuntos</h4>
            <div id="ver_comprobantes" style="display: flex; gap: 10px; flex-wrap: wrap;"></div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button type="button" onclick="cerrarVerEgreso()" style="padding: 8px 20px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cerrar</button>
        </div>
    </div>
</div>

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
                <input type="date" name="fecha" max="{{ date('Y-m-d') }}" required style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <!-- Montos -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;" id="col_monto_usd_edit">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto USD</label>
                    <input type="text" inputmode="decimal" name="monto_usd" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="col_tasa_cambio_edit">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Tasa Cambio</label>
                    <input type="text" inputmode="decimal" name="tasa_cambio" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;">
                    <label id="lbl_monto_bs_edit" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Monto BS</label>
                    <input type="text" inputmode="decimal" name="monto_bs" id="monto_bs_edit" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div style="flex: 1;" id="row_diferencial_edit">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Dif. Cambiario</label>
                    <input type="text" inputmode="decimal" name="diferencial_cambiario" style="width: 100%; padding: 6px; border: 1px solid #fde68a; background-color: #fef9c3; border-radius: 4px;">
                </div>
            </div>

            <!-- Comisión -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Comisión</label>
                    <input type="text" inputmode="decimal" name="comision" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
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
                <button type="button" onclick="agregarDesgloseEdit('','','','','')" style="margin-top: 5px; padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+ Añadir persona</button>
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
