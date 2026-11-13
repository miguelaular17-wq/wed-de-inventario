@extends('layouts.app')

@section('title', 'Ventas al mayor · Facturas')

@section('content')
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <div>
            <h1 style="margin:0;">Facturas al mayor</h1>
            <p class="muted" style="margin:4px 0 0;">Lo facturado se resta del stock de JRZ hasta que la factura se marca «cargada en Profit» (desde ahí lo descuenta Profit).</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('ventas_mayor.stock') }}" class="btn secondary">Stock</a>
            <a href="{{ route('ventas_mayor.create') }}" class="btn primary">+ Nueva factura</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif

    <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <form method="GET" action="{{ route('ventas_mayor.facturas') }}" style="flex:1; display:flex; gap:10px; min-width:300px;">
            <input type="search" name="q" value="{{ $q }}" placeholder="Número, cliente, RIF o vendedora…" style="flex:1; padding:8px 10px;">
            @if($porCobrar)<input type="hidden" name="por_cobrar" value="1">@endif
            <button type="submit" class="btn primary">Buscar</button>
        </form>
        <a href="{{ route('ventas_mayor.facturas', array_filter(['q' => $q, 'por_cobrar' => $porCobrar ? null : 1])) }}" class="btn {{ $porCobrar ? 'primary' : 'secondary' }}">
            {{ $porCobrar ? '✓ ' : '' }}Por cobrar · ${{ number_format($porCobrarTotal, 2) }}
        </a>
    </div>

    <div class="table-wrap" style="margin-top:12px;">
        <table class="data-table" style="font-size:.84rem;">
            <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedora</th><th style="text-align:right;">Total $</th><th style="text-align:right;">Por cobrar $</th><th>Pago</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse($facturas as $f)
                    @php $saldo = $f->saldoUsd(); $estadoPago = $f->estadoPago(); @endphp
                    <tr style="{{ $f->estaAnulada() ? 'opacity:.55;' : '' }}">
                        <td><strong>{{ $f->numeroFormateado() }}</strong></td>
                        <td>{{ $f->fecha->format('d/m/Y') }}</td>
                        <td>{{ $f->cliente?->nombre }}<div class="muted" style="font-size:.72rem;">{{ $f->cliente?->documento }}</div></td>
                        <td>{{ $f->vendedor_nombre }}</td>
                        <td style="text-align:right; font-weight:700;">${{ number_format($f->total_usd, 2) }}<div class="muted" style="font-size:.72rem; font-weight:400;">Bs {{ number_format($f->total_bs, 2, ',', '.') }}</div></td>
                        <td style="text-align:right; font-weight:700; color:{{ $saldo >= 0.01 ? '#b45309' : '#16a34a' }};">{{ $saldo >= 0.01 ? '$'.number_format($saldo, 2) : '—' }}</td>
                        <td>
                            <span class="tag {{ ['pagada' => 'ok', 'parcial' => 'warn', 'pendiente' => 'no'][$estadoPago] }}">{{ ['pagada' => 'Pagada', 'parcial' => 'Abonada', 'pendiente' => 'Sin pago'][$estadoPago] }}</span>
                            <div class="muted" style="font-size:.72rem;">{{ $f->esCredito() ? 'Crédito'.($f->fecha_vencimiento ? ' · vence '.$f->fecha_vencimiento->format('d/m') : '') : 'Contado' }}</div>
                            @if($f->estaVencida())<div style="font-size:.72rem; font-weight:700; color:#dc2626;">⚠ Vencida</div>@endif
                        </td>
                        <td>
                            @if($f->estaAnulada())
                                <span class="tag no">Anulada</span>
                            @elseif($f->en_profit)
                                <span class="tag ok">En Profit</span>
                            @else
                                <span class="tag req">Aparta stock JRZ</span>
                            @endif
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="{{ route('ventas_mayor.show', $f) }}" class="btn secondary" style="padding:3px 8px; font-size:.75rem;">Ver</a>
                            <a href="{{ route('ventas_mayor.pdf', $f) }}" class="btn secondary" style="padding:3px 8px; font-size:.75rem;">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted" style="text-align:center;">Aún no hay facturas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:10px;">{{ $facturas->links() }}</div>
</div>
@endsection
