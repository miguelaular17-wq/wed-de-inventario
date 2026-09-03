@extends('layouts.app')

@section('title', 'Panel de Compras y Distribución')

@section('content')
@push('head')
<style>
/* Clases de fila para hover */
table.data-table tbody tr.row-comprar {
    background-color: #fef2f2;
}
table.data-table tbody tr.row-comprar:hover {
    background-color: #fee2e2 !important;
}
table.data-table tbody tr.row-mala-distribucion {
    background-color: #fffbeb;
}
table.data-table tbody tr.row-mala-distribucion:hover {
    background-color: #fef3c7 !important;
}
/* Close button in modal */
.modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    background: transparent;
    border: none;
    color: #333;
    font-size: 24px;
    cursor: pointer;
}
/* Estilo hover para tarjetas de distribuidor/proveedor */
.provider-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease !important;
}
.provider-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(26, 68, 128, 0.08) !important;
    border-color: #93c5fd !important;
}
.compras-page .segmented {
    flex-wrap: wrap;
    width: auto !important;
    max-width: 100%;
}
.compras-page .tab-btn {
    white-space: nowrap;
}
.compras-page .table-wrap {
    max-height: calc(100vh - 280px);
}
.compras-page table.data-table {
    font-size: 0.8rem;
}
.compras-page table.data-table th,
.compras-page table.data-table td {
    padding: 6px 8px;
}
.compras-page table.data-table th {
    font-size: 0.72rem;
    letter-spacing: 0.01em;
}
.qpedir-table td {
    vertical-align: middle;
}
.qpedir-search {
    width: 100%;
    max-width: 360px;
    padding: 8px 12px 8px 32px;
    border-radius: 8px;
    border: 1.5px solid var(--border);
}
</style>
@endpush
<div class="compras-page">
<div class="page-header">
    <div>
        <h1 style="margin: 0;">Compras y Distribución</h1>
        <p class="lead" style="margin: 4px 0 0;">Analice el stock global para compras o redistribución de inventario entre sucursales.</p>
    </div>
</div>

@if(!auth()->user()->isMarketing())
<div class="panel" style="margin-bottom: 20px; padding: 18px 20px; border: 1px solid #bfdbfe; background: #eff6ff;">
    <form method="GET" action="{{ route('comprador.quiebre.export') }}" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
        <div style="flex: 1 1 280px;">
            <h2 style="margin: 0 0 4px; font-size: 1rem; color: #1e3a8a;">Descargar quiebre de inventario</h2>
            <p style="margin: 0; color: #475569; font-size: 0.82rem; line-height: 1.4;">
                Muestra el stock actual y el total vendido, descontando devoluciones, durante el período seleccionado.
            </p>
        </div>
        <div class="field" style="margin: 0;">
            <label for="quiebre-stock-minimo">Stock máximo a incluir</label>
            <input
                type="number"
                id="quiebre-stock-minimo"
                name="stock_minimo"
                value="{{ old('stock_minimo', 5) }}"
                min="0"
                max="1000000"
                required
                style="width: 145px;"
            >
        </div>
        <div class="field" style="margin: 0;">
            <label for="quiebre-dias">Días a analizar</label>
            <input
                type="number"
                id="quiebre-dias"
                name="dias"
                value="{{ old('dias', 30) }}"
                min="1"
                max="365"
                required
                style="width: 120px;"
            >
        </div>
        <div class="field" style="margin: 0;">
            <label for="quiebre-sede">Sede</label>
            <select id="quiebre-sede" name="sede" style="min-width: 150px;">
                <option value="">Todas</option>
                @foreach(config('inventario.sedes_stock', []) as $sede)
                    <option value="{{ $sede }}" @selected(old('sede') === $sede)>
                        {{ config('inventario.display.'.$sede, $sede) }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="height: 42px; padding: 0 16px; border: 0; background: #2563eb; color: #fff; font-weight: 600;">
            📥 Descargar CSV
        </button>
    </form>
    @if($errors->has('quiebre') || $errors->has('stock_minimo') || $errors->has('dias') || $errors->has('sede'))
        <div style="margin-top: 10px; color: #b91c1c; font-size: 0.82rem;">
            {{ $errors->first('quiebre') ?: $errors->first('stock_minimo') ?: $errors->first('dias') ?: $errors->first('sede') }}
        </div>
    @endif
</div>
@endif

<!-- Selector de Pestañas (Tabs) -->
@php
    $activeTab = $activeTab ?? 'productos';
    $qPedirCount = $qPedirCount ?? (isset($pedidosSolicitados) ? $pedidosSolicitados->count() : 0);
    $tabHref = function (string $tab, array $extra = []) {
        return route('comprador.dashboard', array_merge(['tab' => $tab], $extra));
    };
@endphp
<div class="segmented" style="margin-bottom: 16px; display: flex;">
    @if(!auth()->user()->isMarketing())
        <a href="{{ $tabHref('productos', ['status' => 'MalaDistribucion']) }}" id="tab-btn-dist" class="tab-btn {{ $activeTab === 'productos' && ($statusFilter ?? '') !== 'Comprar' ? 'active' : '' }}" style="text-decoration: none;">Distribución</a>
        <a href="{{ $tabHref('productos', ['status' => 'Comprar']) }}" id="tab-btn-compra" class="tab-btn {{ $activeTab === 'productos' && ($statusFilter ?? '') === 'Comprar' ? 'active' : '' }}" style="text-decoration: none;">Necesidad de Compra</a>
        <a href="{{ $tabHref('proveedores') }}" class="tab-btn {{ $activeTab === 'proveedores' ? 'active' : '' }}" style="text-decoration: none;">General por Proveedor</a>
        <a href="{{ $tabHref('sobrestock') }}" class="tab-btn {{ $activeTab === 'sobrestock' ? 'active' : '' }}" style="text-decoration: none;">Sobre Stock / Sin Rotación</a>
    @else
        <a href="{{ $tabHref('sobrestock') }}" class="tab-btn {{ $activeTab === 'sobrestock' ? 'active' : '' }}" style="text-decoration: none;">Sobre Stock / Sin Rotación</a>
    @endif
    @if(!auth()->user()->isMarketing())
        <a href="{{ $tabHref('qpedir') }}" class="tab-btn {{ $activeTab === 'qpedir' ? 'active' : '' }}" style="text-decoration: none;">Q Pedir @if($qPedirCount) ({{ $qPedirCount }}) @endif</a>
    @endif
    @if(!auth()->user()->isMarketing())
        <a href="{{ route('comprador.existencias') }}" class="tab-btn" style="text-decoration: none; color: inherit; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd;">
            <svg style="width: 16px; height: 16px; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            Existencias Globales
        </a>
    @endif
    @if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
        <a href="{{ $tabHref('publicidad') }}" class="tab-btn {{ $activeTab === 'publicidad' ? 'active' : '' }}" style="text-decoration: none;">Efectividad Publicidad</a>
    @endif
    @if(!empty($puedeMarcarMeta))
        <a href="{{ route('metas.index') }}" class="tab-btn" style="text-decoration: none;">Metas quincena</a>
    @endif
    @if(!auth()->user()->isComprador() && !auth()->user()->isMarketing())
        <a href="{{ $tabHref('cobranzas') }}" class="tab-btn {{ $activeTab === 'cobranzas' ? 'active' : '' }}" style="text-decoration: none;">Cobranzas</a>
    @endif
</div>

<!-- Tab Cobranzas -->
<div id="cobranzas-tab" class="tab-content" style="display: {{ ($activeTab ?? '') === 'cobranzas' ? 'block' : 'none' }};">
    @if(!empty($cobranzasData['fecha_actual']))
    <div style="display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap;">
        <!-- Card 1: Por Sede -->
        <div class="panel" style="flex: 1; min-width: 300px; background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
            <h3 style="text-align: center; font-size: 0.9rem; font-weight: 700; margin-bottom: 16px;">INDICADORES DE COBRANZA POR SEDE AL<br>{{ $cobranzasData['fecha_actual'] }}</h3>
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #e0f2fe; color: #0369a1; text-align: left; font-size: 0.8rem;">
                        <th style="padding: 8px;">SEDE ▼</th>
                        <th style="padding: 8px; text-align: center;">CLIENTE</th>
                        <th style="padding: 8px; text-align: right;">SALDO</th>
                        <th style="padding: 8px; text-align: right;">% GLOBAL</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totCliSede = 0; $totSalSede = 0; $totPorSede = 0; @endphp
                    @foreach($cobranzasData['sede_list'] as $s)
                    @php $totCliSede += $s['clientes']; $totSalSede += $s['saldo']; $totPorSede += $s['porcentaje']; @endphp
                    <tr style="border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <td style="padding: 8px;">{{ $s['sede'] }}</td>
                        <td style="padding: 8px; text-align: center;">{{ $s['clientes'] }}</td>
                        <td style="padding: 8px; text-align: right;">{{ number_format($s['saldo'], 2, ',', '.') }}</td>
                        <td style="padding: 8px; text-align: right;">{{ $s['porcentaje'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #dbeafe; font-weight: bold; font-size: 0.85rem;">
                        <td style="padding: 8px;">Total general</td>
                        <td style="padding: 8px; text-align: center;">{{ $totCliSede }}</td>
                        <td style="padding: 8px; text-align: right;">{{ number_format($totSalSede, 2, ',', '.') }}</td>
                        <td style="padding: 8px; text-align: right;">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Card 2: Por Estatus -->
        <div class="panel" style="flex: 1; min-width: 300px; background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
            <h3 style="text-align: center; font-size: 0.9rem; font-weight: 700; margin-bottom: 16px;">INDICADORES DE COBRANZA POR ESTATUS AL<br>{{ $cobranzasData['fecha_actual'] }}</h3>
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #e0f2fe; color: #0369a1; text-align: left; font-size: 0.8rem;">
                        <th style="padding: 8px;">ESTATUS ▼</th>
                        <th style="padding: 8px; text-align: center;">CLIENTE</th>
                        <th style="padding: 8px; text-align: right;">SALDO</th>
                        <th style="padding: 8px; text-align: right;">% GLOBAL</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totCliEst = 0; $totSalEst = 0; @endphp
                    @foreach($cobranzasData['estatus_list'] as $e)
                    @php $totCliEst += $e['clientes']; $totSalEst += $e['saldo']; @endphp
                    <tr style="background: {{ $e['color'] }}; color: #fff; font-size: 0.85rem; font-weight: 600;">
                        <td style="padding: 8px;">{{ $e['estatus'] }}</td>
                        <td style="padding: 8px; text-align: center;">{{ $e['clientes'] }}</td>
                        <td style="padding: 8px; text-align: right;">{{ number_format($e['saldo'], 2, ',', '.') }}</td>
                        <td style="padding: 8px; text-align: right;">{{ $e['porcentaje'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #dbeafe; font-weight: bold; font-size: 0.85rem; color: #333;">
                        <td style="padding: 8px;">Total general</td>
                        <td style="padding: 8px; text-align: center;">{{ $totCliEst }}</td>
                        <td style="padding: 8px; text-align: right;">{{ number_format($totSalEst, 2, ',', '.') }}</td>
                        <td style="padding: 8px; text-align: right;">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Comparativa Semanal -->
    @if(!empty($cobranzasData['fechas_semanal']))
    <div class="panel" style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; margin-bottom: 24px; overflow-x: auto;">
        <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 16px; color: #333;">Comparativa Semanal</h3>
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: right; min-width: 600px;">
            <thead>
                <tr style="background: #3b82f6; color: white; font-size: 0.8rem;">
                    <th style="padding: 10px; text-align: left;">ESTATUS</th>
                    @foreach($cobranzasData['fechas_semanal'] as $index => $fecha)
                        <th style="padding: 10px;">LUNES<br>{{ $fecha }}</th>
                        @if($index > 0)
                            <th style="padding: 10px;">% DE EFECTIVIDAD</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $totalesLunes = []; @endphp
                @foreach($cobranzasData['semanal_list'] as $row)
                <tr style="border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                    <td style="padding: 10px; background: {{ $row['color'] }}; color: white; font-weight: 600; text-align: left;">{{ $row['estatus'] }}</td>
                    @foreach($row['lunes'] as $index => $lun)
                        @php 
                            if(!isset($totalesLunes[$index])) $totalesLunes[$index] = 0;
                            $totalesLunes[$index] += $lun['saldo'];
                        @endphp
                        <td style="padding: 10px;">{{ number_format($lun['saldo'], 2, ',', '.') }}</td>
                        @if($index > 0)
                            <td style="padding: 10px; color: {{ str_starts_with($lun['efectividad'], '-') ? '#ef4444' : '#10b981' }}; font-weight: 600;">{{ $lun['efectividad'] }}</td>
                        @endif
                    @endforeach
                </tr>
                @endforeach
                <tr style="background: #f8fafc; font-weight: 700; font-size: 0.85rem;">
                    <td style="padding: 10px; text-align: left;">TOTALES</td>
                    @foreach($totalesLunes as $index => $tot)
                        <td style="padding: 10px;">{{ number_format($tot, 2, ',', '.') }}</td>
                        @if($index > 0)
                            @php
                                $prev = $totalesLunes[$index - 1];
                                $ef = $prev > 0 ? round((($prev - $tot) / $prev) * 100, 0) . '%' : '-';
                            @endphp
                            <td style="padding: 10px;">{{ $ef }}</td>
                        @endif
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Detalle de Clientes -->
    <div class="panel" style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #1e3a8a; margin: 0;">Detalle de Clientes</h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 0.85rem; color: #64748b;">Filtrar por Sede:</label>
                <select id="cobranzas-sede-filter" onchange="filterCobranzas(this.value)" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; outline: none;">
                    <option value="ALL">Todas las Sedes</option>
                    @foreach($cobranzasData['sede_list'] as $s)
                        <option value="{{ $s['sede'] }}">{{ $s['sede'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="overflow-x: auto; max-height: 500px;">
            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead style="position: sticky; top: 0; background: #fff; z-index: 10; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                    <tr style="color: #0369a1; text-align: left;">
                        <th style="padding: 12px 8px;">CÓDIGO</th>
                        <th style="padding: 12px 8px;">CLIENTE</th>
                        <th style="padding: 12px 8px; text-align: right;">SALDO USD</th>
                        <th style="padding: 12px 8px; text-align: right;">SALDO BS (Ref)</th>
                        <th style="padding: 12px 8px; text-align: center;">FECHA EMISIÓN</th>
                        <th style="padding: 12px 8px; text-align: center;">ESTATUS</th>
                    </tr>
                </thead>
                <tbody id="cobranzas-tbody">
                    @foreach($cobranzasData['detalle'] as $det)
                    <tr class="cobranza-row" data-sede="{{ $det->sede_nombre }}" style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 10px 8px;">{{ $det->codigo_cliente }}</td>
                        <td style="padding: 10px 8px; font-weight: 600;">{{ $det->nombre_cliente }}</td>
                        <td style="padding: 10px 8px; text-align: right;">{{ number_format($det->saldo, 2, ',', '.') }}</td>
                        <td style="padding: 10px 8px; text-align: right; color: #64748b;">{{ number_format($det->saldo * 40, 2, ',', '.') }}</td>
                        <td style="padding: 10px 8px; text-align: center;">{{ \Carbon\Carbon::parse($det->fecha_emision)->format('d/m/Y') }}</td>
                        <td style="padding: 10px 8px; text-align: center;">
                            <span style="padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; color: #fff; font-weight: 600; background: {{ $det->estatus === 'CRITICO' ? '#ef4444' : ($det->estatus === 'MOROSO' ? '#eab308' : '#84cc16') }};">
                                {{ $det->estatus }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function filterCobranzas(sede) {
            const rows = document.querySelectorAll('.cobranza-row');
            rows.forEach(row => {
                if (sede === 'ALL' || row.dataset.sede === sede) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
    @else
    <div class="panel" style="padding: 40px; text-align: center; color: var(--muted);">
        <p>No hay datos de cobranzas registrados. El sincronizador subirá esta información próximamente.</p>
    </div>
    @endif
</div>

<!-- Tab Q Pedir -->
<div id="qpedir-tab" class="tab-content" style="display: {{ ($activeTab ?? '') === 'qpedir' ? 'block' : 'none' }};">
    @if(isset($pedidosSolicitados) && $pedidosSolicitados->isNotEmpty())
    <div class="panel pedidos-card" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; padding: 20px; border-left: 4px solid #f59e0b; margin-bottom: 24px;">
        <h2 style="margin: 0 0 16px; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background:#fef3c7;color:#b45309;padding:4px 10px;border-radius:6px;font-size:0.85rem;">Q Pedir</span>
                Solicitudes pendientes ({{ collect($pedidosSolicitados)->count() }})
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <form method="GET" action="{{ route('comprador.dashboard') }}" style="display:flex; gap: 4px; align-items: center;">
                    <input type="hidden" name="tab" value="qpedir">
                    <input type="date" name="q_pedir_date" value="{{ request('q_pedir_date') }}" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 4px 8px; font-size: 0.8rem;" onchange="this.form.submit()">
                    @if(request('q_pedir_date'))
                        <a href="{{ route('comprador.dashboard') }}" style="text-decoration: none; color: #ef4444; font-size: 0.8rem; margin-right: 8px;">&times; Quitar</a>
                    @endif
                </form>
                <a href="{{ route('comprador.pedidos.excel') }}" class="btn-reporte" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1px solid #047857; background: #059669; color: white; text-decoration: none;">Excel Detallado</a>
                <a href="{{ route('comprador.pedidos.diario') }}" class="btn-reporte" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1px solid #1e3a8a; background: #2563eb; color: white; text-decoration: none;">PDF Listado Diario</a>
                <button type="button" onclick="generatePdfCharts()" class="btn-reporte" style="padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; border: 1px solid #9333ea; background: #a855f7; color: white;">PDF Gráficos</button>
            </div>
        </h2>
        <p style="margin:0 0 12px;color:#64748b;font-size:0.88rem;">
            Productos solicitados desde el login. Revisa y marca como atendido cuando los proceses.
        </p>
        <div style="margin-bottom: 10px;">
            <input type="search" id="qpedir-filter" class="qpedir-search" placeholder="Filtrar producto, código o categoría…" oninput="filterQPedir(this.value)" style="background: #fff url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 fill=%22%2364748b%22 viewBox=%220 0 24 24%22%3E%3Cpath d=%22M10 4a6 6 0 104.47 10.03l4.75 4.75 1.41-1.41-4.75-4.75A6 6 0 0010 4zm0 2a4 4 0 110 8 4 4 0 010-8z%22/%3E%3C/svg%3E') no-repeat 10px center;">
        </div>

        <!-- Hidden form for PDF Charts -->
        <form id="pdfChartsForm" method="POST" action="{{ route('comprador.pedidos.pdf') }}" style="display:none;">
            @csrf
            <input type="hidden" name="chart_pie" id="pdf_chart_pie">
            <input type="hidden" name="chart_bar" id="pdf_chart_bar">
        </form>

        <div style="position: absolute; left: -9999px; visibility: hidden; width: 600px; height: 400px;">
            <canvas id="hiddenPieChart" width="400" height="400"></canvas>
            <canvas id="hiddenBarChart" width="600" height="400"></canvas>
        </div>
        <div class="table-wrap">
            <table class="data-table qpedir-table">
                <thead>
                    <tr>
                        <th style="width: 110px;">Código</th>
                        <th>Producto</th>
                        <th style="width: 140px;">Categoría</th>
                        <th class="col-number" style="width: 80px;">Pedidos</th>
                        <th style="width: 140px;">Última solicitud</th>
                        <th style="width: 220px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pedidosSolicitados as $pedido)
                    <tr class="qpedir-row" data-filter="{{ strtolower($pedido->producto.' '.$pedido->codigo.' '.$pedido->categoria) }}">
                        <td class="col-code">{{ $pedido->codigo }}</td>
                        <td style="font-weight: 600;">{{ $pedido->producto }}</td>
                        <td style="color: var(--muted);">{{ $pedido->categoria ?: '—' }}</td>
                        <td class="col-number">
                            <span style="background: {{ $pedido->frecuencia > 5 ? '#10b981' : '#e2e8f0' }}; color: {{ $pedido->frecuencia > 5 ? '#fff' : '#475569' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">{{ $pedido->frecuencia }}</span>
                        </td>
                        <td style="color: #64748b; font-size: 0.78rem;">{{ \Carbon\Carbon::parse($pedido->created_at)->diffForHumans() }}</td>
                        <td>
                            <div class="pedido-actions" style="display: flex; gap: 6px; align-items: center;">
                                @if($pedido->estado === 'pendiente' || !$pedido->estado)
                                    <form method="POST" action="{{ route('comprador.pedidos.comprado') }}">
                                        @csrf
                                        <input type="hidden" name="producto" value="{{ $pedido->producto }}">
                                        <button type="submit" class="btn-atender" style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid #10b981; background: #10b981; color: white;">Comprado</button>
                                    </form>
                                    <form method="POST" action="{{ route('comprador.pedidos.fuera_mercado') }}" onsubmit="return confirm('¿Marcar como fuera de mercado (no se puede comprar)?')">
                                        @csrf
                                        <input type="hidden" name="producto" value="{{ $pedido->producto }}">
                                        <button type="submit" class="btn-eliminar" style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid #fecaca; background: #fff; color: #ef4444;">Fuera de mercado</button>
                                    </form>
                                @elseif($pedido->estado === 'comprado')
                                    <span style="color: #10b981; font-weight: 600; font-size: 0.8rem;">✓ Comprado</span>
                                @elseif($pedido->estado === 'fuera_de_mercado')
                                    <span style="color: #ef4444; font-weight: 600; font-size: 0.8rem;">✗ Fuera de mercado</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="panel" style="padding: 40px; text-align: center; color: var(--muted); border: 1px dashed var(--border); border-radius: 12px; background: #f8fafc;">
        <p style="font-size: 1.1rem; font-weight: 500;">No hay solicitudes pendientes en este momento.</p>
    </div>
    @endif
</div>

@if(!auth()->user()->isMarketing())
<!-- Tab 1: Productos y Distribución -->
<div id="productos-tab" class="tab-content" style="display: {{ ($activeTab ?? 'productos') === 'productos' ? 'block' : 'none' }};">
    <div class="panel">
        <h2 style="margin: 0 0 16px; font-size: 1.25rem;">{{ ($statusFilter ?? 'MalaDistribucion') === 'Comprar' ? 'Necesidad de Compra por Producto' : 'Mala Distribución por Producto' }}</h2>
        
        {{-- Barra de búsqueda independiente --}}
        <form method="GET" id="tab1-search-form" style="margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="tab" value="productos">
            {{-- Preserve all current filter values so they don't reset on search --}}
            <input type="hidden" name="categoria" value="{{ request('categoria', 'Ninguno') }}">
            <input type="hidden" name="subcategoria" value="{{ request('subcategoria', 'Ninguno') }}">
            <input type="hidden" name="proveedor" value="{{ request('proveedor', 'Ninguno') }}">
            <input type="hidden" name="sede_destino" value="{{ request('sede_destino', 'Todas') }}">
            <input type="hidden" name="status" id="status-filter" value="{{ $statusFilter ?? 'MalaDistribucion' }}">
            <input type="hidden" name="tp" value="{{ request('tp', 60) }}">
            <div style="flex: 1; max-width: 480px; position: relative;">
                <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:1rem; color:var(--muted); pointer-events:none;">🔍</span>
                <input
                    type="search"
                    id="q"
                    name="q"
                    value="{{ $q ?? '' }}"
                    placeholder="Buscar por código o nombre del producto… (Enter para buscar)"
                    autocomplete="off"
                    style="width:100%; padding: 10px 12px 10px 36px; border-radius: 8px; border: 1.5px solid var(--border); font-size: 0.95rem; background: var(--surface); color: var(--text); transition: border-color 0.2s;"
                    onfocus="this.style.borderColor='var(--blue)'"
                    onblur="this.style.borderColor='var(--border)'"
                >
            </div>
            @if(!empty($q))
                <a href="{{ route('comprador.dashboard', array_merge(request()->except('q'), ['categoria' => $selectedCategoria ?? 'Ninguno', 'subcategoria' => $selectedSubcategoria ?? 'Ninguno', 'proveedor' => $selectedProveedor ?? 'Ninguno', 'sede_destino' => $sedeDestinoFilter ?? 'Todas', 'status' => $statusFilter ?? 'Todos'])) }}" style="font-size:0.82rem; color:var(--muted); text-decoration:none; white-space:nowrap;">✕ Limpiar búsqueda</a>
            @endif
        </form>

        {{-- Filtros de dropdowns --}}
        <form method="GET" class="filter-bar" style="margin-bottom: 20px;">
            <input type="hidden" name="tab" value="productos">
            {{-- Preserve search term when changing filters --}}
            <input type="hidden" name="q" value="{{ $q ?? '' }}">
            <div class="field">
                <label for="categoria">Categoría</label>
                <select id="categoria" name="categoria" onchange="updateSubcatsAndSubmit()">
                    <option value="Ninguno">Todas</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat }}" @selected($selectedCategoria === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="proveedor">Proveedor</label>
                <select id="proveedor" name="proveedor" onchange="this.form.submit();">
                    <option value="Ninguno">Todos</option>
                    @foreach ($proveedores as $prov)
                        <option value="{{ $prov }}" @selected($selectedProveedor === $prov)>{{ $prov }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="subcategoria">Subcategoría</label>
                <select id="subcategoria" name="subcategoria" onchange="this.form.submit();" @disabled($selectedCategoria === 'Ninguno')>
                    <option value="Ninguno">Todas</option>
                    @if($selectedCategoria !== 'Ninguno' && isset($subcategoriasByCategoria[$selectedCategoria]))
                        @foreach($subcategoriasByCategoria[$selectedCategoria] as $subcat)
                            <option value="{{ $subcat }}" @selected($selectedSubcategoria === $subcat)>{{ $subcat }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="field">
                <label for="sede_destino">Sede Destino</label>
                <select id="sede_destino" name="sede_destino" onchange="this.form.submit();">
                    <option value="Todas">Todas</option>
                    @foreach (config('inventario.sedes_stock', ['DORAL','CENTRO','SAMBIL','VIRTUDES','JRZ','ZAMORA']) as $sede)
                        <option value="{{ $sede }}" @selected(($sedeDestinoFilter ?? 'Todas') === $sede)>{{ config('inventario.display.'.$sede, $sede) }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'MalaDistribucion' }}">
            <div class="field">
                <label for="tp">Proyectar Demanda a (días):</label>
                <input type="number" id="tp" name="tp" value="{{ $tp ?? 60 }}" min="1" step="1" onchange="this.form.submit();" style="border-color: var(--blue); font-weight: 500; width: 130px; padding: 10px; border-radius: 8px;">
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    @php
                        $sortFirstUrl = function($column) use ($sortFirst, $dirFirst) {
                            $newDir = ($sortFirst === $column && $dirFirst === 'asc') ? 'desc' : 'asc';
                            return request()->fullUrlWithQuery(['sort_first' => $column, 'dir_first' => $newDir]);
                        };
                        $sortFirstIcon = function($column) use ($sortFirst, $dirFirst) {
                            if ($sortFirst !== $column) return '⇅';
                            return $dirFirst === 'asc' ? '↑' : '↓';
                        };
                    @endphp
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Producto</th>
                        <th style="width: 160px;">Categoría</th>
                        <th class="col-number" style="width: 110px;">
                            <a href="{{ $sortFirstUrl('stock') }}" style="color: inherit; text-decoration: none;">Stock Global {{ $sortFirstIcon('stock') }}</a>
                        </th>
                        <th class="col-number" style="width: 110px;">
                            <a href="{{ $sortFirstUrl('demanda') }}" style="color: inherit; text-decoration: none;">Demanda Global {{ $sortFirstIcon('demanda') }}</a>
                        </th>

                        <th style="min-width: 380px; width: 400px;">Detalles / Distribución sugerida</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productos as $row)
                        @php
                            $isComprar = $row['status'] === 'COMPRAR';
                        @endphp
                        <tr class="@if($isComprar) row-comprar @else row-mala-distribucion @endif">
                            <td class="col-code">{{ $row['cod_centro'] }}</td>
                            <td>
                                <div style="font-weight: 600;">{{ $row['producto'] }}</div>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--muted);">
                                {{ $row['categoria'] }}
                                <div style="font-size: 0.75rem; opacity: 0.8;">{{ $row['subcategoria'] }}</div>
                            </td>
                            <td class="col-number font-semibold">{{ $row['total_stock'] }}</td>
                            <td class="col-number font-semibold">{{ $row['total_demanda'] }}</td>

                            <td>
                                @if ($isComprar)
                                    <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; font-size: 0.8rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
                                        <span style="font-size: 1rem;">🛒</span>
                                        <span>Faltan <strong>{{ $row['total_demanda'] - $row['total_stock'] }} unidades</strong> para cubrir la demanda global.</span>
                                    </div>
                                @else
                                    <div style="font-size: 0.85rem;">
                                        <div style="margin-bottom: 8px; color: #c2410c;">
                                            <strong>Redistribuir excedentes:</strong>
                                        </div>
                                        @php
                                            $redistributions = [];
                                            reset($row['surpluses']);
                                            reset($row['shortages']);
                                            $surpluses = $row['surpluses'];
                                            $shortages = $row['shortages'];

                                            foreach ($shortages as $destSede => $needed) {
                                                foreach ($surpluses as $origSede => $available) {
                                                    if ($needed <= 0 || $available <= 0) continue;
                                                    $transferAmt = min($needed, $available);
                                                    $redistributions[] = [
                                                        'origen' => $origSede,
                                                        'destino' => $destSede,
                                                        'cantidad' => $transferAmt
                                                    ];
                                                    $needed -= $transferAmt;
                                                    $surpluses[$origSede] -= $transferAmt;
                                                }
                                            }
                                        @endphp

                                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
                                            @foreach($redistributions as $r)
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 6px 10px; background: #fff; border: 1px solid #fed7aa; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s;">
                                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #475569;">
                                                        <span class="tag" style="font-size: 0.65rem; background: #475569; color: #fff !important; font-weight: 600; padding: 2px 6px; border: none; margin: 0;">{{ config('inventario.display.'.$r['origen'], $r['origen']) }}</span>
                                                        <span style="color: #c2410c; font-weight: 700; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 3px;">
                                                            <span>➔</span>
                                                            <span style="background: #ffedd5; padding: 1px 5px; border-radius: 4px; border: 1px solid #fed7aa;">{{ $r['cantidad'] }} u.</span>
                                                            <span>➔</span>
                                                        </span>
                                                        <span class="tag" style="font-size: 0.65rem; background: #2563a8; color: #fff !important; font-weight: 600; padding: 2px 6px; border: none; margin: 0;">{{ config('inventario.display.'.$r['destino'], $r['destino']) }}</span>
                                                    </div>
                                                    <form method="POST" action="{{ route('comprador.notify') }}" style="margin: 0; display: inline-block;" onsubmit="handleNotificationSubmit(event, this)">
                                                        @csrf
                                                        <input type="hidden" name="codigo" value="{{ $row['cod_centro'] }}">
                                                        <input type="hidden" name="producto" value="{{ $row['producto'] }}">
                                                        <input type="hidden" name="sede_destino" value="{{ $r['destino'] }}">
                                                        <input type="hidden" name="sede_origen" value="{{ $r['origen'] }}">
                                                        <input type="hidden" name="cantidad" value="{{ $r['cantidad'] }}">
                                                        <button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; background-color: var(--blue); color: #fff; border: none; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
                                                            Notificar
                                                        </button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--muted); padding: 24px;">
                                No hay productos que requieran compra o redistribución en este momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $productos->links('partials.pagination') }}
        </div>
    </div>
</div>

<!-- Tab 2: General por Proveedor -->
<div id="proveedores-tab" class="tab-content" style="display: {{ ($activeTab ?? '') === 'proveedores' ? 'block' : 'none' }};">
    <!-- Barra de búsqueda de proveedor y filtro de demanda -->
    <form method="GET" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
        <input type="hidden" name="tab" value="proveedores">
        <input type="hidden" name="subcategoria" value="{{ request('subcategoria', 'Ninguno') }}">
        <input type="hidden" name="proveedor" value="{{ request('proveedor', 'Ninguno') }}">
        <input type="hidden" name="status" id="status-filter-2" value="{{ $statusFilter ?? 'MalaDistribucion' }}">
        <input type="hidden" name="q" value="{{ request('q', '') }}">
        
        <div class="field" style="margin-bottom: 0;">
            <label for="cat_prov">Categoría</label>
            <select id="cat_prov" name="categoria" onchange="this.form.submit();" style="border-color: var(--border); font-weight: 500;">
                <option value="Ninguno">Todas</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat }}" @selected($selectedCategoria === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        <div class="field field-wide" style="flex: 1; max-width: 400px; margin-bottom: 0;">
            <label for="q-proveedor">Buscar proveedor</label>
            <input type="search" id="q-proveedor" placeholder="Nombre del proveedor..." autocomplete="off" onkeyup="filterProviders(this.value)">
        </div>
        
        <div class="field" style="margin-bottom: 0;">
            <label for="tp_prov">Proyectar Demanda a (días):</label>
            <input type="number" id="tp_prov" name="tp" value="{{ $tp ?? 60 }}" min="1" step="1" onchange="this.form.submit();" style="border-color: var(--blue); font-weight: 500; width: 130px; padding: 10px; border-radius: 8px;">
        </div>
    </form>

    <div id="provider-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
        @forelse ($byProvider as $prov)
            <div class="panel provider-card" 
                 style="cursor: pointer; padding: 20px; border: 1px solid var(--border); border-radius: var(--radius); display: flex; flex-direction: column; justify-content: space-between; gap: 12px; background: var(--panel);"
                 onclick="openProviderModalByIndex({{ $loop->index }}, this)">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <span style="font-size: 1.5rem; background: var(--blue-light); padding: 8px; border-radius: 8px; line-height: 1;">📦</span>
                    <div style="flex: 1; min-width: 0;">
                        <h3 style="margin: 0; font-size: 1.05rem; color: var(--blue); line-height: 1.35; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $prov['proveedor'] }}">
                            {{ $prov['proveedor'] }}
                        </h3>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px; margin-top: auto; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.8rem; color: var(--muted);">Productos:</span>
                        <span class="tag no" style="font-size: 0.72rem; padding: 2px 8px; font-weight: 600;">{{ $prov['total_productos'] }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.8rem; color: var(--muted);">A comprar:</span>
                        <span class="tag warn" style="font-size: 0.72rem; padding: 2px 8px; font-weight: 600;">{{ $prov['total_unidades'] }} u.</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="panel" style="text-align: center; padding: 48px; color: var(--muted); grid-column: 1 / -1;">
                <span style="font-size: 3rem;">🎉</span>
                <p style="margin-top: 12px; font-size: 1rem;">No se encontraron productos pendientes de compra para ningún proveedor.</p>
            </div>
        @endforelse
    </div>
</div>
@endif

<!-- Tab 3: Análisis de Inventario -->
<div id="sobrestock-tab" class="tab-content" style="display: {{ ($activeTab ?? '') === 'sobrestock' ? 'block' : 'none' }};">
    
    {{-- ── Resumen de Riesgo (Cards) ── --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        {{-- Semáforo cards --}}
        <div class="panel" style="padding: 16px; border-left: 4px solid #22c55e;">
            <div style="font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">🟢 Normal</div>
            <div style="font-size: 2rem; font-weight: 700; color: #22c55e; margin: 4px 0;">{{ $resumenRiesgo['semaforo']['verde'] }}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">productos</div>
        </div>
        <div class="panel" style="padding: 16px; border-left: 4px solid #eab308;">
            <div style="font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">🟡 Vigilar</div>
            <div style="font-size: 2rem; font-weight: 700; color: #eab308; margin: 4px 0;">{{ $resumenRiesgo['semaforo']['amarillo'] }}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">productos</div>
        </div>
        <div class="panel" style="padding: 16px; border-left: 4px solid #f97316;">
            <div style="font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">🟠 Sobrestock</div>
            <div style="font-size: 2rem; font-weight: 700; color: #f97316; margin: 4px 0;">{{ $resumenRiesgo['semaforo']['naranja'] }}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">productos</div>
        </div>
        <div class="panel" style="padding: 16px; border-left: 4px solid #ef4444;">
            <div style="font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">🔴 Crítico / Sin Rotación</div>
            <div style="font-size: 2rem; font-weight: 700; color: #ef4444; margin: 4px 0;">{{ $resumenRiesgo['semaforo']['rojo'] }}</div>
            <div style="font-size: 0.75rem; color: var(--muted);">productos</div>
        </div>
    </div>

    {{-- ── Barra de búsqueda independiente para Sobrestock ── --}}
    <form method="GET" id="ss-search-form" style="margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
        <input type="hidden" name="tab" value="sobrestock">
        {{-- Preserve all sobrestock filter values so they don't reset on search --}}
        <input type="hidden" name="ss_categoria" value="{{ $ssFilters['categoria'] }}">
        <input type="hidden" name="ss_subcategoria" value="{{ $ssFilters['subcategoria'] }}">
        <input type="hidden" name="ss_proveedor" value="{{ $ssFilters['proveedor'] }}">
        <input type="hidden" name="ss_sede" value="{{ $ssFilters['sede'] }}">
        <input type="hidden" name="ss_rotacion" value="{{ $ssFilters['rotacion_filter'] }}">
        <input type="hidden" name="ss_sobrestock" value="{{ $ssFilters['sobrestock_filter'] }}">
        <input type="hidden" name="ss_estado" value="{{ $ssFilters['estado_filter'] }}">
        <input type="hidden" name="ss_semaforo" value="{{ $ssFilters['semaforo_filter'] }}">
        <input type="hidden" name="ss_min_dias" value="{{ $ssFilters['min_dias_sin_venta'] }}">
        <input type="hidden" name="ss_min_stock" value="{{ $ssFilters['min_existencia'] }}">
        <input type="hidden" name="ss_sort" value="{{ $ssSortBy }}">
        <input type="hidden" name="ss_dir" value="{{ $ssSortDir }}">
        <div style="flex: 1; max-width: 480px; position: relative;">
            <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:1rem; color:var(--muted); pointer-events:none;">🔍</span>
            <input
                type="search"
                id="ss_buscar"
                name="ss_buscar"
                value="{{ $ssFilters['buscar'] }}"
                placeholder="Buscar por código o nombre del producto… (Enter para buscar)"
                autocomplete="off"
                style="width:100%; padding: 10px 12px 10px 36px; border-radius: 8px; border: 1.5px solid var(--border); font-size: 0.95rem; background: var(--surface); color: var(--text); transition: border-color 0.2s;"
                onfocus="this.style.borderColor='var(--blue)'"
                onblur="this.style.borderColor='var(--border)'"
            >
        </div>
        @if(!empty($ssFilters['buscar']))
            <a href="{{ route('comprador.dashboard', array_merge(request()->except('ss_buscar'), ['ss_buscar' => ''])) }}" style="font-size:0.82rem; color:var(--muted); text-decoration:none; white-space:nowrap;">✕ Limpiar búsqueda</a>
        @endif
    </form>

    {{-- ── Filtros Avanzados ── --}}
    <form method="GET" id="ss-form" class="filter-bar" style="margin-bottom: 20px; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <input type="hidden" name="tab" value="sobrestock">
        {{-- Preserve other tab filters --}}
        <input type="hidden" name="q" value="{{ request('q') }}">
        <input type="hidden" name="categoria" value="{{ request('categoria') }}">
        <input type="hidden" name="proveedor" value="{{ request('proveedor') }}">
        <input type="hidden" name="subcategoria" value="{{ request('subcategoria') }}">
        <input type="hidden" name="page_sobre_stock" value="1">
        <input type="hidden" name="ss_sort" value="{{ $ssSortBy }}">
        <input type="hidden" name="ss_dir" value="{{ $ssSortDir }}">
        {{-- Preserve search term when changing dropdown filters --}}
        <input type="hidden" name="ss_buscar" value="{{ $ssFilters['buscar'] }}">

        <div class="field" style="width: 150px;">
            <label for="ss_categoria">Categoría</label>
            <select id="ss_categoria" name="ss_categoria" onchange="updateSsSubcats()">
                <option value="Ninguno">Todas</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat }}" @selected($ssFilters['categoria'] === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="width: 150px;">
            <label for="ss_subcategoria">Subcategoría</label>
            <select id="ss_subcategoria" name="ss_subcategoria" @disabled($ssFilters['categoria'] === 'Ninguno') onchange="document.getElementById('ss-form').submit();">
                <option value="Ninguno">Todas</option>
                @if($ssFilters['categoria'] !== 'Ninguno' && isset($subcategoriasByCategoria[$ssFilters['categoria']]))
                    @foreach($subcategoriasByCategoria[$ssFilters['categoria']] as $subcat)
                        <option value="{{ $subcat }}" @selected($ssFilters['subcategoria'] === $subcat)>{{ $subcat }}</option>
                    @endforeach
                @endif
            </select>
        </div>
        <div class="field" style="width: 150px;">
            <label for="ss_proveedor">Proveedor</label>
            <select id="ss_proveedor" name="ss_proveedor" onchange="document.getElementById('ss-form').submit();">
                <option value="Ninguno">Todos</option>
                @foreach ($proveedores as $prov)
                    <option value="{{ $prov }}" @selected($ssFilters['proveedor'] === $prov)>{{ $prov }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="width: 130px;">
            <label for="ss_sede">Sede</label>
            <select id="ss_sede" name="ss_sede" onchange="document.getElementById('ss-form').submit();">
                <option value="Todas">Todas</option>
                @foreach ($sedes as $s)
                    <option value="{{ $s }}" @selected($ssFilters['sede'] === $s)>{{ $sedeDisplay[$s] ?? $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="width: 130px;">
            <label for="ss_rotacion">Rotación</label>
            <select id="ss_rotacion" name="ss_rotacion" onchange="document.getElementById('ss-form').submit();">
                <option value="Todos">Todos</option>
                <option value="Normal" @selected($ssFilters['rotacion_filter'] === 'Normal')>Normal</option>
                <option value="Lenta" @selected($ssFilters['rotacion_filter'] === 'Lenta')>Lenta</option>
                <option value="Riesgo" @selected($ssFilters['rotacion_filter'] === 'Riesgo')>Riesgo</option>
                <option value="Sin rotación" @selected($ssFilters['rotacion_filter'] === 'Sin rotación')>Sin rotación</option>
            </select>
        </div>
        <div class="field" style="width: 140px;">
            <label for="ss_sobrestock">Sobrestock</label>
            <select id="ss_sobrestock" name="ss_sobrestock" onchange="document.getElementById('ss-form').submit();">
                <option value="Todos">Todos</option>
                <option value="Normal" @selected($ssFilters['sobrestock_filter'] === 'Normal')>Normal</option>
                <option value="Vigilar" @selected($ssFilters['sobrestock_filter'] === 'Vigilar')>Vigilar</option>
                <option value="Sobrestock" @selected($ssFilters['sobrestock_filter'] === 'Sobrestock')>Sobrestock</option>
                <option value="Sobrestock Crítico" @selected($ssFilters['sobrestock_filter'] === 'Sobrestock Crítico')>Sobrestock Crítico</option>
            </select>
        </div>
        <div class="field" style="width: 130px;">
            <label for="ss_semaforo">Semáforo</label>
            <select id="ss_semaforo" name="ss_semaforo" onchange="document.getElementById('ss-form').submit();">
                <option value="Todos">Todos</option>
                <option value="verde" @selected($ssFilters['semaforo_filter'] === 'verde')>🟢 Verde</option>
                <option value="amarillo" @selected($ssFilters['semaforo_filter'] === 'amarillo')>🟡 Amarillo</option>
                <option value="naranja" @selected($ssFilters['semaforo_filter'] === 'naranja')>🟠 Naranja</option>
                <option value="rojo" @selected($ssFilters['semaforo_filter'] === 'rojo')>🔴 Rojo</option>
            </select>
        </div>
        <div class="field" style="width: 110px;">
            <label for="ss_min_dias">Días sin venta ≥</label>
            <input type="number" id="ss_min_dias" name="ss_min_dias" value="{{ $ssFilters['min_dias_sin_venta'] }}" min="0" placeholder="0">
        </div>
        <div class="field" style="width: 110px;">
            <label for="ss_min_stock">Stock ≥</label>
            <input type="number" id="ss_min_stock" name="ss_min_stock" value="{{ $ssFilters['min_existencia'] }}" min="0" placeholder="0">
        </div>
        <div>
            <button type="submit" class="btn primary" style="padding: 10px 16px;">Aplicar</button>
        </div>
        <div>
            <a href="{{ route('comprador.dashboard') }}" class="btn secondary" style="padding: 10px 16px; text-decoration: none;">Limpiar</a>
        </div>
    </form>

    {{-- ── Tabla de resultados ── --}}
    <div class="panel" style="padding: 8px 0;">
        <div style="padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
            <span style="font-size: 0.9rem; color: var(--muted);">
                Mostrando <strong>{{ $sobreStock->count() }}</strong> de <strong>{{ number_format($sobreStock->total()) }}</strong> productos
            </span>
        </div>
        <div class="table-wrap">
            <table class="data-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">⚡</th>
                        @php
                            $sortUrl = function($col) use ($ssSortBy, $ssSortDir) {
                                $newDir = ($ssSortBy === $col && $ssSortDir === 'asc') ? 'desc' : 'asc';
                                $params = request()->query();
                                $params['ss_sort'] = $col;
                                $params['ss_dir'] = $newDir;
                                $params['page_sobre_stock'] = 1;
                                return '?' . http_build_query($params);
                            };
                            $sortIcon = function($col) use ($ssSortBy, $ssSortDir) {
                                if ($ssSortBy !== $col) return '⇅';
                                return $ssSortDir === 'asc' ? '↑' : '↓';
                            };
                        @endphp
                        <th style="width: 100px;">
                            <a href="{{ $sortUrl('codigo') }}" style="color: inherit; text-decoration: none;">Código {{ $sortIcon('codigo') }}</a>
                        </th>
                        <th>
                            <a href="{{ $sortUrl('producto') }}" style="color: inherit; text-decoration: none;">Producto {{ $sortIcon('producto') }}</a>
                        </th>
                        <th style="width: 130px;">Categoría</th>
                        <th style="width: 100px; text-align: right;">
                            <a href="{{ $sortUrl('total_stock') }}" style="color: inherit; text-decoration: none;">Stock {{ $sortIcon('total_stock') }}</a>
                        </th>
                        <th style="width: 90px; text-align: right;">Costo Unit.</th>
                        <th style="width: 90px; text-align: right;">Cant. Compra</th>
                        <th style="width: 100px; text-align: right;">Costo en Tienda</th>
                        <th style="width: 100px; text-align: right;">
                            <a href="{{ $sortUrl('dias_sin_venta') }}" style="color: inherit; text-decoration: none;">Días s/v {{ $sortIcon('dias_sin_venta') }}</a>
                        </th>
                        <th style="width: 100px; text-align: center;">Rotación</th>
                        <th style="width: 95px; text-align: right;">
                            <a href="{{ $sortUrl('meses_inventario') }}" style="color: inherit; text-decoration: none;">Meses inv {{ $sortIcon('meses_inventario') }}</a>
                        </th>
                        <th style="width: 120px; text-align: center;">Sobrestock</th>
                        @if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
                            <th style="width: 120px; text-align: center;">Publicidad</th>
                        @endif
                        @if(!empty($puedeMarcarMeta))
                            <th style="width: 110px; text-align: center;">Meta</th>
                        @endif
                        <th style="width: 110px; text-align: center;">Últ. Venta</th>
                        <th style="width: 110px; text-align: center;">Últ. Compra</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sobreStock as $item)
                        @php
                            $rowBg = match($item['semaforo']) {
                                'rojo' => '#fef2f2',
                                'naranja' => '#fff7ed',
                                'amarillo' => '#fefce8',
                                default => '',
                            };
                            $semaforoEmoji = match($item['semaforo']) {
                                'verde' => '🟢', 'amarillo' => '🟡', 'naranja' => '🟠', 'rojo' => '🔴', default => '⚪'
                            };
                            $rotColor = match($item['rotacion_color']) {
                                'verde' => '#22c55e', 'amarillo' => '#a16207', 'naranja' => '#ea580c', 'rojo' => '#dc2626', default => '#64748b'
                            };
                            $rotBg = match($item['rotacion_color']) {
                                'verde' => '#f0fdf4', 'amarillo' => '#fefce8', 'naranja' => '#fff7ed', 'rojo' => '#fef2f2', default => '#f8fafc'
                            };
                            $ssColor = match($item['sobrestock_color']) {
                                'verde' => '#22c55e', 'amarillo' => '#a16207', 'naranja' => '#ea580c', 'rojo' => '#dc2626', default => '#64748b'
                            };
                            $ssBg = match($item['sobrestock_color']) {
                                'verde' => '#f0fdf4', 'amarillo' => '#fefce8', 'naranja' => '#fff7ed', 'rojo' => '#fef2f2', default => '#f8fafc'
                            };
                        @endphp
                        <tr style="{{ $rowBg ? "background-color: {$rowBg};" : '' }}">
                            <td style="text-align: center; font-size: 1rem;">{{ $semaforoEmoji }}</td>
                            <td style="font-family: monospace; font-size: 0.85rem; color: var(--blue);">{{ $item['codigo'] }}</td>
                            <td>
                                <div style="font-weight: 500;">{{ $item['producto'] }}</div>
                                <div style="font-size: 0.75rem; color: var(--muted);">{{ $item['proveedor'] }}</div>
                            </td>
                            <td>
                                <span class="tag" style="background: #f1f5f9; color: var(--muted); border-color: #e2e8f0; font-size: 0.7rem;">{{ $item['categoria'] }}</span>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: var(--blue);">{{ number_format($item['total_stock']) }} u.</td>
                            <td style="text-align: right; font-weight: 500; font-size: 0.85rem; color: #475569;">
                                ${{ number_format($item['ultimo_costo_compra'] ?? 0, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 500; font-size: 0.85rem; color: #475569;">
                                {{ number_format($item['ultima_cantidad_compra'] ?? 0) }} u.
                            </td>
                            <td style="text-align: right; font-weight: 600; font-size: 0.85rem; color: #334155;">
                                ${{ number_format($item['precio_unidad'] ?? 0, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 600; color: {{ $item['dias_sin_venta'] > 90 ? '#dc2626' : ($item['dias_sin_venta'] > 60 ? '#ea580c' : ($item['dias_sin_venta'] > 30 ? '#a16207' : '#22c55e')) }};">
                                {{ $item['dias_sin_venta'] >= 999 ? '—' : $item['dias_sin_venta'] . 'd' }}
                            </td>
                            <td style="text-align: center;">
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: {{ $rotBg }}; color: {{ $rotColor }}; border: 1px solid {{ $rotColor }}20;">
                                    {{ $item['rotacion'] }}
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 500;">
                                @if($item['meses_inventario'] !== null && $item['meses_inventario'] < 999)
                                    {{ $item['meses_inventario'] }}m
                                @else
                                    <span style="color: var(--muted);">∞</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: {{ $ssBg }}; color: {{ $ssColor }}; border: 1px solid {{ $ssColor }}20;">
                                    {{ $item['sobrestock'] }}
                                </span>
                            </td>
                            @if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
                                <td style="text-align: center;">
                                    @php
                                        $isAdvertised = in_array($item['id'], $advertisedProductIds, true);
                                    @endphp
                                    <button type="button" 
                                            onclick="toggleAdvertising({{ $item['id'] }}, this)" 
                                            class="btn {{ $isAdvertised ? 'primary' : 'secondary' }}" 
                                            style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                        {{ $isAdvertised ? '📢 En campaña' : '➕ Publicitar' }}
                                    </button>
                                </td>
                            @endif
                            @if(!empty($puedeMarcarMeta))
                                <td style="text-align: center;">
                                    @php
                                        $sedesMetaItem = $metaSedesPorProducto[$item['id']] ?? [];
                                        $tieneMeta = count($sedesMetaItem) > 0;
                                    @endphp
                                    <button type="button"
                                            class="btn {{ $tieneMeta ? 'primary' : 'secondary' }} btn-meta-producto"
                                            data-producto-id="{{ $item['id'] }}"
                                            data-codigo="{{ $item['codigo'] ?? '' }}"
                                            data-sedes-meta="{{ json_encode(array_values($sedesMetaItem)) }}"
                                            style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; font-weight: 600; cursor: pointer;">
                                        {{ $tieneMeta ? '🎯 Meta ('.count($sedesMetaItem).')' : '➕ Meta' }}
                                    </button>
                                </td>
                            @endif
                            <td style="text-align: center; font-size: 0.8rem;">
                                @if($item['ultima_venta'])
                                    <span style="{{ $item['dias_sin_venta'] > 90 ? 'color: #dc2626; font-weight: 600;' : '' }}">{{ $item['ultima_venta'] }}</span>
                                @else
                                    <span style="color: var(--muted); font-style: italic;">Sin datos</span>
                                @endif
                            </td>
                            <td style="text-align: center; font-size: 0.8rem;">
                                @if($item['ultima_compra'])
                                    <span style="{{ ($item['dias_sin_compra'] ?? 999) <= 30 && ($item['dias_sin_venta'] ?? 0) > 90 ? 'color: #dc2626; font-weight: 600;' : '' }}">{{ $item['ultima_compra'] }}</span>
                                @else
                                    <span style="color: var(--muted); font-style: italic;">Sin datos</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ((auth()->user()->isMarketing() || auth()->user()->isAdmin()) ? 15 : 14) + (!empty($puedeMarcarMeta) ? 1 : 0) }}" style="text-align: center; color: var(--muted); padding: 24px;">
                                No se encontraron productos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 0 16px 16px;">
            {{ $sobreStock->links('partials.pagination') }}
        </div>
    </div>
</div>

@if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
<!-- Tab 4: Efectividad Publicidad -->
<div id="publicidad-tab" class="tab-content" style="display: {{ ($activeTab ?? '') === 'publicidad' ? 'block' : 'none' }};">
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 8px;">
            <div>
                <h2 style="margin: 0 0 8px; font-size: 1.25rem; color: var(--blue);">Efectividad de Campañas de Publicidad</h2>
                <p class="muted" style="margin: 0 0 12px;">
                    @if($puedeVerEquipoPublicidad)
                        Control de productos promocionados por todo el equipo de marketing.
                    @else
                        Lleva el control de los productos promocionados, su última venta inicial y si han tenido nuevas ventas después del inicio de la campaña.
                    @endif
                </p>
            </div>
            @if($puedeVerEquipoPublicidad)
                <label style="display: flex; flex-direction: column; gap: 4px; font-size: 0.8rem; font-weight: 600; color: var(--muted);">
                    Filtrar por usuario
                    <select id="filtro-pub-user" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); min-width: 220px; font-weight: 500; color: var(--text);">
                        <option value="todos">Todos los usuarios</option>
                        @foreach($publicidadUsuarios as $pubUser)
                            <option value="{{ $pubUser->id }}">{{ $pubUser->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        
        <div class="table-wrap">
            <table class="data-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Producto</th>
                        @if($puedeVerEquipoPublicidad)
                            <th style="width: 150px;">Usuario</th>
                        @endif
                        <th style="width: 130px;">Categoría</th>
                        <th style="width: 100px; text-align: right;">Stock Global</th>
                        <th style="width: 150px; text-align: center;">Fecha Publicidad</th>
                        <th style="width: 130px; text-align: center;">Venta Anterior</th>
                        <th style="width: 130px; text-align: center;">Última Venta</th>
                        <th style="width: 140px; text-align: center;">¿Nuevas Ventas?</th>
                        <th style="width: 110px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($publicitadosData as $p)
                        <tr data-user-id="{{ $p['user_id'] ?? '' }}">
                            <td style="font-family: monospace; font-size: 0.85rem; color: var(--blue);">{{ $p['codigo'] }}</td>
                            <td style="font-weight: 500;">
                                {{ $p['producto'] }}
                                <div style="font-size: 0.75rem; color: var(--muted);">{{ $p['proveedor'] }}</div>
                            </td>
                            @if($puedeVerEquipoPublicidad)
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; {{ !empty($p['es_propia']) ? 'background:#fdf4ff; color:#a21caf; border:1px solid #f5d0fe;' : 'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;' }}">
                                        {{ $p['usuario'] }}
                                        @if(!empty($p['es_propia'])) (tú) @endif
                                    </span>
                                </td>
                            @endif
                            <td>
                                <span class="tag" style="background: #f1f5f9; color: var(--muted); border-color: #e2e8f0; font-size: 0.7rem;">{{ $p['categoria'] }}</span>
                            </td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($p['total_stock']) }} u.</td>
                            <td style="text-align: center; color: var(--blue); font-weight: 500;">{{ $p['fecha_publicidad'] }}</td>
                            <td style="text-align: center; color: var(--muted);">{{ $p['ultima_venta_original'] }}</td>
                            <td style="text-align: center; font-weight: 600;">{{ $p['ultima_venta_actual'] }}</td>
                            <td style="text-align: center;">
                                @if($p['tuvo_ventas'])
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #f0fdf4; color: #22c55e; border: 1px solid #22c55e30;">
                                        🟢 ¡Sí, vendido!
                                        @if(isset($p['cantidad_vendida_desde_pub']) && $p['cantidad_vendida_desde_pub'] > 0)
                                            ({{ $p['cantidad_vendida_desde_pub'] }} u.)
                                        @endif
                                    </span>
                                @else
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #fef2f2; color: #ef4444; border: 1px solid #ef444430;">
                                        🔴 Sin ventas aún
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @if(!empty($p['es_propia']) || auth()->user()->isAdmin())
                                    <button type="button" 
                                            onclick="toggleAdvertising({{ $p['id'] }}, this)" 
                                            class="btn secondary" 
                                            style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; font-weight: 600; cursor: pointer;"
                                            data-campaign-row="true">
                                        ❌ Quitar
                                    </button>
                                @else
                                    <span style="font-size: 0.75rem; color: var(--muted);">Solo lectura</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $puedeVerEquipoPublicidad ? 10 : 9 }}" style="text-align: center; color: var(--muted); padding: 32px;">
                                No hay productos marcados en campaña de publicidad actualmente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Modal de Desglose de Distribución -->
<div id="distribution-modal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="panel modal-box" style="width: 95%; max-width: 800px; position: relative; padding: 24px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="closeDistributionModal()" aria-label="Cerrar">×</button>
        <h3 id="modal-product-title" style="margin: 0 0 6px; font-size: 1.25rem; color: var(--blue);"></h3>
        <p id="modal-product-code" style="margin: 0 0 16px; font-size: 0.85rem; color: var(--muted); font-family: monospace;"></p>
        
        <h4 style="margin: 0 0 10px; font-size: 0.95rem; font-weight: 600; color: var(--text);">Desglose de Inventario por Sede</h4>
        <div id="modal-distribution-body" style="max-height: 75vh; overflow: auto;">
            <!-- La tabla se inserta dinámicamente -->
        </div>
    </div>
</div>

@if(!empty($puedeMarcarMeta))
<div id="meta-modal" class="modal-overlay" hidden style="z-index: 1200;" data-sedes='@json($sedesMetaDisponibles ?? [])' onclick="if(event.target===this)cerrarMetaModal()">
    <div class="panel modal-box" style="width: 95%; max-width: 420px; position: relative; padding: 24px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="cerrarMetaModal()" aria-label="Cerrar">×</button>
        <h3 style="margin: 0 0 6px; font-size: 1.15rem; color: var(--blue);">Meta de quincena</h3>
        <p class="muted" style="margin: 0 0 12px; font-size: 0.85rem;">Producto <span id="meta-modal-codigo" style="font-family: monospace;"></span>. Solo sedes con stock; se guarda esa cantidad como inicial.</p>
        <div id="meta-modal-sedes" style="max-height: 55vh; overflow: auto;"></div>
        <div style="margin-top: 12px; text-align: right;">
            <a href="{{ route('metas.index') }}" class="btn secondary" style="font-size: .8rem;">Ver panel de metas</a>
        </div>
    </div>
</div>
@endif

<!-- Modal de Desglose de Compras -->
<div id="comprar-modal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="panel modal-box" style="width: 95%; max-width: 800px; position: relative; padding: 24px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="closeComprarModal()" aria-label="Cerrar">×</button>
        <h3 id="modal-comprar-title" style="margin: 0 0 6px; font-size: 1.25rem; color: var(--blue);"></h3>
        <p id="modal-comprar-code" style="margin: 0 0 16px; font-size: 0.85rem; color: var(--muted); font-family: monospace;"></p>
        
        <h4 style="margin: 0 0 10px; font-size: 0.95rem; font-weight: 600; color: var(--text);">Información por Sede</h4>
        <div id="modal-comprar-body" style="max-height: 75vh; overflow: auto;">
            <!-- La tabla se inserta dinámicamente -->
        </div>
    </div>
</div>

<!-- Modal de Resultado de Notificación -->
<div id="notification-result-modal" class="modal-overlay" style="display: none; z-index: 1100;">
    <div class="panel modal-box" style="max-width: 450px; position: relative; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="closeNotificationResultModal()" aria-label="Cerrar">×</button>
        <div style="font-size: 3rem; margin-bottom: 12px;" id="modal-result-icon">🔔</div>
        <h3 id="modal-result-title" style="margin: 0 0 10px; font-size: 1.25rem; color: var(--blue);">Notificación Enviada</h3>
        <p id="modal-result-message" style="margin: 0 0 20px; font-size: 0.9rem; color: var(--text); line-height: 1.5;"></p>
        <button type="button" class="btn" onclick="closeNotificationResultModal()" style="padding: 8px 24px; font-size: 0.9rem; border-radius: 8px; background-color: var(--blue); color: #fff; border: none; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
            Entendido
        </button>
    </div>
</div>

<!-- Modal de Productos por Proveedor -->
<div id="provider-modal" class="modal-overlay" style="display: none;">
    <div class="panel modal-box" style="width: 95%; max-width: 1200px; position: relative; padding: 24px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <button type="button" class="modal-close" onclick="closeProviderModal()" aria-label="Cerrar">×</button>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 8px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.5rem;">📦</span>
                    <h3 id="provider-modal-title" style="margin: 0; font-size: 1.3rem; color: var(--blue); font-weight: 600;"></h3>
                </div>
                <p id="provider-modal-summary" style="margin: 4px 0 0; font-size: 0.85rem; color: var(--muted);"></p>
            </div>
            <button type="button" id="provider-modal-export-btn" class="btn secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--border); margin-right: 28px;">
                <span>📥</span> Exportar Excel
            </button>
        </div>
        
        <div id="provider-modal-body" style="max-height: 75vh; overflow: auto;">
            <!-- La tabla de productos se inserta dinámicamente -->
        </div>
    </div>
</div>

<script>
const allProvidersData = @json(($activeTab ?? '') === 'proveedores' ? $byProvider : []);

function openProviderModalByIndex(index, cardElement) {
    const prov = allProvidersData[index];
    if (prov) {
        openProviderModal(prov.proveedor, prov.productos, cardElement);
    }
}

function updateSsSubcats() {
    // Reset subcategoria and submit to reload options from server
    document.getElementById('ss_subcategoria').value = 'Ninguno';
    document.getElementById('ss-form').submit();
}

function filterProviders(query) {
    const q = query.toLowerCase().trim();
    const cards = document.querySelectorAll('.provider-card');
    cards.forEach(card => {
        const title = card.querySelector('h3').innerText.toLowerCase();
        if (title.includes(q)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateSubcatsAndSubmit() {
    document.getElementById('subcategoria').value = 'Ninguno';
    document.getElementById('categoria').form.submit();
}

function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId).style.display = 'block';
    if(btn) {
        btn.classList.add('active');
    } else {
        // Find and activate the correct button
        if (tabId === 'productos-tab') {
            const currentStatus = document.getElementById('status-filter') ? document.getElementById('status-filter').value : 'MalaDistribucion';
            const btnDist = document.getElementById('tab-btn-dist');
            const btnCompra = document.getElementById('tab-btn-compra');
            if (currentStatus === 'Comprar' && btnCompra) {
                btnCompra.classList.add('active');
            } else if (btnDist) {
                btnDist.classList.add('active');
            }
        } else {
            const targetBtn = document.querySelector(`button[onclick*="${tabId}"]`);
            if (targetBtn) targetBtn.classList.add('active');
        }
    }
    localStorage.setItem('activeCompradorTab', tabId);
}

async function toggleAdvertising(productId, btn) {
    const originalText = btn.innerText;
    const isCampaignRow = btn.hasAttribute('data-campaign-row');
    
    let fechaPublicidad = null;
    if (!isCampaignRow && originalText.includes('Publicitar')) {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = prompt('Ingrese la fecha de inicio de la publicidad (si desea retroactivo):', today);
        if (dateInput === null) {
            return; // Cancelled
        }
        fechaPublicidad = dateInput;
    }
    
    btn.disabled = true;
    btn.innerText = '...';
    try {
        const payload = { producto_id: productId };
        if (fechaPublicidad) {
            payload.fecha_publicidad = fechaPublicidad;
        }
        
        const response = await fetch("{{ route('comprador.publicidad.toggle') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
            if (isCampaignRow) {
                window.location.reload();
                return;
            }
            if (res.status === 'added') {
                btn.innerText = '📢 En campaña';
                btn.className = 'btn primary';
            } else {
                btn.innerText = '➕ Publicitar';
                btn.className = 'btn secondary';
            }
            // Update other matching buttons
            document.querySelectorAll(`button[onclick^="toggleAdvertising(${productId},"]`).forEach(otherBtn => {
                if (otherBtn !== btn) {
                    if (res.status === 'added') {
                        otherBtn.innerText = '📢 En campaña';
                        otherBtn.className = 'btn primary';
                    } else {
                        otherBtn.innerText = '➕ Publicitar';
                        otherBtn.className = 'btn secondary';
                    }
                }
            });
        } else {
            alert(res.message || 'Error al actualizar el estado de publicidad.');
            btn.innerText = originalText;
        }
    } catch (error) {
        console.error(error);
        alert('Error de conexión.');
        btn.innerText = originalText;
    } finally {
        btn.disabled = false;
    }
}

@if(!empty($puedeMarcarMeta))
const sedesMetaFallback = @json(array_values($sedesMetaDisponibles ?? []));
let metaProductoActual = null;
let metaBtnActual = null;
let metaStockPorSede = {};

function fmtStock(n) {
    const v = Number(n || 0);
    return Number.isInteger(v) ? v.toLocaleString('es-VE') : v.toLocaleString('es-VE', { maximumFractionDigits: 2 });
}

function pintarSedesMeta(list, sedesActivas) {
    const filas = sedesMetaFallback
        .map(sede => ({
            sede,
            stock: Number(metaStockPorSede[sede] ?? 0),
            activa: sedesActivas.includes(sede),
        }))
        .filter(row => row.activa || row.stock > 0);

    if (!filas.length) {
        list.innerHTML = '<p class="muted">Este producto no tiene stock en ninguna sede.</p>';
        return;
    }

    list.innerHTML = filas.map(row => {
        return `<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;margin-bottom:8px;background:${row.activa ? '#eff6ff' : '#fff'};">
            <div>
                <div style="font-weight:600;">${row.sede}</div>
                <div class="muted" style="font-size:.78rem;">Stock: <strong style="color:var(--text);">${fmtStock(row.stock)} u.</strong></div>
            </div>
            <button type="button" class="btn ${row.activa ? 'primary' : 'secondary'}" style="padding:4px 10px;font-size:.75rem;"
                data-sede="${row.sede}">${row.activa ? 'Quitar meta' : 'Marcar meta'}</button>
        </div>`;
    }).join('');
    list.querySelectorAll('button[data-sede]').forEach(b => {
        b.addEventListener('click', () => toggleMetaSede(b.dataset.sede, b));
    });
}

async function abrirMetaProducto(btn) {
    const modal = document.getElementById('meta-modal');
    const list = document.getElementById('meta-modal-sedes');
    if (!modal || !list) {
        alert('No se pudo abrir el selector de sede.');
        return;
    }
    metaProductoActual = Number(btn.dataset.productoId);
    metaBtnActual = btn;
    let sedesActivas = [];
    try {
        sedesActivas = JSON.parse(btn.getAttribute('data-sedes-meta') || '[]');
    } catch (e) {
        sedesActivas = [];
    }
    if (!Array.isArray(sedesActivas)) sedesActivas = [];

    document.getElementById('meta-modal-codigo').innerText = btn.dataset.codigo || ('#' + metaProductoActual);
    list.innerHTML = '<p class="muted">Cargando stock por sede…</p>';
    modal.hidden = false;
    modal.style.display = 'flex';

    try {
        const response = await fetch(`{{ url('/metas/productos') }}/${metaProductoActual}/stock`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const res = await response.json();
        if (!response.ok || !res.success) {
            list.innerHTML = `<p class="muted">${res.message || 'No se pudo cargar el stock.'}</p>`;
            return;
        }
        metaStockPorSede = res.stock || {};
        if (Array.isArray(res.sedes_meta)) {
            sedesActivas = res.sedes_meta;
            metaBtnActual.setAttribute('data-sedes-meta', JSON.stringify(sedesActivas));
        }
        pintarSedesMeta(list, sedesActivas);
    } catch (e) {
        console.error(e);
        list.innerHTML = '<p class="muted">Error de conexión al cargar stock.</p>';
    }
}

function cerrarMetaModal() {
    const modal = document.getElementById('meta-modal');
    if (modal) {
        modal.hidden = true;
        modal.style.display = 'none';
    }
    metaProductoActual = null;
    metaBtnActual = null;
    metaStockPorSede = {};
}

async function toggleMetaSede(sede, btn) {
    if (!metaProductoActual || !metaBtnActual) return;
    let sedesActivas = [];
    try {
        sedesActivas = JSON.parse(metaBtnActual.getAttribute('data-sedes-meta') || '[]');
    } catch (e) {
        sedesActivas = [];
    }
    if (!Array.isArray(sedesActivas)) sedesActivas = [];
    const quitar = sedesActivas.includes(sede);
    if (!quitar && Number(metaStockPorSede[sede] ?? 0) <= 0) {
        alert('No hay stock en ' + sede + ' para marcar como meta.');
        return;
    }
    btn.disabled = true;
    try {
        const response = await fetch(quitar ? "{{ route('metas.destroy') }}" : "{{ route('metas.store') }}", {
            method: quitar ? 'DELETE' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ producto_id: metaProductoActual, sede })
        });
        const res = await response.json();
        if (!response.ok || !res.success) {
            alert(res.message || 'No se pudo actualizar la meta.');
            return;
        }
        let next = sedesActivas.slice();
        if (quitar) {
            next = next.filter(s => s !== sede);
        } else if (!next.includes(sede)) {
            next.push(sede);
        }
        metaBtnActual.setAttribute('data-sedes-meta', JSON.stringify(next));
        metaBtnActual.innerText = next.length ? ('🎯 Meta (' + next.length + ')') : '➕ Meta';
        metaBtnActual.className = 'btn ' + (next.length ? 'primary' : 'secondary') + ' btn-meta-producto';
        metaBtnActual.style.padding = '4px 8px';
        metaBtnActual.style.fontSize = '0.75rem';
        metaBtnActual.style.borderRadius = '6px';
        metaBtnActual.style.fontWeight = '600';
        metaBtnActual.style.cursor = 'pointer';
        const list = document.getElementById('meta-modal-sedes');
        if (list) pintarSedesMeta(list, next);
    } catch (e) {
        console.error(e);
        alert('Error de conexión.');
    } finally {
        btn.disabled = false;
    }
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-meta-producto');
    if (btn) {
        e.preventDefault();
        abrirMetaProducto(btn);
    }
});
@endif

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', () => {
    const filtroPub = document.getElementById('filtro-pub-user');
    if (filtroPub) {
        filtroPub.addEventListener('change', function () {
            const value = this.value;
            document.querySelectorAll('#publicidad-tab tbody tr[data-user-id]').forEach(function (row) {
                row.style.display = (value === 'todos' || row.dataset.userId === value) ? '' : 'none';
            });
        });
    }
});

function filterQPedir(value) {
    const needle = (value || '').toLowerCase().trim();
    document.querySelectorAll('#qpedir-tab .qpedir-row').forEach((row) => {
        row.style.display = !needle || (row.dataset.filter || '').includes(needle) ? '' : 'none';
    });
}

function openDistributionModal(code, name, stocks, demands) {
    document.getElementById('modal-product-title').innerText = name;
    document.getElementById('modal-product-code').innerText = 'Código: ' + code;
    
    const sedes = @json(config('inventario.sedes_stock'));
    const displayNames = @json(config('inventario.display'));
    
    let html = `
        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff; width: max-content; min-width: 100%;">
            <table class="data-table" style="margin: 0; font-size: 0.85rem; width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 8px 12px;">Sede</th>
                        <th class="col-number" style="padding: 8px 12px; text-align: right;">Stock</th>
                        <th class="col-number" style="padding: 8px 12px; text-align: right;">Demanda</th>
                        <th class="col-number" style="padding: 8px 12px; text-align: right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    sedes.forEach(sede => {
        const stock = parseInt(stocks[sede] ?? 0);
        const demand = parseInt(demands[sede] ?? 0);
        const diff = stock - demand;
        
        let diffText = diff;
        let diffStyle = '';
        if (diff > 0) {
            diffText = '+' + diff + ' (Excedente)';
            diffStyle = 'color: #047857; font-weight: 600; background: #ecfdf5; border-radius: 4px; padding: 2px 6px; display: inline-block;';
        } else if (diff < 0) {
            diffText = diff + ' (Faltante)';
            diffStyle = 'color: #b91c1c; font-weight: 600; background: #fef2f2; border-radius: 4px; padding: 2px 6px; display: inline-block;';
        } else {
            diffText = '0';
            diffStyle = 'color: var(--muted); font-weight: 500;';
        }
        
        const displayName = displayNames[sede] || Sede;
        
        html += `
            <tr>
                <td style="padding: 8px 12px;"><strong>${displayName}</strong></td>
                <td class="col-number" style="padding: 8px 12px; text-align: right;">${stock}</td>
                <td class="col-number" style="padding: 8px 12px; text-align: right;">${demand}</td>
                <td class="col-number" style="padding: 8px 12px; text-align: right;"><span style="${diffStyle}">${diffText}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('modal-distribution-body').innerHTML = html;
    document.getElementById('distribution-modal').style.display = 'flex';
}

function closeDistributionModal() {
    document.getElementById('distribution-modal').style.display = 'none';
}

document.getElementById('distribution-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDistributionModal();
    }
});

function openComprarModal(code, name, stocks, ultimasVentas, ultimasCompras) {
    document.getElementById('modal-comprar-title').innerText = name;
    document.getElementById('modal-comprar-code').innerText = 'Código: ' + code;
    
    const sedes = @json(config('inventario.sedes_stock'));
    const displayNames = @json(config('inventario.display'));
    
    let html = `
        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff; width: max-content; min-width: 100%;">
            <table class="data-table" style="margin: 0; font-size: 0.85rem; width: 100%;">
                <thead>
                    <tr>
                        <th style="padding: 8px 12px;">Sede</th>
                        <th class="col-number" style="padding: 8px 12px; text-align: right;">Existencia</th>
                        <th style="padding: 8px 12px; text-align: right;">Última Compra</th>
                        <th style="padding: 8px 12px; text-align: right;">Última Venta</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    sedes.forEach(sede => {
        const stock = parseInt(stocks[sede] ?? 0);
        const uc = ultimasCompras[sede] ? ultimasCompras[sede] : '<span style="color:var(--muted);font-style:italic;">Sin datos</span>';
        const uv = ultimasVentas[sede] ? ultimasVentas[sede] : '<span style="color:var(--muted);font-style:italic;">Sin datos</span>';
        
        const displayName = displayNames[sede] || sede;
        
        html += `
            <tr>
                <td style="padding: 8px 12px;"><strong>${displayName}</strong></td>
                <td class="col-number" style="padding: 8px 12px; text-align: right;">${stock}</td>
                <td style="padding: 8px 12px; text-align: right;">${uc}</td>
                <td style="padding: 8px 12px; text-align: right;">${uv}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('modal-comprar-body').innerHTML = html;
    document.getElementById('comprar-modal').style.display = 'flex';
}

function closeComprarModal() {
    document.getElementById('comprar-modal').style.display = 'none';
}

document.getElementById('comprar-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeComprarModal();
    }
});

async function handleNotificationSubmit(event, form) {
    event.preventDefault();
    
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.innerText;
    button.disabled = true;
    button.innerText = 'Enviando...';
    button.style.opacity = '0.7';
    
    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        
        if (response.ok) {
            const destSede = form.querySelector('input[name="sede_destino"]').value;
            const displayNames = @json(config('inventario.display'));
            const destSedeName = displayNames[destSede] || destSede;
            
            document.getElementById('modal-result-icon').innerText = '✅';
            document.getElementById('modal-result-title').innerText = 'Notificación Enviada';
            document.getElementById('modal-result-message').innerText = `La propuesta de redistribución ha sido enviada con éxito al personal de la sede ${destSedeName}.`;
            
            document.getElementById('notification-result-modal').style.display = 'flex';
            
            // Highlight card as notified
            const card = form.closest('div');
            if (card) {
                card.style.border = '1px solid #d1fae5';
                card.style.background = '#f0fdf4';
            }
            button.innerText = 'Notificado';
            button.style.backgroundColor = '#10b981'; // green
            button.style.opacity = '1';
            button.disabled = true;
        } else {
            let errMsg = 'Ocurrió un error al enviar la notificación.';
            try {
                const errData = await response.json();
                if (errData.errors && errData.errors.notify) {
                    errMsg = errData.errors.notify[0];
                }
            } catch(e) {}
            
            document.getElementById('modal-result-icon').innerText = '❌';
            document.getElementById('modal-result-title').innerText = 'Error al Enviar';
            document.getElementById('modal-result-message').innerText = errMsg;
            document.getElementById('notification-result-modal').style.display = 'flex';
            
            button.disabled = false;
            button.innerText = originalText;
            button.style.opacity = '1';
        }
    } catch (error) {
        console.error(error);
        document.getElementById('modal-result-icon').innerText = '❌';
        document.getElementById('modal-result-title').innerText = 'Error de Conexión';
        document.getElementById('modal-result-message').innerText = 'No se pudo comunicar con el servidor. Verifique su conexión.';
        document.getElementById('notification-result-modal').style.display = 'flex';
        
        button.disabled = false;
        button.innerText = originalText;
        button.style.opacity = '1';
    }
}

function closeNotificationResultModal() {
    document.getElementById('notification-result-modal').style.display = 'none';
}

document.getElementById('notification-result-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeNotificationResultModal();
    }
});

let currentProviderProducts = [];
let currentProviderName = '';
let currentCardElement = null;
const providerProductsCache = {};

function openProviderModal(providerName, productos, cardElement) {
    currentProviderName = providerName;
    currentCardElement = cardElement;
    
    if (!providerProductsCache[providerName]) {
        providerProductsCache[providerName] = JSON.parse(JSON.stringify(productos));
    }
    currentProviderProducts = providerProductsCache[providerName];
    
    renderProviderModalTable();
    document.getElementById('provider-modal').style.display = 'flex';
}

function updateProviderModalSummary() {
    const activeProducts = currentProviderProducts.filter(p => !p.excluir_compras);
    const totalProducts = activeProducts.length;
    const totalUnits = activeProducts.reduce((sum, p) => sum + (parseInt(p.faltante) || 0), 0);
    document.getElementById('provider-modal-summary').innerText = `${totalProducts} productos · ${totalUnits} unidades sugeridas a comprar`;
}

function renderProviderModalTable() {
    updateProviderModalSummary();
    
    // Attach event listener to export button by cloning to clear previous listeners
    const exportBtn = document.getElementById('provider-modal-export-btn');
    const newExportBtn = exportBtn.cloneNode(true);
    exportBtn.parentNode.replaceChild(newExportBtn, exportBtn);
    
    newExportBtn.addEventListener('click', () => {
        downloadProviderCsv(currentProviderName, currentProviderProducts);
    });
    
    let html = `
        <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #fff; width: max-content; min-width: 100%;">
            <table class="data-table" style="margin: 0; font-size: 0.85rem; width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 110px; padding: 8px 12px;">Código</th>
                        <th style="padding: 8px 12px;">Producto</th>
                        <th style="width: 150px; padding: 8px 12px;">Categoría</th>
                        <th class="col-number" style="width: 100px; padding: 8px 12px; text-align: right;">Stock Global</th>
                        <th class="col-number" style="width: 100px; padding: 8px 12px; text-align: right;">Demanda</th>
                        <th class="col-number" style="width: 110px; padding: 8px 12px; text-align: right; color: #b91c1c;">A Comprar</th>
                        <th style="width: 100px; padding: 8px 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    currentProviderProducts.forEach(prod => {
        const cat = prod.categoria || '—';
        const subcat = prod.subcategoria ? `<div style="font-size: 0.75rem; opacity: 0.8;">${prod.subcategoria}</div>` : '';
        const isExcluded = !!prod.excluir_compras;
        
        const rowStyle = isExcluded ? 'background-color: #f8fafc; opacity: 0.6; cursor: pointer; user-select: none;' : 'cursor: pointer; user-select: none;';
        const textStyle = isExcluded ? 'text-decoration: line-through; color: var(--muted);' : '';
        
        const stockGlobalCell = isExcluded 
            ? `<td class="col-number" style="padding: 8px 12px; text-align: right; ${textStyle}">${prod.total_stock}</td>`
            : `<td class="col-number" style="padding: 8px 12px; text-align: right; color: var(--blue); text-decoration: underline; cursor: pointer; font-weight: 600;" 
                   onclick="event.stopPropagation(); openDistributionModalFromProvider('${prod.cod_centro}')"
                   title="Ver desglose por sede">
                   ${prod.total_stock}
               </td>`;
               
        const quantityInput = isExcluded
            ? `<span style="font-weight: 600; color: var(--muted);">${prod.faltante}</span>`
            : `<input type="number" 
                      value="${prod.faltante}" 
                      min="0" 
                      style="width: 75px; text-align: right; border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; font-weight: 600; color: #b91c1c; background: #fff;" 
                      onclick="event.stopPropagation()"
                      oninput="updateProductQuantity('${prod.cod_centro}', this.value)">`;
                      
        const actionBtn = isExcluded
            ? `<span class="tag" style="background: #e2e8f0; color: #64748b; font-weight: 600;">Ignorado</span>`
            : `<span class="tag" style="background: #2563eb; color: #fff; font-weight: 600;">Comprar</span>`;
        
        html += `
            <tr style="${rowStyle}" ondblclick="toggleExcludeProduct('${prod.cod_centro}', event)" title="Doble clic para ignorar producto">
                <td class="col-code" style="padding: 8px 12px; ${textStyle}">${prod.cod_centro}</td>
                <td style="padding: 8px 12px; font-weight: 600; ${textStyle}">${prod.producto}</td>
                <td style="padding: 8px 12px; color: var(--muted); ${textStyle}">${cat}${subcat}</td>
                ${stockGlobalCell}
                <td class="col-number" style="padding: 8px 12px; text-align: right; ${textStyle}">${prod.total_demanda}</td>
                <td class="col-number" style="padding: 8px 12px; text-align: right; ${textStyle}">
                    ${quantityInput}
                </td>
                <td style="padding: 8px 12px; text-align: center;">
                    ${actionBtn}
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('provider-modal-body').innerHTML = html;
}

function updateProductQuantity(code, value) {
    const val = parseInt(value) || 0;
    const prod = currentProviderProducts.find(p => p.cod_centro === code);
    if (prod) {
        prod.faltante = val;
        updateProviderModalSummary();
        updateProviderCard();
    }
}

async function toggleExcludeProduct(code, event) {
    if(event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const prod = currentProviderProducts.find(p => p.cod_centro === code);
    if (prod) {
        prod.excluir_compras = !prod.excluir_compras;
        renderProviderModalTable();
        updateProviderCard();
        
        try {
            const resp = await fetch(`/compras/productos/${prod.id}/toggle-exclusion`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            const data = await resp.json();
            if(data.status !== 'success') {
                prod.excluir_compras = !prod.excluir_compras;
                renderProviderModalTable();
                updateProviderCard();
            }
        } catch(e) {
            prod.excluir_compras = !prod.excluir_compras;
            renderProviderModalTable();
            updateProviderCard();
        }
    }
}

function updateProviderCard() {
    if (currentCardElement) {
        const activeProducts = currentProviderProducts.filter(p => !p.excluir_compras);
        const totalUnits = activeProducts.reduce((sum, p) => sum + (parseInt(p.faltante) || 0), 0);
        
        const tagNo = currentCardElement.querySelector('.tag.no');
        const tagWarn = currentCardElement.querySelector('.tag.warn');
        if (tagNo) tagNo.innerText = activeProducts.length;
        if (tagWarn) tagWarn.innerText = totalUnits + ' u.';
    }
}

function openDistributionModalFromProvider(code) {
    const prod = currentProviderProducts.find(p => p.cod_centro === code);
    if (prod) {
        openDistributionModal(prod.cod_centro, prod.producto, prod.stocks || {}, prod.demands || {});
    }
}

function downloadProviderCsv(providerName, productos) {
    const activeProducts = productos.filter(p => !p.excluir_compras);
    
    let csvContent = "\ufeff"; // BOM for Excel encoding support (UTF-8)
    csvContent += "Código;Producto;Categoría;Stock Global;Demanda;A Comprar\n";
    
    activeProducts.forEach(p => {
        const nameEscaped = (p.producto || "").replace(/;/g, ",");
        const catEscaped = (p.categoria || "").replace(/;/g, ",");
        csvContent += `${p.cod_centro};${nameEscaped};${catEscaped};${p.total_stock};${p.total_demanda};${p.faltante}\n`;
    });
    
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `compras_proveedor_${providerName.toLowerCase().replace(/[^a-z0-9]/g, "_")}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function closeProviderModal() {
    document.getElementById('provider-modal').style.display = 'none';
}

document.getElementById('provider-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProviderModal();
    }
});

// Include Chart.js dynamically if not present
if (typeof Chart === 'undefined') {
    const script = document.createElement('script');
    script.src = "https://cdn.jsdelivr.net/npm/chart.js";
    document.head.appendChild(script);
}

function generatePdfCharts() {
    const stats = @json($qPedirStats ?? []);
    if (!stats.global || Object.keys(stats.global).length === 0) {
        alert("No hay datos suficientes para generar gráficos.");
        return;
    }

    // Colors mapping
    const colors = {
        'comprado': '#10b981',
        'fuera_de_mercado': '#ef4444',
        'pendiente': '#f59e0b'
    };
    const labels = {
        'comprado': 'Comprado',
        'fuera_de_mercado': 'Fuera de mercado',
        'pendiente': 'Pendiente / Sin Acción'
    };

    // 1. Pie Chart
    const pieCtx = document.getElementById('hiddenPieChart').getContext('2d');
    const pieLabels = Object.keys(stats.global).map(k => labels[k] || k);
    const pieData = Object.values(stats.global);
    const pieBg = Object.keys(stats.global).map(k => colors[k] || '#ccc');
    
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: pieLabels,
            datasets: [{ data: pieData, backgroundColor: pieBg }]
        },
        options: {
            animation: false,
            responsive: false
        }
    });

    // 2. Bar Chart
    const barCtx = document.getElementById('hiddenBarChart').getContext('2d');
    const categories = Object.keys(stats.categorias || {});
    
    const datasets = Object.keys(colors).map(estado => {
        return {
            label: labels[estado],
            backgroundColor: colors[estado],
            data: categories.map(c => stats.categorias[c][estado] || 0)
        };
    });

    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: categories,
            datasets: datasets
        },
        options: {
            animation: false,
            responsive: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });

    // Timeout para asegurar render
    setTimeout(() => {
        const pieBase64 = document.getElementById('hiddenPieChart').toDataURL('image/png');
        const barBase64 = document.getElementById('hiddenBarChart').toDataURL('image/png');

        document.getElementById('pdf_chart_pie').value = pieBase64;
        document.getElementById('pdf_chart_bar').value = barBase64;
        document.getElementById('pdfChartsForm').submit();
    }, 500);
}
</script>
</div>
@endsection
