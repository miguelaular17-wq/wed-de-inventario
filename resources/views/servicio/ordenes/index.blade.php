@extends('layouts.app')

@section('title', !empty($soloInternas) ? 'Reparaciones internas' : 'Órdenes de servicio')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">{{ !empty($soloInternas) ? 'Reparaciones internas' : 'Servicio técnico' }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                @if(!empty($soloInternas))
                    Órdenes de reparación interna{{ $filtroSede ? ' · '.$filtroSede : '' }}.
                @else
                    Órdenes de taller{{ $filtroSede ? ' · '.$filtroSede : '' }}. El inventario de tienda no se toca desde aquí.
                @endif
            </p>
        </div>
        <a class="btn primary" href="{{ route('servicio.ordenes.create', !empty($soloInternas) ? ['tipo_gestion' => \App\Models\StOrden::TIPO_REPARACION_INTERNA] : []) }}">
            {{ !empty($soloInternas) ? 'Nueva reparación interna' : 'Nueva orden' }}
        </a>
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
        @if(empty($soloInternas))
        <div class="field">
            <label>Empresa de envío</label>
            <select name="empresa_envio">
                <option value="">Todas</option>
                @foreach($empresasEnvio as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(($filtroEmpresaEnvio ?? '') === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="field">
            <label>Técnico</label>
            <select name="tecnico_id">
                <option value="">Todos</option>
                @foreach($tecnicos as $tecnico)
                    <option value="{{ $tecnico->id }}" @selected((string) ($filtroTecnicoId ?? '') === (string) $tecnico->id)>{{ $tecnico->nombre }}</option>
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

    <div class="st-card-grid">
        @forelse($ordenes as $orden)
            @php
                $ev = is_array($orden->evidencias) ? $orden->evidencias : [];
                $evImgs = array_values(array_filter($ev['imagenes'] ?? []));
            @endphp
            <article class="st-card">
                <div class="st-card-fotos {{ count($evImgs) > 1 ? 'is-multi' : '' }}">
                    @forelse($evImgs as $i => $url)
                        <a href="{{ route('servicio.ordenes.evidencia', ['orden' => $orden, 'tipo' => 'img', 'index' => $i]) }}" target="_blank" rel="noopener">
                            <img
                                src="{{ route('servicio.ordenes.evidencia', ['orden' => $orden, 'tipo' => 'img', 'index' => $i]) }}"
                                alt="Foto {{ $i + 1 }} de {{ $orden->codigo() }}"
                                referrerpolicy="no-referrer"
                                loading="lazy"
                            >
                        </a>
                    @empty
                        <div class="st-card-sin-foto">Sin foto</div>
                    @endforelse
                </div>
                <div class="st-card-body">
                    <div class="st-card-top">
                        <div>
                            <a href="{{ route('servicio.ordenes.show', $orden) }}"><strong>{{ $orden->codigo() }}</strong></a>
                            @if($puedeFiltrarSede)
                                <div class="muted" style="font-size:.75rem;">{{ $orden->sede }}</div>
                            @endif
                        </div>
                        <span class="st-card-fecha">{{ $orden->fecha_ingreso?->format('d/m/Y') }}</span>
                    </div>
                    <dl class="st-card-datos">
                        <div>
                            <dt>Gestión</dt>
                            <dd>
                                {{ $orden->etiquetaTipoGestion() }}
                                @if($orden->etiquetaRangoGarantia())
                                    <span class="muted">{{ $orden->etiquetaRangoGarantia() }}</span>
                                @endif
                                @if($orden->esGarantia())
                                    <span style="color:#92400e;">{{ $orden->etiquetaEstadoGarantiaExterna() }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Cliente</dt>
                            <dd>
                                {{ $orden->cliente_nombre }}
                                @if($orden->cliente_telefono)
                                    <span class="muted">{{ $orden->cliente_telefono }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Equipo</dt>
                            <dd>
                                {{ $orden->equipo ?: '—' }}
                                @if($orden->serial)
                                    <span class="muted">{{ $orden->serial }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>
                                {{ $orden->etiquetaEstado() }}
                                @if($orden->excedePresupuesto()) <span title="Excede presupuesto">⚠️</span> @endif
                                @if($orden->transferenciaPendiente()) <span class="muted">· transferencia</span> @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Prioridad</dt>
                            <dd>{{ $orden->etiquetaPrioridad() }}</dd>
                        </div>
                        <div>
                            <dt>Creada por</dt>
                            <dd>{{ $orden->creador?->name ?: '—' }}</dd>
                        </div>
                    </dl>

                    @if(!$orden->esGarantia() && $orden->estadosPermitidos() !== [])
                        <details class="st-card-accion">
                            <summary>Cambiar estado</summary>
                            <form method="POST" action="{{ route('servicio.ordenes.cambiar_estado', $orden) }}">
                                @csrf
                                <select name="estado" required>
                                    <option value="">Nuevo estado…</option>
                                    @foreach($orden->estadosPermitidos() as $key => $label)
                                        @if($key !== \App\Models\StOrden::ESTADO_ENTREGADO || !$orden->excedePresupuesto())
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <textarea name="comentario_estado" required minlength="3" maxlength="1000" rows="2" placeholder="¿Por qué cambia el estado?"></textarea>
                                <button type="submit" class="btn secondary">Guardar estado</button>
                            </form>
                        </details>
                    @endif
                    @if($orden->esGarantia() && $orden->estadoGarantiaExternaActual() !== \App\Models\StOrden::GARANTIA_RECIBIDO)
                        @php $estadoGarantia = $orden->estadoGarantiaExternaActual(); @endphp
                        <details class="st-card-accion">
                            <summary>Gestionar envío de garantía</summary>
                            <form method="POST" action="{{ route('servicio.ordenes.garantia.estado', $orden) }}">
                                @csrf
                                <select name="estado_garantia" required>
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
                                    <select name="empresa" required>
                                        <option value="">Empresa destino…</option>
                                        @foreach(\App\Models\StOrden::EMPRESAS_ENVIO_GARANTIA as $codigo => $nombre)
                                            <option value="{{ $codigo }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                    <input name="motivo" required maxlength="255" placeholder="Motivo del envío, ej: Reparación">
                                @endif
                                <textarea name="comentario_garantia" required minlength="3" maxlength="2000" rows="2"
                                    placeholder="{{ $estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO ? 'Observación inicial de la garantía' : 'Motivo o actualización de la garantía' }}"></textarea>
                                <button type="submit" class="btn secondary">Guardar estado de garantía</button>
                            </form>
                        </details>
                    @endif

                    <div class="st-card-pie">
                        @unless($orden->garantiaExternaBloqueada())
                            <a class="btn secondary" href="{{ route('servicio.ordenes.edit', $orden) }}">Editar</a>
                        @else
                            <a class="btn secondary" href="{{ route('servicio.ordenes.show', $orden) }}">Ver flujo</a>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <p class="muted" style="grid-column:1/-1;">No hay órdenes con esos filtros.</p>
        @endforelse
    </div>
    {{ $ordenes->links() }}
</div>
@endsection
