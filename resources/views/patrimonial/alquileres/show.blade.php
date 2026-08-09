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

    <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:20px; align-items:start;">

        {{-- REGISTRAR PAGO --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Registrar Pago</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.alquileres.pago', $alquiler) }}" method="POST">
                    @csrf
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b;">Período *</label>
                            <input type="text" name="periodo" placeholder="2026-08" required value="{{ now()->format('Y-m') }}"
                                style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.9rem; font-family:inherit;">
                        </div>
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b;">Fecha Vencimiento *</label>
                            <input type="date" name="fecha_vencimiento" required
                                style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.9rem; font-family:inherit;">
                        </div>
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b;">Monto ($) *</label>
                            <input type="number" name="monto" step="0.01" min="0" value="{{ $alquiler->canonActual() }}" required
                                style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.9rem; font-family:inherit;">
                        </div>
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b;">Estado</label>
                            <select name="estado" style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.9rem; font-family:inherit;">
                                <option value="pendiente">Pendiente</option>
                                <option value="pagado">Pagado</option>
                                <option value="vencido">Vencido</option>
                            </select>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:5px;">
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b;">Fecha de Pago</label>
                            <input type="date" name="fecha_pago"
                                style="padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.9rem; font-family:inherit;">
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">💾 Registrar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- HISTORIAL DE PAGOS --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">📋 Historial de Pagos</h3>
                <span style="font-size:0.8rem; color:#64748b;">{{ $alquiler->pagos->count() }} registros</span>
            </div>
            <div style="overflow:auto; max-height:400px;">
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
                        <form action="{{ route('patrimonial.alquileres.actualizar_pago', $pago) }}" method="POST" style="display:inline;">
                            @csrf @method('PUT')
                            <select name="estado" onchange="this.form.submit()"
                                style="margin-top:4px; padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700; border:1px solid #e2e8f0; cursor:pointer;
                                background:{{ $pago->estado === 'pagado' ? '#d1fae5' : ($pago->estado === 'vencido' ? '#fee2e2' : '#fef3c7') }};
                                color:{{ $pago->estado === 'pagado' ? '#065f46' : ($pago->estado === 'vencido' ? '#991b1b' : '#92400e') }};">
                                <option value="pendiente" {{ $pago->estado === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                                <option value="pagado"   {{ $pago->estado === 'pagado'   ? 'selected' : '' }}>Pagado</option>
                                <option value="vencido"  {{ $pago->estado === 'vencido'  ? 'selected' : '' }}>Vencido</option>
                            </select>
                        </form>
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
@endsection
