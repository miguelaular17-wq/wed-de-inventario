@extends('layouts.app')
@section('title', 'Dashboard Patrimonial')
@section('content')

<style>
.pat-header {
    background: linear-gradient(135deg, #1a4480 0%, #2563eb 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 28px;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.pat-header h1 { margin: 0; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.5px; }
.pat-header p  { margin: 4px 0 0; opacity: 0.8; font-size: 0.9rem; }

.pat-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.pat-kpi {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 20px 18px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: transform 0.2s, box-shadow 0.2s;
}
.pat-kpi:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.pat-kpi .kpi-val { font-size: 2rem; font-weight: 700; line-height: 1; margin-bottom: 6px; }
.pat-kpi .kpi-label { font-size: 0.8rem; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
.pat-kpi .kpi-icon { font-size: 1.5rem; margin-bottom: 8px; }

.pat-finance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.pat-finance-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 22px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.pat-finance-card .fc-label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 8px; }
.pat-finance-card .fc-val  { font-size: 1.4rem; font-weight: 700; }

.pat-section { margin-bottom: 28px; }
.pat-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.pat-section-title::before {
    content: '';
    display: inline-block;
    width: 4px;
    height: 18px;
    background: #2563eb;
    border-radius: 2px;
}

.pat-table-wrap {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.pat-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pat-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 10px 14px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.5px; font-size: 11px; }
.pat-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.pat-table tr:last-child td { border-bottom: none; }
.pat-table tr:hover td { background: #f8fafc; }

.alert-badge { display: inline-flex; align-items: center; gap: 6px; background: #fef3c7; color: #92400e; border-radius: 8px; padding: 10px 16px; font-size: 0.85rem; font-weight: 600; border: 1px solid #fde68a; }
.alert-badge.danger { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

.verde  { color: #059669; }
.rojo   { color: #dc2626; }
.azul   { color: #2563eb; }
.gris   { color: #64748b; }

.month-nav { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; }
.month-nav span { font-weight: 600; font-size: 0.95rem; color: #1e293b; }

@media (max-width: 768px) {
    .pat-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .pat-finance-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="wrap" style="max-width:1400px; margin:0 auto; padding:24px 20px;">

    {{-- HEADER --}}
    <div class="pat-header">
        <div>
            <h1>🏢 Gestión Patrimonial</h1>
            <p>Dashboard Financiero · {{ \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y') }}</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            @php
                $mesPrev = \Carbon\Carbon::create($anio, $mes)->subMonth();
                $mesSig  = \Carbon\Carbon::create($anio, $mes)->addMonth();
            @endphp
            <a href="?mes={{ $mesPrev->month }}&anio={{ $mesPrev->year }}"
               style="background:rgba(255,255,255,0.2); color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:0.85rem;">
                ← Mes anterior
            </a>
            <a href="?mes={{ $mesSig->month }}&anio={{ $mesSig->year }}"
               style="background:rgba(255,255,255,0.2); color:#fff; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:0.85rem;">
                Mes siguiente →
            </a>
            <a href="{{ route('patrimonial.propiedades.create') }}"
               style="background:#fff; color:#2563eb; padding:8px 16px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:700;">
                + Nueva Propiedad
            </a>
        </div>
    </div>

    {{-- ALERTAS --}}
    @if($pagosVencidos > 0 || $pagosProximos > 0)
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
        @if($pagosVencidos > 0)
            <a href="{{ route('patrimonial.alquileres.index') }}" class="alert-badge danger">
                🚨 {{ $pagosVencidos }} pago{{ $pagosVencidos > 1 ? 's' : '' }} vencido{{ $pagosVencidos > 1 ? 's' : '' }}
            </a>
        @endif
        @if($pagosProximos > 0)
            <a href="{{ route('patrimonial.alquileres.index') }}" class="alert-badge">
                ⚠️ {{ $pagosProximos }} pago{{ $pagosProximos > 1 ? 's' : '' }} próximo{{ $pagosProximos > 1 ? 's' : '' }} (7 días)
            </a>
        @endif
    </div>
    @endif

    {{-- KPIs PROPIEDADES --}}
    <div class="pat-section">
        <div class="pat-section-title">Estado de Propiedades</div>
        <div class="pat-kpi-grid">
            <div class="pat-kpi">
                <div class="kpi-icon">🏢</div>
                <div class="kpi-val azul">{{ $totalPropiedades }}</div>
                <div class="kpi-label">Total</div>
            </div>
            <div class="pat-kpi">
                <div class="kpi-icon">🔑</div>
                <div class="kpi-val" style="color:#2563eb;">{{ $alquiladas }}</div>
                <div class="kpi-label">Alquiladas</div>
            </div>
            <div class="pat-kpi">
                <div class="kpi-icon">✅</div>
                <div class="kpi-val verde">{{ $disponibles }}</div>
                <div class="kpi-label">Disponibles</div>
            </div>
            <div class="pat-kpi">
                <div class="kpi-icon">🔨</div>
                <div class="kpi-val" style="color:#f59e0b;">{{ $enRemodelacion }}</div>
                <div class="kpi-label">Remodelación</div>
            </div>
            <div class="pat-kpi">
                <div class="kpi-icon">🏠</div>
                <div class="kpi-val" style="color:#8b5cf6;">{{ $usoPropio }}</div>
                <div class="kpi-label">Uso Propio</div>
            </div>
        </div>
    </div>

    {{-- KPIs FINANCIEROS DEL MES --}}
    <div class="pat-section">
        <div class="pat-section-title">Resumen Financiero del Mes</div>
        <div class="pat-finance-grid">
            <div class="pat-finance-card">
                <div class="fc-label">💵 Ingresos</div>
                <div class="fc-val verde">${{ number_format($ingresosMes, 2) }}</div>
            </div>
            <div class="pat-finance-card">
                <div class="fc-label">💸 Gastos</div>
                <div class="fc-val rojo">${{ number_format($gastosMes, 2) }}</div>
            </div>
            <div class="pat-finance-card">
                <div class="fc-label">🤝 Comisiones</div>
                <div class="fc-val" style="color:#f59e0b;">${{ number_format($comisionesMes, 2) }}</div>
            </div>
            <div class="pat-finance-card" style="border: 2px solid {{ $balanceMes >= 0 ? '#10b981' : '#ef4444' }};">
                <div class="fc-label">📊 Balance Neto</div>
                <div class="fc-val {{ $balanceMes >= 0 ? 'verde' : 'rojo' }}">${{ number_format($balanceMes, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- TABLA BALANCE POR PROPIEDAD --}}
    @if($balancePorPropiedad->isNotEmpty())
    <div class="pat-section">
        <div class="pat-section-title">Balance por Propiedad · {{ \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y') }}</div>
        <div class="pat-table-wrap">
            <table class="pat-table">
                <thead>
                    <tr>
                        <th>Propiedad</th>
                        <th>Tipo</th>
                        <th style="text-align:right;">Ingresos</th>
                        <th style="text-align:right;">Gastos</th>
                        <th style="text-align:right;">Comisiones</th>
                        <th style="text-align:right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balancePorPropiedad as $b)
                    <tr>
                        <td style="font-weight:600;">{{ $b['propiedad'] }}</td>
                        <td style="color:#64748b;">{{ ucfirst($b['tipo']) }}</td>
                        <td style="text-align:right;" class="verde">${{ number_format($b['ingresos'], 2) }}</td>
                        <td style="text-align:right;" class="rojo">${{ number_format($b['gastos'], 2) }}</td>
                        <td style="text-align:right;" style="color:#f59e0b;">${{ number_format($b['comisiones'], 2) }}</td>
                        <td style="text-align:right; font-weight:700;" class="{{ $b['balance'] >= 0 ? 'verde' : 'rojo' }}">
                            ${{ number_format($b['balance'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div style="text-align:center; padding:40px; color:#94a3b8; background:#f8fafc; border-radius:12px; border:1px dashed #e2e8f0;">
        <div style="font-size:2.5rem; margin-bottom:12px;">📊</div>
        <div style="font-weight:600; margin-bottom:6px;">Sin transacciones este mes</div>
        <div style="font-size:0.85rem;">Registra ingresos y gastos en <a href="{{ route('patrimonial.transacciones.index') }}" style="color:#2563eb;">Gastos y Mantenimiento</a></div>
    </div>
    @endif

    {{-- LINKS RÁPIDOS --}}
    <div class="pat-section" style="margin-top:28px;">
        <div class="pat-section-title">Acceso Rápido</div>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:12px;">
            @php
            $links = [
                ['🏘️', 'Propiedades',  route('patrimonial.propiedades.index')],
                ['📋', 'Alquileres',   route('patrimonial.alquileres.index')],
                ['📅', 'Reservas',     route('patrimonial.reservas.index')],
                ['💳', 'Transacciones', route('patrimonial.transacciones.index')],
                ['📦', 'Inventario',   route('patrimonial.inventario.index')],
                ['🔑', 'Llaves',       route('patrimonial.llaves.index')],
                ['📁', 'Documentos',   route('patrimonial.documentos.index')],
                ['📄', 'Reporte Mensual', route('patrimonial.reportes.mensual')],
            ];
            @endphp
            @foreach($links as [$icon, $label, $href])
            <a href="{{ $href }}" style="display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; text-decoration:none; color:#334155; font-weight:600; font-size:0.9rem; transition:all 0.2s;"
               onmouseover="this.style.borderColor='#2563eb';this.style.color='#2563eb';"
               onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#334155';">
                <span style="font-size:1.3rem;">{{ $icon }}</span>{{ $label }}
            </a>
            @endforeach
        </div>
    </div>

</div>
@endsection
