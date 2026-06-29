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
</style>
@endpush
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="margin: 0;">Compras y Distribución</h1>
        <p class="lead" style="margin: 4px 0 0;">Analice el stock global para compras o redistribución de inventario entre sucursales.</p>
    </div>
    <div>
        <a href="{{ route('comprador.export', request()->query()) }}" class="btn secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border);">
            <span>📥</span> Exportar Excel Global
        </a>
    </div>
</div>

<!-- Selector de Pestañas (Tabs) -->
<div class="segmented" style="margin-bottom: 24px; display: flex; width: max-content;">
    @if(!auth()->user()->isMarketing())
        <button type="button" class="tab-btn active" onclick="switchTab('productos-tab', this)">Productos y Distribución</button>
        <button type="button" class="tab-btn" onclick="switchTab('proveedores-tab', this)">General por Proveedor</button>
        <button type="button" class="tab-btn" onclick="switchTab('sobrestock-tab', this)">Sobre Stock / Sin Rotación</button>
    @else
        <button type="button" class="tab-btn active" onclick="switchTab('sobrestock-tab', this)">Sobre Stock / Sin Rotación</button>
    @endif
    @if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
        <button type="button" class="tab-btn" onclick="switchTab('publicidad-tab', this)">Efectividad Publicidad</button>
    @endif
</div>

@if(!auth()->user()->isMarketing())
<!-- Tab 1: Productos y Distribución -->
<div id="productos-tab" class="tab-content">
    <div class="panel">
        <h2 style="margin: 0 0 16px; font-size: 1.25rem;">Distribución y Compras por Producto</h2>
        
        {{-- Barra de búsqueda independiente --}}
        <form method="GET" id="tab1-search-form" style="margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
            {{-- Preserve all current filter values so they don't reset on search --}}
            <input type="hidden" name="categoria" value="{{ $selectedCategoria ?? 'Ninguno' }}">
            <input type="hidden" name="subcategoria" value="{{ $selectedSubcategoria ?? 'Ninguno' }}">
            <input type="hidden" name="proveedor" value="{{ $selectedProveedor ?? 'Ninguno' }}">
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'Todos' }}">
            <input type="hidden" name="tp" value="{{ $tp ?? 60 }}">
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
                <a href="{{ route('comprador.dashboard', array_merge(request()->except('q'), ['categoria' => $selectedCategoria ?? 'Ninguno', 'subcategoria' => $selectedSubcategoria ?? 'Ninguno', 'proveedor' => $selectedProveedor ?? 'Ninguno', 'status' => $statusFilter ?? 'Todos'])) }}" style="font-size:0.82rem; color:var(--muted); text-decoration:none; white-space:nowrap;">✕ Limpiar búsqueda</a>
            @endif
        </form>

        {{-- Filtros de dropdowns --}}
        <form method="GET" class="filter-bar" style="margin-bottom: 20px;">
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
                <label for="status">Estado</label>
                <select id="status" name="status" onchange="this.form.submit();">
                    <option value="Todos" @selected($statusFilter === 'Todos')>Todos los estados</option>
                    <option value="Comprar" @selected($statusFilter === 'Comprar')>Necesita Compra (COMPRAR)</option>
                    <option value="MalaDistribucion" @selected($statusFilter === 'MalaDistribucion')>Mala Distribución</option>
                </select>
            </div>
            <div class="field">
                <label for="tp">Proyectar Demanda a (días):</label>
                <input type="number" id="tp" name="tp" value="{{ $tp ?? 60 }}" min="1" step="1" onchange="this.form.submit();" style="border-color: var(--blue); font-weight: 500; width: 130px; padding: 10px; border-radius: 8px;">
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Producto</th>
                        <th style="width: 160px;">Categoría</th>
                        <th class="col-number" style="width: 110px;">Stock Global</th>
                        <th class="col-number" style="width: 110px;">Demanda Global</th>
                        <th style="width: 160px;">Estado</th>
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
                                    <span class="tag warn" 
                                          style="font-size: 0.7rem; padding: 3px 8px; cursor: pointer;"
                                          onclick="openComprarModal('{{ $row['cod_centro'] }}', {{ json_encode($row['producto']) }}, {{ json_encode($row['stocks']) }}, {{ json_encode($row['ultimas_ventas'] ?? []) }}, {{ json_encode($row['ultimas_compras'] ?? []) }})"
                                          title="Ver detalles por sede">COMPRAR</span>
                                @else
                                    <span class="tag req" 
                                          style="font-size: 0.7rem; padding: 3px 8px; cursor: pointer;"
                                          onclick="openDistributionModal('{{ $row['cod_centro'] }}', {{ json_encode($row['producto']) }}, {{ json_encode($row['stocks']) }}, {{ json_encode($row['demands']) }})"
                                          title="Ver por qué hay mala distribución">
                                        MALA DISTRIBUCIÓN
                                    </span>
                                @endif
                            </td>
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
<div id="proveedores-tab" class="tab-content" style="display: none;">
    <!-- Barra de búsqueda de proveedor y filtro de demanda -->
    <form method="GET" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
        <!-- <input type="hidden" name="categoria" value="{{ $selectedCategoria ?? 'Ninguno' }}"> -->
        <input type="hidden" name="subcategoria" value="{{ $selectedSubcategoria ?? 'Ninguno' }}">
        <input type="hidden" name="proveedor" value="{{ $selectedProveedor ?? 'Ninguno' }}">
        <input type="hidden" name="status" value="{{ $statusFilter ?? 'Todos' }}">
        <input type="hidden" name="q" value="{{ $q ?? '' }}">
        
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
<div id="sobrestock-tab" class="tab-content" style="display: none;">
    
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

    {{-- ── Resumen por Sede (collapsible) ── --}}
    <details style="margin-bottom: 20px;">
        <summary class="panel" style="padding: 14px 20px; cursor: pointer; font-weight: 600; color: var(--blue); display: flex; align-items: center; gap: 8px; user-select: none;">
            📊 Resumen por Sede <span style="font-weight: 400; color: var(--muted); font-size: 0.85rem;">(click para expandir)</span>
        </summary>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; margin-top: 12px;">
            @foreach($resumenPorSede as $sedeSummary)
            <div class="panel" style="padding: 14px;">
                <h4 style="margin: 0 0 10px; color: var(--blue);">{{ $sedeSummary['display'] }}</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.85rem;">
                    <span style="color: var(--muted);">Productos:</span>
                    <span style="font-weight: 600;">{{ number_format($sedeSummary['total_productos']) }}</span>
                    <span style="color: var(--muted);">Stock total:</span>
                    <span style="font-weight: 600;">{{ number_format($sedeSummary['stock_total']) }} u.</span>
                    <span style="color: var(--muted);">Sin rotación:</span>
                    <span style="font-weight: 600; color: #ef4444;">{{ $sedeSummary['sin_rotacion'] }}</span>
                    <span style="color: var(--muted);">Sobrestock:</span>
                    <span style="font-weight: 600; color: #f97316;">{{ $sedeSummary['sobrestock'] }}</span>
                    <span style="color: var(--muted);">Inmovilizados:</span>
                    <span style="font-weight: 600; color: #dc2626;">{{ $sedeSummary['inmovilizados'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </details>

    {{-- ── Resumen Rotación & Sobrestock (collapsible) ── --}}
    <details style="margin-bottom: 20px;">
        <summary class="panel" style="padding: 14px 20px; cursor: pointer; font-weight: 600; color: var(--blue); display: flex; align-items: center; gap: 8px; user-select: none;">
            📈 Detalle por Categoría de Riesgo <span style="font-weight: 400; color: var(--muted); font-size: 0.85rem;">(click para expandir)</span>
        </summary>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 12px;">
            {{-- Rotación --}}
            <div class="panel" style="padding: 16px;">
                <h4 style="margin: 0 0 12px; color: var(--text);">Clasificación de Rotación</h4>
                @foreach($resumenRiesgo['rotacion'] as $label => $count)
                    @php
                        $barColor = match($label) { 'Normal' => '#22c55e', 'Lenta' => '#eab308', 'Riesgo' => '#f97316', 'Sin rotación' => '#ef4444', default => '#94a3b8' };
                        $pct = $resumenRiesgo['total'] > 0 ? round(($count / $resumenRiesgo['total']) * 100, 1) : 0;
                    @endphp
                    <div style="margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 3px;">
                            <span>{{ $label }}</span>
                            <span style="font-weight: 600;">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div style="background: #f1f5f9; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: {{ $barColor }}; height: 100%; width: {{ $pct }}%; border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Sobrestock --}}
            <div class="panel" style="padding: 16px;">
                <h4 style="margin: 0 0 12px; color: var(--text);">Clasificación de Sobrestock</h4>
                @foreach($resumenRiesgo['sobrestock'] as $label => $count)
                    @php
                        $barColor = match($label) { 'Normal' => '#22c55e', 'Vigilar' => '#eab308', 'Sobrestock' => '#f97316', 'Sobrestock Crítico' => '#ef4444', default => '#94a3b8' };
                        $pct = $resumenRiesgo['total'] > 0 ? round(($count / $resumenRiesgo['total']) * 100, 1) : 0;
                    @endphp
                    <div style="margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 3px;">
                            <span>{{ $label }}</span>
                            <span style="font-weight: 600;">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div style="background: #f1f5f9; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: {{ $barColor }}; height: 100%; width: {{ $pct }}%; border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Estados especiales --}}
            <div class="panel" style="padding: 16px;">
                <h4 style="margin: 0 0 12px; color: var(--text);">Estados Especiales</h4>
                @foreach($resumenRiesgo['estados'] as $label => $count)
                    @php
                        $barColor = $label === 'Compra Reciente Sin Rotación' ? '#ef4444' : '#f97316';
                        $pct = $resumenRiesgo['total'] > 0 ? round(($count / $resumenRiesgo['total']) * 100, 1) : 0;
                    @endphp
                    <div style="margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 3px;">
                            <span>{{ $label }}</span>
                            <span style="font-weight: 600;">{{ $count }} ({{ $pct }}%)</span>
                        </div>
                        <div style="background: #f1f5f9; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div style="background: {{ $barColor }}; height: 100%; width: {{ $pct }}%; border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </details>

    {{-- ── Barra de búsqueda independiente para Sobrestock ── --}}
    <form method="GET" id="ss-search-form" style="margin-bottom: 14px; display: flex; align-items: center; gap: 10px;">
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
        <div class="field" style="width: 160px;">
            <label for="ss_estado">Estado</label>
            <select id="ss_estado" name="ss_estado" onchange="document.getElementById('ss-form').submit();">
                <option value="Todos">Todos</option>
                <option value="Inventario Inmovilizado" @selected($ssFilters['estado_filter'] === 'Inventario Inmovilizado')>Inmovilizado</option>
                <option value="Compra Reciente Sin Rotación" @selected($ssFilters['estado_filter'] === 'Compra Reciente Sin Rotación')>Compra s/ Rotación</option>
                <option value="Sin estado" @selected($ssFilters['estado_filter'] === 'Sin estado')>Sin estado especial</option>
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
                        <th style="width: 100px; text-align: right;">
                            <a href="{{ $sortUrl('dias_sin_venta') }}" style="color: inherit; text-decoration: none;">Días s/v {{ $sortIcon('dias_sin_venta') }}</a>
                        </th>
                        <th style="width: 100px; text-align: center;">Rotación</th>
                        <th style="width: 95px; text-align: right;">
                            <a href="{{ $sortUrl('meses_inventario') }}" style="color: inherit; text-decoration: none;">Meses inv {{ $sortIcon('meses_inventario') }}</a>
                        </th>
                        <th style="width: 120px; text-align: center;">Sobrestock</th>
                        <th style="width: 140px; text-align: center;">Estado</th>
                        @if(auth()->user()->isMarketing() || auth()->user()->isAdmin())
                            <th style="width: 120px; text-align: center;">Publicidad</th>
                        @endif
                        <th style="width: 100px; text-align: right;">
                            <a href="{{ $sortUrl('prioridad') }}" style="color: inherit; text-decoration: none;">Prioridad {{ $sortIcon('prioridad') }}</a>
                        </th>
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
                            <td style="text-align: center;">
                                @if($item['estado'])
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; background: {{ $item['estado_color'] === 'rojo' ? '#fef2f2' : '#fff7ed' }}; color: {{ $item['estado_color'] === 'rojo' ? '#dc2626' : '#ea580c' }}; border: 1px solid {{ $item['estado_color'] === 'rojo' ? '#dc262620' : '#ea580c20' }};">
                                        {{ $item['estado'] }}
                                    </span>
                                @else
                                    <span style="color: var(--muted); font-size: 0.8rem;">—</span>
                                @endif
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
                            <td style="text-align: right; font-weight: 600; color: {{ $item['prioridad'] > 50000 ? '#dc2626' : ($item['prioridad'] > 10000 ? '#ea580c' : '#64748b') }};">
                                {{ number_format($item['prioridad']) }}
                            </td>
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
                            <td colspan="{{ (auth()->user()->isMarketing() || auth()->user()->isAdmin()) ? 14 : 13 }}" style="text-align: center; color: var(--muted); padding: 24px;">
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
<div id="publicidad-tab" class="tab-content" style="display: none;">
    <div class="panel">
        <h2 style="margin: 0 0 8px; font-size: 1.25rem; color: var(--blue);">Efectividad de Campañas de Publicidad</h2>
        <p class="muted" style="margin-bottom: 20px;">Lleva el control de los productos promocionados, su última venta inicial y si han tenido nuevas ventas después del inicio de la campaña.</p>
        
        <div class="table-wrap">
            <table class="data-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Producto</th>
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
                        <tr>
                            <td style="font-family: monospace; font-size: 0.85rem; color: var(--blue);">{{ $p['codigo'] }}</td>
                            <td style="font-weight: 500;">
                                {{ $p['producto'] }}
                                <div style="font-size: 0.75rem; color: var(--muted);">{{ $p['proveedor'] }}</div>
                            </td>
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
                                    </span>
                                @else
                                    <span style="display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #fef2f2; color: #ef4444; border: 1px solid #ef444430;">
                                        🔴 Sin ventas aún
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <button type="button" 
                                        onclick="toggleAdvertising({{ $p['id'] }}, this)" 
                                        class="btn secondary" 
                                        style="padding: 4px 8px; font-size: 0.75rem; border-radius: 6px; font-weight: 600; cursor: pointer;"
                                        data-campaign-row="true">
                                    ❌ Quitar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--muted); padding: 32px;">
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
const allProvidersData = @json($byProvider);

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
        document.querySelector(`button[onclick*="${tabId}"]`).classList.add('active');
    }
    localStorage.setItem('activeCompradorTab', tabId);
}

async function toggleAdvertising(productId, btn) {
    const originalText = btn.innerText;
    const isCampaignRow = btn.hasAttribute('data-campaign-row');
    btn.disabled = true;
    btn.innerText = '...';
    try {
        const response = await fetch("{{ route('comprador.publicidad.toggle') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ producto_id: productId })
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
            alert('Error al actualizar el estado de publicidad.');
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

// Restore active tab on page load
document.addEventListener('DOMContentLoaded', () => {
    const isMarketing = @json(auth()->user()->isMarketing());
    let defaultTab = isMarketing ? 'sobrestock-tab' : 'productos-tab';
    const activeTab = localStorage.getItem('activeCompradorTab') || defaultTab;
    if (isMarketing && activeTab !== 'sobrestock-tab' && activeTab !== 'publicidad-tab') {
        switchTab('sobrestock-tab', null);
    } else {
        switchTab(activeTab, null);
    }
});

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
    const activeProducts = currentProviderProducts.filter(p => !p.excluded);
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
        const isExcluded = !!prod.excluded;
        
        const rowStyle = isExcluded ? 'background-color: #f8fafc; opacity: 0.6;' : '';
        const textStyle = isExcluded ? 'text-decoration: line-through; color: var(--muted);' : '';
        
        const stockGlobalCell = isExcluded 
            ? `<td class="col-number" style="padding: 8px 12px; text-align: right; ${textStyle}">${prod.total_stock}</td>`
            : `<td class="col-number" style="padding: 8px 12px; text-align: right; color: var(--blue); text-decoration: underline; cursor: pointer; font-weight: 600;" 
                   onclick="openDistributionModalFromProvider('${prod.cod_centro}')"
                   title="Ver desglose por sede">
                   ${prod.total_stock}
               </td>`;
               
        const quantityInput = isExcluded
            ? `<span style="font-weight: 600; color: var(--muted);">${prod.faltante}</span>`
            : `<input type="number" 
                      value="${prod.faltante}" 
                      min="0" 
                      style="width: 75px; text-align: right; border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; font-weight: 600; color: #b91c1c; background: #fff;" 
                      oninput="updateProductQuantity('${prod.cod_centro}', this.value)">`;
                      
        const actionBtn = isExcluded
            ? `<button type="button" class="btn secondary" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px;" onclick="toggleExcludeProduct('${prod.cod_centro}')">Incluir</button>`
            : `<button type="button" class="btn req" style="padding: 4px 8px; font-size: 0.75rem; border-radius: 4px; background-color: #ef4444; color: #fff; border: none; cursor: pointer;" onclick="toggleExcludeProduct('${prod.cod_centro}')">Excluir</button>`;
        
        html += `
            <tr style="${rowStyle}">
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

function toggleExcludeProduct(code) {
    const prod = currentProviderProducts.find(p => p.cod_centro === code);
    if (prod) {
        prod.excluded = !prod.excluded;
        renderProviderModalTable();
        updateProviderCard();
    }
}

function updateProviderCard() {
    if (currentCardElement) {
        const activeProducts = currentProviderProducts.filter(p => !p.excluded);
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
    const activeProducts = productos.filter(p => !p.excluded);
    
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
</script>
    </div>
</div>
@endsection
