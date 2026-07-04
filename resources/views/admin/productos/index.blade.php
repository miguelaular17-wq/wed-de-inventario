@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<div class="page-header">
    <h1>Catálogo de Productos</h1>
    <p class="lead">Consulta de stock e histórico por sede</p>
</div>

<form method="GET" action="{{ route('admin.productos.index') }}" class="filter-bar">
    <div class="field field-wide">
        <label for="buscar">Buscar producto</label>
        <input type="search" id="buscar" name="buscar" value="{{ $buscar ?? '' }}" placeholder="Código o nombre..." autocomplete="off">
    </div>
    <div class="field">
        <label for="sede">Sede a consultar</label>
        <select id="sede" name="sede">
            @foreach($sedes as $s)
                <option value="{{ $s }}" @selected($sedeSeleccionada === $s)>{{ config('inventario.display.'.$s, $s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="display: flex; align-items: flex-end; gap: 8px;">
        <button type="submit" class="btn primary">Consultar</button>
        @if($buscar)
            <a href="{{ route('admin.productos.index', ['sede' => $sedeSeleccionada]) }}" class="btn secondary">Limpiar</a>
        @endif
    </div>
</form>

<section class="table-section-full">
    <div class="table-wrap table-wrap-full">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Proveeedor</th>
                    <th style="text-align: center;">Stock en {{ config('inventario.display.'.$sedeSeleccionada, $sedeSeleccionada) }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="font-family: ui-monospace, monospace; font-size: .85rem;">{{ $row['codigo'] ?? '—' }}</td>
                        <td style="font-weight: 500;">{{ $row['producto'] ?? '—' }}</td>
                        <td style="color: var(--muted); font-size: .9rem;">{{ $row['categoria'] ?? '—' }}</td>
                        <td style="color: var(--muted); font-size: .9rem;">{{ $row['proveedor'] ?? '—' }}</td>
                        <td style="text-align: center;">
                            @if(($row['stock'] ?? 0) > 0)
                                <button type="button" class="btn stock-btn" style="background: var(--blue); color: white; padding: 4px 12px; font-weight: bold; font-size: 1rem; border-radius: 6px;"
                                    onclick="openHistoryModal('{{ addslashes($row['producto']) }}', '{{ $sedeSeleccionada }}', '{{ $row['stock'] }}', '{{ !empty($row['ultima_compra']) ? \Carbon\Carbon::parse($row['ultima_compra'])->format('d/m/Y') : 'Sin registro' }}', '{{ !empty($row['ultima_venta']) ? \Carbon\Carbon::parse($row['ultima_venta'])->format('d/m/Y') : 'Sin registro' }}')">
                                    {{ $row['stock'] }}
                                </button>
                            @else
                                <span style="color: var(--muted); font-weight: bold;">0</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 32px; color: var(--muted);">No se encontraron productos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 20px;">
        {{ $rows->links('pagination::default') }}
    </div>
</section>

<!-- Vanilla JS Modal Overlay -->
<div id="historyModalOverlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; font-family: 'Outfit', sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border);">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Histórico del Producto</h3>
            <button type="button" onclick="closeHistoryModal()" style="background: transparent; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--muted);">&times;</button>
        </div>
        <div style="padding: 20px;">
            <h4 id="modalProductName" style="color: var(--blue); margin-top: 0; margin-bottom: 20px; font-size: 1.1rem; line-height: 1.4;"></h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                    <span style="display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Sede</span>
                    <strong id="modalSedeName" style="font-size: 1.2rem; color: var(--text);"></strong>
                </div>
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                    <span style="display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Stock Actual</span>
                    <strong id="modalStock" style="font-size: 1.3rem; color: var(--blue);"></strong>
                </div>
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                    <span style="display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Última Compra</span>
                    <strong id="modalUltimaCompra" style="font-size: 1rem; color: var(--text);"></strong>
                </div>
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 16px; text-align: center;">
                    <span style="display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Última Venta</span>
                    <strong id="modalUltimaVenta" style="font-size: 1rem; color: var(--text);"></strong>
                </div>
            </div>
        </div>
        <div style="padding: 16px 20px; border-top: 1px solid var(--border); text-align: right; background: #f8fafc;">
            <button type="button" class="btn primary" onclick="closeHistoryModal()" style="padding: 8px 24px;">Cerrar</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const modal = document.getElementById('historyModalOverlay');

    function openHistoryModal(producto, sede, stock, compra, venta) {
        document.getElementById('modalProductName').textContent = producto;
        document.getElementById('modalSedeName').textContent = sede;
        document.getElementById('modalStock').textContent = stock;
        document.getElementById('modalUltimaCompra').textContent = compra;
        document.getElementById('modalUltimaVenta').textContent = venta;
        
        modal.style.display = 'flex';
        // Add subtle fade in animation
        modal.animate([
            { opacity: 0 },
            { opacity: 1 }
        ], { duration: 150, fill: 'forwards' });
    }

    function closeHistoryModal() {
        const animation = modal.animate([
            { opacity: 1 },
            { opacity: 0 }
        ], { duration: 150, fill: 'forwards' });
        
        animation.onfinish = () => {
            modal.style.display = 'none';
        };
    }

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeHistoryModal();
        }
    });
</script>
@endpush
