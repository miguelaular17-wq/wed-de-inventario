@extends('layouts.app')

@section('title', 'Bitácora · '.$equipo->etiqueta())

@section('content')
<div class="panel nomina-page" style="max-width:820px;margin:0 auto;">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('servicio.celulares.bitacora') }}" class="muted" style="font-size:.82rem;">← Buscar</a>
            <h1 style="margin:4px 0 0;">{{ $equipo->etiqueta() }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $equipo->identificador() }}
                @if($equipo->color) · {{ $equipo->color }} @endif
                @if($equipo->telefono_asociado) · Tel. {{ $equipo->telefono_asociado }} @endif
            </p>
        </div>
        <div>
            @auth
                <a class="btn primary" href="{{ route('servicio.ordenes.create', ['equipo_id' => $equipo->id]) }}">Nueva orden</a>
            @else
                <a class="btn primary" href="{{ route('login', ['next' => 'registrar', 'equipo_id' => $equipo->id]) }}">Iniciar sesión para orden</a>
            @endauth
        </div>
    </div>

    <div class="nomina-kpis" style="margin-top:16px;">
        <div class="nomina-kpi"><span>Sede actual</span><strong>{{ $equipo->sede_actual ?: '—' }}</strong></div>
        <div class="nomina-kpi"><span>Estado</span><strong>{{ $equipo->etiquetaEstado() }}</strong></div>
        <div class="nomina-kpi"><span>Órdenes</span><strong>{{ $equipo->ordenes->count() }}</strong></div>
    </div>

    <h3 style="margin:28px 0 12px;">Bitácora del equipo</h3>

    @forelse($equipo->eventos as $evento)
        <div style="display:grid;grid-template-columns:28px 1fr;gap:12px;padding:14px 0;border-bottom:1px solid #e2e8f0;">
            <div style="font-size:1.25rem;line-height:1.2;">{{ $evento->icono() }}</div>
            <div>
                <div style="font-size:.8rem;color:#64748b;">
                    {{ $evento->created_at?->format('d/m/Y H:i') }}
                    @if($evento->sede) · {{ $evento->sede }} @endif
                    @if($evento->usuario) · {{ $evento->usuario->name }} @endif
                </div>
                <div style="font-weight:600;margin-top:2px;">
                    {{ $evento->titulo ?: $evento->etiquetaTipo() }}
                </div>
                @if($evento->descripcion)
                    <div style="margin-top:2px;">{{ $evento->descripcion }}</div>
                @endif
                @if($evento->orden)
                    <div class="muted" style="font-size:.85rem;margin-top:4px;">
                        Orden
                        <a href="{{ route('servicio.ordenes.show', $evento->orden) }}">{{ $evento->orden->codigo() }}</a>
                        · {{ $evento->orden->etiquetaTipoGestion() }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="muted">Todavía no hay eventos en la bitácora de este equipo.</p>
    @endforelse

    @if($equipo->ordenes->isNotEmpty())
        <h3 style="margin:28px 0 12px;">Órdenes vinculadas</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Orden</th><th>Tipo</th><th>Cliente</th><th>Estado</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    @foreach($equipo->ordenes as $orden)
                        <tr>
                            <td><a href="{{ route('servicio.ordenes.show', $orden) }}">{{ $orden->codigo() }}</a></td>
                            <td>{{ $orden->etiquetaTipoGestion() }}</td>
                            <td>{{ $orden->cliente_nombre }}</td>
                            <td>{{ $orden->etiquetaEstado() }}</td>
                            <td>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
