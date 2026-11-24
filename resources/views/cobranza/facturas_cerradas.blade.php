@extends('layouts.app')
@section('title', 'Facturas cerradas · Cobranza')
@section('content')
<style>
    .fc-page { max-width: 1280px; margin: 0 auto; padding: 20px; }
    .fc-cab { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .fc-filtros { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 14px; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; }
    .fc-filtros label { display: flex; flex-direction: column; gap: 4px; font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
    .fc-filtros input, .fc-filtros select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .9rem; min-width: 140px; }
    .fc-filtros input[type=search] { min-width: 220px; }
    .fc-btn { padding: 8px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-weight: 600; cursor: pointer; text-decoration: none; font-size: .88rem; display: inline-flex; align-items: center; }
    .fc-btn.primario { background: var(--blue, #1e3a8a); color: #fff; border-color: var(--blue, #1e3a8a); }
    .fc-btn.peligro { color: #b91c1c; border-color: #fecaca; background: #fff5f5; }
    .fc-tabla { width: 100%; border-collapse: collapse; background: #fff; font-size: .88rem; }
    .fc-tabla th { text-align: left; font-size: .72rem; text-transform: uppercase; color: #64748b; padding: 10px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    .fc-tabla td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .fc-tabla tr:hover td { background: #f8fafc; }
    .fc-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .fc-pill.web { background: #ecfdf5; color: #047857; }
    .fc-pill.manual { background: #eff6ff; color: #1d4ed8; }
    .fc-mono { font-variant-numeric: tabular-nums; font-weight: 700; }
    .fc-muted { color: #64748b; font-size: .8rem; }
</style>
<div class="fc-page">
    <div class="fc-cab">
        <div>
            <h2 style="margin:0; color: var(--blue); font-weight: 800;">Facturas cerradas</h2>
            <p class="muted" style="margin:4px 0 0;">Historial de facturas saldadas o marcadas como pagadas. Ya no aparecen en el dashboard de Cobranza.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span class="fc-muted">{{ number_format($totalCerradas) }} en total</span>
            <a href="{{ route('cobranza.index') }}" class="fc-btn">← Cobranza</a>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#d1e7dd; color:#0f5132; padding:10px 14px; border-radius:8px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#f8d7da; color:#842029; padding:10px 14px; border-radius:8px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('cobranza.facturas_cerradas') }}" class="fc-filtros">
        <label>Buscar
            <input type="search" name="q" value="{{ $q }}" placeholder="Cliente, código o factura">
        </label>
        <label>Sede
            <select name="sede">
                <option value="">Todas</option>
                @foreach($sedes as $s)
                    <option value="{{ $s }}" @selected($sede === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </label>
        <label>Cerrada desde
            <input type="date" name="desde" value="{{ $desde }}">
        </label>
        <label>Hasta
            <input type="date" name="hasta" value="{{ $hasta }}">
        </label>
        <button type="submit" class="fc-btn primario">Aplicar</button>
        @if($q !== '' || $sede !== '' || $desde || $hasta)
            <a href="{{ route('cobranza.facturas_cerradas') }}" class="fc-btn">Limpiar</a>
        @endif
    </form>

    <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:10px;">
        <table class="fc-tabla">
            <thead>
                <tr>
                    <th>Cerrada</th>
                    <th>Factura</th>
                    <th>Cliente</th>
                    <th>Sede</th>
                    <th>Emisión</th>
                    <th>Monto</th>
                    <th>Origen</th>
                    <th>Por</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($filas as $f)
                    <tr>
                        <td>
                            <div class="fc-mono">{{ \Carbon\Carbon::parse($f->cerrado_en)->format('d/m/Y') }}</div>
                            <div class="fc-muted">{{ \Carbon\Carbon::parse($f->cerrado_en)->format('H:i') }}</div>
                        </td>
                        <td>
                            <div class="fc-mono">{{ $f->tipo }} {{ $f->numero }}</div>
                            <div class="fc-muted">{{ $f->id_documento }}</div>
                        </td>
                        <td>
                            <div style="font-weight:600;">{{ $f->cliente }}</div>
                            @if($f->codigo_cliente)
                                <div class="fc-muted">{{ $f->codigo_cliente }}</div>
                            @endif
                        </td>
                        <td>{{ $f->sede }}</td>
                        <td>{{ $f->fecha_emision ? \Carbon\Carbon::parse($f->fecha_emision)->format('d/m/Y') : '—' }}</td>
                        <td class="fc-mono">{{ $f->monto !== null ? '$'.number_format($f->monto, 2) : '—' }}</td>
                        <td>
                            <span class="fc-pill {{ $f->origen === 'Flujo web' ? 'web' : 'manual' }}">{{ $f->origen }}</span>
                        </td>
                        <td>{{ $f->cerrado_por }}</td>
                        <td style="white-space:nowrap; text-align:right;">
                            @if($f->codigo_cliente)
                                <a class="fc-btn" href="{{ route('cobranza.estado_cuenta.cliente', $f->codigo_cliente) }}" title="Estado de cuenta">Estado</a>
                            @endif
                            <form method="POST" action="{{ route('cobranza.facturas_cerradas.reabrir', $f->id) }}" style="display:inline;" onsubmit="return confirm('¿Reabrir {{ $f->tipo }} {{ $f->numero }}? Volverá al dashboard de Cobranza si aún tiene saldo.');">
                                @csrf
                                @method('DELETE')
                                @foreach(request()->only(['q','sede','desde','hasta']) as $k => $v)
                                    @if($v !== null && $v !== '')
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                <button type="submit" class="fc-btn peligro">Reabrir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:28px; text-align:center; color:#64748b;">
                            No hay facturas cerradas con esos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px;">
        {{ $filas->links() }}
    </div>
</div>
@endsection
