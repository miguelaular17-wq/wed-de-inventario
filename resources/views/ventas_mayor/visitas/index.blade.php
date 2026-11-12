@extends('layouts.app')

@section('title', 'Hoja de ruta')

@section('content')
@php
    $colores = ['interesado' => ['#dbeafe', '#1e40af'], 'pedido' => ['#dcfce7', '#166534'], 'volver' => ['#fef3c7', '#92400e'], 'no_interesado' => ['#f1f5f9', '#475569']];
    $resultados = \App\Models\Mayor\MayorVisita::RESULTADOS;
@endphp
<div class="panel">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <div>
            <h1 style="margin:0;">Hoja de ruta</h1>
            <p class="muted" style="margin:4px 0 0;">Visitas para captar clientes al mayor, con la firma de quien atendió y la evidencia.</p>
        </div>
        <a href="{{ route('ventas_mayor.visitas.create') }}" class="btn primary">+ Registrar visita</a>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin:16px 0;">
        <div class="panel" style="padding:12px; background:linear-gradient(135deg,#1e3a8a,#2563eb); border:0;">
            <div style="font-size:.7rem; font-weight:700; color:#bfdbfe;">VISITAS</div>
            <div style="font-size:1.5rem; font-weight:800; color:#fff;">{{ $porResultado->sum() }}</div>
        </div>
        @foreach($resultados as $clave => $texto)
            <a href="{{ route('ventas_mayor.visitas.index', array_merge(array_filter($filtro), ['resultado' => $clave])) }}" class="panel" style="padding:12px; text-decoration:none; border-left:4px solid {{ $colores[$clave][1] }}; {{ $filtro['resultado'] === $clave ? 'background:'.$colores[$clave][0].';' : '' }}">
                <div style="font-size:.7rem; font-weight:700; color:#64748b; text-transform:uppercase;">{{ $texto }}</div>
                <div style="font-size:1.5rem; font-weight:800; color:{{ $colores[$clave][1] }};">{{ (int) ($porResultado[$clave] ?? 0) }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('ventas_mayor.visitas.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px;">
        <div><label style="font-size:.78rem; font-weight:600; display:block;">Desde</label><input type="date" name="desde" value="{{ $filtro['desde'] }}" style="padding:7px;"></div>
        <div><label style="font-size:.78rem; font-weight:600; display:block;">Hasta</label><input type="date" name="hasta" value="{{ $filtro['hasta'] }}" style="padding:7px;"></div>
        <div><label style="font-size:.78rem; font-weight:600; display:block;">Resultado</label>
            <select name="resultado" style="padding:7px;">
                <option value="">Todos</option>
                @foreach($resultados as $clave => $texto)<option value="{{ $clave }}" @selected($filtro['resultado'] === $clave)>{{ $texto }}</option>@endforeach
            </select>
        </div>
        @if($usuarios->isNotEmpty())
            <div><label style="font-size:.78rem; font-weight:600; display:block;">Vendedora</label>
                <select name="usuario" style="padding:7px;">
                    <option value="">Todas</option>
                    @foreach($usuarios as $u)<option value="{{ $u->id }}" @selected($filtro['usuario'] === $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="btn primary">Filtrar</button>
    </form>

    @if($proximas->isNotEmpty())
        <div style="padding:10px 14px; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; margin-bottom:12px;">
            <strong style="color:#92400e;">📅 Próximas visitas (7 días):</strong>
            @foreach($proximas as $p)
                <a href="{{ route('ventas_mayor.visitas.show', $p) }}" style="margin-left:8px; font-size:.85rem;">{{ $p->proxima_visita->format('d/m') }} · {{ $p->negocio_nombre }}</a>{{ $loop->last ? '' : ' ·' }}
            @endforeach
        </div>
    @endif

    <div class="table-wrap">
        <table class="data-table" style="font-size:.84rem;">
            <thead><tr><th>Fecha</th><th>Negocio</th><th>Atendió</th><th>Resultado</th><th>Próxima</th><th>Vendedora</th><th></th></tr></thead>
            <tbody>
                @forelse($visitas as $v)
                    <tr>
                        <td style="white-space:nowrap;">{{ $v->fecha->format('d/m/Y') }}<div class="muted" style="font-size:.72rem;">{{ $v->created_at?->format('h:i A') }}</div></td>
                        <td>
                            <strong>{{ $v->negocio_nombre }}</strong>
                            @if($v->cliente_id)<span class="tag ok" style="font-size:.65rem;">Cliente</span>@endif
                            <div class="muted" style="font-size:.72rem;">{{ collect([$v->negocio_documento, $v->zona, $v->negocio_telefono])->filter()->implode(' · ') }}</div>
                        </td>
                        <td>{{ $v->atendio_nombre }}<div class="muted" style="font-size:.72rem;">{{ collect([$v->atendio_cargo, $v->atendio_telefono])->filter()->implode(' · ') }}</div></td>
                        <td><span style="padding:2px 9px; border-radius:999px; font-size:.75rem; font-weight:700; background:{{ $colores[$v->resultado][0] ?? '#f1f5f9' }}; color:{{ $colores[$v->resultado][1] ?? '#475569' }};">{{ $v->etiquetaResultado() }}</span></td>
                        <td>{{ $v->proxima_visita?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $v->usuario?->name }}</td>
                        <td><a href="{{ route('ventas_mayor.visitas.show', $v) }}" class="btn secondary" style="padding:3px 9px; font-size:.75rem;">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="text-align:center; padding:24px;">No hay visitas en este período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:10px;">{{ $visitas->links() }}</div>
</div>
@endsection
