@extends('layouts.app')

@section('title', 'Factura '.$factura->numeroFormateado())

@section('content')
@php
    $pagado = $factura->totalPagadoUsd();
    $saldo = $factura->saldoUsd();
    $formas = ['Transferencia', 'Pago móvil', 'Zelle', 'Efectivo $', 'Efectivo Bs', 'Binance', 'Punto de venta'];
@endphp
<style>
    .vf-cab { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: center; padding-bottom: 14px; border-bottom: 3px solid #1e3a8a; }
    .vf-marca { display: flex; gap: 14px; align-items: center; }
    .vf-marca img { width: 64px; height: 64px; }
    .vf-tags { display: inline-flex; gap: 6px; flex-wrap: wrap; vertical-align: middle; }
    .vf-tag { font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .3px; }
    .vf-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; }
    .vf-card .t { font-size: .72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
    .vf-card .v { font-size: 1.5rem; font-weight: 800; margin-top: 2px; }
    .vf-barra { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 8px; }
    .vf-barra div { height: 100%; background: #16a34a; }
    .vf-items tfoot td { font-weight: 800; background: #f8fafc; border-top: 2px solid #cbd5e1; }
</style>
<div class="panel">
    <div class="vf-cab">
        <div class="vf-marca">
            <img src="{{ asset('logo.png') }}" alt="">
            <div>
                <h1 style="margin:0;">Factura {{ $factura->numeroFormateado() }}
                    <span class="vf-tags">
                        @if($factura->estaAnulada())
                            <span class="vf-tag" style="background:#fee2e2; color:#991b1b;">Anulada</span>
                        @else
                            <span class="vf-tag" style="{{ $saldo < 0.01 ? 'background:#dcfce7; color:#166534;' : ($factura->estaVencida() ? 'background:#fee2e2; color:#991b1b;' : 'background:#fef3c7; color:#92400e;') }}">
                                {{ $saldo < 0.01 ? 'Pagada' : ($factura->estaVencida() ? 'Vencida' : ($pagado > 0 ? 'Pago parcial' : 'Por cobrar')) }}
                            </span>
                            <span class="vf-tag" style="{{ $factura->en_profit ? 'background:#dbeafe; color:#1e40af;' : 'background:#f1f5f9; color:#475569;' }}">
                                {{ $factura->en_profit ? 'Cargada en Profit' : 'Falta cargar en Profit' }}
                            </span>
                        @endif
                    </span>
                </h1>
                <p class="muted" style="margin:4px 0 0;">{{ $factura->fecha->format('d/m/Y') }} · {{ $factura->esCredito() ? 'A crédito' : 'De contado' }} · Vendedora: <strong>{{ $factura->vendedor_nombre }}</strong> · Hecha por {{ $factura->usuario?->name ?? '—' }}</p>
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('ventas_mayor.facturas') }}" class="btn secondary">← Facturas</a>
            <a href="{{ route('ventas_mayor.pdf', $factura) }}" target="_blank" rel="noopener" class="btn primary">🧾 Factura en PDF</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif
    @if($factura->estaAnulada())
        <div class="alert error" style="margin-top:12px;">Anulada: {{ $factura->anulada_motivo }}</div>
    @endif

    @php $pctPagado = $factura->total_usd > 0 ? min(100, round($pagado / $factura->total_usd * 100)) : 0; @endphp
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-top:16px;">
        <div class="vf-card" style="border-left:4px solid #2563eb;">
            <div class="t">Cliente</div>
            <div style="font-weight:800; font-size:1.05rem; margin-top:2px;">{{ $factura->cliente?->nombre }}</div>
            <div style="font-size:.85rem;">RIF / C.I. {{ $factura->cliente?->documento }}</div>
            <div class="muted" style="font-size:.8rem;">{{ collect([$factura->cliente?->telefono, $factura->cliente?->email, $factura->cliente?->direccion])->filter()->implode(' · ') }}</div>
        </div>
        <div class="vf-card" style="border-left:4px solid #1e3a8a;">
            <div class="t">Total de la factura</div>
            <div class="v">${{ number_format($factura->total_usd, 2) }}</div>
            <div style="font-size:.85rem;">Bs {{ number_format($factura->total_bs, 2, ',', '.') }} <span class="muted">· tasa {{ number_format($factura->tasa_bcv, 4, ',', '.') }}</span></div>
        </div>
        <div class="vf-card" style="border-left:4px solid {{ $saldo < 0.01 ? '#16a34a' : ($factura->estaVencida() ? '#dc2626' : '#f59e0b') }};">
            @if($saldo < 0.01)
                <div class="t">Pagada completa</div>
                <div class="v" style="color:#16a34a;">✓ ${{ number_format($pagado, 2) }}</div>
                <div style="font-size:.85rem;">No queda saldo por cobrar</div>
            @else
                <div class="t">Por cobrar</div>
                <div class="v" style="color:#b45309;">${{ number_format($saldo, 2) }}</div>
                <div style="font-size:.85rem;">Pagado ${{ number_format($pagado, 2) }} de ${{ number_format($factura->total_usd, 2) }}</div>
            @endif
            <div class="vf-barra"><div style="width:{{ $pctPagado }}%;"></div></div>
            @if($factura->esCredito() && $factura->fecha_vencimiento && $saldo >= 0.01)
                <div style="font-size:.82rem; font-weight:700; margin-top:6px; color:{{ $factura->estaVencida() ? '#dc2626' : '#475569' }};">
                    {{ $factura->estaVencida() ? '⚠ Vencida desde el' : 'Vence el' }} {{ $factura->fecha_vencimiento->format('d/m/Y') }}
                </div>
            @endif
        </div>
    </div>

    <h3 style="margin:20px 0 6px;">Productos <span class="muted" style="font-size:.85rem; font-weight:500;">· salen del stock de JRZ</span></h3>
    <div class="table-wrap">
        <table class="data-table vf-items" style="font-size:.86rem;">
            <thead><tr><th style="width:36px;">#</th><th>Código</th><th>Producto</th><th style="text-align:right;">Cantidad</th><th style="text-align:right;">Precio $</th><th style="text-align:right;">Total $</th></tr></thead>
            <tbody>
                @foreach($factura->items as $n => $i)
                    <tr>
                        <td class="muted">{{ $n + 1 }}</td>
                        <td><code>{{ $i->codigo }}</code></td>
                        <td>{{ $i->descripcion }}</td>
                        <td style="text-align:right;">{{ $i->cantidadTexto() }}</td>
                        <td style="text-align:right;">${{ number_format($i->precio_usd, 2) }}</td>
                        <td style="text-align:right; font-weight:700;">${{ number_format($i->total_usd, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;">Total</td>
                    <td style="text-align:right;">{{ number_format($factura->items->sum(fn ($i) => $i->unidadesTotales())) }} und.</td>
                    <td></td>
                    <td style="text-align:right;">${{ number_format($factura->total_usd, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if($factura->observaciones)
        <p style="margin-top:8px;"><strong>Observaciones:</strong> {{ $factura->observaciones }}</p>
    @endif

    <h3 style="margin:18px 0 6px;">Pagos y abonos</h3>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
        @forelse($factura->pagos as $p)
            <div style="border:1px solid #e2e8f0; border-radius:8px; padding:8px; width:210px; font-size:.8rem;">
                @if($p->url)
                    <a href="{{ $p->url }}" target="_blank" rel="noopener">
                        @if($p->esImagen())
                            <img src="{{ $p->url }}" alt="Comprobante" style="width:100%; height:130px; object-fit:cover; border-radius:6px;">
                        @else
                            <div style="height:130px; display:flex; align-items:center; justify-content:center; background:#f1f5f9; border-radius:6px; font-weight:700;">📄 PDF</div>
                        @endif
                    </a>
                @else
                    <div style="height:60px; display:flex; align-items:center; justify-content:center; background:#f8fafc; border-radius:6px; color:#64748b;">Sin comprobante</div>
                @endif
                <div style="margin-top:4px; font-weight:700; font-size:.9rem;">{{ $p->monto_usd ? '$'.number_format($p->monto_usd, 2) : 'Soporte' }}</div>
                <div>{{ collect([$p->fecha?->format('d/m/Y'), $p->forma_pago, $p->referencia ? 'Ref '.$p->referencia : null])->filter()->implode(' · ') ?: $p->nombre_archivo }}</div>
                <div class="muted" style="font-size:.72rem;">{{ $p->usuario?->name }} · {{ $p->created_at?->format('d/m/Y h:i A') }}</div>
                <form method="POST" action="{{ route('ventas_mayor.comprobantes.destroy', [$factura, $p]) }}" onsubmit="return confirm('¿Quitar este comprobante?');" style="margin-top:4px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn secondary" style="padding:2px 8px; font-size:.72rem;">Quitar</button>
                </form>
            </div>
        @empty
            <p class="muted">Aún no hay pagos.</p>
        @endforelse
    </div>

    @if(! $factura->estaAnulada() && $saldo >= 0.01)
        <form method="POST" action="{{ route('ventas_mayor.comprobantes.store', $factura) }}" enctype="multipart/form-data" style="margin-top:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <div style="width:100%; font-weight:700; color:#1e3a8a;">{{ $saldo >= 0.01 ? 'Registrar pago o abono (debe $'.number_format($saldo, 2).')' : 'Agregar comprobante' }}</div>
            <div><label style="font-size:.8rem; font-weight:600;">Monto $</label><input type="number" step="0.01" min="0" max="{{ $saldo }}" name="monto_usd" style="padding:6px; width:120px;" @if($saldo >= 0.01) placeholder="{{ number_format($saldo, 2, '.', '') }}" @endif></div>
            <div><label style="font-size:.8rem; font-weight:600;">Fecha</label><input type="date" name="fecha" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" style="padding:6px;"></div>
            <div><label style="font-size:.8rem; font-weight:600;">Forma de pago</label>
                <select name="forma_pago" style="padding:6px;">
                    <option value="">--</option>
                    @foreach($formas as $f)<option value="{{ $f }}">{{ $f }}</option>@endforeach
                </select>
            </div>
            <div><label style="font-size:.8rem; font-weight:600;">Referencia</label><input type="text" name="referencia" maxlength="120" style="padding:6px;"></div>
            <div style="flex:1; min-width:220px;"><label style="font-size:.8rem; font-weight:600;">Comprobante(s) <span class="muted" style="font-weight:400;">(opcional en efectivo)</span></label><input type="file" name="comprobantes[]" multiple accept="image/*,.pdf"></div>
            <button type="submit" class="btn primary">Guardar</button>
        </form>
    @endunless

    @unless($factura->estaAnulada())
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; padding-top:14px; border-top:1px dashed #cbd5e1;">
            <form method="POST" action="{{ route('ventas_mayor.profit', $factura) }}">
                @csrf
                <button type="submit" class="btn secondary">{{ $factura->en_profit ? '↩ No está cargada en Profit' : '✔ Ya se cargó en Profit' }}</button>
            </form>
            <form method="POST" action="{{ route('ventas_mayor.anular', $factura) }}" onsubmit="const m = prompt('¿Por qué se anula la factura?'); if (!m || m.trim().length < 5) return false; this.motivo.value = m.trim(); return true;">
                @csrf
                <input type="hidden" name="motivo">
                <button type="submit" class="btn" style="background:#ef4444; color:#fff; border:0;">Anular factura</button>
            </form>
        </div>
    @endunless
</div>
@endsection
