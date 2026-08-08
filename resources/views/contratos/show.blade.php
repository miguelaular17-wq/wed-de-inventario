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
                <div style="font-weight: 700; font-size: 1.1rem; color: #7c3aed;">${{ number_format($contrato->getRawOriginal('total_a_pagar'), 2) }}</div>
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
                            <td style="text-align: right; font-weight: 600; color: {{ $cuota->saldo > 0 ? '#dc2626' : '#059669' }}; padding: 12px;">
                                {{ in_array($cuota->estatus, ['prestamo','acumulado']) ? '—' : '$'.number_format($cuota->saldo, 2) }}
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
                                        <button type="button" onclick="abrirPago({{ $cuota->id }}, {{ $cuota->saldo }}, {{ $cuota->numero_cuota }})"
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
                    @forelse($contrato->seguimientos->whereNotIn('resultado', ['PAGO_COMPLETO', 'PAGO_PARCIAL']) as $seg)
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
                <button type="submit" style="padding: 8px 20px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Registrar Pago</button>
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

<script>
function abrirPago(cuotaId, saldo, numCuota) {
    document.getElementById('formPago').action = '/contratos/cuota/' + cuotaId + '/pagar';
    document.getElementById('pagoMonto').value = saldo;
    document.getElementById('pagoNumCuota').textContent = numCuota;
    document.getElementById('modalPago').style.display = 'flex';
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
