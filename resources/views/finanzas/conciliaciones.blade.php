@extends('layouts.app')
@section('title', 'Conciliación Bancaria')
@section('content')

<style>
    .conc-page { padding: 28px; font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
    .conc-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 14px; }
    .conc-title { margin: 0; font-size: 1.6rem; color: #0f172a; font-weight: 800; letter-spacing: -0.5px; }
    .conc-title span { color: #2563eb; }

    /* Toolbar */
    .conc-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .btn-upload { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 10px 18px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(37,99,235,.3); transition: transform .15s; }
    .btn-upload:hover { transform: translateY(-1px); }
    .btn-clear { background: white; color: #ef4444; border: 1.5px solid #fca5a5; padding: 10px 18px; border-radius: 9px; font-weight: 600; cursor: pointer; font-size: 0.95rem; }
    .btn-clear:hover { background: #fef2f2; }
    .select-banco { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.95rem; color: #334155; background: white; min-width: 160px; }
    .btn-filtrar { background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 600; cursor: pointer; }

    /* Alert */
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; }
    .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; text-align: center; }

    /* Bank card */
    .bank-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,.06); margin-bottom: 40px; overflow: hidden; }
    .bank-card-header { background: linear-gradient(135deg, #1e3a5f 0%, #1a4273 100%); padding: 18px 24px; display: flex; align-items: center; gap: 14px; justify-content: space-between; flex-wrap: wrap; }
    .bank-name { color: white; font-size: 1.25rem; font-weight: 800; letter-spacing: .5px; display: flex; align-items: center; gap: 10px; }
    .bank-totals-row { display: flex; gap: 18px; flex-wrap: wrap; }
    .bank-stat { background: rgba(255,255,255,.12); border-radius: 8px; padding: 8px 14px; text-align: center; min-width: 120px; }
    .bank-stat-label { font-size: 0.7rem; color: rgba(255,255,255,.7); text-transform: uppercase; letter-spacing: .5px; display: block; }
    .bank-stat-value { font-size: 1rem; font-weight: 700; color: white; }
    .bank-stat-value.green { color: #6ee7b7; }
    .bank-stat-value.yellow { color: #fcd34d; }
    .bank-stat-value.red { color: #fca5a5; }
    .bank-stat-value.orange { color: #fdba74; }

    /* Sections inside bank */
    .sections-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
    @media(max-width: 900px) { .sections-grid { grid-template-columns: 1fr; } }

    .section-block { border-right: 1px solid #f1f5f9; }
    .section-block:nth-child(even) { border-right: none; }
    .section-block:nth-child(1), .section-block:nth-child(2) { border-bottom: 1px solid #f1f5f9; }

    .section-header { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-bottom: 1px solid #f1f5f9; }
    .section-badge { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .badge-green { background: #10b981; }
    .badge-yellow { background: #f59e0b; }
    .badge-red { background: #ef4444; }
    .badge-purple { background: #8b5cf6; }

    .section-title { font-size: 0.88rem; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: .5px; }
    .section-count { margin-left: auto; font-size: 0.75rem; background: #f1f5f9; color: #64748b; border-radius: 20px; padding: 2px 9px; font-weight: 600; }

    /* Tables */
    .mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .mini-table thead tr { background: #f8fafc; }
    .mini-table th { padding: 9px 14px; font-weight: 600; color: #64748b; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #f1f5f9; }
    .mini-table td { padding: 10px 14px; border-bottom: 1px solid #f8fafc; color: #334155; vertical-align: top; }
    .mini-table tr:last-child td { border-bottom: none; }
    .mini-table tr:hover td { background: #f8fafc; }
    .monto-cell { font-weight: 700; text-align: right; font-family: 'Courier New', monospace; }
    .monto-green { color: #059669; }
    .monto-red { color: #dc2626; }
    .monto-yellow { color: #d97706; }
    .monto-purple { color: #7c3aed; }
    .ref-chip { font-size: 0.75rem; color: #64748b; background: #f1f5f9; border-radius: 4px; padding: 1px 6px; display: inline-block; margin-top: 2px; font-family: monospace; }
    .tipo-chip { font-size: 0.72rem; border-radius: 4px; padding: 2px 7px; font-weight: 600; display: inline-block; }
    .chip-cargo { background: #fef2f2; color: #dc2626; }
    .chip-abono { background: #f0fdf4; color: #16a34a; }

    .empty-row td { text-align: center; color: #94a3b8; padding: 24px; font-size: 0.85rem; }

    /* Section footer */
    .section-footer { padding: 10px 14px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right; font-weight: 700; font-size: 0.9rem; color: #1e293b; }

    /* Upload modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; }
    .modal-box { background: white; border-radius: 14px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,.2); overflow: hidden; }
    .modal-head { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { margin: 0; font-weight: 800; color: #1a4273; font-size: 1.2rem; }
    .modal-close { background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #64748b; line-height: 1; }
    .modal-body { padding: 24px; }
    .modal-foot { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
    .form-label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; background: white; font-family: inherit; font-size: 0.95rem; margin-bottom: 18px; box-sizing: border-box; }
    .file-wrap { display: flex; }
    .file-label { background: #f1f5f9; color: #334155; padding: 10px 16px; border: 1.5px solid #cbd5e1; border-radius: 9px 0 0 9px; cursor: pointer; margin: 0; font-weight: 500; white-space: nowrap; font-size: 0.9rem; }
    .file-name { flex: 1; min-width: 0; padding: 10px; border: 1.5px solid #cbd5e1; border-left: none; border-radius: 0 9px 9px 0; background: white; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; font-family: inherit; }
    .file-hint { font-size: 0.82rem; color: #94a3b8; margin-top: 10px; }
    .btn-cancel { background: white; border: 1.5px solid #cbd5e1; padding: 10px 20px; border-radius: 9px; font-weight: 600; cursor: pointer; color: #475569; font-family: inherit; }
    .btn-submit { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 10px 22px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-family: inherit; }
</style>

<div class="conc-page">

    {{-- Top bar --}}
    <div class="conc-topbar">
        <h2 class="conc-title">Conciliación <span>Bancaria</span></h2>
        <div class="conc-toolbar">
            <button type="button" class="btn-upload" onclick="document.getElementById('uploadModal').style.display='flex'">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Añadir Movimientos
            </button>

            <form action="{{ route('finanzas.conciliaciones') }}" method="GET" style="display:flex;align-items:center;gap:8px;margin:0;flex-wrap:wrap;">
                <input type="date" name="fecha_desde" class="select-banco" value="{{ request('fecha_desde') }}" title="Fecha Desde" style="min-width: 130px;">
                <input type="date" name="fecha_hasta" class="select-banco" value="{{ request('fecha_hasta') }}" title="Fecha Hasta" style="min-width: 130px;">
                <select name="banco_filtro" class="select-banco">
                    <option value="">Todos los Bancos</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}" {{ request('banco_filtro') == $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-filtrar">Filtrar</button>
            </form>

            @if($lineas->count() > 0)
            <form action="{{ route('finanzas.conciliaciones.clear') }}" method="POST" onsubmit="return confirm('¿Borrar todos los movimientos cargados?');">
                @csrf
                <button type="submit" class="btn-clear">Limpiar Todo</button>
            </form>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">⚠️ {{ session('error') }}</div>
    @endif

    @if($bancosActivos->isEmpty())
        <div class="alert-info">
            📂 No hay movimientos cargados aún.<br>
            <small>Usa "Añadir Movimientos" para subir el estado de cuenta de un banco.</small>
        </div>
    @endif

    {{-- Per-bank sections --}}
    @foreach($bancosActivos as $bk_key)
    @php
        $d   = $data_por_banco[$bk_key];
        $bk  = $d['banco'];
        $tit = $d['titular'];
    @endphp

    <div class="bank-card">
        {{-- Bank header --}}
        <div class="bank-card-header">
            <div>
                <div class="bank-name">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    {{ $bk }}
                    <a href="{{ route('finanzas.conciliaciones.reporte-banco', ['banco' => $bk, 'titular' => $tit]) }}" style="margin-left: 15px; font-size: 0.75rem; background-color: rgba(255,255,255,0.15); color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-weight: normal; vertical-align: middle;">📥 PDF</a>
                </div>
                @if($tit)
                    <div style="color:rgba(255,255,255,.65); font-size:0.82rem; font-weight:600; margin-top:4px; letter-spacing:.3px;">Titular: {{ $tit }}</div>
                @endif
            </div>
            <div class="bank-totals-row">
                <div class="bank-stat">
                    <span class="bank-stat-label">✅ Consolidados</span>
                    <span class="bank-stat-value green">Bs. {{ number_format($d['total_conciliados'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">🕐 En Tránsito</span>
                    <span class="bank-stat-value yellow">Bs. {{ number_format($d['total_transito'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">⚠️ Movimientos en el Banco</span>
                    <span class="bank-stat-value red">Bs. {{ number_format($d['total_sin_registrar'], 2) }}</span>
                </div>
                <div class="bank-stat">
                    <span class="bank-stat-label">🏛️ Comisiones</span>
                    <span class="bank-stat-value orange">Bs. {{ number_format($d['total_comisiones'], 2) }}</span>
                </div>
            </div>

            {{-- New row for manual reconciliation calculation --}}
            <div style="margin-top: 10px; padding: 6px 12px; background: rgba(255,255,255,0.05); border-radius: 6px; display: inline-flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Saldo Banco (Manual)</label>
                    <input type="number" step="0.01" class="saldo-banco-input" data-idx="{{ $loop->index }}" 
                           data-consolidados="{{ $d['total_conciliados'] }}"
                           data-transito="{{ $d['total_transito'] }}"
                           data-movbanco="{{ $d['total_sin_registrar'] }}"
                           data-comisiones="{{ $d['total_comisiones'] }}"
                           placeholder="0.00"
                           onkeyup="calcularDiferencia({{ $loop->index }})"
                           onchange="calcularDiferencia({{ $loop->index }})"
                           style="background: white; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-weight: bold; width: 120px; font-size: 0.85rem; color: #334155; margin: 0; height: auto;">
                </div>
                
                <div style="font-size: 1.1rem; color: rgba(255,255,255,0.3);"> = </div>
                
                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Saldo Sistema (Calc)</label>
                    <span id="saldo_sis_{{ $loop->index }}" style="font-size: 0.9rem; font-weight: bold; color: white;">Bs. {{ number_format($d['total_conciliados'], 2) }}</span>
                </div>

                <div style="font-size: 1.1rem; color: rgba(255,255,255,0.3);"> | </div>

                <div style="display:flex; flex-direction:column; gap: 2px;">
                    <label style="font-size: 0.65rem; color: rgba(255,255,255,0.6); text-transform: uppercase; margin: 0;">Diferencia</label>
                    <span id="dif_{{ $loop->index }}" style="font-size: 0.9rem; font-weight: bold; color: white;">Bs. -{{ number_format($d['total_conciliados'], 2) }}</span>
                </div>
                
                <div id="status_{{ $loop->index }}" style="margin-left: 5px; font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; font-weight: bold; display: none;"></div>
            </div>
        </div>


        {{-- 4 sections grid --}}
        <div class="sections-grid">

            {{-- 1. CONSOLIDADOS --}}
            <div class="section-block">
                <div class="section-header">
                    <span class="section-badge badge-green"></span>
                    <span class="section-title">Consolidados</span>
                    <span class="section-count">{{ $d['conciliados']->count() }}</span>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Motivo</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['conciliados'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                            <td>
                                <span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span>
                            </td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['motivo'], 50) }}
                                @if($row['tipo_gasto'])
                                    <br><span class="tipo-chip chip-cargo">{{ $row['tipo_gasto'] }}</span>
                                @endif
                            </td>
                            <td class="monto-cell monto-green">Bs. {{ number_format($row['monto'], 2) }}</td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin movimientos consolidados</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['conciliados']->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format($d['total_conciliados'], 2) }}</div>
                @endif
            </div>

            {{-- 2. EN TRÁNSITO --}}
            <div class="section-block">
                <div class="section-header">
                    <span class="section-badge badge-yellow"></span>
                    <span class="section-title">En Tránsito</span>
                    <span class="section-count">{{ $d['en_transito']->count() }}</span>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Concepto / Motivo</th>
                            <th style="text-align:right">Monto Bs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['en_transito'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['concepto'] ?: $row['motivo'], 50) }}
                                @if($row['tipo_gasto'])
                                    <br><span class="tipo-chip chip-cargo">{{ $row['tipo_gasto'] }}</span>
                                @endif
                            </td>
                            <td class="monto-cell monto-yellow">{{ number_format($row['monto_bs'], 2) }}</td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin movimientos en tránsito</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['en_transito']->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format($d['total_transito'], 2) }}</div>
                @endif
            </div>

            {{-- 3. SIN REGISTRAR --}}
            <div class="section-block">
                <div class="section-header">
                    <span class="section-badge badge-red"></span>
                    <span class="section-title">Movimientos en el Banco</span>
                    <span class="section-count">{{ $d['sin_registrar']->count() }}</span>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Referencia</th>
                            <th>Descripción del Banco</th>
                            <th style="text-align:right">Monto</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['sin_registrar'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td style="max-width:200px;font-size:0.82rem;">
                                {{ Str::limit($row['descripcion'], 55) }}
                                <br>
                                <span class="tipo-chip {{ $row['tipo'] == 'cargo' ? 'chip-cargo' : 'chip-abono' }}">
                                    {{ $row['tipo'] == 'cargo' ? 'Cargo' : 'Abono' }}
                                </span>
                            </td>
                            <td class="monto-cell monto-red">Bs. {{ number_format($row['monto'], 2) }}</td>
                            <td style="text-align:center;">
                                <form action="{{ route('finanzas.conciliaciones.manual') }}" method="POST" style="display:inline-block;" title="Marcar como Conciliado">
                                    @csrf
                                    <input type="hidden" name="linea_id" value="{{ $row['id'] }}">
                                    <button type="submit" class="btn btn-sm btn-success" style="padding: 2px 6px; font-size: 0.75rem;" onclick="return confirm('¿Marcar este movimiento como conciliado manualmente?')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="5">Sin movimientos detectados</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['sin_registrar']->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format($d['total_sin_registrar'], 2) }}</div>
                @endif
            </div>

            {{-- 4. COMISIONES BANCARIAS --}}
            <div class="section-block">
                <div class="section-header">
                    <span class="section-badge badge-purple"></span>
                    <span class="section-title">Comisiones Bancarias</span>
                    <span class="section-count">{{ $d['comisiones']->count() }}</span>
                </div>
                <table class="mini-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción</th>
                            <th>Referencia</th>
                            <th style="text-align:right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($d['comisiones'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                            <td style="max-width:200px;font-size:0.82rem;">{{ Str::limit($row['descripcion'], 55) }}</td>
                            <td><span class="ref-chip">{{ $row['referencia'] ?: '—' }}</span></td>
                            <td class="monto-cell monto-purple">Bs. {{ number_format($row['monto'], 2) }}</td>
                        </tr>
                        @empty
                        <tr class="empty-row"><td colspan="4">Sin comisiones detectadas</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($d['comisiones']->count() > 0)
                <div class="section-footer">Total: Bs. {{ number_format($d['total_comisiones'], 2) }}</div>
                @endif
            </div>

        </div>{{-- end sections-grid --}}
    </div>{{-- end bank-card --}}
    @endforeach

</div>

{{-- UPLOAD MODAL --}}
<div id="uploadModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head">
                <h5 class="modal-title">Subir Archivo del Banco</h5>
                <button type="button" class="modal-close" onclick="document.getElementById('uploadModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">

                {{-- PASO 1: Banco --}}
                <label class="form-label">1. Selecciona el Banco:</label>
                <select name="banco_seleccionado" id="selectBanco" required class="form-control"
                        onchange="filtrarTitulares(this.value)">
                    <option value="">-- Seleccione el Banco --</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>

                {{-- PASO 2: Titular (se filtra según el banco) --}}
                <label class="form-label">2. Selecciona el Titular:</label>
                <select name="titular_seleccionado" id="selectTitular" required class="form-control"
                        disabled style="background:#f8fafc; color:#94a3b8;">
                    <option value="">-- Primero seleccione un Banco --</option>
                </select>

                {{-- Archivo --}}
                <label class="form-label">3. Archivo (Excel o CSV):</label>
                <div class="file-wrap">
                    <label class="file-label">
                        Elegir Archivo
                        <input type="file" name="file[]" multiple accept=".csv,.xls,.xlsx,image/jpeg,image/png" required
                               id="csvUploadInput"
                               onchange="document.getElementById('csvFileName').value = this.files.length > 1 ? this.files.length + ' archivos' : (this.files[0] ? this.files[0].name : '');"
                               style="display:none;">
                    </label>
                    <input type="text" id="csvFileName" placeholder="Ningún archivo seleccionado" readonly class="file-name">
                </div>
                <p class="file-hint">El sistema detectará automáticamente el formato según el banco seleccionado.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('uploadModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Subir y Analizar</button>
            </div>
        </form>
    </div>
</div>

{{-- Mapa banco → titulares (generado desde PHP/BD) --}}
<script>
const titularesPorBanco = @json($titularesPorBanco);

function filtrarTitulares(banco) {
    const selTit = document.getElementById('selectTitular');
    selTit.innerHTML = '<option value="">-- Seleccione el Titular --</option>';

    if (!banco || !titularesPorBanco[banco]) {
        selTit.disabled = true;
        selTit.style.background = '#f8fafc';
        selTit.style.color = '#94a3b8';
        return;
    }

    titularesPorBanco[banco].forEach(function(tit) {
        const opt = document.createElement('option');
        opt.value = tit;
        opt.textContent = tit;
        selTit.appendChild(opt);
    });

    selTit.disabled = false;
    selTit.style.background = 'white';
    selTit.style.color = '#334155';

    // Si solo hay un titular, seleccionarlo automáticamente
    if (titularesPorBanco[banco].length === 1) {
        selTit.value = titularesPorBanco[banco][0];
    }
}

function calcularDiferencia(idx) {
    const input = document.querySelector(`.saldo-banco-input[data-idx="${idx}"]`);
    const difSpan = document.getElementById(`dif_${idx}`);
    const statusDiv = document.getElementById(`status_${idx}`);
    
    if (!input || !difSpan) return;

    const saldoManual = parseFloat(input.value) || 0;
    // El sistema dice que tenemos "consolidados" registrados con certeza.
    const consolidados = parseFloat(input.getAttribute('data-consolidados')) || 0;
    
    const saldoSistema = consolidados;
    const diferencia = saldoManual - saldoSistema;

    // Formatear diferencia
    const formater = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    difSpan.textContent = 'Bs. ' + formater.format(diferencia);

    // Colores
    if (diferencia === 0 && saldoManual !== 0) {
        difSpan.style.color = '#6ee7b7'; // Verde si cuadra exacto
        statusDiv.textContent = '¡CUADRA!';
        statusDiv.style.background = '#065f46';
        statusDiv.style.color = '#a7f3d0';
        statusDiv.style.display = 'inline-block';
    } else if (diferencia !== 0 && saldoManual !== 0) {
        difSpan.style.color = '#fca5a5'; // Rojo si hay diferencia
        statusDiv.textContent = 'DIFERENCIA';
        statusDiv.style.background = '#7f1d1d';
        statusDiv.style.color = '#fecaca';
        statusDiv.style.display = 'inline-block';
    } else {
        difSpan.style.color = 'white';
        statusDiv.style.display = 'none';
    }
}
</script>

@endsection
