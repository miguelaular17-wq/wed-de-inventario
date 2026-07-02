@extends('layouts.app')

@section('title', 'Histórico de Ventas Mensuales')

@section('content')
@push('head')
<style>
/* Estilos modernos y premium */
.dashboard-container {
    padding: 1rem;
    background: #f8fafc;
    min-height: calc(100vh - 60px);
}
.filters-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}
.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: flex-end;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #475569;
}
.form-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.btn-primary {
    background: #2563eb;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-primary:hover {
    background: #1d4ed8;
}
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    overflow: auto;
    max-height: 70vh;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table th, .data-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.data-table th {
    background: #f1f5f9;
    font-weight: 600;
    color: #334155;
    position: sticky;
    top: 0;
    z-index: 10;
}
.data-table tbody tr:hover {
    background-color: #f8fafc;
}
.col-number {
    text-align: right !important;
}
.col-total {
    font-weight: 700;
    background-color: #f0fdf4;
}
.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.nav-link {
    color: #2563eb;
    text-decoration: none;
    font-weight: 600;
}
.nav-link:hover {
    text-decoration: underline;
}
</style>
@endpush

<div class="dashboard-container">
    <div class="header-actions">
        <h2>Histórico de Ventas Mensuales (Comprador)</h2>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="{{ route('comprador.dashboard') }}" class="nav-link">&larr; Volver al Panel Principal</a>
            <a href="{{ route('comprador.historico.export', request()->query()) }}" class="btn-primary" style="display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                <span>📄</span> Exportar a PDF
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="{{ route('comprador.historico') }}" class="filters-grid">
            <div class="form-group">
                <label class="form-label">Desde (Mes)</label>
                <input type="month" name="start_month" class="form-input" value="{{ $startMonth }}">
            </div>
            <div class="form-group">
                <label class="form-label">Hasta (Mes)</label>
                <input type="month" name="end_month" class="form-input" value="{{ $endMonth }}">
            </div>
            
            <div class="form-group">
                <label class="form-label">Buscar Producto / Código</label>
                <input type="text" name="q" class="form-input" placeholder="Nombre o código..." value="{{ $q }}">
            </div>

            <div class="form-group">
                <label class="form-label">Proveedor</label>
                <select name="proveedor" class="form-input">
                    <option value="">Todos</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov }}" {{ $proveedor == $prov ? 'selected' : '' }}>{{ $prov }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-input">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat }}" {{ $categoria == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary" style="height: 38px;">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th class="col-number col-total">TOTAL RANGO</th>
                    @foreach($months as $month)
                        <th class="col-number">{{ \Carbon\Carbon::parse($month.'-01')->translatedFormat('M Y') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($pivoted as $row)
                    <tr>
                        <td>{{ $row['codigo'] }}</td>
                        <td>{{ $row['producto'] }}</td>
                        <td>{{ $row['categoria'] }}</td>
                        <td>{{ $row['proveedor'] }}</td>
                        <td class="col-number col-total">{{ number_format($row['total_general'], 0) }}</td>
                        @foreach($months as $month)
                            <td class="col-number">{{ $row['meses'][$month] > 0 ? number_format($row['meses'][$month], 0) : '-' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + count($months) }}" style="text-align: center; padding: 2rem; color: #64748b;">
                            No se encontraron ventas para los filtros seleccionados. Si no ves datos, asegúrate de correr el botón de "Sincronizar Histórico" en el Sincronizador de Python.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.875rem; color: #64748b;">
                Mostrando {{ $paginatedProducts->firstItem() ?? 0 }} al {{ $paginatedProducts->lastItem() ?? 0 }} de {{ $paginatedProducts->total() }} productos
            </div>
            <div>
                {{ $paginatedProducts->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
