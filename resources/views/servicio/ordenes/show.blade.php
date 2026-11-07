@extends('layouts.app')

@section('title', $orden->codigo())

@php
    $ico = [
        'recepcion' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'conformidad' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/>',
        'recibido' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m9 12 2 2 4-4"/>',
        'editar' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'eliminar' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
        'cliente' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'equipo' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'servicio' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'historial' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'foto' => '<rect x="3" y="5" width="18" height="15" rx="2"/><circle cx="12" cy="12.5" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/>',
        'cambiar' => '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'auditoria' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'entregado' => '<path d="M20 6 9 17l-5-5"/>',
    ];
    $cerrada = $orden->entregada();
    $puedeEntregar = ! $cerrada && $orden->estado !== \App\Models\StOrden::ESTADO_CANCELADO && ! $orden->garantiaExternaBloqueada();
    $tipoEditable = ! $cerrada;
    $verAuditoria = (bool) (auth()->user()?->isGerente() || auth()->user()?->isAdmin());
    $conformidadEditable = ! $orden->garantiaExternaBloqueada() && ! $cerrada;
    $icono = fn (string $n, int $s = 16) => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ico[$n].'</svg>';
@endphp

@push('head')
<style>
.st-acciones { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.st-acciones form { margin:0; }
.st-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border-radius:9px; font-size:.86rem; font-weight:700; text-decoration:none; border:1px solid transparent; cursor:pointer; line-height:1.1; transition:background .15s, border-color .15s, color .15s; }
.st-btn svg { flex-shrink:0; }
.st-btn--primario { background:#1a4480; color:#fff; }
.st-btn--primario:hover { background:#153a6d; }
.st-btn--suave { background:#eef4fc; color:#1a4480; border-color:#c9daf3; }
.st-btn--suave:hover { background:#dbe7f8; }
.st-btn--ok { background:#15803d; color:#fff; }
.st-btn--ok:hover { background:#166534; }
.st-btn--peligro { background:#fff; color:#b91c1c; border-color:#fecaca; }
.st-btn--peligro:hover { background:#fef2f2; border-color:#fca5a5; }
.st-btn--chico { padding:6px 11px; font-size:.8rem; }

.st-ficha { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.st-ficha-cab { display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; padding:18px 22px; background:linear-gradient(135deg, #f8fbff, #eef4fc); border-bottom:1px solid #e2e8f0; }
.st-ficha-equipo { display:flex; align-items:center; gap:12px; min-width:0; }
.st-ficha-equipo .st-avatar { width:44px; height:44px; border-radius:12px; background:#1a4480; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.st-ficha-equipo h3 { margin:0; font-size:1.1rem; color:#0f172a; }
.st-ficha-equipo p { margin:2px 0 0; font-size:.8rem; color:#64748b; font-variant-numeric:tabular-nums; }
.st-chips { display:flex; gap:6px; flex-wrap:wrap; }
.st-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:.75rem; font-weight:700; background:#fff; border:1px solid #e2e8f0; color:#334155; }
.st-chip--valor { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
.st-chip--aviso { background:#fffbeb; border-color:#fde68a; color:#92400e; }
.st-bloques { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:0; }
.st-bloque { padding:18px 22px; border-right:1px solid #f1f5f9; }
.st-bloque:last-child { border-right:0; }
.st-bloque h4 { display:flex; align-items:center; gap:7px; margin:0 0 12px; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#1a4480; }
.st-dato { margin-bottom:11px; }
.st-dato:last-child { margin-bottom:0; }
.st-dato span { display:block; font-size:.72rem; color:#64748b; margin-bottom:1px; }
.st-dato div { font-size:.92rem; color:#0f172a; word-break:break-word; }
.st-dato a { color:#1d4ed8; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
.st-dato a:hover { text-decoration:underline; }
.st-nota { margin:0 22px 16px; padding:10px 12px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0; font-size:.84rem; color:#334155; }
.st-nota--garantia { background:#fffbeb; border-color:#fde68a; color:#78350f; }
.st-evidencias { padding:16px 22px 20px; border-top:1px solid #e2e8f0; }

.st-revision { padding:18px 22px 22px; border-top:1px solid #e2e8f0; display:grid; gap:16px; }
.st-revision-textos { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:12px; }
.st-caja { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; }
.st-caja span { display:block; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:4px; }
.st-caja div { font-size:.95rem; color:#0f172a; white-space:pre-line; }
.st-caja--falla { background:#fff7ed; border-color:#fed7aa; }
.st-caja--falla span { color:#c2410c; }
.st-checklist-cab { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
.st-checklist-cab h4 { margin:0; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#1a4480; }
.st-chip--falla { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
.st-checklist-items { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:8px; }
.st-check { display:flex; align-items:center; gap:9px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:9px; background:#fff; font-size:.85rem; }
.st-check-icono { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; flex-shrink:0; background:#f1f5f9; color:#64748b; }
.st-check-nombre { flex:1; color:#334155; }
.st-check-estado { font-size:.75rem; font-weight:800; color:#64748b; white-space:nowrap; }
.st-check--ok .st-check-icono { background:#dcfce7; color:#15803d; }
.st-check--ok .st-check-estado { color:#15803d; }
.st-check--dano { border-color:#fecaca; background:#fef2f2; }
.st-check--dano .st-check-icono { background:#dc2626; color:#fff; }
.st-check--dano .st-check-estado { color:#b91c1c; }

.st-cerrada { display:flex; gap:12px; align-items:flex-start; background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #15803d; border-radius:12px; padding:12px 16px; margin-bottom:16px; font-size:.86rem; color:#14532d; }
.st-cerrada strong { display:block; font-size:.95rem; margin-bottom:2px; }
.st-cerrada-icono { width:32px; height:32px; border-radius:50%; background:#15803d; color:#fff; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }

.st-plegable { background:#fff; border:1px solid #e2e8f0; border-radius:14px; margin-bottom:16px; overflow:hidden; }
.st-plegable > summary { list-style:none; display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 22px; cursor:pointer; user-select:none; }
.st-plegable > summary::-webkit-details-marker { display:none; }
.st-plegable > summary:hover { background:#f8fafc; }
.st-plegable-titulo { display:inline-flex; align-items:center; gap:8px; font-weight:800; color:#0f172a; }
.st-plegable-estado { font-size:.85rem; color:#475569; flex:1; }
.st-plegable-flecha { color:#94a3b8; transition:transform .15s; }
.st-plegable[open] .st-plegable-flecha { transform:rotate(180deg); }
.st-plegable-cuerpo { padding:4px 22px 20px; border-top:1px solid #f1f5f9; padding-top:16px; }

.st-form-tipo { display:grid; gap:12px; }
.st-form-tipo label { display:grid; gap:4px; font-weight:600; font-size:.86rem; color:#0f172a; }
.st-form-tipo select, .st-form-tipo input, .st-form-tipo textarea { width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:.9rem; font-family:inherit; font-weight:400; background:#fff; }
.st-form-tipo [data-solo] { display:grid; gap:12px; }
.st-form-fila { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; }

.st-audit { padding:10px 0; border-bottom:1px solid #f1f5f9; }
.st-audit:last-child { border-bottom:0; }
.st-audit-cab { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:.84rem; }
.st-audit-cab .muted { font-size:.76rem; }
.st-audit-accion { font-size:.68rem; font-weight:800; text-transform:uppercase; padding:2px 8px; border-radius:999px; background:#e2e8f0; color:#334155; }
.st-audit-accion--creado { background:#dcfce7; color:#166534; }
.st-audit-accion--actualizado { background:#dbeafe; color:#1e40af; }
.st-audit-accion--eliminado { background:#fee2e2; color:#b91c1c; }
.st-audit-motivo { margin-top:4px; font-size:.8rem; color:#92400e; }
.st-audit-tabla { margin-top:6px; border-collapse:collapse; font-size:.8rem; width:100%; }
.st-audit-tabla td { padding:3px 6px; vertical-align:top; }
.st-audit-tabla td:first-child { color:#64748b; white-space:nowrap; width:1%; text-transform:capitalize; }
.st-audit-tabla td:nth-child(3) { color:#94a3b8; width:1%; }
.st-audit-tabla .antes { color:#b91c1c; text-decoration:line-through; word-break:break-word; }
.st-audit-tabla .despues { color:#15803d; word-break:break-word; }

.st-modal-fondo { display:none; position:fixed; inset:0; z-index:2000; background:rgba(15, 23, 42, .6); align-items:center; justify-content:center; padding:20px; }
.st-modal-fondo.abierto { display:flex; }
.st-modal { background:#fff; border-radius:16px; width:min(760px, 100%); max-height:calc(100vh - 40px); display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.3); }
.st-modal form { display:flex; flex-direction:column; min-height:0; flex:1; }
.st-modal-cab { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:18px 22px 14px; border-bottom:1px solid #f1f5f9; }
.st-modal-cab h3 { margin:0; font-size:1.12rem; display:flex; align-items:center; gap:8px; color:#0f172a; }
.st-modal-cab p { margin:4px 0 0; font-size:.82rem; color:#64748b; }
.st-modal-cerrar { border:0; background:transparent; font-size:1.6rem; line-height:1; color:#94a3b8; cursor:pointer; }
.st-modal-cerrar:hover { color:#0f172a; }
.st-modal-cuerpo { padding:16px 22px; overflow-y:auto; flex:1; min-height:0; }
.st-modal-pie { display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; padding:12px 22px 18px; border-top:1px solid #f1f5f9; }
</style>
@endpush

@section('content')
<div style="padding:20px;max-width:960px;margin:0 auto;">
    <a href="{{ route('servicio.ordenes.index') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Órdenes</a>
    @if($errors->any())
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:8px;margin:12px 0;">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    <div class="panel-header-flex" style="margin:10px 0 16px;">
        <div>
            <h2 style="font-weight:700;margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                {{ $orden->codigo() }}
                <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:700;
                    background:{{ $orden->esGarantia() ? '#fef3c7' : '#dbeafe' }};
                    color:{{ $orden->esGarantia() ? '#92400e' : '#1e40af' }};">
                    {{ $orden->etiquetaTipoGestion() }}
                </span>
                @if($orden->excedePresupuesto())
                    <span title="Costos superan el presupuesto">⚠️</span>
                @endif
            </h2>
            <p class="muted" style="margin:4px 0 0;">
                {{ $orden->sede }} · {{ $orden->etiquetaEstado() }} · {{ $orden->etiquetaPrioridad() }}
                · Creada por {{ $orden->creador?->name ?: '—' }}
                @if($orden->transferenciaPendiente())
                    · <span style="color:#b45309;">Transferencia pendiente desde {{ $orden->sede_origen_transfer }}</span>
                @elseif($orden->sede_origen_transfer)
                    · <span style="color:#15803d;">Recibida de {{ $orden->sede_origen_transfer }}</span>
                @endif
            </p>
        </div>
        <div class="st-acciones">
            <a class="st-btn st-btn--suave" target="_blank" href="{{ route('servicio.ordenes.recepcion_pdf', $orden) }}" title="Abrir la hoja de recepción en PDF">
                {!! $icono('recepcion') !!} Hoja de recepción
            </a>
            @if($conformidadEditable)
                <button type="button" class="st-btn st-btn--suave" data-abrir-conformidad title="Anotar el trabajo realizado y las firmas, y sacar la hoja">
                    {!! $icono('conformidad') !!} Hoja de conformidad
                </button>
            @else
                <a class="st-btn st-btn--suave" target="_blank" href="{{ route('servicio.ordenes.conformidad_pdf', $orden) }}" title="Abrir la hoja de conformidad en PDF">
                    {!! $icono('conformidad') !!} Hoja de conformidad
                </a>
            @endif
            @if(! $cerrada && $orden->puedeConfirmarRecepcion(auth()->user()))
                <form method="POST" action="{{ route('servicio.ordenes.confirmar_recepcion', $orden) }}">
                    @csrf
                    <button class="st-btn st-btn--ok" type="submit">{!! $icono('recibido') !!} Marcar recibido</button>
                </form>
            @endif
            @unless($orden->garantiaExternaBloqueada() || $cerrada)
                <a class="st-btn st-btn--primario" href="{{ route('servicio.ordenes.edit', $orden) }}">{!! $icono('editar') !!} Editar</a>
            @endunless
            @if($tipoEditable)
                <button type="button" class="st-btn st-btn--suave" data-abrir-tipo title="Pasar a servicio técnico, garantía o reparación interna">
                    {!! $icono('cambiar') !!} Cambiar tipo
                </button>
            @endif
            @unless($orden->garantiaExternaBloqueada() || $orden->repuestos_descontados_at || $cerrada)
                <form method="POST" action="{{ route('servicio.ordenes.destroy', $orden) }}"
                    onsubmit="return confirm('¿Eliminar {{ $orden->esGarantia() ? 'esta garantía' : 'este ST' }} {{ $orden->codigo() }}? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button class="st-btn st-btn--peligro" type="submit">
                        {!! $icono('eliminar') !!} {{ $orden->esGarantia() ? 'Eliminar garantía' : 'Eliminar ST' }}
                    </button>
                </form>
            @endunless
        </div>
    </div>

    @if($puedeEntregar)
        <form method="POST" action="{{ route('servicio.ordenes.entregar', $orden) }}" id="st-form-entregar" style="display:none;"
              onsubmit="return confirm(@js('¿Marcar '.$orden->codigo().' como entregada al cliente?'
                  .($orden->tieneConformidad() ? '' : ' Se entrega sin hoja de conformidad firmada.')
                  .($orden->excedePresupuesto() ? ' Los costos superan el presupuesto: confirmas que el cliente aceptó el monto.' : '')
                  .' Después la orden queda cerrada y no se podrá modificar.'));">
            @csrf
            @if($orden->excedePresupuesto())<input type="hidden" name="confirmar_exceso" value="1">@endif
        </form>
    @endif

    @if($cerrada)
        <div class="st-cerrada">
            <span class="st-cerrada-icono">{!! $icono('entregado', 18) !!}</span>
            <div>
                <strong>Entregada al cliente · orden cerrada</strong>
                <div>
                    {{ $orden->tieneConformidad() ? 'Con hoja de conformidad firmada el '.$orden->conformidad_at?->format('d/m/Y H:i').'.' : 'Se entregó sin hoja de conformidad firmada.' }}
                    Ya no se puede editar, cambiar de tipo, transferir ni eliminar.
                </div>
            </div>
        </div>
    @endif

    @if($orden->excedePresupuesto())
        <div style="background:#fffbeb;border:1px solid #fcd34d;padding:12px 16px;border-radius:8px;margin-bottom:16px;color:#92400e;">
            Los costos (${{ number_format($orden->costoTotal(), 2) }}) superan el presupuesto (${{ number_format($orden->presupuesto, 2) }}). Confirma con el cliente antes de entregar.
        </div>
    @endif

    @if($orden->esGarantia())
        @php
            $estadoGarantia = $orden->estadoGarantiaExternaActual();
            $garantiaConErrores = $errors->hasAny(['empresa', 'motivo', 'observacion', 'comentario', 'tipo']);
        @endphp
        <details class="st-plegable" @if($garantiaConErrores) open @endif>
            <summary>
                <span class="st-plegable-titulo">{!! $icono('servicio', 16) !!} Flujo externo de garantía</span>
                <span class="st-plegable-estado">
                    <strong>{{ $orden->etiquetaEstadoGarantiaExterna() }}</strong>
                    @if($orden->empresa_envio_garantia) · {{ $orden->etiquetaEmpresaEnvioGarantia() }} @endif
                </span>
                <span class="st-plegable-flecha" aria-hidden="true">▾</span>
            </summary>
            <div class="st-plegable-cuerpo">
            @if($orden->garantia_enviado_at)
                <p class="muted" style="margin:0 0 14px;font-size:.82rem;">
                    Enviado el {{ $orden->garantia_enviado_at->format('d/m/Y h:i A') }}
                    por {{ $orden->enviadoPorGarantia?->name ?: '—' }}
                </p>
            @endif

            @if($cerrada)
                <div class="muted">La orden ya se entregó al cliente: el flujo de garantía queda cerrado.</div>
            @elseif($estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO)
                <form method="POST" action="{{ route('servicio.ordenes.garantia.enviar', $orden) }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-weight:500;margin-bottom:4px;">Empresa destino *</label>
                            <select name="empresa" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                                <option value="">Seleccione…</option>
                                @foreach(\App\Models\StOrden::EMPRESAS_ENVIO_GARANTIA as $codigo => $nombre)
                                    <option value="{{ $codigo }}" @selected(old('empresa') === $codigo)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-weight:500;margin-bottom:4px;">Motivo *</label>
                            <input name="motivo" required maxlength="255" value="{{ old('motivo', 'Reparación') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
                        </div>
                    </div>
                    <label style="display:block;font-weight:500;margin-bottom:4px;">Observación inicial *</label>
                    <textarea name="observacion" required minlength="3" maxlength="2000" rows="3" placeholder="Ej: Equipo no enciende" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('observacion') }}</textarea>
                    <button class="btn primary" type="submit" style="margin-top:10px;">Enviar a empresa</button>
                </form>
            @elseif(in_array($estadoGarantia, [\App\Models\StOrden::GARANTIA_ENVIADO, \App\Models\StOrden::GARANTIA_EN_PROCESO], true))
                <div style="background:#fffbeb;border:1px solid #fcd34d;padding:10px 12px;border-radius:7px;margin-bottom:14px;">
                    El equipo está fuera. La información principal permanece bloqueada hasta registrar su recepción.
                </div>
                <form method="POST" action="{{ route('servicio.ordenes.garantia.actualizacion', $orden) }}" style="margin-bottom:14px;">
                    @csrf
                    <div style="display:grid;grid-template-columns:160px 1fr;gap:8px;">
                        <select name="tipo" required style="padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                            <option value="avance">Avance</option>
                            <option value="comentario">Comentario</option>
                        </select>
                        <textarea name="comentario" required minlength="3" maxlength="2000" rows="2" placeholder="Actualización recibida de la empresa…" style="padding:8px;border:1px solid #ccc;border-radius:6px;"></textarea>
                    </div>
                    <button class="btn secondary" type="submit" style="margin-top:8px;">Agregar a la bitácora</button>
                </form>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    @if($estadoGarantia === \App\Models\StOrden::GARANTIA_ENVIADO)
                        <form method="POST" action="{{ route('servicio.ordenes.garantia.en_proceso', $orden) }}">
                            @csrf
                            <input name="comentario" required minlength="3" maxlength="1000" placeholder="Detalle del diagnóstico" style="padding:8px;border:1px solid #ccc;border-radius:6px;">
                            <button class="btn primary" type="submit">Marcar en proceso</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('servicio.ordenes.garantia.recibir', $orden) }}">
                        @csrf
                        <input name="comentario" maxlength="2000" placeholder="Observación de recepción" style="padding:8px;border:1px solid #ccc;border-radius:6px;">
                        <button class="btn primary" type="submit">Registrar recibido</button>
                    </form>
                </div>
            @else
                <div>
                    Recibido en {{ $orden->sede }} el {{ $orden->garantia_recibido_at?->format('d/m/Y h:i A') ?: '—' }}.
                    Recibido por: {{ $orden->recibidoPorGarantia?->name ?: '—' }}.
                </div>
            @endif
            </div>
        </details>
    @endif

    @php
        $sinIdentidad = (bool) $orden->atributo('identidad_inaccesible');
        $identificador = $orden->imei ? 'IMEI '.$orden->imei : ($orden->serial ? 'Serial '.$orden->serial : ($sinIdentidad ? 'IMEI/serial no accesible' : 'Sin IMEI ni serial'));
        $conCliente = ! ($orden->esCambioEnRango() || $orden->esReparacionInterna());
    @endphp
    <div class="st-ficha">
        <div class="st-ficha-cab">
            <div class="st-ficha-equipo">
                <div class="st-avatar">{!! $icono('equipo', 22) !!}</div>
                <div>
                    <h3>{{ $orden->equipo ?: ($orden->atributo('descripcion_equipo') ?: 'Equipo') }}</h3>
                    <p>{{ $identificador }}</p>
                </div>
            </div>
            <div class="st-chips">
                <span class="st-chip">{{ $orden->etiquetaTipoDispositivo() }}</span>
                @if($orden->valor_dispositivo !== null)
                    <span class="st-chip st-chip--valor">${{ number_format((float) $orden->valor_dispositivo, 2) }}</span>
                @endif
                @if($orden->repuestos_descontados_at)
                    <span class="st-chip st-chip--aviso" title="Repuestos descontados el {{ $orden->repuestos_descontados_at->format('d/m/Y H:i') }}">Repuestos descontados</span>
                @endif
            </div>
        </div>

        @if($orden->esGarantia() && ! $orden->esCambioEnRango())
            <div class="st-nota st-nota--garantia" style="margin-top:16px;">
                <strong>Garantía con la marca ({{ $orden->marcaEquipo() }}).</strong> La empresa es intermediaria; el equipo pertenece al cliente.
            </div>
        @endif
        @if($sinIdentidad)
            <div class="st-nota" style="margin-top:{{ $orden->esGarantia() && ! $orden->esCambioEnRango() ? '0' : '16px' }};">
                IMEI/serial no disponibles al ingreso: no se pudo acceder a la información del equipo.
            </div>
        @endif

        <div class="st-bloques">
            @if($conCliente)
                <div class="st-bloque">
                    <h4>{!! $icono('cliente', 14) !!} Cliente</h4>
                    <div class="st-dato"><span>Nombre</span><div><strong>{{ $orden->cliente_nombre }}</strong></div></div>
                    <div class="st-dato"><span>Teléfono</span><div>{{ $orden->cliente_telefono ?: '—' }}</div></div>
                </div>
            @endif

            <div class="st-bloque">
                <h4>{!! $icono('equipo', 14) !!} Equipo</h4>
                <div class="st-dato"><span>Equipo</span><div>{{ $orden->equipo ?: '—' }}</div></div>
                @if($orden->atributo('descripcion_equipo'))
                    <div class="st-dato"><span>Descripción</span><div>{{ $orden->atributo('descripcion_equipo') }}</div></div>
                @endif
                <div class="st-dato"><span>Valor del dispositivo</span><div><strong>{{ $orden->valor_dispositivo !== null ? '$'.number_format((float) $orden->valor_dispositivo, 2) : '—' }}</strong></div></div>
                <div class="st-dato"><span>IMEI</span><div>{{ $orden->imei ?: ($sinIdentidad ? 'No accesible' : '—') }}</div></div>
                <div class="st-dato"><span>Serial</span><div>{{ $orden->serial ?: ($sinIdentidad ? 'No accesible' : '—') }}</div></div>
                @if($orden->atributo('almacenamiento'))
                    <div class="st-dato"><span>Almacenamiento</span><div>{{ $orden->atributo('almacenamiento') }}</div></div>
                @endif
                @if($orden->atributo('tipo_impresora'))
                    <div class="st-dato"><span>Tipo de impresora</span><div>{{ config('servicio_tecnico.tipos_impresora.'.$orden->atributo('tipo_impresora'), $orden->atributo('tipo_impresora')) }}</div></div>
                @endif
                @if($orden->atributo('serial_lente'))
                    <div class="st-dato"><span>Serial del lente</span><div>{{ $orden->atributo('serial_lente') }}</div></div>
                @endif
                @if($orden->atributo('codigo_lote'))
                    <div class="st-dato"><span>Código de lote</span><div>{{ $orden->atributo('codigo_lote') }}</div></div>
                @endif
                <div class="st-dato"><span>Accesorios</span><div>{{ $orden->accesorios ?: '—' }}</div></div>
                @if($orden->equipo_id)
                    <div class="st-dato"><span>Bitácora</span><div><a href="{{ route('servicio.celulares.show', $orden->equipo_id) }}">{!! $icono('historial', 14) !!} Ver historial del equipo</a></div></div>
                @endif
            </div>

            <div class="st-bloque">
                <h4>{!! $icono('servicio', 14) !!} Servicio</h4>
                <div class="st-dato">
                    <span>Gestión</span>
                    <div><strong>{{ $orden->etiquetaTipoGestion() }}</strong>{{ $orden->etiquetaRangoGarantia() ? ' · '.$orden->etiquetaRangoGarantia() : '' }}</div>
                </div>
                <div class="st-dato"><span>Técnico</span><div>{{ $orden->tecnico?->name ?: '—' }}</div></div>
                <div class="st-dato"><span>Creada por</span><div>{{ $orden->creador?->name ?: '—' }}</div></div>
                <div class="st-dato"><span>Ingreso</span><div>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</div></div>
                @unless($orden->esCambioEnRango())
                    <div class="st-dato"><span>Prometida</span><div>{{ $orden->fecha_prometida?->format('d/m/Y') ?: '—' }}</div></div>
                @endunless
                @if($orden->esGarantia())
                    <div class="st-dato"><span>Empresa responsable del envío</span><div>{{ $orden->etiquetaEmpresaEnvioGarantia() ?: '—' }}</div></div>
                @endif
                @if($orden->esReparacionInterna())
                    <div class="st-dato"><span>Resultado de la reparación</span><div>{{ $orden->etiquetaResultadoInterna() ?: 'Sin estado' }}</div></div>
                @endif
                <div class="st-dato"><span>Repuestos descontados</span><div>{{ $orden->repuestos_descontados_at ? $orden->repuestos_descontados_at->format('d/m/Y H:i') : 'No' }}</div></div>
                <div class="st-dato"><span>Conformidad</span>
                    <div>
                        @if($orden->tieneConformidad())
                            <span style="color:#15803d;font-weight:700;">✓ Firmada</span> el {{ $orden->conformidad_at?->format('d/m/Y H:i') ?: '—' }}
                        @else
                            Pendiente
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @php
            $ev = is_array($orden->evidencias) ? $orden->evidencias : [];
            $evImgs = array_values(array_filter($ev['imagenes'] ?? []));
            $evVideo = $ev['video'] ?? null;
        @endphp
        <div class="st-evidencias">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                <h3 style="margin:0;font-size:1rem;display:flex;align-items:center;gap:7px;">{!! $icono('foto', 16) !!} Evidencias</h3>
                @unless($orden->garantiaExternaBloqueada() || $cerrada)
                    <a class="st-btn st-btn--suave st-btn--chico" href="{{ route('servicio.ordenes.edit', $orden) }}#st-evidencias-edit">{!! $icono('foto', 14) !!} Editar fotos / video</a>
                @endunless
            </div>
            @if($evImgs !== [] || $evVideo)
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    @foreach($evImgs as $i => $url)
                        <a href="{{ route('servicio.ordenes.evidencia', ['orden' => $orden, 'tipo' => 'img', 'index' => $i]) }}" target="_blank" rel="noopener"
                            style="display:block;width:160px;height:160px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;background:#f8fafc;">
                            <img
                                src="{{ route('servicio.ordenes.evidencia', ['orden' => $orden, 'tipo' => 'img', 'index' => $i, 'thumb' => 1]) }}"
                                alt="Evidencia {{ $i + 1 }}"
                                referrerpolicy="no-referrer"
                                style="width:100%;height:100%;object-fit:cover;"
                                onerror="this.style.display='none'; this.parentElement.insertAdjacentHTML('beforeend','<span style=padding:8px;font-size:12px;color:#b91c1c;display:block>No se pudo cargar</span>');"
                            >
                        </a>
                    @endforeach
                </div>
                @if($evVideo)
                    <div style="margin-top:12px;max-width:480px;">
                        <video
                            src="{{ route('servicio.ordenes.evidencia', ['orden' => $orden, 'tipo' => 'video']) }}"
                            controls
                            referrerpolicy="no-referrer"
                            style="width:100%;border-radius:8px;background:#0f172a;"
                        ></video>
                    </div>
                @endif
            @else
                <p class="muted" style="margin:0 0 10px;">Sin fotos ni video todavía.</p>
            @endif

            @unless($orden->garantiaExternaBloqueada() || $cerrada)
                <form method="POST" action="{{ route('servicio.ordenes.evidencias', $orden) }}" enctype="multipart/form-data" style="margin-top:14px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                    @csrf
                    <p style="margin:0 0 8px;font-size:.88rem;font-weight:600;">Agregar o reemplazar evidencias</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div>
                            <label style="display:block;font-size:.82rem;margin-bottom:4px;">Fotos (máx. 3)</label>
                            <input type="file" name="evidencia_imagenes[]" accept="image/*" capture="environment" multiple style="width:100%;font-size:.82rem;">
                        </div>
                        <div>
                            <label style="display:block;font-size:.82rem;margin-bottom:4px;">Video</label>
                            <input type="file" name="evidencia_video" accept="video/*" capture="environment" style="width:100%;font-size:.82rem;">
                        </div>
                    </div>
                    @if($evImgs !== [])
                        <label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:.82rem;">
                            <input type="checkbox" name="quitar_evidencia_imagenes" value="1"> Quitar fotos actuales
                        </label>
                    @endif
                    @if($evVideo)
                        <label style="display:flex;align-items:center;gap:6px;margin-top:4px;font-size:.82rem;">
                            <input type="checkbox" name="quitar_evidencia_video" value="1"> Quitar video actual
                        </label>
                    @endif
                    <p class="muted" style="margin:8px 0 0;font-size:.78rem;">Si subes fotos nuevas, reemplazan las actuales.</p>
                    <button class="btn primary" type="submit" style="margin-top:10px;">Guardar evidencias</button>
                </form>
            @endunless
        </div>
        <div class="st-revision">
            <div class="st-revision-textos">
                <div class="st-caja st-caja--falla">
                    <span>Falla reportada</span>
                    <div>{{ $orden->falla ?: '—' }}</div>
                </div>
                <div class="st-caja">
                    <span>Diagnóstico</span>
                    <div>{{ $orden->diagnostico ?: 'Sin diagnóstico todavía.' }}</div>
                </div>
            </div>
            @if($items = $orden->itemsInspeccionRecepcion())
                @php
                    $conteo = collect($items)->countBy('estado');
                @endphp
                <div class="st-checklist">
                    <div class="st-checklist-cab">
                        <h4>Checklist de recepción</h4>
                        <div class="st-chips">
                            @if($conteo->get('ok'))<span class="st-chip st-chip--valor">{{ $conteo->get('ok') }} OK</span>@endif
                            @if($conteo->get('dano'))<span class="st-chip st-chip--falla">{{ $conteo->get('dano') }} con daño</span>@endif
                            @if($conteo->get('na'))<span class="st-chip">{{ $conteo->get('na') }} N/A</span>@endif
                        </div>
                    </div>
                    <div class="st-checklist-items">
                        @foreach($items as $item)
                            <div class="st-check st-check--{{ in_array($item['estado'], ['ok', 'dano', 'na'], true) ? $item['estado'] : 'vacio' }}">
                                <span class="st-check-icono" aria-hidden="true">{{ match ($item['estado']) { 'ok' => '✓', 'dano' => '!', 'na' => '–', default => '·' } }}</span>
                                <span class="st-check-nombre">{{ $item['etiqueta'] }}</span>
                                <span class="st-check-estado">{{ $orden->etiquetaEstadoInspeccion($item['estado']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($orden->backups->isNotEmpty())
        <div class="panel" style="padding:24px;margin-bottom:16px;">
            <h3 style="margin:0 0 12px;">Celular de backup</h3>
            @foreach($orden->backups as $backup)
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                    <div>
                        <strong>{{ trim($backup->marca.' '.$backup->modelo) ?: 'Backup' }}</strong>
                        <div class="muted" style="font-size:.85rem;">IMEI {{ $backup->imei ?: '—' }} · Serial {{ $backup->serial ?: '—' }}</div>
                    </div>
                    <a class="btn secondary" target="_blank" href="{{ route('servicio.ordenes.recepcion_pdf', $orden) }}">Hoja de recepción (con backup)</a>
                </div>
            @endforeach
        </div>
    @endif

    @if($verAuditoria)
        @php $auditorias = $orden->auditorias()->limit(200)->get(); @endphp
        <details class="st-plegable">
            <summary>
                <span class="st-plegable-titulo">{!! $icono('auditoria', 16) !!} Auditoría de cambios</span>
                <span class="st-plegable-estado">{{ $auditorias->count() === 1 ? '1 registro' : $auditorias->count().' registros' }} · quién cambió qué y cuándo</span>
                <span class="st-plegable-flecha" aria-hidden="true">▾</span>
            </summary>
            <div class="st-plegable-cuerpo">
                @forelse($auditorias as $a)
                    <div class="st-audit">
                        <div class="st-audit-cab">
                            <span class="st-audit-accion st-audit-accion--{{ $a->accion }}">{{ ucfirst($a->accion) }}</span>
                            <strong>{{ $a->user_nombre ?: 'Sistema' }}</strong>
                            <span class="muted">{{ $a->created_at?->format('d/m/Y H:i:s') }} · {{ $a->ruta }}{{ $a->ip ? ' · '.$a->ip : '' }}</span>
                        </div>
                        @if($a->motivo)<div class="st-audit-motivo">Motivo: {{ $a->motivo }}</div>@endif
                        @if($a->accion !== \App\Models\Auditoria::CREADO)
                            <table class="st-audit-tabla">
                                @foreach(($a->cambios ?? []) as $campo => [$antes, $despues])
                                    <tr>
                                        <td>{{ str_replace('_', ' ', $campo) }}</td>
                                        <td class="antes">{{ is_scalar($antes) || $antes === null ? ($antes ?? '—') : json_encode($antes, JSON_UNESCAPED_UNICODE) }}</td>
                                        <td>→</td>
                                        <td class="despues">{{ is_scalar($despues) || $despues === null ? ($despues ?? '—') : json_encode($despues, JSON_UNESCAPED_UNICODE) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </div>
                @empty
                    <p class="muted" style="margin:0;">Sin cambios auditados todavía. Los cambios se registran a partir de hoy.</p>
                @endforelse
            </div>
        </details>
    @endif

    @if($tipoEditable)
    <div class="st-modal-fondo" id="st-modal-tipo" role="dialog" aria-modal="true" aria-labelledby="st-tipo-titulo"
         @if($errors->hasAny(['tipo_gestion', 'rango_garantia', 'motivo_cambio']) || old('motivo_cambio')) data-abrir="1" @endif>
        @php
            $clienteReal = ! ($orden->esReparacionInterna() || $orden->esCambioEnRango());
            $tipoActual = $orden->tipo_gestion ?? \App\Models\StOrden::TIPO_ST;
        @endphp
        <div class="st-modal" style="width:min(560px, 100%);">
            <div class="st-modal-cab">
                <div>
                    <h3 id="st-tipo-titulo">{!! $icono('cambiar', 18) !!} Cambiar tipo de gestión</h3>
                    <p>Ahora es <strong>{{ $orden->etiquetaTipoGestion() }}{{ $orden->etiquetaRangoGarantia() ? ' · '.$orden->etiquetaRangoGarantia() : '' }}</strong>. El cambio queda en la auditoría.</p>
                </div>
                <button type="button" class="st-modal-cerrar" data-cerrar-tipo aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" action="{{ route('servicio.ordenes.tipo_gestion', $orden) }}">
                @csrf
                <div class="st-modal-cuerpo st-form-tipo">
                    <label>Nuevo tipo *
                        <select name="tipo_gestion" id="st-tipo-gestion" required>
                            @foreach(\App\Models\StOrden::TIPOS_GESTION as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('tipo_gestion', $tipoActual) === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div data-solo="GARANTIA">
                        <label>Rango de la garantía *
                            <select name="rango_garantia" id="st-tipo-rango">
                                <option value="">Seleccione…</option>
                                @foreach(\App\Models\StOrden::RANGOS_GARANTIA as $clave => $nombre)
                                    <option value="{{ $clave }}" @selected(old('rango_garantia', $orden->rango_garantia) === $clave)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Empresa del envío
                            <select name="empresa_envio_garantia">
                                <option value="">Sin definir todavía</option>
                                @foreach(\App\Models\StOrden::EMPRESAS_ENVIO_GARANTIA as $clave => $nombre)
                                    <option value="{{ $clave }}" @selected(old('empresa_envio_garantia', $orden->empresa_envio_garantia) === $clave)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div data-solo="CLIENTE">
                        <div class="st-form-fila">
                            <label>Cliente *
                                <input name="cliente_nombre" maxlength="255" value="{{ old('cliente_nombre', $clienteReal ? $orden->cliente_nombre : '') }}">
                            </label>
                            <label>Teléfono
                                <input name="cliente_telefono" maxlength="40" value="{{ old('cliente_telefono', $clienteReal ? $orden->cliente_telefono : '') }}">
                            </label>
                        </div>
                        <label>Cédula <span class="muted" style="font-weight:400;">(obligatoria en garantía fuera de rango)</span>
                            <input name="cliente_cedula" maxlength="40" value="{{ old('cliente_cedula', $clienteReal ? $orden->cliente_cedula : '') }}">
                        </label>
                    </div>
                    @if($orden->garantiaExternaBloqueada())
                        <p class="st-nota st-nota--garantia" data-solo="SALE" style="margin:0;">
                            El equipo figura <strong>{{ mb_strtolower($orden->etiquetaEstadoGarantiaExterna()) }}</strong>{{ $orden->etiquetaEmpresaEnvioGarantia() ? ' a '.$orden->etiquetaEmpresaEnvioGarantia() : '' }}.
                            Si pasa a otro tipo, se registra como recibido en {{ $orden->sede }}.
                        </p>
                    @endif
                    <p class="st-nota" data-solo="INTERNA" style="margin:0;">La reparación interna no lleva cliente: se quitan nombre, teléfono, cédula y fecha prometida.</p>
                    <p class="st-nota" data-solo="DENTRO" style="margin:0;">Dentro del rango el equipo es de la empresa: se quitan los datos del cliente.</p>
                    <label>Motivo del cambio *
                        <textarea name="motivo_cambio" rows="2" required minlength="3" maxlength="1000" placeholder="Ej.: se registró como ST pero el equipo está en garantía">{{ old('motivo_cambio') }}</textarea>
                    </label>
                </div>
                <div class="st-modal-pie">
                    <button type="button" class="st-btn st-btn--suave" data-cerrar-tipo>Cancelar</button>
                    <button type="submit" class="st-btn st-btn--primario">{!! $icono('cambiar') !!} Cambiar tipo</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($conformidadEditable)
    <div class="st-modal-fondo" id="st-modal-conformidad" role="dialog" aria-modal="true" aria-labelledby="st-conf-titulo"
         @if($errors->has('conformidad_trabajo')) data-abrir="1" @endif>
        <div class="st-modal">
            <div class="st-modal-cab">
                <div>
                    <h3 id="st-conf-titulo">{!! $icono('conformidad', 18) !!} Hoja de conformidad</h3>
                    <p>{{ $orden->esReparacionInterna() ? 'Registra el trabajo realizado y la firma del técnico.' : 'El cliente firma cuando se repara y se entrega el equipo.' }}</p>
                </div>
                <button type="button" class="st-modal-cerrar" data-cerrar-conformidad aria-label="Cerrar">&times;</button>
            </div>
            <form method="POST" action="{{ route('servicio.ordenes.conformidad', $orden) }}" target="_blank" id="st-form-conformidad">
                @csrf
                <input type="hidden" name="ver_pdf" value="1">
                <div class="st-modal-cuerpo">
                    @if($orden->tieneConformidad())
                        <div class="st-nota" style="margin:0 0 14px;">
                            Ya registrada el {{ $orden->conformidad_at?->format('d/m/Y H:i') ?: '—' }}. Si guardas de nuevo, se actualiza; las firmas que no vuelvas a dibujar se conservan.
                        </div>
                    @endif
                    <label for="st-conf-trabajo" style="display:block;font-weight:600;margin-bottom:4px;font-size:.88rem;">Trabajo realizado *</label>
                    <textarea id="st-conf-trabajo" name="conformidad_trabajo" rows="3" required
                              style="width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;">{{ old('conformidad_trabajo', $orden->conformidad_trabajo ?: $orden->diagnostico) }}</textarea>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-top:6px;">
                        @unless($orden->esReparacionInterna())
                            @include('servicio.ordenes._firma_pad', ['padId' => 'conf-cliente', 'inputName' => 'firma_conformidad_cliente', 'label' => 'Firma del cliente'])
                        @endunless
                        @include('servicio.ordenes._firma_pad', ['padId' => 'conf-empleado', 'inputName' => 'firma_conformidad_empleado', 'label' => 'Firma del técnico'])
                    </div>
                </div>
                <div class="st-modal-pie">
                    @if($puedeEntregar)
                        <button type="submit" form="st-form-entregar" class="st-btn st-btn--ok" style="margin-right:auto;"
                                title="Marca la orden como entregada sin llenar la hoja. La orden queda cerrada.">
                            {!! $icono('entregado') !!} {{ $orden->tieneConformidad() ? 'Entregado al cliente' : 'Entregado sin hoja' }}
                        </button>
                    @endif
                    @if($orden->tieneConformidad())
                        <a class="st-btn st-btn--suave" target="_blank" href="{{ route('servicio.ordenes.conformidad_pdf', $orden) }}">Ver la hoja sin cambios</a>
                    @endif
                    <button type="button" class="st-btn st-btn--suave" data-cerrar-conformidad>Cancelar</button>
                    <button type="submit" class="st-btn st-btn--primario">{!! $icono('conformidad') !!} Guardar y abrir la hoja</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@include('servicio.ordenes._firma_pad_script')
@if($tipoEditable)
<script>
(function () {
    const modal = document.getElementById('st-modal-tipo');
    if (!modal) return;
    const tipo = document.getElementById('st-tipo-gestion');
    const rango = document.getElementById('st-tipo-rango');
    const GARANTIA = @json(\App\Models\StOrden::TIPO_GARANTIA);
    const INTERNA = @json(\App\Models\StOrden::TIPO_REPARACION_INTERNA);
    const DENTRO = @json(\App\Models\StOrden::RANGO_DENTRO);

    function mostrar(seccion, visible) {
        modal.querySelectorAll('[data-solo="' + seccion + '"]').forEach(el => {
            el.style.display = visible ? '' : 'none';
            el.querySelectorAll('input, select, textarea').forEach(i => i.disabled = !visible);
        });
    }
    function actualizar() {
        const esGarantia = tipo.value === GARANTIA;
        const esInterna = tipo.value === INTERNA;
        const dentro = esGarantia && rango.value === DENTRO;
        mostrar('GARANTIA', esGarantia);
        mostrar('CLIENTE', !esInterna && !dentro);
        mostrar('INTERNA', esInterna);
        mostrar('DENTRO', dentro);
        mostrar('SALE', !esGarantia);
        rango.required = esGarantia;
        modal.querySelector('[name=cliente_nombre]').required = !esInterna && !dentro;
    }
    tipo.addEventListener('change', actualizar);
    rango.addEventListener('change', actualizar);
    actualizar();

    const abrir = () => { modal.classList.add('abierto'); tipo.focus(); };
    const cerrar = () => modal.classList.remove('abierto');
    document.querySelectorAll('[data-abrir-tipo]').forEach(b => b.addEventListener('click', abrir));
    modal.querySelectorAll('[data-cerrar-tipo]').forEach(b => b.addEventListener('click', cerrar));
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrar(); });
    if (modal.dataset.abrir === '1') abrir();
})();
</script>
@endif
@if($conformidadEditable)
<script>
(function () {
    const modal = document.getElementById('st-modal-conformidad');
    if (!modal) return;
    const abrir = () => { modal.classList.add('abierto'); document.getElementById('st-conf-trabajo')?.focus(); };
    const cerrar = () => modal.classList.remove('abierto');
    document.querySelectorAll('[data-abrir-conformidad]').forEach(b => b.addEventListener('click', abrir));
    modal.querySelectorAll('[data-cerrar-conformidad]').forEach(b => b.addEventListener('click', cerrar));
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrar(); });
    // La hoja se abre en otra pestaña; esta se recarga para mostrar la conformidad guardada.
    document.getElementById('st-form-conformidad')?.addEventListener('submit', () => {
        cerrar();
        setTimeout(() => location.reload(), 1500);
    });
    if (modal.dataset.abrir === '1') abrir();
})();
</script>
@endif
@endsection
