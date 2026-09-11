@extends('layouts.app')

@section('title', 'Órdenes de servicio')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Servicio técnico</h1>
            <p class="muted" style="margin:4px 0 0;">
                Órdenes de taller{{ $filtroSede ? ' · '.$filtroSede : '' }}. El inventario de tienda no se toca desde aquí.
            </p>
        </div>
        <a class="btn primary" href="{{ route('servicio.ordenes.create') }}">Nueva orden</a>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        @if($puedeFiltrarSede)
            <div class="field">
                <label>Sede</label>
                <select name="sede">
                    <option value="">Todas</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede }}" @selected($filtroSede === $sede)>{{ $sede }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                @foreach($estados as $key => $label)
                    <option value="{{ $key }}" @selected(request('estado') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente, teléfono, equipo o serial">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Gestión</th>
                    <th>Cliente</th>
                    <th>Equipo</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Creada por</th>
                    <th>Ingreso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ordenes as $orden)
                    <tr>
                        <td>
                            <a href="{{ route('servicio.ordenes.show', $orden) }}"><strong>{{ $orden->codigo() }}</strong></a>
                            @if($puedeFiltrarSede)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->sede }}</div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $orden->etiquetaTipoGestion() }}</strong>
                            @if($orden->etiquetaRangoGarantia())
                                <div class="muted" style="font-size:.75rem;">{{ $orden->etiquetaRangoGarantia() }}</div>
                            @endif
                            @if($orden->esGarantia())
                                <div style="font-size:.75rem;color:#92400e;">{{ $orden->etiquetaEstadoGarantiaExterna() }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $orden->cliente_nombre }}
                            @if($orden->cliente_telefono)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->cliente_telefono }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $orden->equipo ?: '—' }}
                            @if($orden->serial)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->serial }}</div>
                            @endif
                        </td>
                        <td>{{ $orden->etiquetaEstado() }}
                            @if($orden->excedePresupuesto()) <span title="Excede presupuesto">⚠️</span> @endif
                            @if($orden->transferenciaPendiente()) <span class="muted" style="font-size:.75rem;">· transferencia</span> @endif
                            @if(!$orden->esGarantia() && $orden->estadosPermitidos() !== [])
                                <details style="margin-top:5px;min-width:210px;">
                                    <summary style="cursor:pointer;color:#2563eb;font-size:.78rem;">Cambiar estado</summary>
                                    <form method="POST" action="{{ route('servicio.ordenes.cambiar_estado', $orden) }}" style="margin-top:7px;display:grid;gap:6px;">
                                        @csrf
                                        <select name="estado" required style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;background:white;">
                                            <option value="">Nuevo estado…</option>
                                            @foreach($orden->estadosPermitidos() as $key => $label)
                                                @if($key !== \App\Models\StOrden::ESTADO_ENTREGADO || !$orden->excedePresupuesto())
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <textarea name="comentario_estado" required minlength="3" maxlength="1000" rows="2" placeholder="¿Por qué cambia el estado?" style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;"></textarea>
                                        <button type="submit" class="btn secondary" style="padding:5px 8px;">Guardar estado</button>
                                    </form>
                                </details>
                            @endif
                            @if($orden->esGarantia() && $orden->estadoGarantiaExternaActual() !== \App\Models\StOrden::GARANTIA_RECIBIDO)
                                @php $estadoGarantia = $orden->estadoGarantiaExternaActual(); @endphp
                                <details style="margin-top:5px;min-width:230px;">
                                    <summary style="cursor:pointer;color:#92400e;font-size:.78rem;">Gestionar envío de garantía</summary>
                                    <form method="POST" action="{{ route('servicio.ordenes.garantia.estado', $orden) }}" style="margin-top:7px;display:grid;gap:6px;">
                                        @csrf
                                        <select name="estado_garantia" required style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;background:white;">
                                            <option value="">Nuevo estado de envío…</option>
                                            @if($estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO)
                                                <option value="{{ \App\Models\StOrden::GARANTIA_ENVIADO }}">Enviado</option>
                                            @elseif($estadoGarantia === \App\Models\StOrden::GARANTIA_ENVIADO)
                                                <option value="{{ \App\Models\StOrden::GARANTIA_EN_PROCESO }}">En proceso</option>
                                                <option value="{{ \App\Models\StOrden::GARANTIA_RECIBIDO }}">Recibido</option>
                                            @elseif($estadoGarantia === \App\Models\StOrden::GARANTIA_EN_PROCESO)
                                                <option value="{{ \App\Models\StOrden::GARANTIA_RECIBIDO }}">Recibido</option>
                                            @endif
                                        </select>
                                        @if($estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO)
                                            <select name="empresa" required style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;background:white;">
                                                <option value="">Empresa destino…</option>
                                                @foreach(\App\Models\StOrden::EMPRESAS_ENVIO_GARANTIA as $empresa)
                                                    <option value="{{ $empresa }}">{{ $empresa }}</option>
                                                @endforeach
                                            </select>
                                            <input name="motivo" required maxlength="255" placeholder="Motivo del envío, ej: Reparación" style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;">
                                        @endif
                                        <textarea name="comentario_garantia" required minlength="3" maxlength="2000" rows="2"
                                            placeholder="{{ $estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO ? 'Observación inicial de la garantía' : 'Motivo o actualización de la garantía' }}"
                                            style="padding:6px;border:1px solid #cbd5e1;border-radius:5px;"></textarea>
                                        <button type="submit" class="btn secondary" style="padding:5px 8px;">Guardar estado de garantía</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td>{{ $orden->etiquetaPrioridad() }}</td>
                        <td>{{ $orden->creador?->name ?: '—' }}</td>
                        <td>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</td>
                        <td>
                            @unless($orden->garantiaExternaBloqueada())
                                <a class="btn secondary" href="{{ route('servicio.ordenes.edit', $orden) }}">Editar</a>
                            @else
                                <a class="btn secondary" href="{{ route('servicio.ordenes.show', $orden) }}">Ver flujo</a>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">No hay órdenes con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $ordenes->links() }}
    </div>
</div>
@endsection
