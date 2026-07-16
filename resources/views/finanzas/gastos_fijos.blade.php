@extends('layouts.app')
@section('title', 'Gastos Fijos')
@section('content')

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .gf-page {
        padding: 24px;
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        min-height: 100vh;
    }

    /* ── Header ── */
    .gf-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .gf-title {
        margin: 0;
        font-size: 1.6rem;
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .gf-title span { color: #2563eb; }

    /* ── Notifications Panel ── */
    .gf-notif-panel {
        background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
        border: 1.5px solid #fbbf24;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 24px;
        box-shadow: 0 2px 12px rgba(251,191,36,0.15);
    }
    .gf-notif-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        font-weight: 700;
        font-size: 1.05rem;
        color: #92400e;
    }
    .gf-notif-header svg { flex-shrink: 0; }
    .gf-notif-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 10px;
    }
    .gf-notif-item {
        background: rgba(255,255,255,0.8);
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.9rem;
        border-left: 4px solid #f59e0b;
        transition: transform 0.15s;
    }
    .gf-notif-item:hover { transform: translateX(3px); }
    .gf-notif-item.urgente { border-left-color: #ef4444; background: rgba(254,226,226,0.5); }
    .gf-notif-badge {
        display: inline-flex;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .gf-notif-badge.hoy { background: #fee2e2; color: #dc2626; }
    .gf-notif-badge.proximo { background: #fef3c7; color: #d97706; }
    .gf-notif-badge.semanal { background: #dbeafe; color: #2563eb; }
    .gf-notif-cost {
        margin-left: auto;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .gf-btn-pagar {
        background: #10b981;
        border: none;
        color: white;
        border-radius: 6px;
        padding: 5px 8px;
        cursor: pointer;
        font-size: 0.85rem;
        transition: background 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .gf-btn-pagar:hover { background: #059669; }
    .gf-notif-text { flex: 1; min-width: 0; }
    .gf-notif-text strong { color: #0f172a; }
    .gf-notif-text small { color: #64748b; display: block; margin-top: 2px; }

    /* ── Tabs ── */
    .gf-tabs {
        display: flex;
        gap: 6px;
        margin-bottom: 0;
        flex-wrap: wrap;
    }
    .gf-tab {
        padding: 12px 24px;
        border: none;
        background: #e2e8f0;
        color: #475569;
        font-weight: 600;
        font-size: 0.95rem;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
        position: relative;
        bottom: -1px;
    }
    .gf-tab:hover { background: #cbd5e1; color: #1e293b; }
    .gf-tab.active {
        background: #ffffff;
        color: #2563eb;
        box-shadow: 0 -2px 8px rgba(37,99,235,0.1);
        border: 1px solid #e2e8f0;
        border-bottom: 1px solid #ffffff;
        z-index: 2;
    }

    /* ── Sub-Tabs ── */
    .gf-subtabs {
        display: flex;
        gap: 8px;
        background: #f8fafc;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }
    .gf-subtab {
        padding: 6px 14px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #475569;
        font-weight: 600;
        font-size: 0.8rem;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .gf-subtab:hover {
        color: #0f172a;
        background: #f1f5f9;
        border-color: #94a3b8;
    }
    .gf-subtab.active {
        background: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 2px 4px rgba(37,99,235,0.25);
    }

    /* ── Table Wrapper ── */
    .gf-table-wrapper {
        background: #ffffff;
        border-radius: 0 12px 12px 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .gf-table-title {
        background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%);
        color: #ffffff;
        padding: 16px 24px;
        font-size: 1rem;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .gf-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* ── Table ── */
    .gf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        min-width: 900px;
    }
    .gf-table thead th {
        background: linear-gradient(180deg, #1e3a5f 0%, #162d4a 100%);
        color: #ffffff;
        padding: 12px 14px;
        text-align: center;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
        border: 1px solid #2a4a6b;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .gf-table tbody td {
        padding: 9px 12px;
        border: 1px solid #e2e8f0;
        color: #334155;
        vertical-align: middle;
    }
    .gf-table tbody tr:nth-child(even) { background: #f8fafc; }
    .gf-table tbody tr:hover { background: #eff6ff; }

    /* Column-specific styles */
    .gf-table .col-sede {
        font-weight: 600;
        color: #1e3a5f;
        font-size: 0.78rem;
        max-width: 200px;
        background: #f0f5ff;
    }
    .gf-table .col-servicio { font-weight: 600; color: #0f172a; white-space: nowrap; }
    .gf-table .col-fecha { text-align: center; color: #64748b; font-size: 0.82rem; white-space: nowrap; }
    .gf-table .col-empresa { color: #475569; font-size: 0.82rem; }
    .gf-table .col-costo {
        text-align: right;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        background: rgba(37,99,235,0.04);
    }
    .gf-table .col-mes {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .gf-table .col-mes.has-value { color: #0f172a; font-weight: 500; }
    .gf-table .col-mes.no-value { color: #cbd5e1; }

    /* Editable cells */
    .gf-table td.editable {
        cursor: text;
        transition: background 0.2s, outline 0.2s;
        position: relative;
    }
    .gf-table td.editable:hover, .gf-table td.editable:focus {
        background: #fff8e1;
        outline: 2px solid #fbbf24;
        outline-offset: -2px;
        z-index: 2;
    }
    .gf-table td.editable.saving {
        opacity: 0.6;
        pointer-events: none;
    }
    .gf-table td.editable.success {
        animation: flashGreen 1s ease;
    }
    .gf-table td.editable.error {
        animation: flashRed 1s ease;
    }
    @keyframes flashGreen { 0% { background-color: #d1fae5; } 100% { background-color: transparent; } }
    @keyframes flashRed { 0% { background-color: #fee2e2; } 100% { background-color: transparent; } }

    /* Total row */
    .gf-table .total-row {
        background: linear-gradient(135deg, #1e3a5f 0%, #0f2744 100%) !important;
    }
    .gf-table .total-row td {
        color: #ffffff !important;
        font-weight: 700 !important;
        padding: 14px 12px;
        border-color: #2a4a6b !important;
        font-size: 0.88rem;
    }

    /* ── Sede group separator ── */
    .gf-table .sede-first td.col-sede {
        border-top: 3px solid #2563eb;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .gf-page { padding: 12px; }
        .gf-tabs { gap: 4px; }
        .gf-tab { padding: 10px 16px; font-size: 0.85rem; }
        .gf-notif-list { grid-template-columns: 1fr; }
    }
    .col-accion { text-align:center; width:36px; padding:4px !important; }
    .btn-del-row { background:none; border:none; color:#cbd5e1; cursor:pointer; padding:4px 6px; border-radius:5px; font-size:1rem; transition:color .15s,background .15s; line-height:1; }
    .btn-del-row:hover { color:#ef4444; background:#fee2e2; }
    .tr-add-sede td { padding:5px 8px !important; background:#f8fafc !important; border:none !important; border-top:1px dashed #e2e8f0 !important; }
    .btn-add-sede { background:none; border:1.5px dashed #93c5fd; color:#3b82f6; font-size:0.8rem; font-weight:600; padding:5px 12px; border-radius:6px; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:5px; font-family:'Inter',sans-serif; }
    .btn-add-sede:hover { background:#eff6ff; border-color:#3b82f6; }
    .gf-modal-bg { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; align-items:center; justify-content:center; }
    .gf-modal-bg.open { display:flex; }
    .gf-modal { background:#fff; border-radius:14px; padding:28px 32px; width:min(480px,95vw); box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .gf-modal h3 { margin:0 0 20px 0; font-size:1.1rem; font-weight:800; color:#0f172a; }
    .gf-modal label { display:block; font-size:.8rem; font-weight:600; color:#475569; margin-bottom:4px; margin-top:14px; text-transform:uppercase; letter-spacing:.3px; }
    .gf-modal input, .gf-modal select { width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.92rem; background:#f8fafc; box-sizing:border-box; font-family:'Inter',sans-serif; }
    .gf-modal input:focus, .gf-modal select:focus { outline:none; border-color:#3b82f6; background:#fff; }
    .gf-modal-actions { display:flex; gap:10px; margin-top:22px; justify-content:flex-end; }
    .btn-modal-cancel { padding:9px 20px; background:#f1f5f9; border:none; border-radius:8px; color:#64748b; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; }
    .btn-modal-cancel:hover { background:#e2e8f0; }
    .btn-modal-save { padding:9px 22px; background:linear-gradient(135deg,#2563eb,#1d4ed8); border:none; border-radius:8px; color:#fff; font-weight:700; cursor:pointer; font-family:'Inter',sans-serif; }
    .btn-modal-save:disabled { opacity:.6; pointer-events:none; }</style>

<div class="gf-page">
    <!-- Header -->
    <div class="gf-header">
        <h1 class="gf-title"><span>📋</span> Gastos <span>Fijos</span></h1>
        <div style="display:flex;align-items:center;gap:8px;color:#64748b;font-size:0.9rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Mostrando datos hasta <strong style="color:#2563eb;margin-left:4px;">{{ $nombresMeses[$mesActual - 1] }} 2026</strong>
        </div>
    </div>

    <!-- Notifications Panel -->
    @if(count($notificaciones) > 0)
    <div class="gf-notif-panel">
        <div class="gf-notif-header">
            <svg width="22" height="22" fill="none" stroke="#92400e" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            🔔 Facturas Próximas a Pagar ({{ count($notificaciones) }})
        </div>
        <div class="gf-notif-list">
            @foreach($notificaciones as $notif)
            <div class="gf-notif-item {{ $notif['urgente'] ? 'urgente' : '' }}">
                <span class="gf-notif-badge {{ $notif['tipo'] }}">
                    @if($notif['tipo'] === 'hoy') ⚡ HOY
                    @elseif($notif['tipo'] === 'proximo') 📅 Día {{ $notif['dia'] ?? '' }}
                    @else 🔄 {{ $notif['fecha'] }}
                    @endif
                </span>
                <div class="gf-notif-text">
                    <strong>{{ $notif['servicio'] }}</strong>
                    <small>{{ $notif['empresa'] }} — {{ $notif['tabla'] }}</small>
                </div>
                <div class="gf-notif-cost">
                    $ {{ number_format($notif['costo'], 2) }}
                    <button class="gf-btn-pagar" data-costo="{{ $notif['costo'] }}" onclick="marcarPagado({{ $notif['tabla_idx'] }}, {{ $notif['fila_idx'] }}, this)" title="Marcar este gasto como pagado">✔️</button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Tabs -->
    <div class="gf-tabs">
        <button class="gf-tab active" onclick="showTab(0, this)">🏢 Grupo Inmobiliario</button>
        <button class="gf-tab" onclick="showTab(1, this)">🏬 Palacio / Nunes / Euronissi</button>
        <button class="gf-tab" onclick="showTab(2, this)">👤 Directivo</button>
    </div>

    <!-- Tables -->
    @foreach($tablas as $tIndex => $tabla)
    <div class="gf-table-wrapper" id="tabla-{{ $tIndex }}" style="{{ $tIndex > 0 ? 'display:none' : '' }}">
        <div class="gf-table-title">{{ $tabla['titulo'] }}</div>
        
        @if($tabla['tiene_sede'])
            @php
                $sedesList = collect($tabla['filas'])->pluck('sede')->filter()->unique()->values();
            @endphp
            <div class="gf-subtabs">
                <button class="gf-subtab active" onclick="showSubTab({{ $tIndex }}, 'all', this)">Todas las Sedes</button>
                @foreach($sedesList as $sedeName)
                    <button class="gf-subtab" onclick="showSubTab({{ $tIndex }}, '{{ addslashes($sedeName) }}', this)">{{ $sedeName }}</button>
                @endforeach
            </div>
        @endif

        <div class="gf-table-scroll">
            <table class="gf-table">
                <thead>
                    <tr>
                        @if($tabla['tiene_sede'])
                            <th class="th-sede">SEDE</th>
                        @endif
                        <th>SERVICIO</th>
                        <th>FECHA</th>
                        <th>EMPRESA</th>
                        <th>COSTO ESTIMADO</th>
                        @for($m = 0; $m < $mesActual; $m++)
                            <th>{{ $nombresMeses[$m] }}</th>
                        @endfor
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastSede = '___NONE___'; @endphp
                    @foreach($tabla['filas'] as $fIndex => $fila)
                        @php
                            $isSedeFirst = $tabla['tiene_sede'] && !empty($fila['sede']) && $fila['sede'] !== $lastSede;
                            if ($isSedeFirst) $lastSede = $fila['sede'];
                        @endphp
                        <tr class="gasto-row {{ $isSedeFirst ? 'sede-first' : '' }}" 
                            data-sede="{{ $lastSede }}"
                            data-costo="{{ $fila['costo'] ?? 0 }}"
                            @for($m = 0; $m < $mesActual; $m++)
                                data-mes-{{ $m }}="{{ $fila['meses'][$m] ?? 0 }}"
                            @endfor
                        >
                            @if($tabla['tiene_sede'])
                                <td class="col-sede">{{ $fila['sede'] ?? '' }}</td>
                            @endif
                            <td class="col-servicio">{{ $fila['servicio'] }}</td>
                            <td class="col-fecha editable" contenteditable="true" data-type="fecha" data-tidx="{{ $tIndex }}" data-fidx="{{ $fila['fidx'] }}">{{ $fila['fecha'] }}</td>
                            <td class="col-empresa">{{ $fila['empresa'] }}</td>
                            <td class="col-costo editable {{ $fila['costo'] > 0 ? 'has-value' : 'no-value' }}" contenteditable="true" data-type="costo" data-tidx="{{ $tIndex }}" data-fidx="{{ $fila['fidx'] }}" data-original="{{ $fila['costo'] > 0 ? number_format($fila['costo'], 2, '.', '') : '' }}">
                                @if($fila['costo'] > 0)
                                    {{ number_format($fila['costo'], 2, '.', '') }}
                                @else
                                    
                                @endif
                            </td>
                            @for($m = 0; $m < $mesActual; $m++)
                                @php $val = $fila['meses'][$m] ?? null; @endphp
                                <td class="col-mes editable {{ $val !== null ? 'has-value' : 'no-value' }}" contenteditable="true" data-type="monto" data-tidx="{{ $tIndex }}" data-fidx="{{ $fila['fidx'] }}" data-midx="{{ $m }}" data-original="{{ $val !== null ? number_format($val, 2, '.', '') : '' }}">
                                    {{ $val !== null ? number_format($val, 2, '.', '') : '' }}
                                </td>
                            @endfor
                            <td class="col-accion">
                                <button class="btn-del-row" onclick="deleteRow({{ $tIndex }}, {{ $fila['fidx'] }}, {{ isset($fila['custom_id']) ? $fila['custom_id'] : 'null' }}, this)" title="Eliminar fila">✖</button>
                            </td>
                        </tr>
                    @endforeach

                    {{-- Add Row --}}
                    <tr class="tr-add-sede">
                        <td colspan="{{ 4 + ($tabla['tiene_sede'] ? 1 : 0) + $mesActual + 1 }}">
                            <button class="btn-add-sede" onclick="openAddModal({{ $tIndex }}, {{ $tabla['tiene_sede'] ? 'true' : 'false' }})">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                Añadir Nuevo Gasto
                            </button>
                        </td>
                    </tr>

                    {{-- Total Row --}}
                    <tr class="total-row">
                        @if($tabla['tiene_sede'])
                            <td class="td-sede-total"></td>
                        @endif
                        <td colspan="3" style="text-align:center;text-transform:uppercase;letter-spacing:0.5px;">
                            TOTAL {{ $tabla['titulo_corto'] }}
                        </td>
                        <td class="col-costo total-col-costo" style="color:#fff!important;">
                            $ {{ number_format(collect($tabla['filas'])->sum('costo'), 2) }}
                        </td>
                        @for($m = 0; $m < $mesActual; $m++)
                            @php $totalMes = collect($tabla['filas'])->sum(fn($f) => $f['meses'][$m] ?? 0); @endphp
                            <td class="total-mes-{{ $m }}" style="text-align:right;">
                                $ {{ number_format($totalMes, 2) }}
                            </td>
                        @endfor
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

<!-- Modal para Agregar Gasto -->
<div class="gf-modal-bg" id="modalAddGasto">
    <div class="gf-modal">
        <h3>Añadir Nuevo Gasto</h3>
        <input type="hidden" id="modal-tidx" value="">
        
        <div id="modal-sede-container" style="display:none;">
            <label>Sede (Opcional)</label>
            <select id="modal-sede-select" style="margin-bottom: 8px;">
                <option value="">-- Seleccionar Sede --</option>
                <option value="OTRA">Otra (Escribir nueva)</option>
            </select>
            <input type="text" id="modal-sede" placeholder="Ej: INVERSIONES DORAL PARAGUANÁ..." style="display:none; margin-top: 8px;">
        </div>

        <label>Servicio</label>
        <input type="text" id="modal-servicio" placeholder="Ej: INTERNET, CONDOMINIO..." required>

        <label>Empresa / Responsable (Opcional)</label>
        <input type="text" id="modal-empresa" placeholder="Ej: AIRTEK, CORPOELEC...">

        <label>Fecha de Pago (Opcional)</label>
        <input type="text" id="modal-fecha" placeholder="Ej: 15 de cada mes">

        <label>Costo Estimado ($)</label>
        <input type="number" id="modal-costo" step="0.01" min="0" placeholder="0.00">

        <div class="gf-modal-actions">
            <button class="btn-modal-cancel" onclick="closeAddModal()">Cancelar</button>
            <button class="btn-modal-save" onclick="saveNewRow(this)">Guardar Gasto</button>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showTab(index, btn) {
    document.querySelectorAll('.gf-table-wrapper').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.gf-tab').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById('tabla-' + index);
    if (target) {
        target.style.display = '';
        target.style.animation = 'none';
        target.offsetHeight; 
        target.style.animation = 'fadeIn 0.3s ease';
    }
    if (btn) btn.classList.add('active');
}

function showSubTab(tIdx, sedeName, btn) {
    const wrapper = document.getElementById('tabla-' + tIdx);
    if (!wrapper) return;

    // Update active subtab styling
    wrapper.querySelectorAll('.gf-subtab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const isAll = (sedeName === 'all');
    
    // Toggle Sede column visibility
    const thSede = wrapper.querySelector('.th-sede');
    const tdSedeTotal = wrapper.querySelector('.td-sede-total');
    if (thSede) thSede.style.display = isAll ? '' : 'none';
    if (tdSedeTotal) tdSedeTotal.style.display = isAll ? '' : 'none';

    // Filter rows and calculate totals
    const tbody = wrapper.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr.gasto-row');
    
    let sumCosto = 0;
    // We get mesActual from blade logic, max 12
    const mesActual = {{ $mesActual }};
    let sumMeses = new Array(mesActual).fill(0);

    rows.forEach(row => {
        const rowSede = row.getAttribute('data-sede');
        if (isAll || rowSede === sedeName) {
            row.style.display = '';
            
            // Hide/show the sede column cell for this row
            const colSede = row.querySelector('.col-sede');
            if (colSede) colSede.style.display = isAll ? '' : 'none';
            
            // Add to totals
            sumCosto += parseFloat(row.getAttribute('data-costo')) || 0;
            for (let m = 0; m < mesActual; m++) {
                sumMeses[m] += parseFloat(row.getAttribute('data-mes-' + m)) || 0;
            }
        } else {
            row.style.display = 'none';
        }
    });

    // Update total row
    const totalRow = wrapper.querySelector('.total-row');
    if (totalRow) {
        const formatMoney = (val) => '$ ' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        const colCostoTotal = totalRow.querySelector('.total-col-costo');
        if (colCostoTotal) colCostoTotal.innerText = formatMoney(sumCosto);
        
        for (let m = 0; m < mesActual; m++) {
            const totalMes = totalRow.querySelector('.total-mes-' + m);
            if (totalMes) totalMes.innerText = formatMoney(sumMeses[m]);
        }
    }
}

// Editable cells logic
document.querySelectorAll('.gf-table td.editable').forEach(cell => {
    // Focus in
    cell.addEventListener('focus', function() {
        if (!this.hasAttribute('data-original')) {
            this.setAttribute('data-original', this.innerText.trim());
        }
    });

    // Blur / Focus out
    cell.addEventListener('blur', function() {
        const val = this.innerText.trim();
        const original = this.getAttribute('data-original');
        
        if (val === original) return; // No change

        const type = this.getAttribute('data-type');
        const tIdx = this.getAttribute('data-tidx');
        const fIdx = this.getAttribute('data-fidx');
        
        this.classList.add('saving');

        if (type === 'monto') {
            const mIdx = this.getAttribute('data-midx');
            const numVal = val === '' ? null : parseFloat(val.replace(',', ''));
            
            fetch("{{ route('finanzas.gastos_fijos.monto') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ tabla_idx: tIdx, fila_idx: fIdx, mes_idx: mIdx, monto: numVal })
            }).then(async res => {
                if (!res.ok) throw new Error(await res.text());
                return res.json();
            }).then(data => {
                this.classList.remove('saving');
                if (data.ok) {
                    this.setAttribute('data-original', val);
                    this.classList.add('success');
                    if(val !== '') {
                        this.classList.remove('no-value');
                        this.classList.add('has-value');
                    } else {
                        this.classList.add('no-value');
                        this.classList.remove('has-value');
                    }
                    setTimeout(() => this.classList.remove('success'), 1000);
                    
                    // Update the row's data attribute so totals are correct
                    const tr = this.closest('tr');
                    if (tr) {
                        tr.setAttribute('data-mes-' + mIdx, numVal || 0);
                        // Trigger total recalculation for current subtab if active
                        const activeSubtab = this.closest('.gf-table-wrapper').querySelector('.gf-subtab.active');
                        if (activeSubtab) {
                            activeSubtab.click();
                        } else {
                            // If no subtabs (e.g. Grupo Inmobiliario), recalculate "all"
                            showSubTab(tIdx, 'all', null);
                        }
                    }
                } else {
                    this.classList.add('error');
                    this.innerText = original;
                }
            }).catch(() => {
                this.classList.remove('saving');
                this.classList.add('error');
                this.innerText = original;
            });
        } else if (type === 'fecha') {
            fetch("{{ route('finanzas.gastos_fijos.fecha') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ tabla_idx: tIdx, fila_idx: fIdx, fecha: val })
            }).then(async res => {
                if (!res.ok) throw new Error(await res.text());
                return res.json();
            }).then(data => {
                this.classList.remove('saving');
                if (data.ok) {
                    this.setAttribute('data-original', val);
                    this.classList.add('success');
                    setTimeout(() => this.classList.remove('success'), 1000);
                } else {
                    this.classList.add('error');
                    this.innerText = original;
                }
            });
        } else if (type === 'costo') {
            const numVal = val === '' ? null : parseFloat(val.replace(',', ''));
            fetch("{{ route('finanzas.gastos_fijos.costo') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ tabla_idx: tIdx, fila_idx: fIdx, costo: numVal })
            }).then(async res => {
                if (!res.ok) throw new Error(await res.text());
                return res.json();
            }).then(data => {
                this.classList.remove('saving');
                if (data.ok) {
                    this.setAttribute('data-original', val);
                    this.classList.add('success');
                    
                    // Update the row's data attribute so totals are correct
                    const tr = this.closest('tr');
                    if (tr) {
                        tr.setAttribute('data-costo', numVal || 0);
                        // Trigger total recalculation for current subtab if active
                        const activeSubtab = this.closest('.gf-table-wrapper').querySelector('.gf-subtab.active');
                        if (activeSubtab) {
                            activeSubtab.click();
                        } else {
                            // If no subtabs (e.g. Grupo Inmobiliario), recalculate "all"
                            showSubTab(tIdx, 'all', null);
                        }
                    }
                    setTimeout(() => this.classList.remove('success'), 1000);
                } else {
                    this.classList.add('error');
                    this.innerText = original;
                }
            });
        }
    });

    // Enter key
    cell.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.blur(); // Triggers the save
        }
    });
});

// Marcar Pagado logic
function marcarPagado(tIdx, fIdx, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    btn.innerText = '⏳';
    
    const costo = btn.getAttribute('data-costo');

    fetch("{{ route('finanzas.gastos_fijos.pagado') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ tabla_idx: tIdx, fila_idx: fIdx, costo: costo })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            // Eliminar la notificación de la UI con animación
            const item = btn.closest('.gf-notif-item');
            item.style.transform = 'scale(0.9)';
            item.style.opacity = '0';
            setTimeout(() => {
                item.remove();
                // Si ya no quedan notificaciones, ocultar el panel
                if (document.querySelectorAll('.gf-notif-item').length === 0) {
                    const notifPanel = document.querySelector('.gf-notif-panel');
                    if (notifPanel) notifPanel.style.display = 'none';
                }
            }, 300);
            
            // Recargar la página para que se muestre el monto pagado en la celda
            setTimeout(() => {
                window.location.reload();
            }, 350);
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = '✔️';
            alert("Error al marcar como pagado.");
        }
    }).catch(err => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerText = '✔️';
        console.error(err);
    });
}

// ── Lógica de Modal (Añadir Gasto) ──

document.getElementById('modal-sede-select').addEventListener('change', function() {
    const input = document.getElementById('modal-sede');
    if (this.value === 'OTRA') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
    }
});

function openAddModal(tIdx, tieneSede) {
    document.getElementById('modal-tidx').value = tIdx;
    document.getElementById('modal-sede-container').style.display = tieneSede ? 'block' : 'none';
    
    // Clear & setup sede inputs
    const selectSede = document.getElementById('modal-sede-select');
    const inputSede = document.getElementById('modal-sede');
    inputSede.value = '';
    inputSede.style.display = 'none';
    
    // Rebuild options dynamically from table
    selectSede.innerHTML = '<option value="">-- Seleccionar Sede --</option><option value="OTRA">Otra (Escribir nueva)</option>';
    if (tieneSede) {
        const sedes = new Set();
        document.querySelectorAll('#tabla-' + tIdx + ' .col-sede').forEach(td => {
            const val = td.innerText.trim();
            if (val) sedes.add(val);
        });
        sedes.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.innerText = s;
            selectSede.insertBefore(opt, selectSede.lastElementChild);
        });
    }
    selectSede.value = '';
    document.getElementById('modal-servicio').value = '';
    document.getElementById('modal-empresa').value = '';
    document.getElementById('modal-fecha').value = '';
    document.getElementById('modal-costo').value = '';

    document.getElementById('modalAddGasto').classList.add('open');
}

function closeAddModal() {
    document.getElementById('modalAddGasto').classList.remove('open');
}

function saveNewRow(btn) {
    const tIdx = document.getElementById('modal-tidx').value;
    const selectValue = document.getElementById('modal-sede-select').value;
    const sede = selectValue === 'OTRA' ? document.getElementById('modal-sede').value.trim() : selectValue;
    const servicio = document.getElementById('modal-servicio').value.trim();
    const empresa = document.getElementById('modal-empresa').value.trim();
    const fecha = document.getElementById('modal-fecha').value.trim();
    const costo = parseFloat(document.getElementById('modal-costo').value) || 0;

    if (!servicio) {
        alert("El Servicio es obligatorio.");
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Guardando...';

    fetch("{{ route('finanzas.gastos_fijos.agregar') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            tabla_idx: tIdx,
            sede: sede,
            servicio: servicio,
            empresa: empresa,
            fecha: fecha,
            costo: costo
        })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            closeAddModal();
            window.location.reload(); // Recargar para ver la nueva fila correctamente renderizada
        } else {
            alert("Error al agregar el gasto.");
            btn.disabled = false;
            btn.innerText = 'Guardar Gasto';
        }
    }).catch(err => {
        console.error(err);
        alert("Error de conexión.");
        btn.disabled = false;
        btn.innerText = 'Guardar Gasto';
    });
}

// ── Lógica de Eliminar Fila ──
function deleteRow(tIdx, fIdx, customId, btn) {
    if (!confirm("¿Seguro que deseas eliminar este gasto de la lista?")) return;

    btn.disabled = true;
    btn.style.opacity = '0.5';

    fetch("{{ route('finanzas.gastos_fijos.eliminar') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            tabla_idx: tIdx,
            fila_idx: fIdx,
            custom_id: customId
        })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            // Ocultar la fila visualmente o recargar
            btn.closest('tr').remove();
            // Lo más seguro es recargar para que los totales cuadren
            // window.location.reload(); 
        } else {
            alert("Error al eliminar el gasto.");
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }).catch(err => {
        console.error(err);
        alert("Error de conexión.");
        btn.disabled = false;
        btn.style.opacity = '1';
    });
}
</script>

@endsection

