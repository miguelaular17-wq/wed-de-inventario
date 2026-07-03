@extends('layouts.app')
@section('title', 'Flujo de Caja y Disponibilidad')
@section('content')

<style>
    /* Estilos copiados exactamente del Excel */
    .excel-table {
        border-collapse: collapse;
        font-family: Arial, sans-serif;
        font-size: 14px;
        background-color: white;
        border: 2px solid black;
    }
    .excel-table th, .excel-table td {
        border: 1px solid black;
        padding: 4px;
    }
    .excel-table thead th {
        font-weight: bold;
        text-align: center;
    }
    .excel-header-row {
        background-color: #d9e1f2 !important;
        text-align: center;
        font-weight: bold;
    }
    .excel-header-col {
        background-color: #d9e1f2 !important;
        font-weight: bold;
    }
    .tasa-header {
        background-color: #fce4d6 !important;
        font-weight: bold;
        text-align: center;
    }
    .tasa-value {
        background-color: #fce4d6 !important;
        font-weight: bold;
    }
    .excel-input {
        width: 100%;
        border: none;
        background: transparent;
        text-align: right;
        outline: none;
    }
    .excel-input:focus {
        background-color: #e9ecef;
    }
    .tc-cell {
        width: 25px;
        text-align: center;
        border: 1px solid black;
    }
    
    /* Resumen derecho */
    .resumen-table {
        border-collapse: collapse;
        font-family: Arial, sans-serif;
        font-size: 14px;
        background-color: white;
        border: 2px solid black;
        width: 100%;
        margin-bottom: 20px;
    }
    .resumen-table th, .resumen-table td {
        border: 1px solid black;
        padding: 4px;
    }
    .resumen-header {
        background-color: #d9e1f2 !important;
        font-weight: bold;
        text-align: center;
    }
    .resumen-value {
        background-color: #c6e0b4 !important;
        text-align: right;
        font-weight: bold;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0 text-gray-800">Flujo de Caja y Disponibilidad</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoEgresoModal">
            <i class="bi bi-plus-circle"></i> Nuevo Egreso
        </button>
    </div>

    <div class="row">
        <!-- TABLA DISPONIBILIDAD (IZQUIERDA) -->
        <div class="col-xl-7 col-lg-8 mb-4">
            <div class="table-responsive">
                <table class="excel-table w-100">
                    <thead>
                        <tr>
                            <th colspan="5" class="excel-header-row" style="border: 2px solid black; border-right: 1px solid black;">DISPONIBILIDAD EN TIEMPO REAL</th>
                            <th class="tasa-header" style="border-top: 2px solid black;">TASA BCV USD</th>
                            <th class="tasa-value" style="border-top: 2px solid black; border-right: 2px solid black;">
                                <input type="number" step="0.01" class="excel-input text-center fw-bold" 
                                    value="{{ $resumen->tasa_bcv_usd }}" data-type="resumen" data-id="{{ $resumen->id }}" data-field="tasa_bcv_usd">
                            </th>
                        </tr>
                        <tr class="excel-header-row" style="border-bottom: 2px solid black;">
                            <th style="width: 25px; border-left: 2px solid black;">TC</th>
                            <th>BANCO</th>
                            <th>TITULAR</th>
                            <th>BS TC</th>
                            <th>BS DISPONIBLES</th>
                            <th>USD TC</th>
                            <th style="border-right: 2px solid black;">USD DISP.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cuentasBancarias as $cb)
                        <tr>
                            <td class="tc-cell" style="background-color: {{ $cb->color_tc ?: '#fff' }}; border-left: 2px solid black;"></td>
                            <td>{{ $cb->banco }}</td>
                            <td>{{ $cb->titular }}</td>
                            <td>
                                Bs. <input type="number" step="0.01" class="excel-input" style="width:calc(100% - 30px);"
                                    value="{{ $cb->bs_tc }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_tc">
                            </td>
                            <td>
                                Bs. <input type="number" step="0.01" class="excel-input" style="width:calc(100% - 30px);"
                                    value="{{ $cb->bs_disponibles }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="bs_disponibles">
                            </td>
                            <td>
                                $ <input type="number" step="0.01" class="excel-input" style="width:calc(100% - 20px);"
                                    value="{{ $cb->usd_tc }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_tc">
                            </td>
                            <td style="border-right: 2px solid black; {{ $loop->last ? 'background-color:#c6e0b4;' : '' }}">
                                $ <input type="number" step="0.01" class="excel-input" style="width:calc(100% - 20px);"
                                    value="{{ $cb->usd_disp }}" data-type="cuenta" data-id="{{ $cb->id }}" data-field="usd_disp">
                            </td>
                        </tr>
                        @endforeach
                        <!-- LAST ROW SUMMARY -->
                        <tr style="border-bottom: 2px solid black;">
                            <td colspan="3" class="text-end fw-bold" style="background-color:#bdd7ee; border-left: 2px solid black; border-bottom: 2px solid black;">Bs.</td>
                            <td style="background-color:#bdd7ee; border-bottom: 2px solid black;" class="fw-bold text-end">Bs. <span id="sum_bs_tc">0.00</span></td>
                            <td style="background-color:#bdd7ee; border-bottom: 2px solid black;" class="fw-bold text-end">Bs. <span id="sum_bs_disp">0.00</span></td>
                            <td style="background-color:#c6e0b4; border-bottom: 2px solid black;" class="fw-bold text-end">$ <span id="sum_usd_tc">0.00</span></td>
                            <td style="background-color:#c6e0b4; border-right: 2px solid black; border-bottom: 2px solid black;" class="fw-bold text-end">$ <span id="sum_usd_disp">0.00</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PANELES DERECHA (LEYENDA Y RESUMEN) -->
        <div class="col-xl-3 col-lg-4 mb-4">
            
            <table class="resumen-table mb-4">
                <thead>
                    <tr><th colspan="2" class="resumen-header" style="border: 2px solid black;">TIPO DE CUENTA</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold text-center" style="border-left: 2px solid black;">P.V/TRANSF/P.M</td>
                        <td style="background-color:#f4b183; width:40px; border-right: 2px solid black;"></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-center" style="border-left: 2px solid black;">TERCEROS P.V/P.M</td>
                        <td style="background-color:#ff0000; border-right: 2px solid black;"></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-center" style="border-left: 2px solid black;">CASHEA</td>
                        <td style="background-color:#ffff00; border-right: 2px solid black;"></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-center" style="border-left: 2px solid black;">AVANCES</td>
                        <td style="background-color:#0070c0; border-right: 2px solid black;"></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-center" style="border-left: 2px solid black; border-bottom: 2px solid black;">B/MOVIMIENTO</td>
                        <td style="background-color:#fff; border-right: 2px solid black; border-bottom: 2px solid black;"></td>
                    </tr>
                </tbody>
            </table>

            <table class="resumen-table">
                <tbody>
                    <tr>
                        <td class="resumen-header" style="border: 2px solid black; padding: 12px;">SALDO INICIAL</td>
                    </tr>
                    <tr>
                        <td class="resumen-value" style="border-left: 2px solid black; border-right: 2px solid black; padding: 6px;">
                            $<input type="number" step="0.01" class="excel-input text-end" style="width:calc(100% - 20px); font-weight:bold;"
                                value="{{ $resumen->saldo_inicial }}" data-type="resumen" data-id="{{ $resumen->id }}" data-field="saldo_inicial">
                        </td>
                    </tr>
                    <tr>
                        <td class="resumen-header" style="border-left: 2px solid black; border-right: 2px solid black; padding: 12px;">TOTAL SALIDAS BS</td>
                    </tr>
                    <tr>
                        <td class="resumen-value" style="border-left: 2px solid black; border-right: 2px solid black; padding: 6px;">
                            ${{ number_format($total_salidas_bs, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="resumen-header" style="border-left: 2px solid black; border-right: 2px solid black; padding: 12px;">QUEDA DEL DIA ANTERIOR</td>
                    </tr>
                    <tr>
                        <td class="resumen-value" style="border-left: 2px solid black; border-right: 2px solid black; padding: 6px;">
                            $<input type="number" step="0.01" class="excel-input text-end" style="width:calc(100% - 20px); font-weight:bold;"
                                value="{{ $resumen->queda_dia_anterior }}" data-type="resumen" data-id="{{ $resumen->id }}" data-field="queda_dia_anterior">
                        </td>
                    </tr>
                    <tr>
                        <td class="resumen-header text-danger" style="border-left: 2px solid black; border-right: 2px solid black; padding: 12px;">TOTAL DIFERENCIAL CAMBIARIO</td>
                    </tr>
                    <tr>
                        <td class="resumen-value" style="border-left: 2px solid black; border-right: 2px solid black; padding: 6px;">
                            ${{ number_format($total_diferencial_cambiario, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="resumen-header text-danger" style="border-left: 2px solid black; border-right: 2px solid black; padding: 12px;">% TOTAL DIFERENCIAL CAMBIARIO</td>
                    </tr>
                    <tr>
                        <td class="resumen-value" style="border: 2px solid black; border-top: 1px solid black; padding: 6px;">
                            <input type="number" step="0.01" class="excel-input text-center" style="width:calc(100% - 20px); font-weight:bold;"
                                value="{{ $resumen->porcentaje_total_diferencial }}" data-type="resumen" data-id="{{ $resumen->id }}" data-field="porcentaje_total_diferencial">%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EGRESOS REALIZADOS -->
    <h4 class="mb-3 mt-4">EGRESOS REALIZADOS</h4>
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
    // Calculadora Egreso
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

    // AJAX Guardado en Vivo
    const editables = document.querySelectorAll('.excel-input');
    
    function updateSums() {
        let bsTc = 0, bsDisp = 0, usdTc = 0, usdDisp = 0;
        document.querySelectorAll('input[data-field="bs_tc"]').forEach(i => bsTc += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="bs_disponibles"]').forEach(i => bsDisp += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="usd_tc"]').forEach(i => usdTc += parseFloat(i.value)||0);
        document.querySelectorAll('input[data-field="usd_disp"]').forEach(i => usdDisp += parseFloat(i.value)||0);
        
        // Sumar campos fijos si es necesario, o solo mostrarlos
        document.getElementById('sum_bs_tc').textContent = bsTc.toFixed(2);
        document.getElementById('sum_bs_disp').textContent = bsDisp.toFixed(2);
        document.getElementById('sum_usd_tc').textContent = usdTc.toFixed(2);
        document.getElementById('sum_usd_disp').textContent = usdDisp.toFixed(2);
    }
    
    updateSums(); // Init sums

    editables.forEach(input => {
        input.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const field = this.getAttribute('data-field');
            const value = this.value;
            
            updateSums();

            let url = '';
            if (type === 'cuenta') {
                url = `/finanzas/flujo-caja/cuenta/${id}`;
            } else {
                url = `/finanzas/flujo-caja/resumen/${id}`;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ field: field, value: value })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.style.backgroundColor = '#d1e7dd';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                } else {
                    alert('Error guardando el campo');
                }
            })
            .catch(err => console.error(err));
        });
    });
});
</script>
@endsection
