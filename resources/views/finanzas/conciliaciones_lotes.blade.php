@extends('layouts.app')
@section('title', 'Lotes de punto de venta')
@section('content')
<style>
    .conc-page { padding: 28px; font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
    .conc-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 14px; }
    .conc-title { margin: 0; font-size: 1.6rem; color: #0f172a; font-weight: 800; letter-spacing: -0.5px; }
    .conc-title span { color: #0f766e; }
    .conc-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .btn-upload { background: linear-gradient(135deg,#0f766e,#0d9488); color: white; padding: 10px 18px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; text-decoration: none; }
    .btn-link { background: white; color: #1e3a8a; border: 1.5px solid #bfdbfe; padding: 10px 16px; border-radius: 9px; font-weight: 700; text-decoration: none; }
    .select-banco { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.95rem; color: #334155; background: white; min-width: 140px; }
    .btn-filtrar { background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 600; cursor: pointer; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; }
    .kpis { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 22px; }
    .kpi { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; min-width: 160px; }
    .kpi span { display: block; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .4px; }
    .kpi strong { font-size: 1.35rem; color: #0f172a; }
    .bank-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,.06); margin-bottom: 28px; overflow: hidden; }
    .bank-card-header { background: linear-gradient(135deg, #134e4a 0%, #0f766e 100%); padding: 16px 22px; color: white; }
    .sections-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; }
    @media(max-width: 1100px) { .sections-grid { grid-template-columns: 1fr; } }
    .section-block { border-right: 1px solid #f1f5f9; }
    .section-header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
    .section-title { font-size: 0.82rem; font-weight: 800; text-transform: uppercase; color: #1e293b; }
    .section-count { margin-left: auto; background: #f1f5f9; color: #64748b; border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; font-weight: 700; }
    .mini-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .mini-table th { padding: 8px 12px; text-align: left; color: #64748b; font-size: 0.74rem; text-transform: uppercase; background: #f8fafc; }
    .mini-table td { padding: 9px 12px; border-top: 1px solid #f8fafc; vertical-align: top; }
    .empty-row td { text-align: center; color: #94a3b8; padding: 22px; }
    .section-footer { padding: 8px 12px; text-align: right; font-weight: 800; background: #f8fafc; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; }
    .modal-box { background: white; border-radius: 14px; width: 100%; max-width: 520px; }
    .modal-head, .modal-foot { padding: 16px 22px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .modal-foot { border-bottom: none; border-top: 1px solid #e2e8f0; justify-content: flex-end; gap: 10px; }
    .modal-body { padding: 22px; }
    .form-label { display: block; font-weight: 700; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 9px; margin-bottom: 14px; box-sizing: border-box; }
    .file-wrap { display: flex; }
    .file-label { background: #f1f5f9; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px 0 0 9px; cursor: pointer; }
    .file-name { flex: 1; border: 1.5px solid #cbd5e1; border-left: none; border-radius: 0 9px 9px 0; padding: 10px; }
    .btn-cancel { background: white; border: 1.5px solid #cbd5e1; padding: 10px 16px; border-radius: 9px; font-weight: 700; cursor: pointer; }
    .btn-submit { background: #0f766e; color: white; border: none; padding: 10px 16px; border-radius: 9px; font-weight: 800; cursor: pointer; }
</style>
<div class="conc-page">
    <div class="conc-topbar">
        <div>
            <h2 class="conc-title">Lotes de <span>punto de venta</span></h2>
            <p style="margin:4px 0 0;color:#64748b;">Cruza el Excel de medios de pago con las liquidaciones LIQ del extracto. Los egresos se concilian aparte.</p>
        </div>
        <div class="conc-toolbar">
            <a class="btn-link" href="{{ route('finanzas.conciliaciones') }}">Egresos</a>
            <button type="button" class="btn-upload" onclick="document.getElementById('mediosPagoModal').style.display='flex'">Medios de pago BDV</button>
            <form action="{{ route('finanzas.conciliaciones.lotes') }}" method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin:0;">
                <input type="date" name="fecha_desde" class="select-banco" value="{{ $filtros['fecha_desde'] ?? '' }}">
                <input type="date" name="fecha_hasta" class="select-banco" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                <select name="banco_filtro" class="select-banco">
                    <option value="">Todos los bancos</option>
                    @foreach($bancos as $banco)
                        <option value="{{ $banco }}" @selected(($filtros['banco_filtro'] ?? '') === $banco)>{{ $banco }}</option>
                    @endforeach
                </select>
                <button class="btn-filtrar" type="submit">Filtrar</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">{{ session('error') }}</div>
    @endif

    <div class="kpis">
        <div class="kpi"><span>Lotes</span><strong>{{ number_format($totalLotes) }}</strong></div>
        <div class="kpi"><span>Conciliados</span><strong>{{ number_format($totalConciliados) }}</strong></div>
        <div class="kpi"><span>Sin liquidar</span><strong>{{ number_format($totalPendientes) }}</strong></div>
        <div class="kpi"><span>LIQ sin lote</span><strong>{{ number_format($totalLiq) }}</strong></div>
    </div>

    @forelse($tarjetas as $tarjeta)
        <div class="bank-card">
            <div class="bank-card-header">
                <strong>{{ $tarjeta['banco'] }}</strong>
                @if($tarjeta['titular'])
                    <div style="opacity:.8;font-size:.85rem;">Titular: {{ $tarjeta['titular'] }}</div>
                @endif
            </div>
            <div class="sections-grid">
                <div class="section-block">
                    <div class="section-header"><span class="section-title">Lotes conciliados</span><span class="section-count">{{ $tarjeta['conciliados']->count() }}</span></div>
                    <table class="mini-table">
                        <thead><tr><th>Fecha</th><th>Lote</th><th>Neto</th></tr></thead>
                        <tbody>
                            @forelse($tarjeta['conciliados'] as $lote)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $lote->lote_referencia ?: '—' }}</td>
                                    <td>Bs. {{ number_format((float) $lote->monto, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="3">Sin lotes conciliados</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($tarjeta['conciliados']->count() > 0)
                        <div class="section-footer">Bs. {{ number_format($tarjeta['total_conciliado'], 2) }}</div>
                    @endif
                </div>
                <div class="section-block">
                    <div class="section-header"><span class="section-title">Lotes sin liquidación</span><span class="section-count">{{ $tarjeta['pendientes']->count() }}</span></div>
                    <table class="mini-table">
                        <thead><tr><th>Fecha</th><th>Lote</th><th>Neto</th></tr></thead>
                        <tbody>
                            @forelse($tarjeta['pendientes'] as $lote)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ $lote->lote_referencia ?: '—' }}</td>
                                    <td>Bs. {{ number_format((float) $lote->monto, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="3">Todos los lotes tienen LIQ</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($tarjeta['pendientes']->count() > 0)
                        <div class="section-footer">Bs. {{ number_format($tarjeta['total_pendiente'], 2) }}</div>
                    @endif
                </div>
                <div class="section-block">
                    <div class="section-header"><span class="section-title">LIQ del banco sin lote</span><span class="section-count">{{ $tarjeta['liq_pendientes']->count() }}</span></div>
                    <table class="mini-table">
                        <thead><tr><th>Fecha</th><th>Descripción</th><th>Monto</th></tr></thead>
                        <tbody>
                            @forelse($tarjeta['liq_pendientes'] as $linea)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($linea->descripcion, 42) }}</td>
                                    <td>Bs. {{ number_format((float) $linea->monto, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="3">Sin liquidaciones pendientes</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($tarjeta['liq_pendientes']->count() > 0)
                        <div class="section-footer">Bs. {{ number_format($tarjeta['total_liq'], 2) }}</div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="bank-card" style="padding:28px;color:#64748b;">No hay lotes ni liquidaciones en ese rango. Sube el Excel de medios de pago o carga primero el extracto en Conciliación de egresos.</div>
    @endforelse
</div>

<div id="mediosPagoModal" class="modal-overlay">
    <div class="modal-box">
        <form action="{{ route('finanzas.conciliaciones.medios_pago') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-head">
                <h3 style="margin:0;">Medios de pago — Banco de Venezuela</h3>
                <button type="button" class="btn-cancel" onclick="document.getElementById('mediosPagoModal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:#64748b;margin-top:0;">Excel con Fecha, N° Lote y <strong>Monto Neto</strong>. Se cruza con los abonos LIQ.TARJETA / LIQUIDACION T/ del extracto.</p>
                <label class="form-label">Titular (opcional)</label>
                <select name="titular_seleccionado" class="form-control">
                    <option value="">El del extracto</option>
                    @foreach(($titularesPorBanco['VENEZUELA'] ?? []) as $titular)
                        <option value="{{ $titular }}">{{ $titular }}</option>
                    @endforeach
                </select>
                <label class="form-label">Archivo</label>
                <div class="file-wrap">
                    <label class="file-label">Elegir
                        <input type="file" name="file" accept=".xls,.xlsx,.csv" required style="display:none;" onchange="document.getElementById('mediosFileName').value = this.files[0] ? this.files[0].name : '';">
                    </label>
                    <input id="mediosFileName" class="file-name" placeholder="Ningún archivo" readonly>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="document.getElementById('mediosPagoModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-submit">Conciliar lotes</button>
            </div>
        </form>
    </div>
</div>
@endsection
