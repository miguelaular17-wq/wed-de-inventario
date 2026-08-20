@extends('layouts.app')
@section('title', 'Contrato ' . $contrato->numero_contrato)
@section('content')
<div style="padding: 20px; max-width: 1400px; margin: 0 auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <a href="{{ route('contratos.lista') }}" style="color: #64748b; text-decoration: none; font-size: 0.85rem;">← Volver a lista</a>
            <h2 style="color: var(--blue); font-weight: 700; margin: 6px 0 0;">{{ $contrato->cliente }}</h2>
            <span style="color: #64748b; font-size: 0.9rem;">Contrato: <strong>{{ $contrato->numero_contrato }}</strong></span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('contratos.reporte', $contrato->id) }}" target="_blank" style="padding: 8px 16px; background: #3b82f6; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">⬇️ Descargar Reporte</a>
            @if($contrato->estado !== 'liquidado')
                <a href="{{ route('contratos.edit', $contrato->id) }}" style="padding: 8px 16px; background: #f59e0b; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">✏️ Editar</a>
                <button type="button" onclick="document.getElementById('modalSeguimiento').style.display='flex'" style="padding: 8px 16px; background: #7c3aed; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">📞 Registrar Llamada</button>
                <a href="{{ route('contratos.liquidar', $contrato->id) }}" style="padding: 8px 16px; background: #dc2626; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">⚠️ Liquidar Contrato</a>
            @endif
        </div>
    </div>

    @if($contrato->estado === 'liquidado')
        <div style="background: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong style="display: block; font-size: 1.1rem; margin-bottom: 4px;">⚠️ ESTE CONTRATO FUE LIQUIDADO Y REESTRUCTURADO</strong>
                <span>Esta deuda ha sido refinanciada y el contrato quedó cerrado. No se admiten más pagos.</span>
            </div>
            @if($contrato->liquidado_en_contrato_id)
                <a href="{{ route('contratos.show', $contrato->liquidado_en_contrato_id) }}" style="background: #ef4444; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Ver Nuevo Contrato ➔</a>
            @endif
        </div>
    @endif

    @if(session('success'))
        <div style="background: #d1e7dd; color: #0f5132; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="background: #f8d7da; color: #842029; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Info del contrato --}}
    <div class="panel" style="padding: 20px; margin-bottom: 20px;">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Garantía</div>
                <div style="font-weight: 500;">
                    {{ $contrato->garantia ?: '—' }}
                    @if($contrato->garantia_aumento)
                        <span style="display: inline-block; margin-top: 2px; font-size: 0.8rem; background: #fef9c3; color: #854d0e; padding: 2px 6px; border-radius: 4px;">+ {{ $contrato->garantia_aumento }}</span>
                    @endif
                    @if($contrato->garantia_documento)
                        <br><a href="{{ $contrato->garantia_documento }}" target="_blank" style="color: var(--blue); text-decoration: none; font-size: 0.85rem; display: inline-block; margin-top: 4px;">📄 Ver Documento</a>
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Teléfono</div>
                <div style="font-weight: 500;">{{ $contrato->telefono ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Fecha Inicio</div>
                <div style="font-weight: 500;">{{ $contrato->fecha_inicio?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Capital</div>
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--blue);">${{ number_format($contrato->capital, 2) }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Cuota Fija</div>
                <div style="font-weight: 700; font-size: 1.1rem;">${{ number_format($contrato->cuota_fija, 2) }}</div>
                @if((float)$contrato->interes_porcentaje > 0)
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">= Capital × {{ number_format($contrato->interes_porcentaje * 100, 2) }}%</div>
                @endif
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Total a Pagar</div>
                <div style="font-weight: 700; font-size: 1.1rem; color: #7c3aed;">${{ number_format($contrato->totalDeuda(), 2) }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Interés</div>
                <div style="font-weight: 500;">{{ number_format($contrato->interes_porcentaje * 100, 2) }}%</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Frecuencia</div>
                <div style="font-weight: 500;">{{ $contrato->frecuencia }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Asesor</div>
                <div style="font-weight: 500;">{{ $contrato->responsable?->name ?? '—' }}</div>
            </div>
            </div>
            
            @if($contrato->garantia_documento)
                @php
                    $ext = strtolower(pathinfo(parse_url($contrato->garantia_documento, PHP_URL_PATH), PATHINFO_EXTENSION));
                @endphp
                @if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp']))
                    <div style="width: 120px; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; border-left: 1px solid #e2e8f0; padding-left: 20px;">
                        <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Vista Previa</div>
                        <a href="{{ $contrato->garantia_documento }}" target="_blank">
                            <img src="{{ $contrato->garantia_documento }}" alt="Vista previa garantía" style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        </a>
                    </div>
                @endif
            @endif
        </div>
        @if($contrato->observaciones)
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed #e2e8f0;">
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Observaciones</div>
                <div>{{ $contrato->observaciones }}</div>
            </div>
        @endif

        {{-- Alerta si cuotas pagadas pero aún debe capital --}}
        @php
            $cuotasActivas = $contrato->cuotas->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])->count();
            $totalRaw = (float) $contrato->getRawOriginal('total_a_pagar');
        @endphp
        @if($cuotasActivas === 0 && $totalRaw > 0)
            <div style="margin-top: 16px; padding: 12px 16px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;">⚠️</span>
                <div>
                    <strong style="color: #92400e;">Cuotas pagadas, pero saldo pendiente de capital:</strong>
                    <span style="color: #92400e; font-size: 1.05rem; font-weight: 700;"> ${{ number_format($totalRaw, 2) }}</span>
                    <div style="font-size: 0.85rem; color: #78350f; margin-top: 2px;">Genere una nueva cuota para continuar el cobro.</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Cuotas --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <h3 style="color: var(--blue); margin: 0;">📄 Plan de Pagos</h3>
        <div style="display: flex; gap: 10px;">
            <button type="button" onclick="document.getElementById('modalAumentarCapital').style.display='flex'" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                ➕ Aumentar Capital
            </button>
            <button type="button" onclick="document.getElementById('modalAjustarCuota').style.display='flex'" style="padding: 8px 16px; background: #7c3aed; color: white; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                ✏️ Ajustar Cuota Fija
            </button>
            @if($contrato->getRawOriginal('total_a_pagar') > 0)
                <form method="POST" action="{{ route('contratos.generarCuota', $contrato->id) }}">
                    @csrf
                    <button type="submit" style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        ➕ Generar Próxima Cuota
                    </button>
                </form>
            @endif
        </div>
    </div>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 24px;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 0.9rem;">
                        <th style="padding: 12px; text-align: left;">#</th>
                        <th style="padding: 12px; text-align: left;">Vencimiento</th>
                        <th style="padding: 12px; text-align: right;">Monto</th>
                        <th style="padding: 12px; text-align: right;">Int. Pagado</th>
                        <th style="padding: 12px; text-align: right;">Abono Cap.</th>
                        <th style="padding: 12px; text-align: right;">Saldo</th>
                        <th style="padding: 12px; text-align: left;">Forma Pago</th>
                        <th style="padding: 12px; text-align: left;">Fecha Pago</th>
                        <th style="padding: 12px; text-align: center;">Estatus</th>
                        <th style="padding: 12px; text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contrato->cuotas as $cuota)
                        @php
                            $rowStyle = match($cuota->estatus) {
                                'prestamo'   => 'background: #f0fdf4;',
                                'pagado'     => 'background: #f0fdf4;',
                                'vencido'    => 'background: #fef2f2;',
                                'parcial'    => 'background: #fffbeb;',
                                'acumulado'  => 'background: #f5f3ff;',
                                default      => '',
                            };
                            $badgeStyle = match($cuota->estatus) {
                                'prestamo'  => 'background:#10b981; color:white;',
                                'pagado'    => 'background:#059669; color:white;',
                                'vencido'   => 'background:#dc2626; color:white;',
                                'parcial'   => 'background:#f59e0b; color:white;',
                                'acumulado' => 'background:#7c3aed; color:white;',
                                default     => 'background:#e2e8f0; color:#475569;',
                            };
                            $badgeLabel = match($cuota->estatus) {
                                'prestamo'  => 'NUEVO PRÉSTAMO',
                                'acumulado' => 'ACUMULADO',
                                default     => strtoupper($cuota->estatus),
                            };
                        @endphp
                        <tr style="{{ $rowStyle }}">
                            <td style="font-weight: 600; color: #64748b; padding: 12px;">{{ $cuota->estatus === 'prestamo' ? '➕' : $cuota->numero_cuota }}</td>
                            <td style="padding: 12px;">{{ $cuota->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                            <td style="text-align: right; font-weight: 500; padding: 12px;">
                                @if($cuota->estatus === 'prestamo')
                                    <span style="color: #10b981; font-weight: 600;">+${{ number_format($cuota->monto, 2) }}</span>
                                @else
                                    ${{ number_format($cuota->monto, 2) }}
                                @endif
                            </td>
                            <td style="text-align: right; color: #059669; font-weight: 500; padding: 12px;">{{ in_array($cuota->estatus, ['prestamo','acumulado']) ? '—' : '$'.number_format($cuota->monto_pagado, 2) }}</td>
                            <td style="text-align: right; color: #7c3aed; font-weight: 500; padding: 12px;">{{ in_array($cuota->estatus, ['prestamo','acumulado']) ? '—' : '$'.number_format($cuota->abono_capital ?? 0, 2) }}</td>
                            <td style="text-align: right; font-weight: 600; color: {{ ($cuota->monto - $cuota->monto_pagado) > 0 ? '#dc2626' : '#059669' }}; padding: 12px;">
                                {{ in_array($cuota->estatus, ['prestamo','acumulado']) ? '—' : '$'.number_format($cuota->monto - $cuota->monto_pagado, 2) }}
                            </td>
                            <td style="color: #475569; font-size: 0.9rem; padding: 12px;">{{ $cuota->forma_pago ?: '—' }}</td>
                            <td style="font-size: 0.9rem; padding: 12px;">{{ $cuota->fecha_pago?->format('d/m/Y') ?? '—' }}</td>
                            <td style="text-align: center; padding: 12px;">
                                <span style="padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; {{ $badgeStyle }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td style="text-align: center; padding: 12px; display: flex; gap: 5px; justify-content: center; align-items: center;">
                                @if($contrato->estado === 'liquidado')
                                    <span style="color: #94a3b8; font-size: 0.85rem;">🔒 Bloqueado</span>
                                @else
                                    @if(in_array($cuota->estatus, ['pendiente', 'vencido', 'parcial']))
                                        <button type="button" onclick="abrirPago({{ $cuota->id }}, {{ $cuota->monto - $cuota->monto_pagado }}, {{ $cuota->numero_cuota }})"
                                            style="padding: 4px 10px; background: #059669; color: white; border: none; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">💰 Pagar</button>
                                    @elseif(in_array($cuota->estatus, ['prestamo', 'acumulado']))
                                        <span style="color: #94a3b8; font-size: 0.85rem;">—</span>
                                    @else
                                        <span style="color: #94a3b8; font-size: 0.85rem;">✓</span>
                                    @endif

                                    @if(in_array($cuota->estatus, ['pagado', 'parcial']) && !in_array($cuota->estatus, ['prestamo', 'acumulado']))
                                        <button type="button" onclick="abrirEditarPago({{ $cuota->id }}, {{ $cuota->numero_cuota }}, {{ (float)$cuota->monto_pagado }}, {{ (float)$cuota->abono_capital ?? 0 }})"
                                            style="padding: 4px 10px; background: #f59e0b; color: white; border: none; border-radius: 4px; font-size: 0.8rem; cursor: pointer;" title="Editar Montos Pagados">✏️</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Historial de Pagos --}}
    <h3 style="color: var(--blue); margin-bottom: 12px; margin-top: 30px;">💰 Historial de Pagos</h3>
    <div class="panel" style="padding: 0; overflow: hidden; margin-bottom: 20px;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Resultado</th>
                        <th>Promesa de Pago</th>
                        <th>Cuota</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contrato->seguimientos->whereIn('resultado', ['PAGO_COMPLETO', 'PAGO_PARCIAL', 'EDICION_PAGO']) as $seg)
                        @php
                            $resColor = match($seg->resultado) {
                                'PAGO_COMPLETO'  => 'background:#059669; color:white;',
                                'PAGO_PARCIAL'   => 'background:#10b981; color:white;',
                                'EDICION_PAGO'   => 'background:#f59e0b; color:white;',
                                default          => 'background:#e2e8f0; color:#475569;',
                            };
                        @endphp
                        <tr>
                            <td style="font-size: 0.85rem; white-space: nowrap;">{{ $seg->fecha_hora?->format('d/m/Y H:i') }}</td>
                            <td style="font-weight: 500;">{{ $seg->usuario?->name ?? '—' }}</td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; {{ $resColor }}">
                                    {{ $seg->resultadoLabel() }}
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">{{ $seg->fecha_prometida_pago?->format('d/m/Y') ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $seg->cuota ? '#'.$seg->cuota->numero_cuota : '—' }}</td>
                            <td style="font-size: 0.85rem; color: #475569; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">{{ $seg->comentarios ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">No hay pagos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Seguimientos --}}
    <h3 style="color: var(--blue); margin-bottom: 12px;">📞 Historial de Seguimiento / Llamadas</h3>
    <div class="panel" style="padding: 0; overflow: hidden;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Resultado</th>
                        <th>Promesa de Pago</th>
                        <th>Cuota</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contrato->seguimientos->whereNotIn('resultado', ['PAGO_COMPLETO', 'PAGO_PARCIAL', 'EDICION_PAGO']) as $seg)
                        @php
                            $resColor = match($seg->resultado) {
                                'PROMESA_PAGO'   => 'background:#3b82f6; color:white;',
                                'NUEVO_PRESTAMO' => 'background:#10b981; color:white;',
                                'ACUMULADO'      => 'background:#7c3aed; color:white;',
                                'NO_CONTESTA', 'SIN_SEÑAL', 'BUZON_MENSAJES' => 'background:#f59e0b; color:white;',
                                default          => 'background:#e2e8f0; color:#475569;',
                            };
                        @endphp
                        <tr>
                            <td style="font-size: 0.85rem; white-space: nowrap;">{{ $seg->fecha_hora?->format('d/m/Y H:i') }}</td>
                            <td style="font-weight: 500;">{{ $seg->usuario?->name ?? '—' }}</td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; {{ $resColor }}">
                                    {{ $seg->resultadoLabel() }}
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">{{ $seg->fecha_prometida_pago?->format('d/m/Y') ?? '—' }}</td>
                            <td style="font-size: 0.85rem;">{{ $seg->cuota ? '#'.$seg->cuota->numero_cuota : '—' }}</td>
                            <td style="font-size: 0.85rem; color: #475569; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">{{ $seg->comentarios ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">No hay seguimientos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Pago --}}
<div id="modalPago" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div style="background: white; width: 95%; max-width: 450px; padding: 24px; border-radius: 14px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <button type="button" onclick="document.getElementById('modalPago').style.display='none'" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: var(--blue);">💰 Registrar Pago — Cuota #<span id="pagoNumCuota"></span></h3>
        <form id="formPago" method="POST" action="">
            @csrf
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Monto Interés/Cuota</label>
                <input type="number" step="0.01" name="monto_pagado" id="pagoMonto" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Abono a Capital (Opcional)</label>
                <input type="number" step="0.01" name="abono_capital" value="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Forma de Pago</label>
                <select name="forma_pago" id="pagoFormaPago" onchange="togglePagoFields()" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                    <option value="">-- Seleccione --</option>
                    <option value="ZELLE">ZELLE</option>
                    <option value="EFECTIVO">EFECTIVO</option>
                    <option value="TRANSFERENCIA_DIVISAS">TRANSFERENCIA DIVISAS</option>
                    <option value="TRANSFERENCIA_BCV">TRANSFERENCIA BCV</option>
                    <option value="PAGO_MOVIL">PAGO MÓVIL</option>
                    <option value="DEPOSITO">DEPÓSITO</option>
                    <option value="BINANCE">BINANCE</option>
                    <option value="CRUCE">CRUCE</option>
                </select>
            </div>
            
            <div id="pagoExtraFields" style="display: none; padding: 12px; border: 1px dashed #ccc; border-radius: 6px; margin-bottom: 12px; background: #fafafa;">
                <!-- Tasa de Cambio -->
                <div id="pagoFieldTasa" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Tasa de Cambio (Bs)</label>
                    <input type="number" step="0.01" name="tasa_cambio" id="pagoTasaCambio" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>

                <!-- Banco Destino -->
                <div id="pagoFieldBancoDestino" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Banco Destino (Empresa)</label>
                    <select name="banco_destino" id="pagoBancoDestino" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                        <option value="">-- Seleccione Banco --</option>
                        @foreach($cuentasBancarias ?? [] as $cuenta)
                            <option value="{{ $cuenta->banco }} - {{ $cuenta->titular }}">{{ $cuenta->banco }} - {{ $cuenta->titular }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Banco Origen -->
                <div id="pagoFieldBancoOrigen" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Banco Origen (Cliente)</label>
                    <input type="text" name="banco_origen" id="pagoBancoOrigen" placeholder="Ej. Banesco" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>

                <!-- Referencia -->
                <div id="pagoFieldReferencia" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Referencia</label>
                    <input type="text" name="referencia" id="pagoReferencia" pattern="[a-zA-Z0-9]+" title="Solo letras y números" placeholder="Ej. 12345678" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Fecha de Pago</label>
                <input type="date" name="fecha_pago" required value="{{ now()->toDateString() }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Comentario (opcional)</label>
                <textarea name="comentario" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" onclick="document.getElementById('modalPago').style.display='none'" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="button" onclick="previsualizarRecibo()" style="padding: 8px 20px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Registrar Pago</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Pago --}}
<div id="modalEditarPago" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div style="background: white; width: 95%; max-width: 450px; padding: 24px; border-radius: 14px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <button type="button" onclick="document.getElementById('modalEditarPago').style.display='none'" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: #f59e0b;">✏️ Editar Totales - Cuota #<span id="editPagoNumCuota"></span></h3>
        
        <div style="margin-bottom: 12px; font-size: 0.9rem; color: #475569; background: #fffbeb; padding: 10px; border-radius: 8px;">
            Ajusta los totales pagados en esta cuota. El sistema calculará la diferencia y recalculará automáticamente el capital total y las cuotas futuras pendientes si el abono a capital cambia.
        </div>

        <form id="formEditarPago" method="POST" action="">
            @csrf
            @method('PUT')
            
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Monto Interés/Cuota Total Pagado</label>
                <input type="number" step="0.01" name="monto_pagado" id="editPagoMonto" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Abono a Capital Total (Opcional)</label>
                <input type="number" step="0.01" name="abono_capital" id="editPagoAbono" value="0" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" onclick="document.getElementById('modalEditarPago').style.display='none'" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 20px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Actualizar Pago y Recalcular</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Seguimiento --}}
<div id="modalSeguimiento" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div style="background: white; width: 95%; max-width: 500px; padding: 24px; border-radius: 14px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <button type="button" onclick="document.getElementById('modalSeguimiento').style.display='none'" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: #7c3aed;">📞 Registrar Llamada / Contacto</h3>
        <form method="POST" action="{{ route('contratos.seguimiento') }}">
            @csrf
            <input type="hidden" name="contrato_id" value="{{ $contrato->id }}">
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Resultado</label>
                <select name="resultado" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                    @foreach($resultados as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Cuota relacionada (opcional)</label>
                <select name="cuota_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                    <option value="">— Ninguna —</option>
                    @foreach($contrato->cuotas->whereIn('estatus', ['pendiente', 'vencido', 'parcial']) as $cuo)
                        <option value="{{ $cuo->id }}">Cuota #{{ $cuo->numero_cuota }} — Vence {{ $cuo->fecha_vencimiento?->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Fecha prometida de pago</label>
                <input type="date" name="fecha_prometida_pago" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Comentarios</label>
                <textarea name="comentarios" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;"></textarea>
            </div>
            <div style="margin-bottom: 12px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="contactado" value="1" checked style="width: 16px; height: 16px;">
                    <span style="font-weight: 500;">Se logró contactar al cliente</span>
                </label>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" onclick="document.getElementById('modalSeguimiento').style.display='none'" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 20px; background: #7c3aed; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Guardar Seguimiento</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Ajustar Cuota Fija --}}
<div id="modalAjustarCuota" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 450px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #7c3aed; font-size: 1.1rem; margin: 0;">✏️ Ajustar Cuota Fija</h3>
            <button type="button" onclick="document.getElementById('modalAjustarCuota').style.display='none'" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8;">×</button>
        </div>
        <p style="color: #64748b; font-size: 0.88rem; margin-bottom: 16px;">
            Al guardar, se actualizará la cuota fija del contrato y se recalcularán el monto y saldo de todas las cuotas <strong>vencidas, parciales y pendientes</strong>.
        </p>
        <form method="POST" action="{{ route('contratos.ajustarCuotaFija', $contrato->id) }}">
            @csrf
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 0.85rem; color: #475569; font-weight: 600; margin-bottom: 6px;">Cuota Fija Actual</label>
                <div style="padding: 10px 14px; background: #f1f5f9; border-radius: 8px; font-weight: 700; color: #7c3aed;">${{ number_format($contrato->cuota_fija, 2) }}</div>
            </div>
            <div style="margin-bottom: 22px;">
                <label for="nueva_cuota_fija" style="display: block; font-size: 0.85rem; color: #475569; font-weight: 600; margin-bottom: 6px;">Nueva Cuota Fija ($)</label>
                <input type="number" id="nueva_cuota_fija" name="nueva_cuota_fija" step="0.01" min="0.01" required
                    placeholder="Ej: 498.40"
                    style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; font-weight: 600; color: #1e293b; outline: none;"
                    onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('modalAjustarCuota').style.display='none'"
                    style="padding: 10px 20px; background: #e2e8f0; color: #475569; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancelar</button>
                <button type="submit"
                    style="padding: 10px 20px; background: #7c3aed; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">💾 Guardar y Recalcular</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Aumentar Capital (mejorado) --}}
<div id="modalAumentarCapital" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div style="background: white; width: 95%; max-width: 480px; padding: 24px; border-radius: 14px; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <button type="button" onclick="document.getElementById('modalAumentarCapital').style.display='none'" style="position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        <h3 style="margin-top: 0; color: #10b981;">➕ Agregar Nuevo Préstamo al Capital</h3>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 16px;">Este monto se sumará al capital actual del contrato y la cuota fija se recalculará.</p>
        <form method="POST" action="{{ route('contratos.aumentarCapital', $contrato->id) }}" enctype="multipart/form-data">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Monto a agregar ($) *</label>
                    <input type="number" step="0.01" name="monto" required min="0.01" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Fecha del aumento *</label>
                    <input type="date" name="fecha_aumento" required value="{{ now()->toDateString() }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 8px; font-size: 0.9rem;">Garantía del préstamo *</label>
                <div style="display: flex; gap: 16px;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="garantia_tipo" value="misma" checked onchange="toggleGarantiaNueva(this.value)" style="width: 16px; height: 16px;">
                        Misma garantía del contrato
                        @if($contrato->garantia)
                            <span style="color: #64748b; font-size: 0.8rem;">({{ $contrato->garantia }})</span>
                        @endif
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="garantia_tipo" value="nueva" onchange="toggleGarantiaNueva(this.value)" style="width: 16px; height: 16px;">
                        Nueva garantía
                    </label>
                </div>
            </div>

            <div id="campoGarantiaNueva" style="display: none; margin-bottom: 12px; border-left: 3px solid #10b981; padding-left: 10px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Descripción de la nueva garantía *</label>
                <input type="text" name="garantia_nueva" id="inputGarantiaNueva" placeholder="Ej: MOTO HONDA CRF 2023" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 8px;">
                
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Documento o Imagen (Opcional)</label>
                <input type="file" name="garantia_documento" accept=".pdf,.jpg,.jpeg,.png" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.85rem;">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Comentario (opcional)</label>
                <textarea name="comentario" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;"></textarea>
            </div>

            @if((float)$contrato->interes_porcentaje > 0)
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 0.85rem; color: #14532d;">
                    <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="recalcular_cuota" value="1" checked style="margin-top: 2px;">
                        <div>
                            <strong>Recalcular Cuota Fija Automáticamente</strong><br>
                            Si marcas esta opción, la cuota fija subirá a: <strong>(Capital actual + Monto) × {{ number_format($contrato->interes_porcentaje * 100, 2) }}%</strong>.<br>
                            Si la desmarcas, la deuda subirá pero el cliente seguirá pagando la misma cuota de siempre.
                        </div>
                    </label>
                </div>
            @endif

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" onclick="document.getElementById('modalAumentarCapital').style.display='none'" style="padding: 8px 20px; background: #94a3b8; color: white; border: none; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" style="padding: 8px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Agregar Préstamo</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Recibo de Pago --}}
<div id="modalRecibo" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; overflow-y:auto;">
    <div style="background:white; width:95%; max-width:620px; border-radius:4px; box-shadow:0 20px 60px rgba(0,0,0,0.4); margin:20px auto; font-family:'Times New Roman',serif;">

        {{-- Recibo imprimible --}}
        <div id="reciboContenido" style="padding:30px 40px;">

            {{-- Encabezado --}}
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px;">
                <div style="text-align:left;">
                    <img src="/logo_recibo.png" alt="Logo Grupo Jenu" style="height:80px;width:auto;">
                </div>
                <div style="text-align:right; font-size:0.78rem; line-height:1.6;">
                    <div style="font-weight:700; font-size:0.9rem; text-decoration:underline;">Grupo Inmobiliario y de Transporte</div>
                    <div style="font-weight:700; font-size:0.9rem; text-decoration:underline;">Je Nu &amp; Asociados, C.A.</div>
                    <div>Rif.: J-50255135-2</div>
                    <div>Calle Girardot, con Av. Santa Irene, Punto Fijo - Edo.</div>
                    <div>Falcon, Zona Postal 4102. <strong>Teléfono:</strong> 0412-6937658</div>
                </div>
            </div>

            {{-- Fecha y título --}}
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <table style="border-collapse:collapse; font-size:0.8rem;">
                    <tr>
                        <th style="border:1px solid #000; padding:3px 10px;">DIA</th>
                        <th style="border:1px solid #000; padding:3px 10px;">MES</th>
                        <th style="border:1px solid #000; padding:3px 10px;">AÑO</th>
                    </tr>
                    <tr>
                        <td id="reciboDia" style="border:1px solid #000; padding:3px 10px; text-align:center;"></td>
                        <td id="reciboMes" style="border:1px solid #000; padding:3px 10px; text-align:center;"></td>
                        <td id="reciboAnio" style="border:1px solid #000; padding:3px 10px; text-align:center;"></td>
                    </tr>
                </table>
                <div style="text-align:center;">
                    <div id="reciboControl" style="color:#cc0000; font-weight:700; font-size:1rem;"></div>
                    <div style="font-weight:900; font-size:1.3rem; letter-spacing:1px;">RECIBO</div>
                </div>
                <div style="border:2px solid #000; padding:8px 16px; text-align:center; min-width:80px;">
                    <div style="font-size:0.7rem; font-weight:700;">BS/$.</div>
                    <div id="reciboMonto" style="font-size:1.4rem; font-weight:700;"></div>
                </div>
            </div>

            <hr style="border:1px solid #000; margin:0 0 14px;">

            {{-- Cuerpo --}}
            <div style="margin-bottom:12px; display:flex; align-items:baseline; gap:10px;">
                <span style="font-weight:700; white-space:nowrap;">Recibí de:</span>
                <span id="reciboCliente" style="border-bottom:1px solid #000; flex:1; padding-bottom:2px; font-weight:600;"></span>
            </div>
            <div style="margin-bottom:6px; display:flex; align-items:baseline; gap:10px;">
                <span style="font-weight:700; white-space:nowrap;">La Suma de:</span>
                <span id="reciboSumaLetras" style="border-bottom:1px solid #000; flex:1; padding-bottom:2px; font-style:italic;"></span>
            </div>
            <div style="text-align:right; font-size:0.8rem; font-weight:700; margin-bottom:14px;">Bolivares / Dólares</div>

            <hr style="border:1px solid #000; margin:0 0 12px;">

            <div style="margin-bottom:20px; display:flex; align-items:baseline; gap:10px;">
                <span style="font-weight:700; white-space:nowrap;">Por Concepto de</span>
                <span id="reciboConcepto" style="border-bottom:1px solid #000; flex:1; padding-bottom:2px;"></span>
            </div>

            <hr style="border:1px solid #000; margin:0 0 20px;">

            {{-- Forma de pago --}}
            <div style="display:flex; gap:30px; align-items:center; flex-wrap:wrap; margin-bottom:30px;">
                <div>
                    <span style="font-weight:700; text-decoration:underline;">Forma de</span><br>
                    <span style="font-weight:700; text-decoration:underline;">Pago:</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:16px; font-size:0.85rem;">
                    <label>Efectivo <span id="reciboChkEfectivo" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                    <label>Transferencia Bs <span id="reciboChkTransf" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                    <label>Pago Movil <span id="reciboChkMovil" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                    <label>Zelle <span id="reciboChkZelle" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                    <label>Binance <span id="reciboChkBinance" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                    <label>Otro <span id="reciboChkOtro" style="border:1px solid #000; width:14px; height:14px; display:inline-block; text-align:center; line-height:13px; font-size:11px;"></span></label>
                </div>
                <div style="margin-left:8px; font-size:0.85rem;">Titular: <span id="reciboTitular" style="border-bottom:1px solid #000; min-width:100px; display:inline-block;"></span></div>
            </div>

            {{-- Firmas --}}
            <div style="display:flex; justify-content:space-between; margin-top:10px;">
                <div style="text-align:center; width:40%;">
                    <img src="/firma_recibo.png" alt="Firma" style="height:56px;width:auto;display:block;margin:0 auto 4px;">
                    <div style="border-top:1px solid #000; padding-top:6px; font-weight:700; font-size:0.85rem;">Recibi Conforme</div>
                </div>
                <div style="text-align:center; width:40%;">
                    <div style="border-top:1px solid #000; padding-top:6px; font-weight:700; font-size:0.85rem;">Entregue Conforme</div>
                </div>
            </div>

        </div>{{-- fin reciboContenido --}}

        {{-- Botones (no se imprimen) --}}
        <div id="reciboBotones" style="padding:16px 40px 24px; display:flex; gap:12px; justify-content:flex-end; border-top:1px solid #e2e8f0;">
            <button type="button" onclick="cerrarRecibo()" style="padding:8px 20px; background:#94a3b8; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600;">← Volver</button>
            <button type="button" onclick="imprimirRecibo()" style="padding:8px 20px; background:#3b82f6; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600;">🖨️ Imprimir</button>
            <button type="button" onclick="confirmarPago()" style="padding:8px 20px; background:#059669; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600;">✅ Confirmar y Guardar</button>
        </div>
    </div>
</div>

<script>
// ===== Datos del contrato para el recibo =====
const reciboClienteNombre = '{{ addslashes($contrato->cliente) }}';
const reciboNumContrato = '{{ $contrato->numero_contrato }}';

function abrirPago(cuotaId, saldo, numCuota) {
    document.getElementById('formPago').action = '/contratos/cuota/' + cuotaId + '/pagar';
    document.getElementById('pagoMonto').value = saldo;
    document.getElementById('pagoNumCuota').textContent = numCuota;
    document.getElementById('modalPago').style.display = 'flex';
}

// ===== RECIBO =====
function numeroALetras(num) {
    const unidades = ['','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE',
        'DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE','DIECIOCHO','DIECINUEVE'];
    const decenas = ['','','VEINTE','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
    const centenas = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS','SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
    if (isNaN(num) || num <= 0) return 'CERO';
    if (num === 100) return 'CIEN';
    num = Math.round(num);
    let resultado = '';
    if (num >= 1000) {
        let miles = Math.floor(num / 1000);
        resultado += (miles === 1 ? 'MIL' : numeroALetras(miles) + ' MIL');
        num = num % 1000;
        if (num > 0) resultado += ' ';
    }
    if (num >= 100) {
        resultado += centenas[Math.floor(num / 100)];
        num = num % 100;
        if (num > 0) resultado += ' ';
    }
    if (num >= 20) {
        resultado += decenas[Math.floor(num / 10)];
        if (num % 10 > 0) resultado += ' Y ' + unidades[num % 10];
    } else if (num > 0) {
        resultado += unidades[num];
    }
    return resultado.trim();
}

function montoEnLetras(monto) {
    const parts = parseFloat(monto).toFixed(2).split('.');
    const entero = parseInt(parts[0]);
    const centavos = parseInt(parts[1]);
    let letras = numeroALetras(entero) + ' DÓLARES AMERICANOS';
    if (centavos > 0) letras += ' CON ' + centavos + '/100';
    return letras;
}

const mesesNombres = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];

function previsualizarRecibo() {
    const form = document.getElementById('formPago');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const monto = parseFloat(document.getElementById('pagoMonto').value) || 0;
    const abono = parseFloat(form.querySelector('[name=abono_capital]').value) || 0;
    const totalPago = monto + abono;
    const forma = document.getElementById('pagoFormaPago').value;
    const fechaVal = form.querySelector('[name=fecha_pago]').value;
    const comentario = form.querySelector('[name=comentario]').value;
    const numCuota = document.getElementById('pagoNumCuota').textContent;

    // Fecha
    let dia='', mes='', anio='';
    if (fechaVal) {
        const fd = new Date(fechaVal + 'T00:00:00');
        dia = String(fd.getDate()).padStart(2,'0');
        mes = String(fd.getMonth()+1).padStart(2,'0');
        anio = fd.getFullYear();
    }

    // Número de control
    const now = new Date();
    const ctrl = 'NM-' + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0');

    // Concepto
    let concepto = 'PAGO CUOTA #' + numCuota + ' - CONTRATO ' + reciboNumContrato;
    if (fechaVal) concepto += ' - MES DE ' + mesesNombres[parseInt(mes)-1] + ' ' + anio;
    if (comentario) concepto += ' (' + comentario.toUpperCase() + ')';
    if (abono > 0) concepto += ' + ABONO A CAPITAL $' + abono.toFixed(2);

    // Llenar recibo
    document.getElementById('reciboDia').textContent = dia;
    document.getElementById('reciboMes').textContent = mes;
    document.getElementById('reciboAnio').textContent = anio;
    document.getElementById('reciboControl').textContent = 'Control Interno N°. ' + ctrl;
    document.getElementById('reciboMonto').textContent = totalPago.toFixed(2) + '$';
    document.getElementById('reciboCliente').textContent = reciboClienteNombre;
    document.getElementById('reciboSumaLetras').textContent = montoEnLetras(totalPago);
    document.getElementById('reciboConcepto').textContent = concepto;
    document.getElementById('reciboTitular').textContent = '';

    // Forma de pago - limpiar todos
    ['reciboChkEfectivo','reciboChkTransf','reciboChkMovil','reciboChkZelle','reciboChkBinance','reciboChkOtro'].forEach(id => {
        document.getElementById(id).textContent = '';
    });
    const chkMap = {
        'EFECTIVO': 'reciboChkEfectivo',
        'TRANSFERENCIA_BCV': 'reciboChkTransf',
        'TRANSFERENCIA_DIVISAS': 'reciboChkTransf',
        'DEPOSITO': 'reciboChkTransf',
        'PAGO_MOVIL': 'reciboChkMovil',
        'ZELLE': 'reciboChkZelle',
        'BINANCE': 'reciboChkBinance',
    };
    const chkId = chkMap[forma] || 'reciboChkOtro';
    document.getElementById(chkId).textContent = 'X';

    // Titular (banco destino si aplica)
    const bancoDest = document.getElementById('pagoBancoDestino')?.value || '';
    const bancoOrig = document.getElementById('pagoBancoOrigen')?.value || '';
    document.getElementById('reciboTitular').textContent = bancoDest || bancoOrig || '';

    // Ocultar modal pago y mostrar recibo
    document.getElementById('modalPago').style.display = 'none';
    document.getElementById('modalRecibo').style.display = 'flex';
}

function cerrarRecibo() {
    document.getElementById('modalRecibo').style.display = 'none';
    document.getElementById('modalPago').style.display = 'flex';
}

function imprimirRecibo() {
    // Recoger datos del recibo en pantalla
    const dia        = document.getElementById('reciboDia')?.textContent || '';
    const mes        = document.getElementById('reciboMes')?.textContent || '';
    const anio       = document.getElementById('reciboAnio')?.textContent || '';
    const control    = document.getElementById('reciboControl')?.textContent || '';
    const monto      = document.getElementById('reciboMonto')?.textContent || '';
    const cliente    = document.getElementById('reciboCliente')?.textContent || '';
    const sumaLetras = document.getElementById('reciboSumaLetras')?.textContent || '';
    const concepto   = document.getElementById('reciboConcepto')?.textContent || '';
    const titular    = document.getElementById('reciboTitular')?.textContent || '';

    function chk(id) { return document.getElementById(id)?.textContent?.trim() === 'X' ? 'X' : ''; }
    const cEfectivo = chk('reciboChkEfectivo');
    const cTransf   = chk('reciboChkTransf');
    const cMovil    = chk('reciboChkMovil');
    const cZelle    = chk('reciboChkZelle');
    const cBinance  = chk('reciboChkBinance');
    const cOtro     = chk('reciboChkOtro');

    function box(val) {
        return `<span style="border:1.5px solid #000;width:16px;height:16px;display:inline-flex;align-items:center;justify-content:center;font-size:11pt;font-weight:900;">${val}</span>`;
    }

    const ventana = window.open('', '_blank', 'width=900,height=720');
    ventana.document.write(`<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Recibo</title>
<style>
@page { size: letter portrait; margin: 20mm 20mm 20mm 20mm; }
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Times New Roman',Times,serif;font-size:13pt;color:#000;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:2px solid #000;margin-bottom:16px;}
.logo-grupo{font-size:28pt;font-weight:900;line-height:1;}
.logo-jenu{font-size:26pt;font-weight:900;font-style:italic;line-height:1;}
.logo-rif{font-size:8pt;color:#555;margin-top:3px;}
.empresa{text-align:right;font-size:9.5pt;line-height:1.75;}
.empresa b{font-size:10.5pt;text-decoration:underline;}
.titulo-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.fecha-tbl{border-collapse:collapse;font-size:10.5pt;}
.fecha-tbl th,.fecha-tbl td{border:1.5px solid #000;padding:5px 16px;text-align:center;font-weight:700;}
.ctrl{color:#cc0000;font-weight:700;font-size:13pt;text-align:center;}
.titulo-recibo{font-size:22pt;font-weight:900;letter-spacing:3px;text-align:center;}
.monto-box{border:2.5px solid #000;padding:8px 22px;text-align:center;min-width:110px;}
.monto-label{font-size:9pt;font-weight:700;}
.monto-val{font-size:22pt;font-weight:900;}
hr{border:0;border-top:1.5px solid #000;margin:10px 0;}
.field-row{display:flex;align-items:baseline;gap:14px;margin-bottom:14px;}
.flabel{font-weight:700;white-space:nowrap;min-width:138px;font-size:13pt;}
.fval{border-bottom:1.5px solid #000;flex:1;padding-bottom:3px;font-size:13pt;}
.italic{font-style:italic;}
.right{text-align:right;font-size:10pt;font-weight:700;margin-bottom:10px;}
.forma-row{display:flex;align-items:flex-start;gap:20px;margin:22px 0 42px;flex-wrap:wrap;}
.forma-lbl{font-weight:700;text-decoration:underline;font-size:12pt;line-height:1.6;min-width:78px;}
.checks{display:flex;flex-wrap:wrap;gap:18px;align-items:center;font-size:11pt;}
.checks label{display:flex;align-items:center;gap:6px;white-space:nowrap;}
.titular-txt{font-size:11pt;white-space:nowrap;}
.titular-line{display:inline-block;border-bottom:1.5px solid #000;min-width:150px;margin-left:6px;}
.firmas{display:flex;justify-content:space-between;margin-top:60px;}
.firma{width:42%;text-align:center;}
.firma-linea{border-top:1.5px solid #000;padding-top:8px;font-weight:700;font-size:11pt;}
</style>
</head><body>

<div class="header">
  <div>
    <img src="${window.location.origin}/logo_recibo.png" alt="Logo Grupo Jenu" style="height:90px;width:auto;">
  </div>
  <div class="empresa">
    <div><b>Grupo Inmobiliario y de Transporte</b></div>
    <div><b>Je Nu &amp; Asociados, C.A.</b></div>
    <div>Rif.: J-50255135-2</div>
    <div>Calle Girardot, con Av. Santa Irene, Punto Fijo - Edo.</div>
    <div>Falcon, Zona Postal 4102. <strong>Tel&eacute;fono:</strong> 0412-6937658</div>
  </div>
</div>

<div class="titulo-row">
  <table class="fecha-tbl">
    <tr><th>DIA</th><th>MES</th><th>A&Ntilde;O</th></tr>
    <tr><td>${dia}</td><td>${mes}</td><td>${anio}</td></tr>
  </table>
  <div>
    <div class="ctrl">${control}</div>
    <div class="titulo-recibo">RECIBO</div>
  </div>
  <div class="monto-box">
    <div class="monto-label">BS/$.</div>
    <div class="monto-val">${monto}</div>
  </div>
</div>

<hr>

<div class="field-row">
  <span class="flabel">Recib&iacute; de:</span>
  <span class="fval">${cliente}</span>
</div>
<div class="field-row">
  <span class="flabel">La Suma de:</span>
  <span class="fval italic">${sumaLetras}</span>
</div>
<div class="right">Bolivares / D&oacute;lares</div>

<hr>

<div class="field-row">
  <span class="flabel">Por Concepto de</span>
  <span class="fval">${concepto}</span>
</div>

<hr>

<div class="forma-row">
  <div class="forma-lbl">Forma de<br>Pago:</div>
  <div class="checks">
    <label>Efectivo ${box(cEfectivo)}</label>
    <label>Transferencia Bs ${box(cTransf)}</label>
    <label>Pago Movil ${box(cMovil)}</label>
    <label>Zelle ${box(cZelle)}</label>
    <label>Binance ${box(cBinance)}</label>
    <label>Otro ${box(cOtro)}</label>
  </div>
  <div class="titular-txt">Titular:<span class="titular-line">${titular}</span></div>
</div>

<div class="firmas">
  <div class="firma">
    <img src="${window.location.origin}/firma_recibo.png" alt="Firma" style="height:64px;width:auto;display:block;margin:0 auto 4px;">
    <div class="firma-linea">Recibi Conforme</div>
  </div>
  <div class="firma"><div style="height:68px;"></div><div class="firma-linea">Entregue Conforme</div></div>
</div>

</body></html>`);
    ventana.document.close();
    ventana.focus();
    setTimeout(() => { ventana.print(); }, 600);
}

function confirmarPago() {
    document.getElementById('formPago').submit();
}

function abrirEditarPago(cuotaId, numeroCuota, montoPagadoActual, abonoCapitalActual) {
    document.getElementById('editPagoNumCuota').textContent = numeroCuota;
    document.getElementById('editPagoMonto').value = montoPagadoActual;
    document.getElementById('editPagoAbono').value = abonoCapitalActual;
    document.getElementById('formEditarPago').action = '/contratos/cuota/' + cuotaId + '/pagar';
    document.getElementById('modalEditarPago').style.display = 'flex';
}

function togglePagoFields() {
    const forma = document.getElementById('pagoFormaPago').value;
    const extraFields = document.getElementById('pagoExtraFields');
    const fTasa = document.getElementById('pagoFieldTasa');
    const fBancoDest = document.getElementById('pagoFieldBancoDestino');
    const fBancoOrig = document.getElementById('pagoFieldBancoOrigen');
    const fRef = document.getElementById('pagoFieldReferencia');

    const iptTasa = document.getElementById('pagoTasaCambio');
    const iptBancoDest = document.getElementById('pagoBancoDestino');
    const iptBancoOrig = document.getElementById('pagoBancoOrigen');
    const iptRef = document.getElementById('pagoReferencia');

    // Reset validations and values
    iptTasa.required = false; iptTasa.value = '';
    iptBancoDest.required = false; iptBancoDest.value = '';
    iptBancoOrig.required = false; iptBancoOrig.value = '';
    iptRef.required = false; iptRef.value = '';

    if (!forma || forma === 'EFECTIVO' || forma === 'CRUCE') {
        extraFields.style.display = 'none';
        fTasa.style.display = 'none';
        fBancoDest.style.display = 'none';
        fBancoOrig.style.display = 'none';
        fRef.style.display = 'none';
        return;
    }

    extraFields.style.display = 'block';
    
    if (forma === 'ZELLE' || forma === 'BINANCE' || forma === 'TRANSFERENCIA_DIVISAS') {
        fTasa.style.display = 'none';
        fBancoDest.style.display = 'none';
        fBancoOrig.style.display = 'none';
        
        fRef.style.display = 'block';
        iptRef.required = true;
    } else if (forma === 'TRANSFERENCIA_BCV' || forma === 'PAGO_MOVIL' || forma === 'DEPOSITO') {
        fTasa.style.display = 'block';
        iptTasa.required = true;

        fBancoDest.style.display = 'block';
        iptBancoDest.required = true;

        fBancoOrig.style.display = 'block';
        iptBancoOrig.required = true;

        fRef.style.display = 'block';
        iptRef.required = true;
    }
}

function toggleGarantiaNueva(valor) {
    const campo = document.getElementById('campoGarantiaNueva');
    const input = document.getElementById('inputGarantiaNueva');
    if (valor === 'nueva') {
        campo.style.display = 'block';
        input.required = true;
    } else {
        campo.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
</script>
@endsection
