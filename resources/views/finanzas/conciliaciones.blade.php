@extends('layouts.app')
@section('title', 'Conciliación Bancaria Inteligente')
@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="font-weight:700; color:#1e293b;">Conciliación Bancaria Inteligente</h2>
        @if($lineas->count() > 0)
        <form action="{{ route('finanzas.conciliaciones.clear') }}" method="POST" onsubmit="return confirm('¿Seguro que deseas borrar todas las líneas y empezar de cero?');">
            @csrf
            <button class="btn btn-danger" style="border-radius:8px;">Limpiar y Empezar de Nuevo</button>
        </form>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($lineas->count() == 0)
    <!-- UPLOAD ZONE -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:12px; background: #f8fafc;">
        <div class="card-body p-4">
            <h5 class="card-title" style="font-weight: 700; color: #334155;">1. Subir Estado de Cuenta (Formato CSV)</h5>
            <p class="text-muted">Exporta tu estado de cuenta en formato CSV y súbelo. Indica en qué número de columna está cada dato (la primera columna es la 0, la segunda es la 1, etc).</p>
            
            <form action="{{ route('finanzas.conciliaciones.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Archivo CSV:</label>
                        <input type="file" name="file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Columna Fecha:</label>
                        <input type="number" name="col_fecha" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Columna Descripción:</label>
                        <input type="number" name="col_descripcion" class="form-control" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Columna Referencia:</label>
                        <input type="number" name="col_referencia" class="form-control" value="2" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Columna Monto:</label>
                        <input type="number" name="col_monto" class="form-control" value="3" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100" style="border-radius:8px; font-weight: bold;">Subir y Analizar</button>
            </form>
        </div>
    </div>
    @else
    
    <!-- RESULTS ZONE -->
    <div class="row">
        <!-- FALTAN EN SISTEMA -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm border-0" style="border-radius:12px; border-top: 4px solid #ef4444 !important;">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Falta en Sistema (Gastos del Banco no registrados)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color:#fef2f2;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Referencia</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($faltan_sistema as $linea)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $linea->descripcion }}</td>
                                        <td>{{ $linea->referencia }}</td>
                                        <td class="text-end fw-bold text-danger">{{ number_format($linea->monto, 2) }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMissingModal{{ $linea->id }}">
                                                + Agregar a Flujo
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Modal -->
                                    <div class="modal fade" id="addMissingModal{{ $linea->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('finanzas.conciliaciones.add_missing') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="linea_id" value="{{ $linea->id }}">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Clasificar Gasto Faltante</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Gasto:</label>
                                                            <input type="text" class="form-control" value="{{ $linea->descripcion }}" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Monto:</label>
                                                            <input type="text" class="form-control" value="{{ number_format($linea->monto, 2) }}" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Categoría de Egreso:</label>
                                                            <select name="categoria_egreso" class="form-select" required>
                                                                <option value="egreso_realizado">Egreso Realizado</option>
                                                                <option value="otros_egresos">Otros Egresos</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Cuenta Bancaria (Sistema):</label>
                                                            <select name="cuenta" class="form-select" required>
                                                                @foreach($cuentasBancarias as $cta)
                                                                    <option value="{{ $cta->nombre }}">{{ $cta->nombre }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-success">Guardar y Conciliar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <!-- CONCILIADOS -->
            <div class="card shadow-sm border-0 h-100" style="border-radius:12px; border-top: 4px solid #10b981 !important;">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success fw-bold"><i class="bi bi-check-circle"></i> Emparejados Correctamente</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color:#ecfdf5;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($conciliados as $linea)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $linea->descripcion }}</td>
                                        <td class="text-end">{{ number_format($linea->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <!-- FALTAN EN BANCO (TRANSITO) -->
            <div class="card shadow-sm border-0 h-100" style="border-radius:12px; border-top: 4px solid #f59e0b !important;">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-warning fw-bold" style="color: #d97706 !important;"><i class="bi bi-clock-history"></i> Tránsito (En Sistema, no en Banco)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color:#fffbeb;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($faltan_banco as $flujo)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($flujo->fecha)->format('d/m/Y') }}</td>
                                        <td>{{ $flujo->concepto }} <small class="text-muted d-block">{{ $flujo->referencia }}</small></td>
                                        <td class="text-end fw-bold text-warning" style="color: #d97706 !important;">{{ number_format($flujo->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
