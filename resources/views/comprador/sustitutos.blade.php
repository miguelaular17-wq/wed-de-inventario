@extends('layouts.app')

@section('title', 'Análisis de Sustitutos y Compras')

@section('content')
@push('head')
<style>
.dashboard-container {
    padding: 1rem;
    background: #f8fafc;
    min-height: calc(100vh - 60px);
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
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #2563eb;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
}
.btn-primary:hover {
    background: #1d4ed8;
}
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: white;
    color: #334155;
    padding: 0.5rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
}
.filters-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}
.form-input {
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.875rem;
}
.group-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.group-header {
    background: #f1f5f9;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    color: #1e293b;
    border-left: 4px solid #3b82f6;
    display: flex;
    justify-content: space-between;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table th, .data-table td {
    padding: 0.75rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
}
.data-table th {
    color: #64748b;
    font-weight: 600;
}
.text-danger { color: #ef4444; font-weight: 700; }
.text-success { color: #10b981; font-weight: 700; }
.text-right { text-align: right !important; }
</style>
@endpush

<div class="dashboard-container">
    <div class="header-actions">
        <div>
            <h2 style="margin:0;">Análisis de Sustitutos y Compras</h2>
            <p style="margin:4px 0 0; color:#64748b;">Compara productos similares por subcategoría y nombre para optimizar tus compras.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('comprador.dashboard') }}" class="btn-secondary">&larr; Volver</a>
            <a href="{{ request()->fullUrlWithQuery(['export_pdf' => 1]) }}" class="btn-primary">
                <span>📄</span> Exportar a PDF
            </a>
        </div>
    </div>

    <form class="filters-card" method="GET" action="{{ route('comprador.sustitutos') }}">
        <div style="display:flex; flex-direction:column; gap:0.5rem; flex:1;">
            <label style="font-weight:600; font-size:0.875rem;">Buscar</label>
            <input type="text" name="q" class="form-input" value="{{ $q }}" placeholder="Nombre o subcategoría...">
        </div>
        <div style="display:flex; flex-direction:column; gap:0.5rem; flex:1;">
            <label style="font-weight:600; font-size:0.875rem;">Categoría</label>
            <select name="categoria" class="form-input" onchange="this.form.submit()">
                <option value="">-- Selecciona una categoría --</option>
                @foreach($categorias as $cat)
                    <option value="{{ $cat }}" {{ $categoria == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary" style="height:38px;">Filtrar</button>
    </form>

    @forelse($sustitutos as $group)
        <div class="group-card">
            <div class="group-header">
                <span>Subcategoría: {{ $group['subcategoria'] }}</span>
                <span style="color:#64748b;">Tipo: {{ $group['keyword'] }}</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="15%">Código</th>
                        <th width="40%">Producto</th>
                        <th width="20%">Proveedor</th>
                        <th width="10%" class="text-right">Stock Global</th>
                        <th width="15%" class="text-right">Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['productos'] as $prod)
                        @php
                            $isExcluded = !!$prod->excluir_compras;
                            $rowStyle = $isExcluded ? 'background-color: #f8fafc; opacity: 0.6; cursor: pointer; user-select: none;' : 'cursor: pointer; user-select: none;';
                            $textStyle = $isExcluded ? 'text-decoration: line-through; color: var(--muted);' : '';
                        @endphp
                        <tr style="{{ $rowStyle }}" ondblclick="toggleExclude(this, {{ $prod->id }})" title="Doble clic para excluir/incluir de compras">
                            <td style="{{ $textStyle }}">{{ $prod->codigo }}</td>
                            <td style="font-weight:500; {{ $textStyle }}">{{ $prod->nombre }}</td>
                            <td style="{{ $textStyle }}">{{ $prod->proveedor ?: 'Sin proveedor' }}</td>
                            <td class="text-right {{ $prod->stock_total == 0 ? 'text-danger' : 'text-success' }}" style="{{ $textStyle }}">
                                {{ number_format($prod->stock_total, 0) }}
                            </td>
                            <td class="text-right" style="color:#64748b; {{ $textStyle }}">
                                {{ $prod->ultima_compra ? \Carbon\Carbon::parse($prod->ultima_compra)->format('d/m/Y') : 'Nunca' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="group-card" style="padding:3rem; text-align:center; color:#64748b;">
            @if(empty($categoria))
                <span style="font-size: 3rem;">👆</span>
                <h3 style="margin-top:1rem; margin-bottom:0.5rem;">Selecciona una Categoría</h3>
                <p>Por favor selecciona una categoría en el filtro superior para cargar el análisis de surtido. Esto evita que la página cargue miles de productos a la vez y sea lenta.</p>
            @else
                <h3 style="margin-bottom:0.5rem;">No se encontraron sustitutos</h3>
                <p>Intenta cambiar los filtros o asegúrate de que los productos tengan subcategorías asignadas.</p>
            @endif
        </div>
    @endforelse
</div>

<script>
async function toggleExclude(rowElement, productId) {
    if (window.getSelection) { window.getSelection().removeAllRanges(); }
    
    // Toggle UI optimistic
    const isCurrentlyExcluded = rowElement.style.opacity === '0.6';
    
    if (isCurrentlyExcluded) {
        rowElement.style.backgroundColor = '';
        rowElement.style.opacity = '1';
        Array.from(rowElement.cells).forEach(td => { td.style.textDecoration = 'none'; td.style.color = td.getAttribute('data-original-color') || ''; });
    } else {
        rowElement.style.backgroundColor = '#f8fafc';
        rowElement.style.opacity = '0.6';
        Array.from(rowElement.cells).forEach(td => { 
            if(!td.getAttribute('data-original-color')) td.setAttribute('data-original-color', td.style.color);
            td.style.textDecoration = 'line-through'; 
            td.style.color = 'var(--muted)'; 
        });
    }

    try {
        const resp = await fetch(`/compras/productos/${productId}/toggle-exclusion`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '{{ csrf_token() }}'
            }
        });
        const data = await resp.json();
        if(data.status !== 'success') {
            throw new Error('Server returned failed status');
        }
    } catch(e) {
        console.error('Error toggling exclusion:', e);
        // Revert UI on error
        if (isCurrentlyExcluded) {
            rowElement.style.backgroundColor = '#f8fafc';
            rowElement.style.opacity = '0.6';
            Array.from(rowElement.cells).forEach(td => { td.style.textDecoration = 'line-through'; td.style.color = 'var(--muted)'; });
        } else {
            rowElement.style.backgroundColor = '';
            rowElement.style.opacity = '1';
            Array.from(rowElement.cells).forEach(td => { td.style.textDecoration = 'none'; td.style.color = td.getAttribute('data-original-color') || ''; });
        }
    }
}
</script>
@endsection
