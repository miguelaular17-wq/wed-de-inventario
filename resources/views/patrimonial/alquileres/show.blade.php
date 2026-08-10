@extends('layouts.app')
@section('title', 'Alquiler - ' . $alquiler->inquilino_nombre)
@section('content')

<div style="max-width:1000px; margin:0 auto; padding:24px 20px;">
    <div style="margin-bottom:14px; font-size:0.88rem;">
        <a href="{{ route('patrimonial.alquileres.index') }}" style="color:#2563eb; text-decoration:none;">← Alquileres</a>
        · <a href="{{ route('patrimonial.propiedades.show', $alquiler->propiedad_id) }}" style="color:#2563eb; text-decoration:none;">{{ $alquiler->propiedad->nombre }}</a>
    </div>

    {{-- HEADER --}}
    <div style="background:linear-gradient(135deg,#1a4480,#2563eb); border-radius:16px; padding:24px 28px; color:#fff; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div>
                <div style="font-size:0.8rem; opacity:0.7; margin-bottom:6px;">{{ $alquiler->propiedad->nombre }} · {{ $alquiler->contrato_nro ?? 'Sin Nº' }}</div>
                <h1 style="margin:0; font-size:1.3rem; font-weight:700;">{{ $alquiler->inquilino_nombre }}</h1>
                <div style="margin-top:8px; display:flex; gap:16px; flex-wrap:wrap; font-size:0.85rem; opacity:0.85;">
                    <span>💰 ${{ number_format($alquiler->canonActual(), 2) }} / {{ $alquiler->tipo_canon }}</span>
                    <span>📅 Desde {{ optional($alquiler->fecha_inicio)->format('d/m/Y') }} hasta {{ $alquiler->fecha_fin ? \Carbon\Carbon::parse($alquiler->fecha_fin)->format('d/m/Y') : 'Indefinido' }}</span>
                    <span>🗓️ Día {{ $alquiler->dia_pago ?? 'N/A' }}</span>
                    @if($alquiler->inquilino_contacto) <span>📞 {{ $alquiler->inquilino_contacto }}</span> @endif
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <span style="padding:5px 14px; border-radius:20px; font-size:0.82rem; font-weight:700;
                    background:{{ $alquiler->estado === 'activo' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)' }};
                    color:#fff;">
                    {{ ucfirst($alquiler->estado) }}
                </span>
                <a href="{{ route('patrimonial.alquileres.edit', $alquiler) }}" style="padding:8px 16px; background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:600;">✏️ Editar</a>
            </div>
        </div>
    </div>

    <div style="max-width:800px; margin:0 auto;">
        {{-- HISTORIAL DE PAGOS --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">📋 Cuotas e Historial de Pagos</h3>
                <span style="font-size:0.8rem; color:#64748b;">{{ $alquiler->pagos->count() }} cuotas</span>
            </div>
            <div style="overflow:auto; max-height:500px;">
                @forelse($alquiler->pagos->sortByDesc('fecha_vencimiento') as $pago)
                <div style="padding:12px 18px; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem; color:#334155;">{{ $pago->periodo }}</div>
                        <div style="font-size:0.78rem; color:#94a3b8;">Vence: {{ optional($pago->fecha_vencimiento)->format('d/m/Y') }}</div>
                        @if($pago->fecha_pago)
                            <div style="font-size:0.78rem; color:#059669;">Pagado: {{ optional($pago->fecha_pago)->format('d/m/Y') }}</div>
                        @endif
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:700; font-size:0.95rem; color:#334155;">${{ number_format($pago->monto, 2) }}</div>
                        @if($pago->monto_pagado > 0 && $pago->estado !== 'pagado')
                            <div style="font-size:0.75rem; color:#059669; font-weight:600; margin-bottom: 2px;">Abonado: ${{ number_format($pago->monto_pagado, 2) }}</div>
                        @endif
                        @if($pago->estado !== 'pagado')
                            <button onclick="abrirModalPagos({{ $pago->id }}, '{{ $pago->periodo }}', {{ $pago->getSaldo() }})"
                                    style="margin-top:4px; padding:4px 10px; font-size:0.8rem; border-radius:6px; border:1px solid #10b981; background:#10b981; color:#fff; cursor:pointer; font-weight:600;">
                                💰 Pagar
                            </button>
                        @else
                            <span style="display:inline-block; margin-top:4px; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; background:#d1fae5; color:#065f46;">
                                Pagado
                            </span>
                        @endif
                    </div>
                </div>
                @empty
                    <div style="padding:30px; text-align:center; color:#94a3b8; font-size:0.88rem;">Sin pagos registrados aún.</div>
                @endforelse
            </div>
            @if($alquiler->pagos->isNotEmpty())
            <div style="padding:12px 18px; background:#f8fafc; border-top:2px solid #e2e8f0; display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700;">
                <span style="color:#059669;">Pagados: ${{ number_format($alquiler->pagos->where('estado','pagado')->sum('monto'), 2) }}</span>
                <span style="color:#dc2626;">Pendientes: ${{ number_format($alquiler->pagos->where('estado','pendiente')->sum('monto'), 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ELIMINAR --}}
    <div style="margin-top:24px; text-align:right;">
        <form method="POST" action="{{ route('patrimonial.alquileres.destroy', $alquiler) }}"
              onsubmit="return confirm('¿Eliminar este contrato de alquiler?')">
            @csrf @method('DELETE')
            <button type="submit" style="padding:8px 16px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.85rem;">🗑️ Eliminar Alquiler</button>
        </form>
    </div>
</div>

{{-- MODAL REGISTRAR PAGO --}}
<div id="modalPagos" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:100%; max-width:500px; border-radius:12px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h3 id="modalPagoTitle" style="margin:0 0 16px 0; font-size:1.1rem; color:#1e293b;">Registrar Pago</h3>
        
        <form id="formPago" method="POST" action="">
            @csrf @method('PUT')
            <input type="hidden" name="estado" value="pagado">
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Monto Pagado *</label>
                    <input type="number" name="monto" id="monto_pagado" step="0.01" min="0" required
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Fecha Pago *</label>
                    <input type="date" name="fecha_pago" required value="{{ date('Y-m-d') }}"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:0.8rem; font-weight:600; color:#475569;">Forma de Pago *</label>
                <select name="forma_pago" id="forma_pago" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" onchange="toggleCamposPago()">
                    <option value="Transferencia BCV">Transferencia BCV</option>
                    <option value="Pago Móvil">Pago Móvil</option>
                    <option value="Zelle">Zelle</option>
                    <option value="Efectivo USD">Efectivo USD</option>
                    <option value="Binance">Binance</option>
                </select>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;" id="div_tasa_ref">
                <div id="div_tasa">
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Tasa de Cambio</label>
                    <input type="number" name="tasa_cambio" step="0.0001" placeholder="Ej: 36.50"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div id="div_referencia">
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Nro Referencia</label>
                    <input type="text" name="referencia"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;" id="div_bancos">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Banco Origen</label>
                    <input type="text" name="banco_origen"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Banco Destino</label>
                    <input type="text" name="banco_destino"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#475569;">Comentario</label>
                <textarea name="comentario" rows="2" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;"></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="cerrarModalPagos()" style="padding:8px 16px; background:#f1f5f9; color:#475569; border:none; border-radius:6px; cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:8px 16px; background:#059669; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Guardar Pago</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function abrirModalPagos(idPago, periodo, saldoP) {
    document.getElementById('modalPagoTitle').innerText = 'Pagar Cuota - ' + periodo;
    document.getElementById('monto_pagado').value = saldoP.toFixed(2);
    document.getElementById('formPago').action = '/patrimonial/alquileres/pago/' + idPago;
    document.getElementById('modalPagos').style.display = 'flex';
    toggleCamposPago();
}

function cerrarModalPagos() {
    document.getElementById('modalPagos').style.display = 'none';
    document.getElementById('formPago').reset();
}

function toggleCamposPago() {
    const forma = document.getElementById('forma_pago').value;
    const divTasa = document.getElementById('div_tasa');
    const divRef = document.getElementById('div_referencia');
    const divBancos = document.getElementById('div_bancos');
    const divTasaRef = document.getElementById('div_tasa_ref');

    if (forma === 'Zelle' || forma === 'Binance') {
        divTasa.style.display = 'none';
        divRef.style.display = 'block';
        divBancos.style.display = 'none';
        divTasaRef.style.display = 'block';
        divTasaRef.style.gridTemplateColumns = '1fr';
    } else if (forma === 'Efectivo USD') {
        divTasaRef.style.display = 'none';
        divBancos.style.display = 'none';
    } else {
        divTasaRef.style.display = 'grid';
        divTasaRef.style.gridTemplateColumns = '1fr 1fr';
        divTasa.style.display = 'block';
        divRef.style.display = 'block';
        divBancos.style.display = 'grid';
    }
}
</script>
@endpush
@endsection
