@extends('layouts.app')
@section('title', 'Alquileres')
@section('content')

<div style="max-width:1300px; margin:0 auto; padding:24px 20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Alquileres
            </div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">📋 Alquileres Fijos</h1>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('patrimonial.alquileres.calendario') }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none; background:#10b981; color:#fff;">
                🗓️ Calendario de Cobros
            </a>
            <a href="{{ route('patrimonial.alquileres.create') }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none; background:#2563eb; color:#fff;">
                + Nuevo Alquiler
            </a>
        </div>
    </div>

    {{-- ALERTAS --}}
    @if($pagosVencidos->isNotEmpty())
    <div style="background:#fee2e2; border:1px solid #fca5a5; border-radius:10px; padding:14px 18px; margin-bottom:18px;">
        <div style="font-weight:700; color:#991b1b; margin-bottom:8px;">🚨 Pagos Vencidos ({{ $pagosVencidos->count() }})</div>
        @foreach($pagosVencidos as $pago)
        <div style="font-size:0.85rem; color:#7f1d1d; margin-bottom:4px;">
            • {{ $pago->alquiler->propiedad->nombre ?? '—' }} — {{ $pago->alquiler->inquilino_nombre }}
            · Período {{ $pago->periodo }} · ${{ number_format($pago->monto, 2) }}
            · Vencía {{ optional($pago->fecha_vencimiento)->format('d/m/Y') }}
        </div>
        @endforeach
    </div>
    @endif

    @if($pagosProximos->isNotEmpty())
    <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:10px; padding:14px 18px; margin-bottom:18px;">
        <div style="font-weight:700; color:#92400e; margin-bottom:8px;">⚠️ Próximos a Vencer ({{ $pagosProximos->count() }})</div>
        @foreach($pagosProximos as $pago)
        <div style="font-size:0.85rem; color:#78350f; margin-bottom:4px;">
            • {{ $pago->alquiler->propiedad->nombre ?? '—' }} — {{ $pago->alquiler->inquilino_nombre }}
            · Período {{ $pago->periodo }} · ${{ number_format($pago->monto, 2) }}
            · Vence {{ optional($pago->fecha_vencimiento)->format('d/m/Y') }}
        </div>
        @endforeach
    </div>
    @endif

    {{-- FILTROS --}}
    <form method="GET" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
        <div>
            <label style="font-size:0.78rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Propiedad</label>
            <select name="propiedad_id" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                <option value="">Todas</option>
                @foreach($propiedades as $id => $nombre)
                    <option value="{{ $id }}" {{ request('propiedad_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.78rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Estado</label>
            <select name="estado" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                <option value="">Todos</option>
                <option value="activo" {{ request('estado')=='activo'?'selected':'' }}>Activo</option>
                <option value="vencido" {{ request('estado')=='vencido'?'selected':'' }}>Vencido</option>
                <option value="terminado" {{ request('estado')=='terminado'?'selected':'' }}>Terminado</option>
            </select>
        </div>
        <button type="submit" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.88rem; cursor:pointer;">Filtrar</button>
        <a href="{{ route('patrimonial.alquileres.index') }}" style="padding:8px 16px; background:#fff; color:#334155; border:1px solid #e2e8f0; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none;">Limpiar</a>
    </form>

    {{-- TABLA --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        @if($alquileres->isEmpty())
            <div style="text-align:center; padding:50px 20px; color:#94a3b8;">
                <div style="font-size:2.5rem; margin-bottom:10px;">📄</div>
                <div style="font-weight:600; margin-bottom:8px;">Sin alquileres registrados</div>
                <a href="{{ route('patrimonial.alquileres.create') }}" style="display:inline-block; margin-top:10px; padding:9px 18px; background:#2563eb; color:#fff; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.88rem;">+ Nuevo Alquiler</a>
            </div>
        @else
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Propiedad</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Inquilino</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Canon</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Inicio</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:left; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Día Pago</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:center; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Estado</th>
                    <th style="padding:10px 14px; border-bottom:2px solid #e2e8f0; text-align:center; font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alquileres as $alq)
                <tr style="transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9;">
                        <a href="{{ route('patrimonial.propiedades.show', $alq->propiedad_id) }}" style="color:#2563eb; text-decoration:none; font-weight:600;">
                            {{ $alq->propiedad->nombre ?? '—' }}
                        </a>
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#334155;">
                        {{ $alq->inquilino_nombre }}
                        @if($alq->inquilino_contacto)
                            <div style="font-size:0.78rem; color:#94a3b8;">{{ $alq->inquilino_contacto }}</div>
                        @endif
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; font-weight:600; color:#059669;">
                        ${{ number_format($alq->canonActual(), 2) }}
                        <div style="font-size:0.75rem; color:#94a3b8;">{{ $alq->tipo_canon }}</div>
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.85rem;">
                        <div style="font-weight:600;">{{ optional($alq->fecha_inicio)->format('d/m/Y') }}</div>
                        <div style="font-size:0.75rem;">{{ $alq->fecha_fin ? 'a ' . \Carbon\Carbon::parse($alq->fecha_fin)->format('d/m/Y') : 'Indefinido' }}</div>
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; color:#64748b; font-size:0.85rem;">
                        Día {{ $alq->dia_pago ?? '—' }}
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; text-align:center;">
                        <span style="padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;
                            background:{{ $alq->estado === 'activo' ? '#d1fae5' : ($alq->estado === 'terminado' ? '#f1f5f9' : '#fee2e2') }};
                            color:{{ $alq->estado === 'activo' ? '#065f46' : ($alq->estado === 'terminado' ? '#64748b' : '#991b1b') }};">
                            {{ ucfirst($alq->estado) }}
                        </span>
                    </td>
                    <td style="padding:10px 14px; border-bottom:1px solid #f1f5f9; text-align:center;">
                        <a href="{{ route('patrimonial.alquileres.show', $alq) }}" style="display:inline-flex; align-items:center; gap:4px; padding:5px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:7px; text-decoration:none; color:#334155; font-size:0.78rem; font-weight:600;">Ver</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:14px 16px;">{{ $alquileres->links() }}</div>
        @endif
    </div>
</div>
@endsection
