@extends('layouts.app')
@section('title', 'Flujo de Caja y Disponibilidad')
@section('content')

<style>
    /* Premium Modern Styles */
    .dashboard-container {
        max-width: 1600px;
        margin: 0 auto;
    }
    #nuevoEgresoModal .ne-box {
        width: min(96vw, 980px);
        max-width: 980px;
        padding: 20px 24px 18px;
        transition: max-width .18s ease, width .18s ease;
    }
    #nuevoEgresoModal .ne-box.ne-wide {
        width: min(96vw, 1320px);
        max-width: 1320px;
    }
    #nuevoEgresoModal .ne-body {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        align-items: start;
    }
    #nuevoEgresoModal .ne-box.ne-wide .ne-body {
        grid-template-columns: minmax(0, 1fr) minmax(380px, 460px);
    }
    #nuevoEgresoModal .ne-side {
        display: none;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
        max-height: calc(95vh - 120px);
        overflow-y: auto;
        padding-left: 4px;
    }
    #nuevoEgresoModal .ne-box.ne-wide .ne-side {
        display: flex;
        border-left: 1px solid #e2e8f0;
        padding-left: 16px;
    }
    #nuevoEgresoModal .ne-side > [id^="panel_"],
    #nuevoEgresoModal .ne-side > [id^="container_asiento"] {
        margin-bottom: 0 !important;
    }
    #nuevoEgresoModal .ne-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 20px;
        align-items: start;
    }
    #nuevoEgresoModal .ne-col {
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 0;
    }
    #nuevoEgresoModal .ne-row {
        display: flex;
        gap: 12px;
    }
    #nuevoEgresoModal .ne-row > * { flex: 1; min-width: 0; }
    #nuevoEgresoModal .ne-full { grid-column: 1 / -1; }
    #nuevoEgresoModal .ne-sec {
        margin: 0 0 4px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
    }
    #nuevoEgresoModal .ne-lbl {
        display: block;
        margin-bottom: 3px;
        font-weight: 500;
        font-size: 0.88rem;
        color: #334155;
    }
    #nuevoEgresoModal .ne-inp,
    #nuevoEgresoModal select.ne-inp,
    #nuevoEgresoModal input.ne-inp {
        width: 100%;
        padding: 7px 9px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        box-sizing: border-box;
        font: inherit;
    }
    #nuevoEgresoModal .ne-opts {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 22px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    #nuevoEgresoModal .ne-opts label {
        display: inline-flex !important;
        align-items: center;
        gap: 8px;
        flex: 0 0 auto;
        margin: 0;
        padding: 2px 0;
        font-size: 0.88rem;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
        cursor: pointer;
        overflow: visible;
    }
    #nuevoEgresoModal .ne-opts input[type="checkbox"] {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
        margin: 0;
    }
    #nuevoEgresoModal .gf-picker {
        background: #fff;
        border: 1.5px solid #93c5fd;
        border-radius: 8px;
        overflow: hidden;
    }
    #nuevoEgresoModal .gf-picker-search {
        width: 100%;
        border: none;
        border-bottom: 1px solid #dbeafe;
        padding: 9px 12px;
        font-size: 0.85rem;
        box-sizing: border-box;
        outline: none;
        background: #f8fbff;
    }
    #nuevoEgresoModal .gf-picker-search:focus { background: #fff; }
    #nuevoEgresoModal .gf-picker-list {
        max-height: 260px;
        overflow-y: auto;
        padding: 4px;
    }
    #nuevoEgresoModal .gf-picker-list::-webkit-scrollbar { width: 6px; }
    #nuevoEgresoModal .gf-picker-list::-webkit-scrollbar-thumb {
        background: #bfdbfe;
        border-radius: 6px;
    }
    #nuevoEgresoModal .gf-picker-group {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
        padding: 8px 8px 4px;
    }
    #nuevoEgresoModal .gf-picker-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px 10px;
        align-items: center;
        width: 100%;
        text-align: left;
        border: none;
        background: transparent;
        border-radius: 7px;
        padding: 8px 10px;
        cursor: pointer;
        font: inherit;
        color: inherit;
    }
    #nuevoEgresoModal .gf-picker-item:hover,
    #nuevoEgresoModal .gf-picker-item:focus {
        background: #eff6ff;
        outline: none;
    }
    #nuevoEgresoModal .gf-picker-item.is-urgente { box-shadow: inset 3px 0 0 #f59e0b; }
    #nuevoEgresoModal .gf-picker-title {
        font-size: 0.84rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #nuevoEgresoModal .gf-picker-sub {
        font-size: 0.72rem;
        color: #64748b;
        line-height: 1.25;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #nuevoEgresoModal .gf-picker-amt {
        font-size: 0.84rem;
        font-weight: 700;
        color: #1d4ed8;
        white-space: nowrap;
    }
    #nuevoEgresoModal .gf-picker-empty {
        padding: 14px 12px;
        font-size: 0.82rem;
        color: #64748b;
        text-align: center;
    }
    #nuevoEgresoModal #panel_gasto_fijo {
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    #nuevoEgresoModal .gf-item {
        background: #fff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    #nuevoEgresoModal .gf-item-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
        min-width: 0;
    }
    #nuevoEgresoModal .gf-item-info {
        min-width: 0;
        flex: 1 1 auto;
        overflow: hidden;
    }
    #nuevoEgresoModal .gf-item-title {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.86rem;
        line-height: 1.3;
        word-break: normal;
        overflow-wrap: anywhere;
    }
    #nuevoEgresoModal .gf-item-sub,
    #nuevoEgresoModal .gf-item-meta {
        font-size: 0.72rem;
        color: #64748b;
        line-height: 1.3;
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #nuevoEgresoModal .gf-item-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 96px;
        gap: 8px;
        width: 100%;
    }
    #nuevoEgresoModal .gf-item-actions select,
    #nuevoEgresoModal .gf-item-actions input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        padding: 6px 8px;
        border: 1px solid #93c5fd;
        border-radius: 6px;
        font-size: 0.82rem;
        background: #fff;
    }
    #nuevoEgresoModal .gf-item-actions input {
        font-weight: 700;
        text-align: right;
    }
    #nuevoEgresoModal .gf-item-del {
        border: none;
        background: #fee2e2;
        color: #b91c1c;
        border-radius: 6px;
        cursor: pointer;
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        line-height: 1;
    }
    @media (max-width: 900px) {
        #nuevoEgresoModal .ne-grid { grid-template-columns: 1fr; }
        #nuevoEgresoModal .ne-box.ne-wide .ne-body { grid-template-columns: 1fr; }
        #nuevoEgresoModal .ne-box.ne-wide .ne-side {
            border-left: none;
            padding-left: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            max-height: none;
        }
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
        <h2 style="margin: 0; font-size: 1.5rem; color: var(--blue); font-weight: 600;">{{ ($soloAvances ?? false) ? 'Avances y cambios' : 'Flujo de Caja y Disponibilidad' }}</h2>
        @if($soloAvances ?? false)
        <div style="display: flex; gap: 10px; align-items: center;">
            <form method="GET" action="{{ route('finanzas.flujo_caja') }}" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Desde:</label>
                <input type="date" id="fecha_desde_avances" name="fecha_desde" value="{{ $fecha_desde }}" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 6px;" onchange="this.form.submit()">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Hasta:</label>
                <input type="date" id="fecha_hasta_avances" name="fecha_hasta" value="{{ $fecha_hasta }}" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 6px;" onchange="this.form.submit()">
            </form>
            <button type="button" onclick="descargarReporteAvances('pdf')" title="Descargar mis movimientos del período" style="background: #7c3aed; color: #fff; border: 1px solid #6d28d9; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer;">PDF</button>
            <button type="button" onclick="descargarReporteAvances('xlsx')" title="Descargar mis movimientos del período" style="background: #059669; color: #fff; border: 1px solid #047857; border-radius: 6px; padding: 8px 14px; font-weight: 600; cursor: pointer;">Excel</button>
            <button class="btn btn-primary" style="background-color: #1a4273; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;" onclick="openNuevoEgresoModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nuevo Egreso
            </button>
        </div>
        @elseif(!auth()->user()->isAuditor())
        <div style="display: flex; gap: 10px;">
            <form action="{{ route('finanzas.reset_daily') }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas eliminar TODOS los datos financieros y empezar el día completamente en blanco?');">
                @csrf
                <button type="submit" class="btn" style="background-color: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: all 0.2s; cursor: pointer;" onmouseover="this.style.background='#dc2626';" onmouseout="this.style.background='#ef4444';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    Limpiar Día
                </button>
            </form>
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

    @if(!auth()->user()->isAuditor() && ! ($soloAvances ?? false))
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
                    <span class="widget-label">TERCEROS</span>
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

@unless($soloAvances ?? false)
<!-- EGRESOS REALIZADOS -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 10px;">
        <h3 style="color: var(--blue); margin: 0;">EGRESOS REALIZADOS</h3>
        <form id="form-fechas-flujo" method="GET" action="{{ route('finanzas.flujo_caja') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Desde:</label>
            <input type="date" id="fecha_desde_input" name="fecha_desde" value="{{ $fecha_desde }}" style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;" onchange="this.form.submit()">
            
            <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Hasta:</label>
            <input type="date" id="fecha_hasta_input" name="fecha_hasta" value="{{ $fecha_hasta }}" style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;" onchange="this.form.submit()">
            
            <button type="button" onclick="descargarReporteBusqueda('pdf')" style="background: #7c3aed; color: #fff; border: 1px solid #6d28d9; border-radius: 6px; padding: 6px 12px; font-weight: 600; cursor: pointer;">PDF</button>
            <button type="button" onclick="descargarReporteBusqueda('xlsx')" style="background: #059669; color: #fff; border: 1px solid #047857; border-radius: 6px; padding: 6px 12px; font-weight: 600; cursor: pointer;">Excel</button>
        </form>
    </div>

    {{-- BARRA DE BÚSQUEDA / FILTROS --}}
    <div id="barra-filtros-egresos" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <input id="filtro-texto" type="text" placeholder="🔍 Buscar motivo o tipo de gasto..." oninput="aplicarFiltros()" style="flex:1; min-width:180px; padding:7px 12px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; outline:none;">
        <input id="filtro-referencia" type="search" inputmode="numeric" autocomplete="off" placeholder="# Buscar referencia..." oninput="aplicarFiltros()" style="flex:1; min-width:150px; padding:7px 12px; border:1px solid #cbd5e1; border-radius:7px; font-size:0.875rem; outline:none;">
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
                        <tr data-egreso-cat="egreso_realizado" data-referencia="{{ $mov->referencia }}">
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
                            <td>{{ $mov->tipo_gasto ?: '-' }}@if($mov->es_todoticket) <span style="display:inline-block;margin-left:4px;background:#d1fae5;color:#065f46;font-size:0.7rem;padding:1px 6px;border-radius:999px;">TodoTicket</span>@endif</td>
                            <td>
                                @php
                                    $motivoEr = (string) ($mov->motivo ?: '-');
                                    $sobranteEr = str_contains($motivoEr, ' · Sobrante $') ? explode(' · Sobrante $', $motivoEr, 2) : null;
                                    $faltanteEr = (! $sobranteEr && str_contains($motivoEr, ' · Faltante $'))
                                        ? explode(' · Faltante $', $motivoEr, 2)
                                        : null;
                                @endphp
                                @if($sobranteEr)
                                    <div>{{ $sobranteEr[0] !== '' ? $sobranteEr[0] : '-' }}</div>
                                    <div style="margin-top:3px; font-size:0.75rem; color:#9a3412; font-weight:600;">Sobrante ${{ $sobranteEr[1] }}</div>
                                @elseif($faltanteEr)
                                    <div>{{ $faltanteEr[0] !== '' ? $faltanteEr[0] : '-' }}</div>
                                    <div style="margin-top:3px; font-size:0.75rem; color:#9a3412; font-weight:600;">Faltante ${{ $faltanteEr[1] }}</div>
                                @else
                                    {{ $motivoEr }}
                                @endif
                                @if(!empty($mov->detalle_todoticket) && is_array($mov->detalle_todoticket))
                                    <div style="margin-top:4px;font-size:0.75rem;color:#047857;">
                                        Recarga {{ number_format($mov->detalle_todoticket['recarga'] ?? 0, 2, ',', '.') }}
                                        · Comisión {{ number_format($mov->detalle_todoticket['comision'] ?? 0, 2, ',', '.') }}
                                        · IVA {{ number_format($mov->detalle_todoticket['iva'] ?? 0, 2, ',', '.') }}
                                        · Total Real {{ number_format($mov->detalle_todoticket['total_real'] ?? $mov->monto_bs, 2, ',', '.') }}
                                    </div>
                                @endif
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
                                @if(!empty($mov->asiento['debe']))
                                    <div style="margin-top: 5px;">
                                        <button type="button" onclick='verAsiento(@json($mov->asiento))' style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; cursor: pointer;">
                                            📒 Ver asiento
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
                                @if(auth()->user()->canAccess('finanzas.eliminar'))
                                <form method="POST" action="{{ route('finanzas.destroy_egreso', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este egreso? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Eliminar egreso"
                                        style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                </form>
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

@endunless
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
                        <tr data-egreso-cat="otros_egresos" data-referencia="{{ $mov->referencia }}">
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
                                @if(auth()->user()->canAccess('finanzas.eliminar') || ($soloAvances ?? false))
                                <form method="POST" action="{{ route('finanzas.destroy_egreso', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este avance o egreso? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Eliminar avance o egreso"
                                        style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                </form>
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

@unless($soloAvances ?? false)
    <!-- INGRESOS -->
    <div class="dashboard-container" style="margin-top: 10px;">
        <h3 style="margin-bottom: 15px; color: #0f766e; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5m-7 7l7-7 7 7"/></svg>
            INGRESOS
        </h3>
        <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1.5px solid #99f6e4;">
            <div class="table-wrap">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr style="background: #f0fdfa;">
                            <th style="width: 100px;">Fecha</th>
                            <th>Cuenta</th>
                            <th>Origen · tipo</th>
                            <th>Motivo</th>
                            <th>Ref.</th>
                            <th class="col-number" style="text-align: right;">Monto Bs</th>
                            <th class="col-number" style="text-align: right;">Monto USD</th>
                            <th style="text-align: center; width: 90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ingresos as $mov)
                            <tr data-egreso-cat="ingresos" data-referencia="{{ $mov->referencia }}">
                                <td>{{ $mov->fecha }}</td>
                                <td>
                                    <strong style="color: #0f766e;">{{ $mov->banco }}</strong><br>
                                    <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                </td>
                                <td>
                                    {{ $mov->titular_receptor ?: '-' }}
                                    @if($mov->tipo_gasto)<br><span class="muted" style="font-size: 0.8rem;">{{ $mov->tipo_gasto }}</span>@endif
                                </td>
                                <td>{{ $mov->motivo ?: '-' }}</td>
                                <td>
                                    {{ $mov->referencia ?: '-' }}
                                    @if($mov->es_conciliado)<br><span style="font-size: 0.72rem; color: #15803d; font-weight: 700;">✓ en el banco</span>@endif
                                </td>
                                <td class="col-number" style="text-align: right; font-weight: 500; color: #0f766e;">Bs. {{ number_format((float) $mov->monto_bs, 2) }}</td>
                                <td class="col-number" style="text-align: right;">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                                <td style="text-align: center; white-space: nowrap;">
                                    @php
                                        $allComprobantesIng = array_values(array_filter(array_merge(
                                            $mov->comprobantes ?? [],
                                            ($mov->comprobante_url && !in_array($mov->comprobante_url, $mov->comprobantes ?? [])) ? [$mov->comprobante_url] : []
                                        )));
                                    @endphp
                                    @if(count($allComprobantesIng) > 0)
                                        <button type="button" onclick='abrirGaleria(@json($allComprobantesIng))' title="Ver comprobantes ({{ count($allComprobantesIng) }})"
                                            style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer; margin-right: 2px;">📎 {{ count($allComprobantesIng) }}</button>
                                    @endif
                                    @if(auth()->user()->isAuditor())
                                    <button type="button" onclick='abrirVerEgreso(@json($mov))' title="Ver detalle"
                                        style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">👁️</button>
                                    @elseif(auth()->user()->canAccess('finanzas.editar'))
                                    <button type="button" onclick='abrirEditarEgreso(@json($mov))' title="Editar ingreso"
                                        style="background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; border-radius: 4px; padding: 3px 7px; font-size: 0.8rem; cursor: pointer;">✏️</button>
                                    @endif
                                    @if(auth()->user()->canAccess('finanzas.eliminar'))
                                    <form method="POST" action="{{ route('finanzas.destroy_egreso', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este ingreso? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar ingreso"
                                            style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--muted);">No hay ingresos registrados. Regístralos con «Nuevo Egreso» → Tipo: INGRESOS.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f0fdfa; border-top: 2px solid #99f6e4; font-weight: bold;">
                            <td colspan="5" style="text-align: right; color: #0f766e;">TOTAL INGRESOS</td>
                            <td class="col-number" style="text-align: right; color: #0f766e;">Bs. {{ number_format((float) $ingresos->sum('monto_bs'), 2) }}</td>
                            <td class="col-number" style="text-align: right; color: #0f766e;">$ {{ number_format((float) $ingresos->sum('monto_usd'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- EGRESOS EN DIVISAS -->
    <div class="dashboard-container" style="margin-top: 10px;">
        <h3 style="margin-bottom: 15px; color: #166534; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20m-7-7h14m-14-6h14"/></svg>
            EGRESOS EN DIVISAS
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
                            <tr data-egreso-cat="egreso_divisas" data-referencia="{{ $mov->referencia }}">
                                <td>{{ $mov->fecha }}</td>
                                <td>
                                    <strong style="color: #166534;">{{ $mov->banco }}</strong><br>
                                    <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                </td>
                                <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                                <td>
                                    @php
                                        $motivoDiv = (string) ($mov->motivo ?: '-');
                                        $tieneSobrante = str_contains($motivoDiv, 'Sobrante $');
                                    @endphp
                                    @if($tieneSobrante)
                                        @php
                                            [$motivoBase, $sobranteTxt] = array_pad(explode(' · Sobrante $', $motivoDiv, 2), 2, null);
                                        @endphp
                                        <div>{{ $motivoBase !== '' ? $motivoBase : '-' }}</div>
                                        @if($sobranteTxt)
                                            <div style="margin-top:3px; font-size:0.75rem; color:#9a3412; font-weight:600;">Sobrante ${{ $sobranteTxt }}</div>
                                        @endif
                                    @else
                                        {{ $motivoDiv }}
                                    @endif
                                </td>
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
                                    @if(auth()->user()->canAccess('finanzas.eliminar'))
                                    <form method="POST" action="{{ route('finanzas.destroy_egreso', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este pago en divisas? Esta acción no se puede deshacer.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar pago en divisas"
                                            style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                    </form>
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

    <!-- COMPRA DE DIVISAS -->
    <div class="dashboard-container" style="margin-top: 10px;">
        <h3 style="margin-bottom: 15px; color: #0369a1; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            COMPRA DE DIVISAS
        </h3>
        <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1.5px solid #bae6fd;">
            <div class="table-wrap">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr style="background: #f0f9ff;">
                            <th style="width: 100px;">Fecha</th>
                            <th>Banco y Titular</th>
                            <th>Referencia</th>
                            <th>Motivo</th>
                            <th class="col-number" style="text-align: right;">Monto BS</th>
                            <th class="col-number" style="text-align: right;">Monto USD</th>
                            <th style="text-align: center; width: 90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($compras_divisas ?? collect()) as $mov)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($mov->fecha)->format('Y-m-d') }}</td>
                                <td>
                                    <strong style="color: #0369a1;">{{ $mov->banco }}</strong><br>
                                    <span class="muted" style="font-size: 0.85rem;">{{ $mov->titular }}</span>
                                </td>
                                <td>{{ $mov->referencia ?: '—' }}</td>
                                <td>{{ $mov->motivo ?: ($mov->concepto ?: '—') }}</td>
                                <td class="col-number" style="text-align: right; font-weight: 500;">
                                    Bs. {{ number_format((float) $mov->monto_bs, 2) }}
                                </td>
                                <td class="col-number" style="text-align: right;">
                                    {{ $mov->monto_usd ? '$'.number_format((float) $mov->monto_usd, 2) : '—' }}
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    @if(auth()->user()->canAccess('finanzas.eliminar'))
                                    <form method="POST" action="{{ route('finanzas.destroy_compra_divisa', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Eliminar esta compra de divisas?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Eliminar"
                                            style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: var(--muted);">No hay compras de divisas en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        @php
                            $tot_compra_bs = ($compras_divisas ?? collect())->sum('monto_bs');
                            $tot_compra_usd = ($compras_divisas ?? collect())->sum('monto_usd');
                        @endphp
                        <tr style="background-color: #f0f9ff; border-top: 2px solid #bae6fd; font-weight: bold;">
                            <td colspan="4" style="text-align: right; color: #0369a1;">TOTAL COMPRA DE DIVISAS</td>
                            <td class="col-number" style="text-align: right; color: #0369a1;">Bs. {{ number_format((float) $tot_compra_bs, 2) }}</td>
                            <td class="col-number" style="text-align: right; color: #0369a1;">$ {{ number_format((float) $tot_compra_usd, 2) }}</td>
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
                        <tr data-egreso-cat="traslados" data-referencia="{{ $mov->referencia }}">
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
                                @if(auth()->user()->canAccess('finanzas.eliminar'))
                                <form method="POST" action="{{ route('finanzas.destroy_egreso', $mov->id) }}" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar esta transferencia? Esta acción no se puede deshacer.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Eliminar transferencia"
                                        style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:4px;padding:3px 7px;font-size:0.8rem;cursor:pointer;">🗑️</button>
                                </form>
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
@endunless


<!-- Modal Nuevo Egreso -->
<div id="nuevoEgresoModal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="panel modal-box ne-box" style="position: relative; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 95vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeNuevoEgresoModal()" aria-label="Cerrar" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin: 0 40px 16px 0; font-size: 1.15rem; color: var(--blue); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
            <span id="nuevoEgresoModalTitulo">Nuevo Egreso</span>
            <button type="button" id="btn-ocr" onclick="document.getElementById('ocr-upload').click()" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px; flex-shrink: 0;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span id="ocr-btn-text">Escanear Recibo</span>
            </button>
            <input type="file" id="ocr-upload" accept="image/*" style="display: none;" onchange="handleOcrUpload(event)">
        </h3>
        
        <form id="formNuevoEgreso" method="POST" action="{{ route('finanzas.store_egreso') }}" enctype="multipart/form-data" onsubmit="return validarDesglose(event)">
            @csrf
            <input type="hidden" name="cuenta_por_pagar_id" id="cuenta_por_pagar_id" value="">
            <input type="hidden" name="adelanto_abono_ids" id="adelanto_abono_ids" value="">
            <input type="hidden" name="pago_manual_id" id="pago_manual_id" value="">
            <div id="banner_pago_cuenta" style="display:none; margin-bottom: 12px; padding: 10px 12px; background: #fff7ed; border: 1px solid #fdba74; border-radius: 8px; color: #9a3412; font-size: 0.85rem;"></div>

            <div class="ne-body">
            <div class="ne-main">
            <div class="ne-grid">
                <div class="ne-col">
                    <div class="ne-sec">Movimiento</div>
                    <div class="ne-row">
                        <div>
                            <label class="ne-lbl">Tipo de Egreso</label>
                            <select name="categoria_egreso" id="categoria_egreso" class="ne-inp" onchange="toggleTraslados()" required>
                        @if($soloAvances ?? false)
                            <option value="otros_egresos" selected>OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                        @else
                        <option value="egreso_realizado">EGRESOS REALIZADOS</option>
                        <option value="egreso_divisas">EGRESOS EN DIVISAS</option>
                        <option value="compra_divisas">COMPRA DE DIVISAS</option>
                        <option value="otros_egresos">OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                        <option value="traslados">TRASLADOS</option>
                        <option value="ingresos">INGRESOS</option>
                        @endif
                    </select>
                </div>
                        <div>
                            <label class="ne-lbl">Fecha</label>
                            <input type="date" name="fecha" class="ne-inp" value="{{ $fecha_filtro }}" max="{{ date('Y-m-d') }}" required>
                </div>
            </div>

                    <div class="ne-row">
                        <div style="flex: 2.2;">
                            <label class="ne-lbl" id="lbl_banco_titular">Banco y Titular</label>
                            <select id="banco_titular" name="banco_titular" class="ne-inp" required>
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
                        <div id="col_referencia" style="flex: 0.9;">
                            <label class="ne-lbl">Ref.</label>
                            <input type="text" name="referencia" class="ne-inp" placeholder="# Referencia">
                </div>
            </div>

                    <div id="row_receptor" style="display: none;">
                        <label class="ne-lbl">Banco Receptor y Titular Receptor</label>
                        <select name="banco_titular_receptor" id="banco_titular_receptor" class="ne-inp">
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
            
                    <div class="ne-sec">Montos</div>
                    <div class="ne-row">
                        <div id="col_monto_usd">
                            <label class="ne-lbl">Monto USD</label>
                            <input type="text" inputmode="decimal" name="monto_usd" id="monto_usd" class="ne-inp">
                </div>
                        <div id="col_tasa_cambio">
                            <label class="ne-lbl">Tasa de Cambio</label>
                            <input type="text" inputmode="decimal" name="tasa_cambio" id="tasa_cambio" class="ne-inp">
                </div>
                        <div id="col_monto_bs">
                            <label class="ne-lbl" id="lbl_monto_bs">Monto BS</label>
                            <input type="text" inputmode="decimal" name="monto_bs" id="monto_bs" class="ne-inp">
                </div>
            </div>

                    <div id="row_monto_total_gasto">
                        <label class="ne-lbl">Monto total del gasto</label>
                        <input type="text" inputmode="decimal" name="monto_total_gasto" id="monto_total_gasto" class="ne-inp" placeholder="Total de la cuenta (si hoy solo abonas una parte)">
            </div>

                    <div id="row_diferencial" class="ne-row">
                        <div>
                            <label class="ne-lbl">Dif. Cambiario</label>
                            <input type="text" inputmode="decimal" name="diferencial_cambiario" class="ne-inp">
                </div>
                        <div>
                            <label class="ne-lbl">Comisión</label>
                            <input type="text" inputmode="decimal" name="comision" id="comision" class="ne-inp">
                </div>
            </div>

                    <div id="row_traslado_extra" class="ne-row" style="display: none;">
                        <div>
                            <label class="ne-lbl">Comisión (Bs)</label>
                            <input type="text" inputmode="decimal" name="comision" id="comision_traslado" class="ne-inp"
                                oninput="calcTraslado()" placeholder="0,00" disabled>
                </div>
                        <div>
                            <label class="ne-lbl">Monto USD <small style="color:#64748b;">(calculado)</small></label>
                            <input type="text" inputmode="decimal" name="monto_usd" id="monto_usd_traslado" class="ne-inp"
                                readonly placeholder="Se calcula con la tasa del día"
                                style="background:#f8fafc; color:#475569; cursor:default;">
                        </div>
                </div>
            </div>

                <div class="ne-col">
                    <div class="ne-sec">Detalle</div>
                    <div id="row_tipo_gasto">
                        <label class="ne-lbl" id="lbl_tipo_gasto">Tipo de Gasto</label>
                        <select name="tipo_gasto" id="tipo_gasto" class="ne-inp" required>
                    <option value="">-- Seleccione un tipo de gasto --</option>
                    @foreach(array_diff($tiposGasto, ['001 - COMPRA DE MERCANCIA']) as $tipoGasto)
                        <option value="{{ $tipoGasto }}">{{ $tipoGasto }}</option>
                    @endforeach
                </select>
            </div>
            
                    <div class="ne-row">
                        <div>
                            <label class="ne-lbl">Sede (Opcional)</label>
                            <select name="sede" class="ne-inp">
                        <option value="">-- Seleccione una sede --</option>
                        @php
                            $sedesFinanzas = array_values(array_unique(array_merge(config('inventario.sedes_locales', []), config('inventario.sedes_finanzas_extra', []))));
                        @endphp
                        @foreach($sedesFinanzas as $sedeLocal)
                            <option value="{{ $sedeLocal }}">{{ $sedeLocal }}</option>
                        @endforeach
                    </select>
                </div>
                        <div id="col_placa">
                            <label class="ne-lbl">Placa del vehículo</label>
                            <input type="text" name="placa_vehiculo" class="ne-inp" placeholder="Ej. ABC-123">
                </div>
            </div>

                    <div>
                        <label class="ne-lbl" id="beneficiario_label">Beneficiario</label>
                        <select id="beneficiario" name="beneficiario" class="ne-inp">
                    <option value="">-- Seleccione un beneficiario --</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov }}">{{ $prov }}</option>
                    @endforeach
                </select>
                <input type="hidden" id="nomina_empleado_id" name="nomina_empleado_id" value="">
                <small id="beneficiario_ayuda" style="display:none; margin-top:4px; color:#047857;">
                    Solo aparecen empleados activos marcados como Servicio Técnico en su ficha de Nómina.
                </small>
                <small id="beneficiario_ayuda_libre" style="display:block; margin-top:4px; color:#64748b;">
                    Puedes elegir de la lista o escribir un nombre y pulsar Enter.
                </small>
            </div>

                    <div>
                        <label class="ne-lbl">Motivo (Breve descripción)</label>
                        <input type="text" name="motivo" id="motivo_egreso" class="ne-inp" placeholder="Ej. Pago de internet mensual...">
            </div>
            </div>
            </div>
            </div>{{-- /.ne-main --}}

                <div class="ne-side" id="ne_side_panels">
                    <div id="panel_todoticket" style="display:none; background: #ecfdf5; border: 1.5px solid #6ee7b7; border-radius: 10px; padding: 14px 16px;">
                <p style="margin: 0 0 10px; font-size: 0.8rem; color: #047857;">Copia los montos del <strong>Detalle Monto a Pagar</strong>. El <strong>Total Real</strong> es lo que se guarda y concilia con el banco.</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Recarga Tarjeta Electrónica</label>
                        <input type="text" inputmode="decimal" name="tt_recarga" id="tt_recarga" oninput="calcTodoTicket()" placeholder="903.127,29" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Comisión</label>
                        <input type="text" inputmode="decimal" name="tt_comision" id="tt_comision" oninput="calcTodoTicket()" placeholder="9.031,27" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Total IVA 16%</label>
                        <input type="text" inputmode="decimal" name="tt_iva" id="tt_iva" oninput="calcTodoTicket()" placeholder="1.445,00" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Retención ISLR</label>
                        <input type="text" inputmode="decimal" name="tt_ret_islr" id="tt_ret_islr" oninput="calcTodoTicket()" placeholder="451,56" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Retención IVA</label>
                        <input type="text" inputmode="decimal" name="tt_ret_iva" id="tt_ret_iva" oninput="calcTodoTicket()" placeholder="1.083,75" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Retención 1x1000 / 1x500 / 1x100</label>
                        <input type="text" inputmode="decimal" name="tt_ret_1x1000" id="tt_ret_1x1000" oninput="calcTodoTicket()" placeholder="0,00" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Retención Resp. Social</label>
                        <input type="text" inputmode="decimal" name="tt_ret_resp_social" id="tt_ret_resp_social" oninput="calcTodoTicket()" placeholder="0,00" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 3px;">Retención ISAE</label>
                        <input type="text" inputmode="decimal" name="tt_ret_isae" id="tt_ret_isae" oninput="calcTodoTicket()" placeholder="0,00" style="width:100%; padding:6px; border:1px solid #a7f3d0; border-radius:4px;">
                    </div>
                </div>
                <div style="margin-top: 12px; padding: 10px; background: #065f46; color: white; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700;">Total Real</span>
                    <strong id="tt_total_real_lbl" style="font-size: 1.15rem;">Bs. 0,00</strong>
                </div>
            </div>

                    <div id="panel_gasto_fijo" style="display:none; background: linear-gradient(135deg,#eff6ff,#dbeafe); border: 1.5px solid #93c5fd; border-radius: 10px; padding: 14px 16px;">
                <div style="margin-bottom: 10px;">
                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:5px;">
                        Agregar gasto fijo
                    </label>
                            <div class="gf-picker" id="gf_picker">
                                <input type="search" id="gf_picker_q" class="gf-picker-search" placeholder="Buscar servicio, empresa o sede…" autocomplete="off" oninput="buildGastoFijoSelect()">
                                <div id="sel_gasto_fijo" class="gf-picker-list" role="listbox" aria-label="Gastos fijos">
                                    <div class="gf-picker-empty">Cargando gastos pendientes…</div>
                                </div>
                            </div>
                            <p style="font-size:0.72rem; color:#3b82f6; margin:6px 0 0;">Haz clic en un gasto para agregarlo. Si la factura cubre varios, agrégalos todos.</p>
                </div>
                <div id="gf_items" style="display:flex; flex-direction:column; gap:8px;"></div>
                <div id="gf_items_total" style="display:none; margin-top:8px; text-align:right; font-size:0.85rem; color:#1e40af; font-weight:700;"></div>
                <div id="gf_items_inputs"></div>

                        <div id="panel_cobranza_diff" style="display:none; margin-top:12px; padding:12px; background:#fff7ed; border:1.5px solid #fdba74; border-radius:8px;">
                            <p style="margin:0 0 8px; font-size:0.82rem; color:#9a3412; font-weight:600;">
                                Diferencia gasto − pago: <span id="cobranza_diff_lbl">$0,00</span>
                            </p>
                            <p style="margin:0 0 10px; font-size:0.75rem; color:#c2410c;">Elige una factura de Cobranza <strong>o</strong> justifica el faltante si no corresponde a cobranza.</p>
                            <input type="hidden" name="cobranza_factura_sede" id="cobranza_factura_sede" value="">
                            <input type="hidden" name="cobranza_factura_doc" id="cobranza_factura_doc" value="">
                            <input type="hidden" name="cobranza_monto" id="cobranza_monto" value="">
                            <label style="display:block; font-size:0.78rem; font-weight:600; color:#9a3412; margin-bottom:4px;">Buscar factura / deuda</label>
                            <input type="search" id="cobranza_factura_q" placeholder="Cliente, código o factura…" autocomplete="off"
                                style="width:100%; padding:7px 10px; border:1px solid #fdba74; border-radius:6px; font-size:0.85rem; margin-bottom:6px;">
                            <div id="cobranza_factura_res" style="max-height:160px; overflow:auto; display:flex; flex-direction:column; gap:4px;"></div>
                            <div id="cobranza_factura_sel" style="display:none; margin-top:8px; padding:8px 10px; background:#ffedd5; border-radius:6px; font-size:0.82rem; color:#9a3412;"></div>
                            <div style="margin-top:12px; padding-top:10px; border-top:1px dashed #fdba74;">
                                <label for="cobranza_diferencia_justificacion" style="display:block; font-size:0.78rem; font-weight:600; color:#9a3412; margin-bottom:4px;">Justificar faltante (si no es Cobranza)</label>
                                <textarea name="cobranza_diferencia_justificacion" id="cobranza_diferencia_justificacion" rows="2" maxlength="500"
                                    placeholder="Ej.: ajuste de tarifa, descuento del proveedor, error en estimado…"
                                    style="width:100%; padding:7px 10px; border:1px solid #fdba74; border-radius:6px; font-size:0.85rem; resize:vertical;"></textarea>
                            </div>
                        </div>
            </div>

                    @include('finanzas.partials.asiento_egreso', ['sufijo' => ''])
                    </div>
            </div>{{-- /.ne-body --}}

            <div style="margin: 14px 0 0;">
                <div class="ne-sec">Opciones</div>
                <div id="row_opciones_egreso" class="ne-opts">
                    <label style="color: #92400e;">
                        <input type="checkbox" id="chk_asiento" onchange="faToggleAsiento('')" style="accent-color: #f59e0b;">
                        Asiento contable
                    </label>
                    <label id="row_cuenta_por_pagar" style="color: #9a3412;">
                        <input type="checkbox" id="chk_cuenta_por_pagar" name="es_cuenta_por_pagar" value="1" style="accent-color: #ea580c;">
                        Cuenta por pagar
                    </label>
                    <label style="color: #065f46;">
                        <input type="checkbox" id="chk_todoticket" name="es_todoticket" value="1" onchange="toggleTodoTicket()" style="accent-color: #059669;">
                        TodoTicket
                    </label>
                    <label style="color: #1e40af;">
                        <input type="checkbox" id="chk_gasto_fijo" onchange="toggleGastoFijoPanel()" style="accent-color: #3b82f6;">
                        Gasto fijo
                    </label>
                </div>
            </div>

            <div style="margin: 14px 0 12px;">
                <div class="ne-sec">Comprobantes</div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem;">
                    Comprobantes de Pago
                    <span id="comp-counter" style="font-size:0.78rem; color:#64748b; margin-left:6px;">0 / 6</span>
                </label>
                <div id="comp-grid" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;"></div>
                <div id="paste-area" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 14px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s;"
                    onmouseover="if(!pasteAreaDisabled())this.style.borderColor='#3b82f6';"
                    onmouseout="if(!pasteAreaDisabled())this.style.borderColor='#cbd5e1';">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <span id="paste-text" style="display: block; color: #64748b; font-size: 13px;">Haz clic o pega (<b>Ctrl+V</b>) para añadir imagen &bull; máx. 6</span>
                </div>
                <input type="file" name="comprobantes[]" id="comprobante-input" accept="image/*,application/pdf" multiple style="display: none;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; padding-top: 4px; border-top: 1px solid #e2e8f0; margin-top: 4px;">
                <button type="button" onclick="closeNuevoEgresoModal(); sessionStorage.removeItem('pending_ocr_txs'); document.querySelector('#nuevoEgresoModal button[type=\'submit\']').innerText='Guardar Egreso'; document.querySelector('#nuevoEgresoModal button[type=\'submit\']').style.backgroundColor='#1a4273';" style="padding: 10px 20px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancelar</button>
                <button type="submit" style="padding: 10px 20px; background-color: #1a4273; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">Guardar Egreso</button>
            </div>
        </form>
    </div>
</div>

<div id="historialCuentaModal" class="modal-overlay" style="display: none; z-index: 1200;">
    <div class="panel modal-box" style="width: 95%; max-width: 1100px; position: relative; padding: 15px 20px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); max-height: 95vh; overflow-y: auto;">
        <button type="button" class="modal-close" onclick="closeHistorialCuentaPorPagar()" aria-label="Cerrar" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 id="historialCuentaTitulo" style="margin: 0 0 8px; font-size: 1.1rem; color: #9a3412;">Historial</h3>
        <p id="historialCuentaResumen" style="margin: 0 0 12px; color: #64748b; font-size: 0.9rem;"></p>
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
                    </tr>
                </thead>
                <tbody id="historialCuentaBody"></tbody>
            </table>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top: 14px;">
            <button type="button" onclick="closeHistorialCuentaPorPagar()" style="padding: 8px 16px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;">Cerrar</button>
            <button type="button" id="historialCuentaPagarBtn" onclick="" style="padding: 8px 16px; background-color: #1a4273; color: white; border: none; border-radius: 6px; cursor: pointer; display:none;">Pagar</button>
        </div>
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

@php
    $cuentasPorPagarJs = ($cuentas_por_pagar ?? collect())->map(function ($cuenta) {
        return [
            'id' => $cuenta->id,
            'fecha' => optional($cuenta->fecha)->format('Y-m-d'),
            'beneficiario' => $cuenta->beneficiario,
            'tipo_gasto' => $cuenta->tipo_gasto,
            'motivo' => $cuenta->motivo,
            'sede' => $cuenta->sede,
            'moneda' => $cuenta->moneda,
            'monto_total' => (float) $cuenta->monto_total,
            'monto_pagado' => (float) $cuenta->monto_pagado,
            'saldo' => (float) $cuenta->saldo,
            'abierta' => $cuenta->estaAbierta(),
            'pagos' => $cuenta->pagos->map(function ($mov) {
                return [
                    'fecha' => $mov->fecha,
                    'banco' => $mov->banco,
                    'titular' => $mov->titular,
                    'banco_receptor' => $mov->banco_receptor,
                    'titular_receptor' => $mov->titular_receptor,
                    'tipo_gasto' => $mov->tipo_gasto,
                    'motivo' => $mov->motivo,
                    'monto_usd' => (float) $mov->monto_usd,
                    'tasa_cambio' => (float) $mov->tasa_cambio,
                    'diferencial_cambiario' => (float) $mov->diferencial_cambiario,
                    'monto_bs' => (float) $mov->monto_bs,
                    'comision' => (float) $mov->comision,
                ];
            })->values(),
        ];
    })->values();
@endphp

<script>
function selectedTablasReporte() {
    let selected = [];
    if(document.getElementById('rep_egresos')?.checked) selected.push('egreso_realizado');
    if(document.getElementById('rep_otros')?.checked) selected.push('otros_egresos');
    if(document.getElementById('rep_divisas')?.checked) selected.push('egreso_divisas');
    if(document.getElementById('rep_compra')?.checked) selected.push('compra_divisas');
    if(document.getElementById('rep_traslados')?.checked) selected.push('traslados');
    return selected;
}
function descargarReporteAvances(formato) {
    const fDesde = document.getElementById('fecha_desde_avances')?.value || '';
    const fHasta = document.getElementById('fecha_hasta_avances')?.value || '';
    let url = '{{ route("finanzas.flujo_caja.reporte") }}?desde=' + encodeURIComponent(fDesde) + '&hasta=' + encodeURIComponent(fHasta);
    url += '&cats=otros_egresos&formato=' + encodeURIComponent(formato);
    window.open(url, '_blank');
}
function descargarReporteBusqueda(formato) {
    const esPdf = formato === 'pdf';
    const html = `<div style="text-align: left; margin: 15px auto; width: fit-content; display: flex; flex-direction: column; gap: 10px;">
                <label style="cursor: pointer;"><input type="checkbox" id="rep_egresos" value="egreso_realizado" checked style="margin-right: 8px;"> Egresos Realizados</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_otros" value="otros_egresos" checked style="margin-right: 8px;"> Otros Egresos (Avances y Cambios)</label>
                <label style="cursor: pointer;"><input type="checkbox" id="rep_divisas" value="egreso_divisas" checked style="margin-right: 8px;"> Egresos en Divisas</label>
                ${esPdf ? '' : '<label style="cursor: pointer;"><input type="checkbox" id="rep_compra" value="compra_divisas" checked style="margin-right: 8px;"> Compra de Divisas</label>'}
                <label style="cursor: pointer;"><input type="checkbox" id="rep_traslados" value="traslados" checked style="margin-right: 8px;"> Traslados Bancarios</label>
           </div>`;

    Swal.fire({
        title: esPdf ? 'Descargar PDF' : 'Descargar Excel',
        html: html,
        showCancelButton: true,
        confirmButtonText: esPdf ? 'Descargar PDF' : 'Descargar Excel',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: esPdf ? '#7c3aed' : '#059669',
        preConfirm: () => {
            const cats = selectedTablasReporte();
            if (!cats.length) {
                Swal.showValidationMessage('Marca al menos una tabla.');
                return false;
            }
            return cats;
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }
        const fDesde = document.getElementById('fecha_desde_input')?.value || '';
        const fHasta = document.getElementById('fecha_hasta_input')?.value || '';
        const txt = document.getElementById('filtro-texto')?.value || '';
        const ref = document.getElementById('filtro-referencia')?.value || '';
        const cats = result.value || [];
        let url = '{{ route("finanzas.flujo_caja.reporte") }}?desde=' + encodeURIComponent(fDesde) + '&hasta=' + encodeURIComponent(fHasta);
        if (txt) url += '&q=' + encodeURIComponent(txt);
        if (ref) url += '&ref=' + encodeURIComponent(ref);
        url += '&cats=' + encodeURIComponent(cats.join(','));
        url += '&formato=' + encodeURIComponent(formato);
        window.open(url, '_blank');
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

function categoriaCuentaDeValor(valor) {
    const parts = String(valor || '').split('|');
    return (parts[2] || '').toUpperCase();
}
function esBancaInternacionalCat(cat) {
    return String(cat || '').includes('BANCA INTERNACIONAL');
}
function cuentaBancaInternacionalActual() {
    const raw = window.tsBancoTitular?.getValue?.() || document.getElementById('banco_titular')?.value || '';
    return esBancaInternacionalCat(categoriaCuentaDeValor(raw));
}
/** Banca internacional: solo USD (sin tasa ni Bs) y el egreso viaja como egreso en divisas. */
function aplicarBancaInternacional() {
    const sel = document.getElementById('categoria_egreso');
    if (!sel || @json($soloAvances ?? false)) return;
    const intl = cuentaBancaInternacionalActual();
    if (intl && !['ingresos', 'traslados', 'compra_divisas'].includes(sel.value)) {
        sel.value = 'egreso_divisas';
    }
    toggleTraslados();
}

function toggleTraslados() {
    const val = document.getElementById('categoria_egreso').value;
    const isTraslado = val === 'traslados';
    const intl = cuentaBancaInternacionalActual();
    const isDivisas = val === 'egreso_divisas' || (intl && !isTraslado && val !== 'compra_divisas');
    const isCompraDivisas = val === 'compra_divisas';
    const isIngreso = val === 'ingresos';
    const sinTasa = isTraslado || isDivisas || (intl && !isCompraDivisas);
    
    document.getElementById('row_receptor').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor').required = isTraslado;
    
    document.getElementById('lbl_banco_titular').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs').innerText = isTraslado ? 'Monto BS' : 'Monto BS';
    
    document.getElementById('col_monto_usd').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio').style.display = sinTasa ? 'none' : 'block';
    document.getElementById('col_monto_bs').style.display = (isDivisas || (intl && isIngreso)) ? 'none' : 'block';
    
    document.getElementById('row_diferencial').style.display = (isTraslado || isDivisas || isCompraDivisas || intl) ? 'none' : 'flex';
    document.getElementById('row_tipo_gasto').style.display = (isTraslado || isCompraDivisas) ? 'none' : 'block';
    document.getElementById('row_traslado_extra').style.display = isTraslado ? 'flex' : 'none';
    
    // Disable fields that would create duplicate POST keys
    const comisionNormal = document.getElementById('comision');
    const comisionTraslado = document.getElementById('comision_traslado');
    const usdNormal = document.getElementById('monto_usd');
    const usdTraslado = document.getElementById('monto_usd_traslado');
    if (comisionNormal) comisionNormal.disabled = (isTraslado || isDivisas || isCompraDivisas);
    if (comisionTraslado) comisionTraslado.disabled = !isTraslado;
    if (usdNormal) usdNormal.disabled = isTraslado;
    if (usdTraslado) usdTraslado.disabled = !isTraslado;
    
    const bsNormal = document.getElementById('monto_bs');
    const tasaNormal = document.getElementById('tasa_cambio');
    if (bsNormal) {
        bsNormal.disabled = isDivisas || (intl && isIngreso);
        if (bsNormal.disabled) bsNormal.value = '';
    }
    if (tasaNormal && sinTasa) {
        tasaNormal.value = '';
    }
    
    // When switching to traslado mode, auto-calc USD if monto_bs already has value
    if (isTraslado) calcTraslado();
    
    document.getElementById('tipo_gasto').required = !isTraslado && !isCompraDivisas && !isIngreso && !document.getElementById('chk_todoticket')?.checked;
    aplicarModoIngreso(isIngreso);
}

// Ingreso: dinero que entra a la cuenta. Sin diferencial, comisión, desglose ni cuenta por pagar.
function aplicarModoIngreso(isIngreso) {
    const enPagoCuenta = !!document.getElementById('cuenta_por_pagar_id')?.value;
    const set = (id, fn) => { const el = document.getElementById(id); if (el) fn(el); };
    if (isIngreso) {
        document.getElementById('row_diferencial').style.display = 'none';
        set('comision', el => el.disabled = true);
        ['chk_asiento', 'chk_cuenta_por_pagar', 'chk_todoticket', 'chk_gasto_fijo'].forEach(id => set(id, el => {
            if (el.checked) { el.checked = false; el.dispatchEvent(new Event('change')); }
        }));
        set('tipo_gasto', el => el.required = false);
    }
    set('row_opciones_egreso', el => el.style.display = isIngreso ? 'none' : 'flex');
    set('col_placa', el => el.style.display = isIngreso ? 'none' : '');
    set('row_monto_total_gasto', el => el.style.display = (isIngreso || enPagoCuenta) ? 'none' : '');
    set('lbl_banco_titular', el => { if (isIngreso) el.innerText = 'Cuenta que recibe el dinero'; });
    set('lbl_tipo_gasto', el => el.innerText = isIngreso ? 'Tipo de ingreso (opcional)' : 'Tipo de Gasto');
    set('beneficiario_label', el => el.innerText = isIngreso ? 'Quién paga (origen)' : 'Beneficiario');
    set('motivo_egreso', el => el.placeholder = isIngreso ? 'Ej. Cobro de alquiler local 3...' : 'Ej. Pago de internet mensual...');
    if (!enPagoCuenta) {
        set('nuevoEgresoModalTitulo', el => el.textContent = isIngreso ? 'Nuevo Ingreso' : 'Nuevo Egreso');
        const btn = document.querySelector('#nuevoEgresoModal button[type="submit"]');
        if (btn) btn.innerText = isIngreso ? 'Guardar Ingreso' : 'Guardar Egreso';
    }
}

function formatBsVe(n) {
    return (n || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function calcTodoTicket() {
    if (!document.getElementById('chk_todoticket')?.checked) return;
    const num = (id) => Math.abs(window.parseLocalNumber(document.getElementById(id)?.value) || 0);
    const recarga = num('tt_recarga');
    const comision = num('tt_comision');
    const iva = num('tt_iva');
    const ret = num('tt_ret_islr') + num('tt_ret_iva') + num('tt_ret_1x1000') + num('tt_ret_resp_social') + num('tt_ret_isae');
    const total = Math.round((recarga + comision + iva - ret) * 100) / 100;
    document.getElementById('tt_total_real_lbl').innerText = 'Bs. ' + formatBsVe(total);
    const bs = document.getElementById('monto_bs');
    if (bs) bs.value = formatBsVe(total);
    const com = document.getElementById('comision');
    if (com) com.value = formatBsVe(comision);
    syncTodoTicketUsd();
}

function syncTodoTicketUsd() {
    const usd = document.getElementById('monto_usd');
    const tasa = document.getElementById('tasa_cambio');
    const bs = document.getElementById('monto_bs');
    if (!usd || !tasa || !bs) return;
    const tasaN = window.parseLocalNumber(tasa.value) || 0;
    const bsN = window.parseLocalNumber(bs.value) || 0;
    if (tasaN > 0 && bsN > 0) {
        usd.value = (bsN / tasaN).toFixed(2).replace('.', ',');
    }
}

function syncNuevoEgresoSide() {
    const box = document.querySelector('#nuevoEgresoModal .ne-box');
    if (!box) return;
    const open = !!document.getElementById('chk_todoticket')?.checked
        || !!document.getElementById('chk_gasto_fijo')?.checked
        || !!document.getElementById('chk_asiento')?.checked;
    box.classList.toggle('ne-wide', open);
}

function toggleTodoTicket() {
    const on = document.getElementById('chk_todoticket').checked;
    document.getElementById('panel_todoticket').style.display = on ? 'block' : 'none';
    const bs = document.getElementById('monto_bs');
    const usd = document.getElementById('monto_usd');
    const tasa = document.getElementById('tasa_cambio');
    const com = document.getElementById('comision');
    if (bs) bs.readOnly = on;
    if (usd) usd.readOnly = on;
    if (tasa) tasa.readOnly = false;
    if (com) com.readOnly = on;
    if (on) {
        if (window.tsTipoGasto) window.tsTipoGasto.setValue('100 - TODOTICKET');
        else document.getElementById('tipo_gasto').value = '100 - TODOTICKET';
        document.getElementById('tipo_gasto').required = false;
        if (tasa && !(window.parseLocalNumber(tasa.value) > 0)) {
            const bcv = document.querySelector('input[data-field="tasa_bcv_usd"]');
            if (bcv && window.parseLocalNumber(bcv.value) > 0) {
                tasa.value = bcv.value;
            }
        }
        calcTodoTicket();
    } else {
        if (bs) bs.readOnly = false;
        if (usd) usd.readOnly = false;
        document.getElementById('tipo_gasto').required = document.getElementById('categoria_egreso').value !== 'traslados';
        document.getElementById('tt_total_real_lbl').innerText = 'Bs. 0,00';
    }
    syncNuevoEgresoSide();
}

// ── Gasto Fijo Linking Functions ──
let _gfPendientes = null; // cached list
let _gfItems = [];        // gastos fijos que paga este egreso: {gf, mes, anio, monto}
const _gfMeses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const escGf = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function limpiarGastosFijosEgreso() {
    _gfItems = [];
    renderGastoFijoItems();
    const panel = document.getElementById('panel_gasto_fijo');
    if (panel) panel.style.display = 'none';
    syncNuevoEgresoSide();
}

async function toggleGastoFijoPanel() {
    const chk = document.getElementById('chk_gasto_fijo');
    const panel = document.getElementById('panel_gasto_fijo');

    if (!chk.checked) {
        panel.style.display = 'none';
        _gfItems = [];
        renderGastoFijoItems();
        syncNuevoEgresoSide();
        return;
    }

    panel.style.display = 'block';
    syncNuevoEgresoSide();

    // Lazy-load the list only once
    if (!_gfPendientes) {
        const sel = document.getElementById('sel_gasto_fijo');
        if (sel) sel.innerHTML = '<div class="gf-picker-empty">Cargando…</div>';
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
    if (!sel) return;
    const q = (document.getElementById('gf_picker_q')?.value || '').trim().toLowerCase();

    if (!_gfPendientes) {
        sel.innerHTML = '<div class="gf-picker-empty">Cargando…</div>';
        return;
    }

    const disponibles = _gfPendientes.filter(gf => {
        if (_gfItems.some(it => it.gf.id === gf.id)) return false;
        if (!q) return true;
        const haystack = [gf.servicio, gf.empresa, gf.sede, gf.tabla_label].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });

    if (!_gfPendientes.length) {
        sel.innerHTML = '<div class="gf-picker-empty">Sin gastos fijos activos</div>';
        return;
    }
    if (!disponibles.length) {
        sel.innerHTML = `<div class="gf-picker-empty">${q ? 'Sin coincidencias para “' + escGf(q) + '”' : 'Ya agregaste todos los disponibles'}</div>`;
        return;
    }

    const grupos = [
        { key: 'pend', label: 'Pendientes', items: disponibles.filter(g => g.pendiente) },
        { key: 'resto', label: 'Otros', items: disponibles.filter(g => !g.pendiente) },
    ];

    sel.innerHTML = grupos.map(grupo => {
        if (!grupo.items.length) return '';
        const rows = grupo.items.map(gf => {
            const sub = [gf.empresa, gf.sede].filter(Boolean).join(' · ');
            const title = escGf(gf.servicio || 'Gasto fijo');
            const subEsc = escGf(sub);
            const amt = '$' + parseFloat(gf.costo || 0).toFixed(2);
            return `<button type="button" class="gf-picker-item${gf.urgente ? ' is-urgente' : ''}" data-gf-id="${gf.id}" title="${title}${sub ? ' — ' + subEsc : ''}">
                <span style="min-width:0;">
                    <div class="gf-picker-title">${gf.urgente ? '⚡ ' : ''}${title}</div>
                    ${sub ? `<div class="gf-picker-sub">${subEsc}</div>` : ''}
                </span>
                <span class="gf-picker-amt">${amt}</span>
            </button>`;
        }).join('');
        return `<div class="gf-picker-group">${grupo.label}</div>${rows}`;
    }).join('');

    sel.querySelectorAll('[data-gf-id]').forEach(btn => {
        btn.addEventListener('click', () => onGastoFijoSelected(Number(btn.dataset.gfId)));
    });
}

function onGastoFijoSelected(id) {
    id = parseInt(id, 10);
    if (!id || !_gfPendientes) return;

    const gf = _gfPendientes.find(g => g.id === id);
    if (!gf || _gfItems.some(it => it.gf.id === id)) return;

    // Mes por defecto: el pendiente más viejo (con atraso es el mes anterior); si no, el mes actual.
    const hoy = new Date();
    const periodo = (gf.periodos && gf.periodos[0]) || { mes_idx: hoy.getMonth(), anio: hoy.getFullYear() };
    _gfItems.push({ gf, mes: periodo.mes_idx, anio: periodo.anio, monto: parseFloat(gf.costo || 0).toFixed(2) });
    const q = document.getElementById('gf_picker_q');
    if (q) q.value = '';
    buildGastoFijoSelect();
    renderGastoFijoItems();
}

function opcionesMesGastoFijo(item) {
    const hoy = new Date();
    const anioActual = hoy.getFullYear();
    const opciones = [];
    const vistos = new Set();
    (item.gf.periodos || []).forEach(p => {
        const key = p.mes_idx + '|' + p.anio;
        vistos.add(key);
        opciones.push({ key, texto: `${_gfMeses[p.mes_idx]}${p.anio !== anioActual ? ' ' + p.anio : ''} (pendiente)` });
    });
    const pagados = new Set(item.gf.meses_pagados || []);
    for (let m = 0; m <= Math.min(11, hoy.getMonth() + 1); m++) {
        const key = m + '|' + anioActual;
        if (vistos.has(key)) continue;
        opciones.push({ key, texto: `${_gfMeses[m]}${pagados.has(m) ? ' (ya pagado)' : ''}` });
    }
    const actual = item.mes + '|' + item.anio;
    return opciones.map(o => `<option value="${o.key}" ${o.key === actual ? 'selected' : ''}>${o.texto}</option>`).join('');
}

function renderGastoFijoItems() {
    const box = document.getElementById('gf_items');
    const inputs = document.getElementById('gf_items_inputs');
    const totalBox = document.getElementById('gf_items_total');
    if (!box || !inputs) return;

    box.innerHTML = '';
    inputs.innerHTML = '';
    let total = 0;

    _gfItems.forEach((item, idx) => {
        const gf = item.gf;
        total += parseFloat(item.monto) || 0;
        const fila = document.createElement('div');
        fila.className = 'gf-item';
        const detalle = escGf([gf.empresa, gf.sede, gf.tabla_label].filter(Boolean).join(' · '));
        fila.innerHTML = `
            <div class="gf-item-top">
                <div class="gf-item-info">
                    <div class="gf-item-title">${escGf(gf.servicio)}</div>
                    ${detalle ? `<div class="gf-item-sub" title="${detalle}">${detalle}</div>` : ''}
                    <div class="gf-item-meta">Estimado $${parseFloat(gf.costo).toFixed(2)}${gf.meses_atraso > 0 ? ' · paga mes siguiente' : ''}</div>
            </div>
                <button type="button" class="gf-item-del" data-gf-quitar="${idx}" title="Quitar">✖</button>
            </div>
            <div class="gf-item-actions">
                <select data-gf-mes="${idx}">${opcionesMesGastoFijo(item)}</select>
                <input type="number" data-gf-monto="${idx}" step="0.01" min="0" value="${escGf(item.monto)}" placeholder="USD">
            </div>`;
        box.appendChild(fila);

        [['id', gf.id], ['mes_idx', item.mes], ['anio', item.anio], ['monto', item.monto]].forEach(([campo, valor]) => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = `gf_items[${idx}][${campo}]`;
            h.value = valor;
            inputs.appendChild(h);
        });
    });

    box.querySelectorAll('[data-gf-mes]').forEach(el => el.addEventListener('change', () => {
        const [mes, anio] = el.value.split('|').map(Number);
        const item = _gfItems[Number(el.dataset.gfMes)];
        item.mes = mes;
        item.anio = anio;
        renderGastoFijoItems();
    }));
    box.querySelectorAll('[data-gf-monto]').forEach(el => el.addEventListener('change', () => {
        _gfItems[Number(el.dataset.gfMonto)].monto = el.value;
        renderGastoFijoItems();
    }));
    box.querySelectorAll('[data-gf-quitar]').forEach(el => el.addEventListener('click', () => {
        _gfItems.splice(Number(el.dataset.gfQuitar), 1);
        buildGastoFijoSelect();
        renderGastoFijoItems();
    }));

    if (totalBox) {
        totalBox.style.display = _gfItems.length > 1 ? 'block' : 'none';
        totalBox.textContent = `Total de los ${_gfItems.length} gastos fijos: $${total.toFixed(2)}`;
    }
    actualizarPanelCobranzaDiff();
}

function pagoUsdEgresoActual() {
    return window.parseLocalNumber(document.getElementById('monto_usd')?.value) || 0;
}
function totalGfUsdActual() {
    return _gfItems.reduce((s, it) => s + (parseFloat(it.monto) || 0), 0);
}
function limpiarSeleccionCobranzaDiff() {
    const sede = document.getElementById('cobranza_factura_sede');
    const doc = document.getElementById('cobranza_factura_doc');
    if (sede) sede.value = '';
    if (doc) doc.value = '';
    const sel = document.getElementById('cobranza_factura_sel');
    if (sel) { sel.style.display = 'none'; sel.textContent = ''; }
    const box = document.getElementById('cobranza_factura_res');
    if (box) box.innerHTML = '';
    const q = document.getElementById('cobranza_factura_q');
    if (q) q.value = '';
}
function limpiarJustificacionCobranzaDiff() {
    const just = document.getElementById('cobranza_diferencia_justificacion');
    if (just) just.value = '';
}
function actualizarPanelCobranzaDiff() {
    const panel = document.getElementById('panel_cobranza_diff');
    if (!panel) return;
    const total = totalGfUsdActual();
    const pago = pagoUsdEgresoActual();
    const diff = Math.round((total - pago) * 100) / 100;
    const show = _gfItems.length > 0 && diff > 0.009;
    panel.style.display = show ? 'block' : 'none';
    const lbl = document.getElementById('cobranza_diff_lbl');
    if (lbl) lbl.textContent = '$' + diff.toFixed(2).replace('.', ',');
    const monto = document.getElementById('cobranza_monto');
    if (monto && show) monto.value = diff.toFixed(2);
    if (!show) {
        limpiarSeleccionCobranzaDiff();
        limpiarJustificacionCobranzaDiff();
        if (monto) monto.value = '';
    }
}
(function initCobranzaDiffBusqueda() {
    const q = document.getElementById('cobranza_factura_q');
    const box = document.getElementById('cobranza_factura_res');
    if (!q || !box) return;
    let t = null;
    const url = @json(route('finanzas.cobranza_facturas'));
    q.addEventListener('input', () => {
        clearTimeout(t);
        t = setTimeout(async () => {
            const texto = q.value.trim();
            if (texto.length < 2) { box.innerHTML = ''; return; }
            try {
                const res = await fetch(url + '?q=' + encodeURIComponent(texto), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                const facturas = data.facturas || [];
                box.innerHTML = facturas.length ? facturas.map(f => `
                    <button type="button" data-sede="${escGf(f.sede)}" data-doc="${escGf(f.doc)}" data-cliente="${escGf(f.cliente)}" data-numero="${escGf(f.numero)}" data-saldo="${f.saldo}"
                        style="text-align:left; border:1px solid #fed7aa; background:#fff; border-radius:6px; padding:7px 9px; cursor:pointer; font-size:0.8rem;">
                        <strong>${escGf(f.numero)}</strong> · ${escGf(f.cliente)} · saldo $${Number(f.saldo).toFixed(2)}
                    </button>`).join('') : '<span style="font-size:0.78rem;color:#9a3412;">Sin facturas con saldo.</span>';
                box.querySelectorAll('button[data-doc]').forEach(btn => btn.addEventListener('click', () => {
                    document.getElementById('cobranza_factura_sede').value = btn.dataset.sede || '';
                    document.getElementById('cobranza_factura_doc').value = btn.dataset.doc || '';
                    limpiarJustificacionCobranzaDiff();
                    const sel = document.getElementById('cobranza_factura_sel');
                    sel.style.display = 'block';
                    sel.textContent = 'Aplicar a ' + (btn.dataset.numero || '') + ' · ' + (btn.dataset.cliente || '') + ' (saldo $' + Number(btn.dataset.saldo || 0).toFixed(2) + ')';
                    box.innerHTML = '';
                    q.value = '';
                }));
            } catch (e) {
                box.innerHTML = '<span style="font-size:0.78rem;color:#b91c1c;">No se pudo buscar.</span>';
            }
        }, 300);
    });
    document.getElementById('cobranza_diferencia_justificacion')?.addEventListener('input', function () {
        if (this.value.trim().length >= 3) limpiarSeleccionCobranzaDiff();
    });
    document.getElementById('monto_usd')?.addEventListener('input', actualizarPanelCobranzaDiff);
})();

const proveedoresBeneficiario = @json($proveedores->values()->map(fn ($nombre) => ['value' => $nombre, 'text' => $nombre]));
const tecnicosBeneficiario = @json($empleadosServicioTecnico->map(fn ($empleado) => [
    'value' => (string) $empleado->id,
    'text' => $empleado->nombre().' · '.$empleado->cedula(),
])->values());

function actualizarBeneficiariosPorTipoGasto() {
    const tipoActual = window.tsTipoGasto?.getValue?.() || document.getElementById('tipo_gasto')?.value || '';
    const esServicioTecnico = tipoActual === '058 - SERVICIO TECNICO (GARANTIAS)';
    const opciones = esServicioTecnico ? tecnicosBeneficiario : proveedoresBeneficiario;
    const select = document.getElementById('beneficiario');
    const label = document.getElementById('beneficiario_label');
    const ayuda = document.getElementById('beneficiario_ayuda');
    const ayudaLibre = document.getElementById('beneficiario_ayuda_libre');

    if (!select) return;
    select.required = esServicioTecnico;
    const nominaId = document.getElementById('nomina_empleado_id');
    if (nominaId) nominaId.value = '';
    const esIngreso = document.getElementById('categoria_egreso')?.value === 'ingresos';
    if (label) label.textContent = esServicioTecnico ? 'Empleado de Servicio Técnico' : (esIngreso ? 'Quién paga (origen)' : 'Beneficiario');
    if (ayuda) ayuda.style.display = esServicioTecnico ? 'block' : 'none';
    if (ayudaLibre) ayudaLibre.style.display = esServicioTecnico ? 'none' : 'block';

    if (window.tsBeneficiario) {
        window.tsBeneficiario.clear(true);
        window.tsBeneficiario.clearOptions();
        window.tsBeneficiario.addOption(opciones);
        window.tsBeneficiario.refreshOptions(false);
        window.tsBeneficiario.settings.create = !esServicioTecnico;
        window.tsBeneficiario.settings.createOnBlur = !esServicioTecnico;
        window.tsBeneficiario.settings.placeholder = esServicioTecnico
            ? '-- Seleccione el empleado --'
            : 'Seleccione o escriba un beneficiario';
        window.tsBeneficiario.inputState();
        return;
    }

    select.innerHTML = '<option value="">' +
        (esServicioTecnico ? '-- Seleccione el empleado --' : 'Seleccione o escriba un beneficiario') +
        '</option>';
    opciones.forEach(opcion => select.add(new Option(opcion.text, opcion.value)));
}

function actualizarTecnicoEdit(selected = null) {
    const tipo = document.getElementById('edit_tipo_gasto')?.value;
    const row = document.getElementById('row_tecnico_edit');
    const select = document.getElementById('edit_nomina_empleado_id');
    if (!row || !select) return;

    const visible = tipo === '058 - SERVICIO TECNICO (GARANTIAS)';
    row.style.display = visible ? 'block' : 'none';
    select.required = visible;
    if (selected !== null) select.value = String(selected || '');
    if (!visible) select.value = '';

    const rowBeneficiario = document.getElementById('row_beneficiario_edit');
    const esTraslado = document.getElementById('row_receptor_edit')?.style.display === 'flex';
    if (rowBeneficiario) {
        rowBeneficiario.style.display = (visible || esTraslado) ? 'none' : 'block';
    }
}

function setBeneficiarioEdit(valor) {
    const ts = window.tsBeneficiarioEdit;
    const nombre = (valor || '').trim();
    if (!ts) return;
    if (!nombre) {
        ts.clear(true);
        return;
    }
    if (!ts.options[nombre]) {
        ts.addOption({ value: nombre, text: nombre });
    }
    ts.setValue(nombre, true);
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
    if (srcTG) {
        window.tsTipoGasto = new TomSelect("#tipo_gasto", Object.assign({}, tsSettings, {
            onChange: function () { actualizarBeneficiariosPorTipoGasto(); },
        }));
    }
    if (dstTG) window.tsEditTipoGasto = new TomSelect("#edit_tipo_gasto", tsSettings);
    dstTG?.addEventListener('change', () => actualizarTecnicoEdit());

    const tsBankSettings = {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: '-- Seleccione --',
        maxOptions: null
    };
    if (document.getElementById('banco_titular')) {
        window.tsBancoTitular = new TomSelect("#banco_titular", Object.assign({}, tsBankSettings, {
            onChange() { if (typeof aplicarBancaInternacional === 'function') aplicarBancaInternacional(); }
        }));
    }
    if (document.getElementById('banco_titular_receptor')) window.tsBancoTitularReceptor = new TomSelect("#banco_titular_receptor", tsBankSettings);
    document.getElementById('banco_titular')?.addEventListener('change', () => {
        if (typeof aplicarBancaInternacional === 'function') aplicarBancaInternacional();
    });
    if (document.getElementById('beneficiario_edit')) {
        window.tsBeneficiarioEdit = new TomSelect("#beneficiario_edit", {
            create: true,
            createOnBlur: true,
            persist: true,
            sortField: { field: "text", direction: "asc" },
            placeholder: 'Seleccione o escriba un beneficiario',
            maxOptions: null,
            render: {
                option_create: function (data, escape) {
                    return '<div class="create">Usar «<strong>' + escape(data.input) + '</strong>»</div>';
                }
            }
        });
    }
    if (document.getElementById('beneficiario')) {
        window.tsBeneficiario = new TomSelect("#beneficiario", {
            create: true,
            createOnBlur: true,
            persist: true,
            sortField: { field: "text", direction: "asc" },
            placeholder: 'Seleccione o escriba un beneficiario',
            maxOptions: null,
            render: {
                option_create: function (data, escape) {
                    return '<div class="create">Usar «<strong>' + escape(data.input) + '</strong>»</div>';
                }
            }
        });
    }
    document.getElementById('beneficiario')?.addEventListener('change', function () {
        const esServicioTecnico = (window.tsTipoGasto?.getValue?.() || srcTG?.value) === '058 - SERVICIO TECNICO (GARANTIAS)';
        document.getElementById('nomina_empleado_id').value = esServicioTecnico ? this.value : '';
    });
    actualizarBeneficiariosPorTipoGasto();

    window.CUENTAS_POR_PAGAR = @json($cuentasPorPagarJs);

    function fmtNum(n) {
        const v = Number(n || 0);
        return v ? v.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-';
    }

    window.resetNuevoEgresoCuenta = function() {
        const chkGf = document.getElementById('chk_gasto_fijo');
        if (chkGf) chkGf.checked = false;
        if (typeof limpiarGastosFijosEgreso === 'function') limpiarGastosFijosEgreso();
        const idInput = document.getElementById('cuenta_por_pagar_id');
        const banner = document.getElementById('banner_pago_cuenta');
        const titulo = document.getElementById('nuevoEgresoModalTitulo');
        const chk = document.getElementById('chk_cuenta_por_pagar');
        const rowChk = document.getElementById('row_cuenta_por_pagar');
        const rowTotal = document.getElementById('row_monto_total_gasto');
        const totalInput = document.getElementById('monto_total_gasto');
        if (idInput) idInput.value = '';
        const adelantoIds = document.getElementById('adelanto_abono_ids');
        if (adelantoIds) adelantoIds.value = '';
        const pagoManualId = document.getElementById('pago_manual_id');
        if (pagoManualId) pagoManualId.value = '';
        if (banner) { banner.style.display = 'none'; banner.textContent = ''; }
        if (titulo) titulo.textContent = 'Nuevo Egreso';
        if (chk) chk.checked = false;
        if (rowChk) rowChk.style.display = 'flex';
        if (rowTotal) rowTotal.style.display = '';
        if (totalInput) totalInput.value = '';
        const submitBtn = document.querySelector('#nuevoEgresoModal button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Guardar Egreso';
        // No dejar pegado el tipo 058 (modo Servicio Técnico) de un egreso anterior.
        if (window.tsTipoGasto) {
            window.tsTipoGasto.clear(true);
        } else {
            const tg = document.getElementById('tipo_gasto');
            if (tg) tg.value = '';
        }
        if (typeof actualizarBeneficiariosPorTipoGasto === 'function') {
            actualizarBeneficiariosPorTipoGasto();
        }
    };

    window.openNuevoEgresoModal = function() {
        window.resetNuevoEgresoCuenta();
        if (typeof toggleTraslados === 'function') toggleTraslados();
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };
    window.closeNuevoEgresoModal = function() {
        document.getElementById('nuevoEgresoModal').style.display = 'none';
        window.resetNuevoEgresoCuenta();
        // Reset multi-comprobante
        multiFiles = [];
        renderCompGrid();
    };

    window.openHistorialCuentaPorPagar = function(id) {
        const cuenta = (window.CUENTAS_POR_PAGAR || []).find(c => Number(c.id) === Number(id));
        if (!cuenta) return;
        const sim = String(cuenta.moneda).toUpperCase() === 'BS' ? 'Bs. ' : '$';
        document.getElementById('historialCuentaTitulo').textContent = 'Historial — ' + (cuenta.beneficiario || cuenta.motivo || ('Cuenta #' + cuenta.id));
        document.getElementById('historialCuentaResumen').textContent =
            'Total ' + sim + fmtNum(cuenta.monto_total) + ' · Pagado ' + sim + fmtNum(cuenta.monto_pagado) + ' · Saldo ' + sim + fmtNum(cuenta.saldo);
        const body = document.getElementById('historialCuentaBody');
        body.innerHTML = (cuenta.pagos || []).map(mov => {
            const destino = mov.banco_receptor || mov.titular_receptor
                ? `<div style="color:#94a3b8;font-size:1.2rem;">➔</div>
                   <div><strong style="color:#10b981;">${mov.banco_receptor || ''}</strong><br><span class="muted" style="font-size:0.85rem;">${mov.titular_receptor || ''}</span></div>`
                : '';
            return `<tr>
                <td>${mov.fecha || '-'}</td>
                <td>
                    <div style="display:flex;gap:15px;align-items:center;">
                        <div><strong style="color:var(--blue);">${mov.banco || ''}</strong><br><span class="muted" style="font-size:0.85rem;">${mov.titular || ''}</span></div>
                        ${destino}
                    </div>
                </td>
                <td>${mov.tipo_gasto || '-'}</td>
                <td>${mov.motivo || '-'}</td>
                <td class="col-number" style="text-align:right;">${mov.monto_usd ? '$' + fmtNum(mov.monto_usd) : '-'}</td>
                <td class="col-number" style="text-align:right;">${mov.tasa_cambio ? fmtNum(mov.tasa_cambio) : '-'}</td>
                <td class="col-number" style="text-align:right;color:var(--danger);">${mov.diferencial_cambiario ? fmtNum(mov.diferencial_cambiario) : '-'}</td>
                <td class="col-number" style="text-align:right;">${mov.monto_bs ? 'Bs.' + fmtNum(mov.monto_bs) : '-'}</td>
                <td class="col-number" style="text-align:right;">${mov.comision ? fmtNum(mov.comision) : '-'}</td>
            </tr>`;
        }).join('') || `<tr><td colspan="9" style="text-align:center;padding:20px;color:#64748b;">Sin pagos registrados.</td></tr>`;
        const pagarBtn = document.getElementById('historialCuentaPagarBtn');
        if (cuenta.abierta) {
            pagarBtn.style.display = 'inline-block';
            pagarBtn.onclick = function() { closeHistorialCuentaPorPagar(); openPagoCuentaPorPagar(cuenta.id); };
        } else {
            pagarBtn.style.display = 'none';
        }
        document.getElementById('historialCuentaModal').style.display = 'flex';
    };

    window.closeHistorialCuentaPorPagar = function() {
        document.getElementById('historialCuentaModal').style.display = 'none';
    };

    window.openPagoCuentaPorPagar = function(id) {
        const cuenta = (window.CUENTAS_POR_PAGAR || []).find(c => Number(c.id) === Number(id));
        if (!cuenta || !cuenta.abierta) return;
        window.resetNuevoEgresoCuenta();
        document.getElementById('cuenta_por_pagar_id').value = cuenta.id;
        document.getElementById('nuevoEgresoModalTitulo').textContent = 'Pagar cuenta por pagar';
        const sim = String(cuenta.moneda).toUpperCase() === 'BS' ? 'Bs. ' : '$';
        const banner = document.getElementById('banner_pago_cuenta');
        banner.style.display = 'block';
        banner.textContent = 'Abono a ' + (cuenta.beneficiario || cuenta.motivo || ('cuenta #' + cuenta.id)) +
            '. Saldo pendiente: ' + sim + fmtNum(cuenta.saldo) + ' de ' + sim + fmtNum(cuenta.monto_total) + '. Completa los mismos campos de un egreso.';
        document.getElementById('row_cuenta_por_pagar').style.display = 'none';
        document.getElementById('row_monto_total_gasto').style.display = 'none';
        const chk = document.getElementById('chk_cuenta_por_pagar');
        if (chk) chk.checked = false;
        const motivo = document.querySelector('#formNuevoEgreso input[name="motivo"]');
        if (motivo && !motivo.value && cuenta.motivo) motivo.value = cuenta.motivo;
        if (window.tsTipoGasto && cuenta.tipo_gasto) {
            window.tsTipoGasto.setValue(cuenta.tipo_gasto);
        }
        actualizarBeneficiariosPorTipoGasto();
        if (window.tsBeneficiario && cuenta.beneficiario) {
            try {
                window.tsBeneficiario.addOption({ value: cuenta.beneficiario, text: cuenta.beneficiario });
                window.tsBeneficiario.setValue(cuenta.beneficiario);
            } catch (e) {}
        }
        const submitBtn = document.querySelector('#nuevoEgresoModal button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Registrar pago';
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };

    (function autoAbrirPagoCuenta() {
        const id = new URLSearchParams(window.location.search).get('pagar_cuenta');
        if (!id) return;
        setTimeout(function () {
            if (typeof window.openPagoCuentaPorPagar === 'function') {
                window.openPagoCuentaPorPagar(id);
            }
        }, 200);
    })();

    window.openPagoAdelantoSinEmpresa = function(params) {
        window.resetNuevoEgresoCuenta();
        const cat = document.getElementById('categoria_egreso');
        if (cat) {
            cat.value = 'egreso_realizado';
            if (typeof toggleTraslados === 'function') toggleTraslados();
        }
        document.getElementById('nuevoEgresoModalTitulo').textContent = 'Pagar adelanto (sin empresa)';
        const banner = document.getElementById('banner_pago_cuenta');
        if (banner) {
            banner.style.display = 'block';
            banner.textContent = 'Pago manual de adelanto: ' + (params.beneficiario || '') +
                '. Completa banco/referencia y guarda la factura.';
        }
        const idsInput = document.getElementById('adelanto_abono_ids');
        if (idsInput) idsInput.value = params.abono_ids || '';
        const empInput = document.getElementById('nomina_empleado_id');
        if (empInput && params.nomina_empleado_id) empInput.value = params.nomina_empleado_id;
        const motivo = document.querySelector('#formNuevoEgreso input[name="motivo"]');
        if (motivo && params.motivo) motivo.value = params.motivo;
        const fecha = document.querySelector('#formNuevoEgreso input[name="fecha"]');
        if (fecha && params.fecha) fecha.value = params.fecha;
        const montoUsd = document.getElementById('monto_usd');
        if (montoUsd && params.monto_usd) montoUsd.value = params.monto_usd;
        const montoBs = document.getElementById('monto_bs');
        if (montoBs && params.monto_bs) montoBs.value = params.monto_bs;
        const tasa = document.getElementById('tasa_cambio');
        if (tasa && params.tasa_cambio) tasa.value = params.tasa_cambio;
        if (window.tsBeneficiario && params.beneficiario) {
            try {
                window.tsBeneficiario.addOption({ value: params.beneficiario, text: params.beneficiario });
                window.tsBeneficiario.setValue(params.beneficiario);
            } catch (e) {}
        }
        const submitBtn = document.querySelector('#nuevoEgresoModal button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Registrar pago';
        document.getElementById('nuevoEgresoModal').style.display = 'flex';
    };

    (function autoAbrirPagoAdelanto() {
        const qs = new URLSearchParams(window.location.search);
        if (qs.get('pagar_adelanto') !== '1') return;
        setTimeout(function () {
            if (typeof window.openPagoAdelantoSinEmpresa === 'function') {
                window.openPagoAdelantoSinEmpresa({
                    beneficiario: qs.get('beneficiario') || '',
                    monto_usd: qs.get('monto_usd') || '',
                    monto_bs: qs.get('monto_bs') || '',
                    tasa_cambio: qs.get('tasa_cambio') || '',
                    motivo: qs.get('motivo') || '',
                    nomina_empleado_id: qs.get('nomina_empleado_id') || '',
                    abono_ids: qs.get('abono_ids') || '',
                    fecha: qs.get('fecha') || '',
                });
            }
        }, 250);
    })();

    window.openPagoManualNomina = function(params) {
        window.openPagoAdelantoSinEmpresa({
            beneficiario: params.beneficiario || '',
            monto_usd: params.monto_usd || '',
            monto_bs: params.monto_bs || '',
            tasa_cambio: params.tasa_cambio || '',
            motivo: params.motivo || '',
            nomina_empleado_id: params.nomina_empleado_id || '',
            abono_ids: '',
            fecha: params.fecha || '',
        });
        document.getElementById('nuevoEgresoModalTitulo').textContent = 'Pagar nómina/comisión (sin empresa)';
        const banner = document.getElementById('banner_pago_cuenta');
        if (banner) {
            banner.style.display = 'block';
            banner.textContent = 'Pago manual de ' + (params.beneficiario || 'persona sin empresa') +
                '. Completa banco/referencia y guarda la factura.';
        }
        const pagoId = document.getElementById('pago_manual_id');
        if (pagoId) pagoId.value = params.pago_manual_id || '';
    };

    (function autoAbrirPagoManualNomina() {
        const qs = new URLSearchParams(window.location.search);
        if (qs.get('pagar_manual_nomina') !== '1') return;
        setTimeout(function () {
            if (typeof window.openPagoManualNomina === 'function') {
                window.openPagoManualNomina({
                    beneficiario: qs.get('beneficiario') || '',
                    monto_usd: qs.get('monto_usd') || '',
                    monto_bs: qs.get('monto_bs') || '',
                    tasa_cambio: qs.get('tasa_cambio') || '',
                    motivo: qs.get('motivo') || '',
                    nomina_empleado_id: qs.get('nomina_empleado_id') || '',
                    pago_manual_id: qs.get('pago_manual_id') || '',
                    fecha: qs.get('fecha') || '',
                });
            }
        }, 250);
    })();

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
        const esTodoTicket = document.getElementById('chk_todoticket')?.checked;

        if (esTodoTicket) {
            if (tasa > 0 && bs > 0) {
                usdInput.value = (bs / tasa).toFixed(2).replace(".", ",");
            }
        } else if (tasa > 0) {
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
    const chkCuenta = document.getElementById('chk_cuenta_por_pagar');
    const cuentaId = document.getElementById('cuenta_por_pagar_id')?.value;
    if (chkCuenta && chkCuenta.checked && !cuentaId) {
        const total = window.parseLocalNumber(document.getElementById('monto_total_gasto')?.value) || 0;
        if (total <= 0) {
            alert('Indica el monto total del gasto para marcar esta cuenta por pagar.');
            event.preventDefault();
            return false;
        }
    }
    const panelDiff = document.getElementById('panel_cobranza_diff');
    if (panelDiff && panelDiff.style.display !== 'none') {
        const sede = (document.getElementById('cobranza_factura_sede')?.value || '').trim();
        const doc = (document.getElementById('cobranza_factura_doc')?.value || '').trim();
        const just = (document.getElementById('cobranza_diferencia_justificacion')?.value || '').trim();
        if ((!sede || !doc) && just.length < 5) {
            alert('Hay un faltante (gasto mayor al pago). Elige una factura de Cobranza o escribe la justificación (mín. 5 caracteres).');
            event.preventDefault();
            document.getElementById('cobranza_diferencia_justificacion')?.focus();
            return false;
        }
    }
    return faValidarAsiento('', event);
}

// ── Asiento contable del egreso: un debe y uno o varios haber en $ que suman lo mismo ──
function faCaja(s) { return document.getElementById('container_asiento' + s); }
function faNum(v) { return Math.round((parseFloat(String(v ?? '').replace(',', '.')) || 0) * 100) / 100; }
function faFmt(n) { return '$' + n.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

// Monto en $ del egreso: el USD escrito o el Bs entre la tasa.
function faMontoEgreso(s) {
    const form = faCaja(s).closest('form');
    const usd = window.parseLocalNumber(form.querySelector('[name="monto_usd"]')?.value) || 0;
    if (usd > 0) return Math.round(usd * 100) / 100;
    const bs = window.parseLocalNumber(form.querySelector('[name="monto_bs"]')?.value) || 0;
    const tasa = window.parseLocalNumber(form.querySelector('[name="tasa_cambio"]')?.value) || 0;
    return bs > 0 && tasa > 0 ? Math.round(bs / tasa * 100) / 100 : 0;
}

function faToggleAsiento(s) {
    const on = document.getElementById('chk_asiento' + s).checked;
    const caja = faCaja(s);
    caja.style.display = on ? 'block' : 'none';
    document.getElementById('asiento_activo' + s).value = on ? '1' : '0';
    caja.querySelectorAll('input:not([type="hidden"])').forEach(i => { i.disabled = !on; });
    if (on && !document.getElementById('haberes_asiento' + s).children.length) faAgregarHaber(s);
    faTotalesAsiento(s);
    if (s === '') syncNuevoEgresoSide();
}

// El debe es siempre el monto en $ del egreso: se sigue al USD, Bs o tasa de arriba.
document.addEventListener('DOMContentLoaded', () => ['', '_edit'].forEach(s => {
    const form = faCaja(s)?.closest('form');
    if (!form) return;
    const seguir = e => {
        if (e.target.closest('#container_asiento' + s)) return;
        if (document.getElementById('chk_asiento' + s)?.checked) faTotalesAsiento(s);
    };
    form.addEventListener('input', seguir);
    form.addEventListener('change', seguir);
}));

@php
    $faSedes = array_values(array_unique(array_merge(config('inventario.sedes_locales', []), config('inventario.sedes_finanzas_extra', []))));
    $faTiposGasto = array_values(array_diff($tiposGasto ?? [], ['001 - COMPRA DE MERCANCIA']));
@endphp
window.FA_SEDES = @json($faSedes);
window.FA_TIPOS_GASTO = @json($faTiposGasto);

function faOptsSelect(items, selected, placeholder) {
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    let html = `<option value="">${esc(placeholder)}</option>`;
    (items || []).forEach(v => {
        html += `<option value="${esc(v)}" ${String(v) === String(selected || '') ? 'selected' : ''}>${esc(v)}</option>`;
    });
    return html;
}

function faDefaultsEgreso(s) {
    const form = faCaja(s)?.closest('form');
    const sede = form?.querySelector('[name="sede"]')?.value || '';
    const tipo = s === '_edit'
        ? (window.tsEditTipoGasto?.getValue?.() || form?.querySelector('[name="tipo_gasto"]')?.value || '')
        : (window.tsTipoGasto?.getValue?.() || form?.querySelector('[name="tipo_gasto"]')?.value || '');
    const beneficiario = s === '_edit'
        ? (window.tsBeneficiarioEdit?.getValue?.() || form?.querySelector('[name="beneficiario"]')?.value || '')
        : (window.tsBeneficiario?.getValue?.() || form?.querySelector('[name="beneficiario"]')?.value || '');
    const placa = form?.querySelector('[name="placa_vehiculo"]')?.value || '';
    return { sede, tipo_gasto: tipo, beneficiario, placa };
}

function faAgregarHaber(s, linea = {}) {
    const lista = document.getElementById('haberes_asiento' + s);
    const i = Date.now() + Math.floor(Math.random() * 1000);
    const fila = document.createElement('div');
    fila.className = 'fa-haber';
    fila.style.cssText = 'display:flex; flex-direction:column; gap:6px; margin-bottom:8px; background:white; padding:8px; border:1px solid #fde68a; border-radius:6px;';
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const inp = 'min-width:0; padding:6px; border:1px solid #ccc; border-radius:4px; font:inherit; background:#fff;';
    const defs = faDefaultsEgreso(s);
    const sedeSel = linea.sede !== undefined && linea.sede !== null ? linea.sede : defs.sede;
    const tipoSel = linea.tipo_gasto !== undefined && linea.tipo_gasto !== null ? linea.tipo_gasto : defs.tipo_gasto;
    const benefVal = linea.beneficiario !== undefined && linea.beneficiario !== null ? linea.beneficiario : defs.beneficiario;
    const placaVal = linea.placa !== undefined && linea.placa !== null
        ? linea.placa
        : (linea.placa_vehiculo !== undefined && linea.placa_vehiculo !== null ? linea.placa_vehiculo : defs.placa);
    fila.innerHTML = `
        <div style="display:flex; gap:6px; align-items:center;">
            <input type="text" name="asiento_haber[${i}][detalle]" class="fa-haber-detalle" maxlength="255" placeholder="Detalle" value="${esc(linea.detalle)}" style="flex:1; ${inp}">
            <input type="number" step="0.01" min="0" name="asiento_haber[${i}][monto]" class="fa-haber-monto" placeholder="Monto $" value="${linea.monto ?? ''}" style="width:96px; ${inp}">
            <button type="button" title="Quitar" style="padding:6px 10px; background:#ef4444; color:white; border:none; border-radius:4px; cursor:pointer; flex-shrink:0;">&times;</button>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
            <input type="text" name="asiento_haber[${i}][beneficiario]" class="fa-haber-beneficiario" maxlength="255" placeholder="Beneficiario (opc.)" value="${esc(benefVal)}" style="${inp}" title="Beneficiario de este haber">
            <select name="asiento_haber[${i}][sede]" class="fa-haber-sede" style="${inp}" title="Sede de este haber">
                ${faOptsSelect(window.FA_SEDES, sedeSel, 'Sede (opc.)')}
                    </select>
            <select name="asiento_haber[${i}][tipo_gasto]" class="fa-haber-tipo" style="${inp}" title="Tipo de gasto de este haber">
                ${faOptsSelect(window.FA_TIPOS_GASTO, tipoSel, 'Tipo de gasto (opc.)')}
                    </select>
            <input type="text" name="asiento_haber[${i}][placa]" class="fa-haber-placa" maxlength="40" placeholder="Placa (si aplica)" value="${esc(placaVal)}" style="${inp}" title="Placa del vehículo, si aplica">
        </div>`;
    fila.querySelector('button').addEventListener('click', () => {
        if (lista.children.length > 1) { fila.remove(); faTotalesAsiento(s); }
    });
    fila.querySelector('.fa-haber-monto').addEventListener('input', () => faTotalesAsiento(s));
    lista.appendChild(fila);
    faTotalesAsiento(s);
}

function faPartesIguales(s) {
    const debe = faMontoEgreso(s);
    const montos = [...document.querySelectorAll('#haberes_asiento' + s + ' .fa-haber-monto')];
    if (debe <= 0 || !montos.length) return;
    const parte = Math.floor(debe / montos.length * 100) / 100;
    montos.forEach((inp, k) => {
        inp.value = (k === montos.length - 1 ? Math.round((debe - parte * (montos.length - 1)) * 100) / 100 : parte).toFixed(2);
    });
    faTotalesAsiento(s);
}

// Cuenta del debe: el Banco y Titular elegido arriba.
function faCuentaEgreso(s) {
    const [banco, titular] = String(faCaja(s).closest('form').querySelector('[name="banco_titular"]')?.value || '').split('|');
    return [banco, titular].map(v => (v || '').trim()).filter(Boolean).join(' · ').toUpperCase();
}

function faTotalesAsiento(s) {
    const debe = faMontoEgreso(s);
    const caja = faCaja(s);
    caja.querySelector('.fa-debe-cuenta').value = faCuentaEgreso(s);
    caja.querySelector('.fa-debe-detalle').value = caja.closest('form').querySelector('[name="motivo"]')?.value.trim() || '';
    caja.querySelector('.fa-debe-monto').value = debe > 0 ? debe.toFixed(2) : '';
    let haber = 0;
    document.querySelectorAll('#haberes_asiento' + s + ' .fa-haber-monto').forEach(inp => { haber += faNum(inp.value); });
    haber = Math.round(haber * 100) / 100;
    const cuadra = debe > 0 && Math.abs(debe - haber) < 0.01;
    const el = document.getElementById('estado_asiento' + s);
    el.textContent = `Debe ${faFmt(debe)} · Haber ${faFmt(haber)}` + (cuadra ? ' ✓ Cuadra' : (haber > debe ? ' · sobra ' + faFmt(haber - debe) : ' · falta ' + faFmt(debe - haber)));
    el.style.color = cuadra ? '#059669' : '#dc2626';
    return cuadra;
}

function faValidarAsiento(s, event) {
    const chk = document.getElementById('chk_asiento' + s);
    if (!chk || !chk.checked) return true;
    const caja = faCaja(s);
    const fallar = msg => { alert('Asiento contable: ' + msg); event.preventDefault(); return false; };
    if (faMontoEgreso(s) <= 0) return fallar('primero pon el monto del egreso.');
    if (!faCuentaEgreso(s)) return fallar('elige el Banco y Titular del egreso: es la cuenta del debe.');
    const filas = [...caja.querySelectorAll('.fa-haber')];
    if (filas.some(f => !f.querySelector('.fa-haber-detalle').value.trim() || faNum(f.querySelector('.fa-haber-monto').value) <= 0)) {
        return fallar('cada haber necesita su detalle y su monto en $.');
    }
    if (!faTotalesAsiento(s)) return fallar('el debe y el haber no cuadran.');
    return true;
}

// Al editar: vacía el asiento y, si el egreso tiene uno, lo carga.
function faCargarAsiento(s, asiento) {
    const caja = faCaja(s);
    document.getElementById('haberes_asiento' + s).innerHTML = '';
    const tiene = asiento && asiento.debe;
    if (tiene) {
        (asiento.haber || []).forEach(h => faAgregarHaber(s, h));
    }
    document.getElementById('chk_asiento' + s).checked = !!tiene;
    faToggleAsiento(s);
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

function tablaAsiento(asiento) {
    const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const td = 'padding: 7px 8px; border-bottom: 1px solid #fde68a;';
    const meta = (l) => {
        const partes = [];
        if (l.beneficiario) partes.push('Benef.: ' + l.beneficiario);
        if (l.sede) partes.push('Sede: ' + l.sede);
        if (l.tipo_gasto) partes.push(l.tipo_gasto);
        if (l.placa || l.placa_vehiculo) partes.push('Placa: ' + (l.placa || l.placa_vehiculo));
        return partes.join(' · ');
    };
    const fila = (l, alDebe) => {
        const extra = !alDebe ? meta(l) : '';
        return `<tr>
        <td style="${td}${alDebe ? '' : ' padding-left: 24px;'}">${l.cuenta ? `<strong>${esc(l.cuenta)}</strong>` : ''}${l.detalle ? `<div style="${l.cuenta ? 'font-size: 0.78rem; color: #64748b;' : ''}">${esc(l.detalle)}</div>` : ''}${extra ? `<div style="font-size: 0.72rem; color: #b45309; margin-top: 2px;">${esc(extra)}</div>` : ''}</td>
        <td style="${td} text-align: right;">${alDebe ? faFmt(faNum(l.monto)) : ''}</td>
        <td style="${td} text-align: right;">${alDebe ? '' : faFmt(faNum(l.monto))}</td>
    </tr>`;
    };
    const haber = asiento.haber || [];
    const total = haber.reduce((t, h) => t + faNum(h.monto), 0);
    return `<table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
        <thead><tr style="color: #92400e; text-align: left;"><th style="padding: 6px 8px;">Cuenta / detalle</th><th style="padding: 6px 8px; text-align: right;">Debe</th><th style="padding: 6px 8px; text-align: right;">Haber</th></tr></thead>
        <tbody>${fila(asiento.debe, true)}${haber.map(h => fila(h, false)).join('')}</tbody>
        <tfoot><tr style="font-weight: 700;"><td style="padding: 7px 8px;">Total</td><td style="padding: 7px 8px; text-align: right;">${faFmt(faNum(asiento.debe.monto))}</td><td style="padding: 7px 8px; text-align: right;">${faFmt(total)}</td></tr></tfoot>
    </table>`;
}

function verAsiento(asiento) {
    document.getElementById('modalAsientoTabla').innerHTML = tablaAsiento(asiento);
    document.getElementById('modalAsiento').style.display = 'flex';
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
function cuentaBancaInternacionalEdit() {
    const raw = document.getElementById('banco_titular_edit')?.value || '';
    return esBancaInternacionalCat(categoriaCuentaDeValor(raw));
}
function aplicarCategoriaEdit(categoria, cambioManual) {
    const isTraslado = (categoria === 'traslados');
    const isIngreso = (categoria === 'ingresos');
    const intl = cuentaBancaInternacionalEdit();
    const isDivisas = categoria === 'egreso_divisas' || (intl && !isTraslado && categoria !== 'compra_divisas');
    const sinTasa = isTraslado || isDivisas || (intl && !isIngreso);
    document.getElementById('titulo_editar_egreso').innerText = isIngreso ? '✏️ Editar Ingreso' : '✏️ Editar Egreso';
    const rowCategoria = document.getElementById('row_categoria_edit');
    if (rowCategoria && !@json($soloAvances ?? false)) rowCategoria.style.display = isIngreso ? 'none' : 'block';
    const lblBenef = document.getElementById('lbl_beneficiario_edit');
    if (lblBenef) lblBenef.innerText = isIngreso ? 'Origen (quién pagó)' : 'Beneficiario';

    document.getElementById('row_receptor_edit').style.display = isTraslado ? 'flex' : 'none';
    document.getElementById('banco_titular_receptor_edit').required = isTraslado;

    document.getElementById('lbl_banco_titular_edit').innerText = isTraslado ? 'Banco Emisor y Titular Emisor' : 'Banco y Titular';
    document.getElementById('lbl_monto_bs_edit').innerText = isTraslado ? 'Monto' : 'Monto BS';

    document.getElementById('col_monto_usd_edit').style.display = isTraslado ? 'none' : 'block';
    document.getElementById('col_tasa_cambio_edit').style.display = sinTasa ? 'none' : 'block';
    const colBsEdit = document.getElementById('col_monto_bs_edit');
    if (colBsEdit) colBsEdit.style.display = (isDivisas || (intl && isIngreso)) ? 'none' : 'block';
    const rowDifEdit = document.getElementById('row_diferencial_edit');
    if (rowDifEdit) rowDifEdit.style.display = (isTraslado || isDivisas || intl) ? 'none' : 'block';

    document.getElementById('row_tipo_gasto_edit').style.display = (isTraslado || isIngreso) ? 'none' : 'block';
    // Para no dar error con TomSelect requerimos el elemento base
    const dstTG = document.getElementById('edit_tipo_gasto');
    if (dstTG) dstTG.required = !isTraslado && !isIngreso;

    const sel = document.getElementById('categoria_egreso_edit');
    if (sel && intl && !['ingresos', 'traslados', 'compra_divisas'].includes(sel.value)) {
        sel.value = 'egreso_divisas';
        categoria = 'egreso_divisas';
    }
    const aviso = document.getElementById('aviso_categoria_edit');
    if (!aviso || !sel) return;
    const original = sel.dataset.original || '';
    if (cambioManual && categoria !== original) {
        const nombre = sel.options[sel.selectedIndex]?.text || categoria;
        aviso.textContent = 'Al guardar, este movimiento pasa a «' + nombre + '».'
            + (isTraslado ? ' Elige el banco receptor.' : '');
        aviso.style.display = 'block';
        if (isTraslado) document.getElementById('banco_titular_receptor_edit').focus();
    } else {
        aviso.style.display = 'none';
    }
}

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
    actualizarTecnicoEdit(mov.nomina_empleado_id || '');

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

    const categoriaSel = document.getElementById('categoria_egreso_edit');
    const categoriaMov = mov.categoria_egreso || 'egreso_realizado';
    categoriaSel.dataset.original = categoriaMov;
    categoriaSel.value = categoriaMov;
    if (categoriaSel.value !== categoriaMov) {
        // Categoría que no se cambia desde aquí: se manda tal cual y no se ofrece el selector.
        categoriaSel.disabled = true;
    } else {
        categoriaSel.disabled = false;
    }
    aplicarCategoriaEdit(categoriaMov, false);
    const isTraslado = (categoriaMov === 'traslados');

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

    actualizarTecnicoEdit(mov.nomina_empleado_id || '');
    const esServicioTecnico = (mov.tipo_gasto || '') === '058 - SERVICIO TECNICO (GARANTIAS)';
    setBeneficiarioEdit((!isTraslado && !esServicioTecnico) ? (mov.titular_receptor || '') : '');

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

    faCargarAsiento('_edit', mov.asiento);

    m.style.display = 'flex';
}

function cerrarEditarEgreso() {
    document.getElementById('modalEditarEgreso').style.display = 'none';
}

function validarDesgloseEdit(event) {
    return faValidarAsiento('_edit', event);
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

    const asCont = document.getElementById('ver_asiento_container');
    if (mov.asiento && mov.asiento.debe) {
        document.getElementById('ver_asiento_tabla').innerHTML = tablaAsiento(mov.asiento);
        asCont.style.display = 'block';
    } else {
        asCont.style.display = 'none';
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
    const referencia = normalizarReferencia(document.getElementById('filtro-referencia')?.value || '');
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
        const referenciaMatch = !referencia || normalizarReferencia(tr.dataset.referencia || '').includes(referencia);
        
        const show = catMatch && textMatch && bancoMatch && beneficiarioMatch && referenciaMatch;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const contador = document.getElementById('filtro-contador');
    if (contador) {
        contador.textContent = texto || cat || banco || beneficiario || referencia
            ? `Mostrando ${visible} de ${total} registros`
            : '';
    }
}

// Ignora espacios, guiones y mayúsculas: "0012-345" encuentra "0012345".
function normalizarReferencia(valor) {
    return String(valor).toLowerCase().replace(/[^a-z0-9]/g, '');
}

function limpiarFiltros() {
    const txt = document.getElementById('filtro-texto');
    const txtBanco = document.getElementById('filtro-banco');
    const txtBeneficiario = document.getElementById('filtro-beneficiario');
    const txtReferencia = document.getElementById('filtro-referencia');
    const cat = document.getElementById('filtro-cat');
    if (txt) txt.value = '';
    if (txtBanco) txtBanco.value = '';
    if (txtBeneficiario) txtBeneficiario.value = '';
    if (txtReferencia) txtReferencia.value = '';
    if (cat) cat.value = '';
    aplicarFiltros();
}

</script>

<div id="modalAsiento" class="modal-overlay" style="display: none; z-index: 1200; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;" onclick="if (event.target === this) this.style.display = 'none'">
    <div class="panel" style="width: 95%; max-width: 560px; position: relative; padding: 20px; border-radius: 12px; background: white;">
        <button type="button" onclick="document.getElementById('modalAsiento').style.display = 'none'" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: #b45309;">📒 Asiento contable</h3>
        <div id="modalAsientoTabla"></div>
    </div>
</div>

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

        <div id="ver_asiento_container" style="display: none; background: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #fde68a; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: #b45309; font-size: 0.95rem;">📒 Asiento contable</h4>
            <div id="ver_asiento_tabla"></div>
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
        <h3 id="titulo_editar_egreso" style="margin-top: 0; color: var(--blue);">✏️ Editar Egreso</h3>

        <form id="formEditarEgreso" method="POST" action="" enctype="multipart/form-data" onsubmit="return validarDesgloseEdit(event)">
            @csrf

            <!-- Tipo de egreso -->
            <div id="row_categoria_edit" style="margin-bottom: 12px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; {{ ($soloAvances ?? false) ? 'display:none;' : '' }}">
                <label for="categoria_egreso_edit" style="display: block; margin-bottom: 3px; font-weight: 600; font-size: 0.9rem;">Tipo de Egreso</label>
                <select name="categoria_egreso" id="categoria_egreso_edit" onchange="aplicarCategoriaEdit(this.value, true)" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-weight: 600;">
                    <option value="egreso_realizado">EGRESOS REALIZADOS</option>
                    <option value="egreso_divisas">EGRESOS EN DIVISAS</option>
                    <option value="otros_egresos">OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                    <option value="traslados">TRASLADOS</option>
                </select>
                <div id="aviso_categoria_edit" style="display: none; margin-top: 6px; font-size: 0.8rem; color: #92400e;"></div>
            </div>

            <!-- Banco + Referencia -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                <div style="flex: 2;">
                    <label id="lbl_banco_titular_edit" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Banco y Titular</label>
                    <select name="banco_titular" id="banco_titular_edit" required onchange="aplicarCategoriaEdit(document.getElementById('categoria_egreso_edit')?.value || 'egreso_realizado', false)" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
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
                <div style="flex: 1;" id="col_monto_bs_edit">
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

            <div id="row_tecnico_edit" style="display:none; margin-bottom:10px;">
                <label style="display:block; margin-bottom:3px; font-weight:500; font-size:.9rem;">Empleado de Servicio Técnico</label>
                <select id="edit_nomina_empleado_id" name="nomina_empleado_id" style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; background:white;">
                    <option value="">-- Seleccione el empleado --</option>
                    @foreach($empleadosServicioTecnico as $tecnico)
                        <option value="{{ $tecnico->id }}">{{ $tecnico->nombre() }} · {{ $tecnico->cedula() }}</option>
                    @endforeach
                </select>
            </div>

            <div id="row_beneficiario_edit" style="margin-bottom: 10px;">
                <label id="lbl_beneficiario_edit" style="display: block; margin-bottom: 3px; font-weight: 500; font-size: 0.9rem;">Beneficiario</label>
                <select id="beneficiario_edit" name="beneficiario" style="width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; background: white;">
                    <option value="">-- Seleccione un beneficiario --</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov }}">{{ $prov }}</option>
                    @endforeach
                </select>
                <small style="display:block; margin-top:4px; color:#64748b;">Puedes elegir de la lista o escribir un nombre y pulsar Enter.</small>
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

            <div style="margin-bottom: 15px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500; font-size: 0.95rem;">
                    <input type="checkbox" id="chk_asiento_edit" onchange="faToggleAsiento('_edit')" style="width: 16px; height: 16px; accent-color: #f59e0b;">
                    Asiento contable
                </label>
            </div>
            @include('finanzas.partials.asiento_egreso', ['sufijo' => '_edit'])

            <!-- Buttons -->
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                <button type="button" onclick="cerrarEditarEgreso()" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 20px; background: var(--blue); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
