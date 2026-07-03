@extends('layouts.app')
@section('title', 'Flujo de Caja')
@section('content')
<div class="container-fluid px-4 py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="font-weight:700; color:#1e293b;">Flujo de Caja</h2>
        <button class="btn btn-primary" style="border-radius:8px;" data-bs-toggle="modal" data-bs-target="#nuevoEgresoModal">+ Nuevo Egreso</button>
    </div>

    <!-- EGRESOS REALIZADOS -->
    <h4 class="mb-3">EGRESOS REALIZADOS</h4>
    <div class="card shadow-sm border-0 mb-5" style="border-radius:12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th>Fecha</th>
                            <th>Banco y Titular</th>
                            <th>Motivo</th>
                            <th class="text-end">USD</th>
                            <th class="text-end">Tasa Cambio</th>
                            <th class="text-end">Dif. Cambiario</th>
                            <th class="text-end">BS</th>
                            <th class="text-end">Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($egresos_realizados as $mov)
                            <tr>
                                <td>{{ $mov->fecha }}</td>
                                <td>
                                    <strong>{{ $mov->banco }}</strong><br>
                                    <small class="text-muted">{{ $mov->titular }}</small>
                                </td>
                                <td>{{ $mov->motivo ?: '-' }}</td>
                                <td class="text-end">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->tasa_cambio ? number_format($mov->tasa_cambio, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay egresos realizados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- OTROS EGRESOS (AVANCES Y CAMBIOS) -->
    <h4 class="mb-3">OTROS EGRESOS (AVANCES Y CAMBIOS)</h4>
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th>Fecha</th>
                            <th>Banco y Titular</th>
                            <th>Motivo</th>
                            <th class="text-end">USD</th>
                            <th class="text-end">Tasa Cambio</th>
                            <th class="text-end">Dif. Cambiario</th>
                            <th class="text-end">BS</th>
                            <th class="text-end">Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($otros_egresos as $mov)
                            <tr>
                                <td>{{ $mov->fecha }}</td>
                                <td>
                                    <strong>{{ $mov->banco }}</strong><br>
                                    <small class="text-muted">{{ $mov->titular }}</small>
                                </td>
                                <td>{{ $mov->motivo ?: '-' }}</td>
                                <td class="text-end">{{ $mov->monto_usd ? '$'.number_format($mov->monto_usd, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->tasa_cambio ? number_format($mov->tasa_cambio, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->diferencial_cambiario ? number_format($mov->diferencial_cambiario, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->monto_bs ? 'Bs.'.number_format($mov->monto_bs, 2) : '-' }}</td>
                                <td class="text-end">{{ $mov->comision ? number_format($mov->comision, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay otros egresos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Egreso -->
<div class="modal fade" id="nuevoEgresoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('finanzas.store_egreso') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Egreso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Egreso</label>
                        <select name="categoria_egreso" class="form-select" required>
                            <option value="egreso_realizado">EGRESOS REALIZADOS</option>
                            <option value="otros_egresos">OTROS EGRESOS (AVANCES Y CAMBIOS)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Banco y Titular</label>
                        <select name="banco_titular" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta['banco'] }}|{{ $cuenta['titular'] }}|{{ $cuenta['categoria'] }}">
                                    {{ $cuenta['banco'] }} - {{ $cuenta['titular'] }} ({{ $cuenta['categoria'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Monto USD</label>
                        <input type="number" step="0.01" name="monto_usd" id="monto_usd" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tasa de Cambio</label>
                        <input type="number" step="0.01" name="tasa_cambio" id="tasa_cambio" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monto BS <small class="text-muted">(Auto)</small></label>
                        <input type="number" step="0.01" name="monto_bs" id="monto_bs" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Diferencial Cambiario</label>
                        <input type="number" step="0.01" name="diferencial_cambiario" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Comisión</label>
                        <input type="number" step="0.01" name="comision" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Motivo</label>
                        <input type="text" name="motivo" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Egreso</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const usdInput = document.getElementById('monto_usd');
    const tasaInput = document.getElementById('tasa_cambio');
    const bsInput = document.getElementById('monto_bs');

    function calcularBs() {
        const usd = parseFloat(usdInput.value) || 0;
        const tasa = parseFloat(tasaInput.value) || 0;
        if(usd > 0 && tasa > 0) {
            bsInput.value = (usd * tasa).toFixed(2);
        }
    }

    usdInput.addEventListener('input', calcularBs);
    tasaInput.addEventListener('input', calcularBs);
});
</script>
@endsection
