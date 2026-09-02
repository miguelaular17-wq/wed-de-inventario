@extends('layouts.app')

@section('title', $orden->codigo())

@section('content')
<div style="padding:20px;max-width:960px;margin:0 auto;">
    <a href="{{ route('servicio.ordenes.index') }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Órdenes</a>
    <div class="panel-header-flex" style="margin:10px 0 16px;">
        <div>
            <h2 style="font-weight:700;margin:0;">
                {{ $orden->codigo() }}
                @if($orden->excedePresupuesto())
                    <span title="Costos superan el presupuesto">⚠️</span>
                @endif
            </h2>
            <p class="muted" style="margin:4px 0 0;">
                {{ $orden->sede }} · {{ $orden->etiquetaEstado() }} · {{ $orden->etiquetaPrioridad() }}
                @if($orden->transferenciaPendiente())
                    · <span style="color:#b45309;">Transferencia pendiente desde {{ $orden->sede_origen_transfer }}</span>
                @elseif($orden->sede_origen_transfer)
                    · <span style="color:#15803d;">Recibida de {{ $orden->sede_origen_transfer }}</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($orden->puedeConfirmarRecepcion(auth()->user()))
                <form method="POST" action="{{ route('servicio.ordenes.confirmar_recepcion', $orden) }}">
                    @csrf
                    <button class="btn primary" type="submit">Confirmar recepción</button>
                </form>
            @endif
            <a class="btn primary" href="{{ route('servicio.ordenes.edit', $orden) }}">Editar</a>
        </div>
    </div>

    @if($orden->excedePresupuesto())
        <div style="background:#fffbeb;border:1px solid #fcd34d;padding:12px 16px;border-radius:8px;margin-bottom:16px;color:#92400e;">
            Los costos (${{ number_format($orden->costoTotal(), 2) }}) superan el presupuesto (${{ number_format($orden->presupuesto, 2) }}). Confirma con el cliente antes de entregar.
        </div>
    @endif

    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <div><span class="muted">Cliente</span><div><strong>{{ $orden->cliente_nombre }}</strong></div></div>
            <div><span class="muted">Teléfono</span><div>{{ $orden->cliente_telefono ?: '—' }}</div></div>
            <div><span class="muted">Equipo</span><div>{{ $orden->equipo ?: '—' }}</div></div>
            <div><span class="muted">Serial</span><div>{{ $orden->serial ?: '—' }}</div></div>
            <div><span class="muted">Ingreso</span><div>{{ $orden->fecha_ingreso?->format('d/m/Y') }}</div></div>
            <div><span class="muted">Prometida</span><div>{{ $orden->fecha_prometida?->format('d/m/Y') ?: '—' }}</div></div>
            <div><span class="muted">Técnico</span><div>{{ $orden->tecnico?->name ?: '—' }}</div></div>
            <div><span class="muted">Repuestos descontados</span><div>{{ $orden->repuestos_descontados_at ? $orden->repuestos_descontados_at->format('d/m/Y H:i') : 'No' }}</div></div>
        </div>
        <hr style="border:none;border-top:1px dashed #e2e8f0;margin:20px 0;">
        <p class="muted" style="margin:0 0 4px;">Falla</p>
        <p>{{ $orden->falla ?: '—' }}</p>
        <p class="muted" style="margin:16px 0 4px;">Diagnóstico</p>
        <p>{{ $orden->diagnostico ?: '—' }}</p>
    </div>

    <div class="panel" style="padding:24px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Costos</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            <div class="nomina-kpi"><span>Presupuesto</span><strong>${{ number_format($orden->presupuesto ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Mano de obra</span><strong>${{ number_format($orden->costo_mano_obra ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Repuestos</span><strong>${{ number_format($orden->costo_refacciones ?? 0, 2) }}</strong></div>
            <div class="nomina-kpi"><span>Total</span><strong>${{ number_format($orden->costoTotal(), 2) }}</strong></div>
        </div>
        @if($orden->repuestosLineas->isNotEmpty())
            <table class="data-table" style="margin-top:16px;">
                <thead><tr><th>Repuesto</th><th>Cant.</th><th>P. venta</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                    @foreach($orden->repuestosLineas as $linea)
                        <tr>
                            <td>{{ $linea->repuesto?->nombre ?? '—' }}</td>
                            <td>{{ $linea->cantidad }}</td>
                            <td>${{ number_format($linea->precio_unitario, 2) }}</td>
                            <td>${{ number_format($linea->subtotalVenta(), 2) }}</td>
                            <td>{{ $linea->descontado ? '✓ Descontado' : 'Pendiente' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

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
@endsection
