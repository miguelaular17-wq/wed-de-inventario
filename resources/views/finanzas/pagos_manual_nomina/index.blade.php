@extends('layouts.app')

@section('title', 'Pagos manuales nómina')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Pagos manuales · sin empresa</h1>
            <p class="muted" style="margin:4px 0 0;">
                Nómina y comisiones enviadas desde el TXT. Ve pagando uno a uno; cada “Pagar” abre la factura con los datos listos.
            </p>
        </div>
        <div class="nomina-kpi" style="min-width:140px;">
            <span>Pendiente</span>
            <strong>${{ number_format($totalPendienteUsd, 2) }}</strong>
        </div>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field">
            <label>Estado</label>
            <select name="estado">
                <option value="PENDIENTE" @selected($estado === 'PENDIENTE')>Pendiente</option>
                <option value="PAGADO" @selected($estado === 'PAGADO')>Pagado</option>
                <option value="" @selected($estado === '')>Todos</option>
            </select>
        </div>
        <div class="field">
            <label>Tipo</label>
            <select name="tipo">
                <option value="">Todos</option>
                <option value="NOMINA" @selected($tipo === 'NOMINA')>Nómina</option>
                <option value="COMISION" @selected($tipo === 'COMISION')>Comisión</option>
            </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Cédula</th>
                    <th>Tipo</th>
                    <th>Quincena</th>
                    <th>USD</th>
                    <th>Bs</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $pago)
                    <tr>
                        <td>
                            <strong>{{ $pago->empleado?->nombre() ?? '—' }}</strong>
                        </td>
                        <td>{{ $pago->empleado?->cedula() ?: '—' }}</td>
                        <td>{{ $pago->etiquetaTipo() }}</td>
                        <td>{{ $pago->periodo?->etiqueta ?? '—' }}</td>
                        <td>${{ number_format($pago->monto_usd, 2) }}</td>
                        <td>Bs {{ number_format($pago->montoBs(), 2) }}</td>
                        <td>
                            {{ $pago->estado }}
                            @if($pago->enviadoPor)
                                <div class="muted" style="font-size:.72rem;">por {{ $pago->enviadoPor->name }}</div>
                            @endif
                        </td>
                        <td>
                            @if($pago->isPendiente())
                                <a class="btn primary" href="{{ route('finanzas.pagos_manual_nomina.pagar', $pago) }}">Pagar</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No hay pagos en este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $items->links() }}</div>
</div>
@endsection
