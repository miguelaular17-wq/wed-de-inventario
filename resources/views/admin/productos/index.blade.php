@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<div class="content-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Catálogo de Productos</h2>
            <p class="text-muted">Consulta de stock e histórico por sede</p>
        </div>
    </div>

    <div class="card card-custom mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.productos.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Buscar producto</label>
                    <input type="text" name="buscar" class="form-control" value="{{ $buscar ?? '' }}" placeholder="Código o nombre...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sede a consultar</label>
                    <select name="sede" class="form-select">
                        @foreach($sedes as $s)
                            <option value="{{ $s }}" {{ $sedeSeleccionada === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
                @if($buscar)
                <div class="col-md-2">
                    <a href="{{ route('admin.productos.index', ['sede' => $sedeSeleccionada]) }}" class="btn btn-light w-100">Limpiar</a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Proveeedor</th>
                            <th class="text-center">Stock en {{ $sedeSeleccionada }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['codigo'] ?? '—' }}</td>
                                <td>{{ $row['producto'] ?? '—' }}</td>
                                <td>{{ $row['categoria'] ?? '—' }}</td>
                                <td>{{ $row['proveedor'] ?? '—' }}</td>
                                <td class="text-center">
                                    @if(($row['stock'] ?? 0) > 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary stock-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#historyModal"
                                            data-producto="{{ $row['producto'] }}"
                                            data-sede="{{ $sedeSeleccionada }}"
                                            data-stock="{{ $row['stock'] }}"
                                            data-compra="{{ !empty($row['ultima_compra']) ? \Carbon\Carbon::parse($row['ultima_compra'])->format('d/m/Y') : 'Sin registro' }}"
                                            data-venta="{{ !empty($row['ultima_venta']) ? \Carbon\Carbon::parse($row['ultima_venta'])->format('d/m/Y') : 'Sin registro' }}">
                                            <strong>{{ $row['stock'] }}</strong>
                                        </button>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No se encontraron productos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        {{ $rows->links() }}
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="historyModalLabel">Histórico del Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6 id="modalProductName" class="text-primary mb-3"></h6>
        <div class="row g-3">
            <div class="col-6">
                <div class="p-3 border rounded bg-light text-center">
                    <span class="d-block text-muted small mb-1">Sede</span>
                    <strong id="modalSedeName" class="fs-5"></strong>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 border rounded bg-light text-center">
                    <span class="d-block text-muted small mb-1">Stock Actual</span>
                    <strong id="modalStock" class="fs-5 text-dark"></strong>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 border rounded bg-white text-center">
                    <span class="d-block text-muted small mb-1">Última Compra</span>
                    <strong id="modalUltimaCompra"></strong>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 border rounded bg-white text-center">
                    <span class="d-block text-muted small mb-1">Última Venta</span>
                    <strong id="modalUltimaVenta"></strong>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const historyModal = document.getElementById('historyModal');
    if (historyModal) {
        historyModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            // Extract info from data-bs-* attributes
            const producto = button.getAttribute('data-producto');
            const sede = button.getAttribute('data-sede');
            const stock = button.getAttribute('data-stock');
            const compra = button.getAttribute('data-compra');
            const venta = button.getAttribute('data-venta');
            
            // Update the modal's content
            document.getElementById('modalProductName').textContent = producto;
            document.getElementById('modalSedeName').textContent = sede;
            document.getElementById('modalStock').textContent = stock;
            document.getElementById('modalUltimaCompra').textContent = compra;
            document.getElementById('modalUltimaVenta').textContent = venta;
        });
    }
});
</script>
@endpush
