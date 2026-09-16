@extends('layouts.app')

@section('title', $orden->codigo())

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
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn primary" target="_blank" href="{{ route('servicio.ordenes.recepcion_pdf', $orden) }}">Hoja de recepción</a>
            <a class="btn secondary" target="_blank" href="{{ route('servicio.ordenes.conformidad_pdf', $orden) }}">Hoja de conformidad</a>
            @if($orden->puedeConfirmarRecepcion(auth()->user()))
                <form method="POST" action="{{ route('servicio.ordenes.confirmar_recepcion', $orden) }}">
                    @csrf
                    <button class="btn primary" type="submit">Marcar recibido</button>
                </form>
            @endif
            @unless($orden->garantiaExternaBloqueada())
                <a class="btn primary" href="{{ route('servicio.ordenes.edit', $orden) }}">Editar</a>
            @endunless
        </div>
    </div>

    @if($orden->excedePresupuesto())
        <div style="background:#fffbeb;border:1px solid #fcd34d;padding:12px 16px;border-radius:8px;margin-bottom:16px;color:#92400e;">
            Los costos (${{ number_format($orden->costoTotal(), 2) }}) superan el presupuesto (${{ number_format($orden->presupuesto, 2) }}). Confirma con el cliente antes de entregar.
        </div>
    @endif

    @if($orden->esGarantia())
        @php $estadoGarantia = $orden->estadoGarantiaExternaActual(); @endphp
        <div class="panel" style="padding:24px;margin-bottom:16px;">
            <h3 style="margin:0 0 6px;">Flujo externo de garantía</h3>
            <p style="margin:0 0 16px;">
                Estado: <strong>{{ $orden->etiquetaEstadoGarantiaExterna() }}</strong>
                @if($orden->empresa_envio_garantia) · {{ $orden->etiquetaEmpresaEnvioGarantia() }} @endif
            </p>
            @if($orden->garantia_enviado_at)
                <p class="muted" style="margin:-10px 0 14px;font-size:.82rem;">
                    Enviado el {{ $orden->garantia_enviado_at->format('d/m/Y h:i A') }}
                    por {{ $orden->enviadoPorGarantia?->name ?: '—' }}
                </p>
            @endif

            @if($estadoGarantia === \App\Models\StOrden::GARANTIA_PENDIENTE_ENVIO)
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
    @endif

    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <div>
                <span class="muted">Gestión</span>
                <div><strong>{{ $orden->etiquetaTipoGestion() }}</strong>{{ $orden->etiquetaRangoGarantia() ? ' · '.$orden->etiquetaRangoGarantia() : '' }}</div>
            </div>
            <div><span class="muted">Creada por</span><div>{{ $orden->creador?->name ?: '—' }}</div></div>
            @unless($orden->esCambioEnRango() || $orden->esReparacionInterna())
            <div><span class="muted">Cliente</span><div><strong>{{ $orden->cliente_nombre }}</strong></div></div>
            <div><span class="muted">Teléfono</span><div>{{ $orden->cliente_telefono ?: '—' }}</div></div>
            @endunless
            @if($orden->esGarantia() && ! $orden->esCambioEnRango())
                <div style="grid-column:1/-1;"><span class="muted">Garantía</span><div>La garantía es con la marca del equipo ({{ $orden->marcaEquipo() }}). La empresa es intermediaria; el equipo pertenece al cliente.</div></div>
            @endif
            <div><span class="muted">Equipo</span><div>{{ $orden->equipo ?: '—' }}</div></div>
            <div><span class="muted">Tipo de dispositivo</span><div>{{ $orden->etiquetaTipoDispositivo() }}</div></div>
            <div><span class="muted">Valor del dispositivo</span><div><strong>{{ $orden->valor_dispositivo !== null ? '$'.number_format((float) $orden->valor_dispositivo, 2) : '—' }}</strong></div></div>
            <div><span class="muted">IMEI</span><div>{{ $orden->imei ?: '—' }}</div></div>
            <div><span class="muted">Serial</span><div>{{ $orden->serial ?: '—' }}</div></div>
            @if($orden->atributo('almacenamiento'))
                <div><span class="muted">Almacenamiento</span><div>{{ $orden->atributo('almacenamiento') }}</div></div>
            @endif
            @if($orden->atributo('tipo_impresora'))
                <div><span class="muted">Tipo de impresora</span><div>{{ config('servicio_tecnico.tipos_impresora.'.$orden->atributo('tipo_impresora'), $orden->atributo('tipo_impresora')) }}</div></div>
            @endif
            @if($orden->atributo('serial_lente'))
                <div><span class="muted">Serial del lente</span><div>{{ $orden->atributo('serial_lente') }}</div></div>
            @endif
            @if($orden->atributo('codigo_lote'))
                <div><span class="muted">Código de lote</span><div>{{ $orden->atributo('codigo_lote') }}</div></div>
            @endif
            <div><span class="muted">Accesorios</span><div>{{ $orden->accesorios ?: '—' }}</div></div>
            @if($orden->equipo_id)
                <div>
                    <span class="muted">Bitácora</span>
                    <div><a href="{{ route('servicio.celulares.show', $orden->equipo_id) }}">Ver historial del equipo</a></div>
                </div>
            @endif
            <div><span class="muted">Ingreso</span><div>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</div></div>
            @unless($orden->esCambioEnRango())
            <div><span class="muted">Prometida</span><div>{{ $orden->fecha_prometida?->format('d/m/Y') ?: '—' }}</div></div>
            @endunless
            <div><span class="muted">Técnico</span><div>{{ $orden->tecnico?->name ?: '—' }}</div></div>
            @if($orden->esGarantia())
                <div><span class="muted">Empresa responsable del envío</span><div>{{ $orden->etiquetaEmpresaEnvioGarantia() ?: '—' }}</div></div>
            @endif
            <div><span class="muted">Repuestos descontados</span><div>{{ $orden->repuestos_descontados_at ? $orden->repuestos_descontados_at->format('d/m/Y H:i') : 'No' }}</div></div>
        </div>
        <hr style="border:none;border-top:1px dashed #e2e8f0;margin:20px 0;">
        <p class="muted" style="margin:0 0 4px;">Falla</p>
        <p>{{ $orden->falla ?: '—' }}</p>
        @if($orden->itemsInspeccionRecepcion())
            <p class="muted" style="margin:16px 0 8px;">Checklist de recepción</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;font-size:.85rem;">
                @foreach($orden->itemsInspeccionRecepcion() as $item)
                    <div>{{ $item['etiqueta'] }}: <strong>{{ $orden->etiquetaEstadoInspeccion($item['estado']) }}</strong></div>
                @endforeach
            </div>
        @endif
        <p class="muted" style="margin:16px 0 4px;">Diagnóstico</p>
        <p>{{ $orden->diagnostico ?: '—' }}</p>
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

    @unless($orden->garantiaExternaBloqueada())
    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <h3 style="margin:0 0 8px;">Hoja de conformidad (equipo reparado)</h3>
        <p class="muted" style="margin:0 0 14px;font-size:.85rem;">
            {{ $orden->esReparacionInterna() ? 'Registra el trabajo realizado y la firma del técnico.' : 'Segunda hoja: el cliente firma cuando se repara y se entrega el equipo.' }}
        </p>
        @if($orden->tieneConformidad())
            <p style="margin:0 0 12px;">Registrada el {{ $orden->conformidad_at?->format('d/m/Y H:i') ?: '—' }}.</p>
            <p>{{ $orden->conformidad_trabajo }}</p>
        @endif
        <form method="POST" action="{{ route('servicio.ordenes.conformidad', $orden) }}">
            @csrf
            <div style="margin-bottom:12px;">
                <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Trabajo realizado *</label>
                <textarea name="conformidad_trabajo" rows="3" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">{{ old('conformidad_trabajo', $orden->conformidad_trabajo ?: $orden->diagnostico) }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                @unless($orden->esReparacionInterna())
                @include('servicio.ordenes._firma_pad', ['padId' => 'conf-cliente', 'inputName' => 'firma_conformidad_cliente', 'label' => 'Firma del cliente'])
                @endunless
                @include('servicio.ordenes._firma_pad', ['padId' => 'conf-empleado', 'inputName' => 'firma_conformidad_empleado', 'label' => 'Firma del técnico'])
            </div>
            <div style="margin-top:12px;">
                <button class="btn primary" type="submit">Guardar conformidad</button>
            </div>
        </form>
    </div>
    @endunless

    <div class="panel" style="padding:24px;">
        <h3 style="margin:0 0 12px;">Línea de tiempo</h3>
        @forelse($orden->eventos as $evento)
            <div style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
                <div style="font-size:.8rem;color:#64748b;">{{ $evento->created_at?->format('d/m/Y H:i') }} · {{ $evento->usuario?->name ?? 'Sistema' }}</div>
                <div>{{ $evento->descripcion }}</div>
            </div>
        @empty
            <p class="muted">Sin eventos registrados.</p>
        @endforelse
    </div>
</div>
@include('servicio.ordenes._firma_pad_script')
@endsection
