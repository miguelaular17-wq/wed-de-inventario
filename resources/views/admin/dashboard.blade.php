@extends('layouts.app')

@section('title', 'Panel admin')

@section('content')
<div class="panel" data-tour="admin-dashboard">
    <h1 style="margin-top:0;">Panel de administración</h1>
    <p class="muted">Gestión multisede: importación de stock y auditoría de movimientos.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">
        <div class="stat-card">
            <div class="stat-value">{{ $productCount }}</div>
            <div class="stat-label">Productos activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $movementStats['total'] ?? 0 }}</div>
            <div class="stat-label">Movimientos totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $movementStats['requisiciones'] ?? 0 }}</div>
            <div class="stat-label">Requisiciones</div>
        </div>
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="{{ route('admin.import.create') }}" class="btn" data-tour="admin-import-btn">Subir ExelMultiSede (.xlsx)</a>
        <a href="{{ route('admin.movimientos.index') }}" class="btn secondary">Ver movimientos (todas las sedes)</a>
        <a href="{{ route('admin.users.index') }}" class="btn secondary">Gestionar usuarios</a>
        @if(session('sede_local'))
            <a href="{{ route('ventas.index') }}" class="btn secondary">Ir a Ventas</a>
        @else
            <a href="{{ route('sede.select') }}" class="btn secondary">Elegir sede operativa</a>
        @endif
    </div>

    @if($lastImport)
        <p class="muted" style="margin-top:16px;">Última actualización de stock: {{ $lastImport }}</p>
    @endif
</div>

<div class="panel" style="margin-top:24px;" data-tour="admin-recommendations">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">Compras recomendadas</h2>
            <p class="muted" style="margin:4px 0 0;">Productos que conviene solicitar según demanda local y stock disponible en otras sedes.</p>
            <p class="muted" style="margin:4px 0 0;font-size:.95rem;">Existencia y demanda son de la sede local principal; la vista lateral muestra stock y ventas de 60 días por sede.</p>
            <p class="muted" style="margin:10px 0 0;font-size:.95rem;"><strong>{{ $recommendedProductCount }}</strong> productos recomendados / <strong>{{ $recommendedTotalUnits }}</strong> unidades sugeridas</p>
        </div>
        <form method="GET" style="display:flex;gap:12px;align-items:center;">
            <label for="proveedor-select" class="muted" style="margin:0;">Proveedor</label>
            <select id="proveedor-select" name="proveedor" onchange="this.form.submit()" style="min-width:220px;">
                <option value="">Todos</option>
                @foreach($proveedores as $prov)
                    <option value="{{ $prov }}" @selected($selectedProveedor === $prov)>{{ $prov }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($recomendados->isEmpty())
        <div class="panel empty-state" style="margin-top:16px;">
            <p>No hay recomendaciones de compra para el proveedor seleccionado.</p>
        </div>
    @else
        <div style="margin-top:16px; overflow-x:auto;">
            <table class="data-table" style="min-width:860px;">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Proveedor</th>
                        <th>Total stock</th>
                        <th>Total demanda</th>
                        <th>Sugerido</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recomendados as $row)
                        <tr class="selectable-row" data-producto="{{ $row['producto'] }}" data-stocks='@json($row['stocks'])' data-ventas='@json($row['ventas_internas'])' data-ventas15='@json($row['ventas_internas_15d'])'>
                            <td>{{ $row['cod_centro'] }}</td>
                            <td>{{ $row['producto'] }}</td>
                            <td>{{ $row['proveedor'] ?? '—' }}</td>
                            <td>{{ $row['total_stock'] }}</td>
                            <td>{{ $row['total_demanda'] }}</td>
                            <td><strong>{{ $row['sugerido'] }}</strong></td>
                            <td>{{ $row['accion'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="product-details" class="modal" style="display:none;">
            <div class="modal-backdrop"></div>
            <div class="modal-content panel">
                <button type="button" class="modal-close" aria-label="Cerrar">×</button>
                <h3>Detalle por sede</h3>
                <p id="detail-product-name" class="muted"></p>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;flex-wrap:wrap;">
                    <p id="detail-sales-mode-label" class="muted" style="margin:0;">Mostrando ventas en 60 días</p>
                    <button type="button" id="toggle-sales-mode" class="sales-toggle">Ver ventas 15d</button>
                </div>
                <div style="overflow-x:auto; margin-top:12px;">
                    <table class="data-table" style="min-width:540px;">
                        <thead>
                            <tr>
                                <th>Sede</th>
                                <th>Existencia</th>
                                <th id="detail-sales-header">Ventas 60d</th>
                            </tr>
                        </thead>
                        <tbody id="detail-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('head')
<style>
    .stat-card { background:#eef3fb;border-radius:8px;padding:16px;text-align:center; }
    .stat-value { font-size:1.8rem;font-weight:700;color:var(--blue); }
    .stat-label { font-size:.85rem;color:#555;margin-top:4px; }
    .selectable-row { cursor:pointer; }
    .selectable-row:hover { background:#f8fafc; }
    .modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:1000; }
    .modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.45); }
    .modal-content { position:relative; z-index:1; width:min(92vw, 760px); max-height:90vh; overflow:auto; padding:24px; border-radius:14px; box-shadow:0 18px 40px rgba(0,0,0,.16); }
    .modal-close { position:absolute; top:14px; right:14px; background:transparent; border:none; color:#333; font-size:24px; cursor:pointer; }
    .sales-toggle { border:1px solid #cbd5e1; background:#fff; color:#222; padding:6px 12px; border-radius:999px; cursor:pointer; }
    .sales-toggle.active { background:var(--blue); color:#fff; border-color:var(--blue); }
    #product-details table td, #product-details table th { padding:10px; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.selectable-row');
        const detailPanel = document.getElementById('product-details');
        const detailName = document.getElementById('detail-product-name');
        const detailBody = document.getElementById('detail-table-body');
        const toggleButton = document.getElementById('toggle-sales-mode');
        const detailSalesHeader = document.getElementById('detail-sales-header');
        const detailSalesLabel = document.getElementById('detail-sales-mode-label');

        let activeRow = null;
        let showing15d = false;

        function renderDetailRows(stocks, ventas60, ventas15) {
            const sales = showing15d ? ventas15 : ventas60;
            detailSalesHeader.textContent = showing15d ? 'Ventas 15d' : 'Ventas 60d';
            detailSalesLabel.textContent = showing15d ? 'Mostrando ventas en 15 días' : 'Mostrando ventas en 60 días';
            toggleButton.textContent = showing15d ? 'Ver ventas 60d' : 'Ver ventas 15d';

            detailBody.innerHTML = Object.keys(stocks).map(sede => {
                return `<tr><td>${sede}</td><td>${stocks[sede] ?? 0}</td><td>${sales[sede] ?? 0}</td></tr>`;
            }).join('');
        }

        rows.forEach(row => {
            row.addEventListener('click', () => {
                activeRow = row;
                showing15d = false;

                const product = row.dataset.producto;
                const stocks = JSON.parse(row.dataset.stocks || '{}');
                const ventas = JSON.parse(row.dataset.ventas || '{}');
                const ventas15 = JSON.parse(row.dataset.ventas15 || '{}');

                detailName.textContent = `Producto seleccionado: ${product}`;
                renderDetailRows(stocks, ventas, ventas15);
                detailPanel.style.display = 'flex';
            });
        });

        toggleButton?.addEventListener('click', () => {
            if (!activeRow) {
                return;
            }

            showing15d = !showing15d;
            const stocks = JSON.parse(activeRow.dataset.stocks || '{}');
            const ventas = JSON.parse(activeRow.dataset.ventas || '{}');
            const ventas15 = JSON.parse(activeRow.dataset.ventas15 || '{}');
            renderDetailRows(stocks, ventas, ventas15);
        });

        const closeButton = document.querySelector('.modal-close');
        const backdrop = document.querySelector('.modal-backdrop');

        const hideModal = () => {
            detailPanel.style.display = 'none';
            activeRow = null;
        };

        closeButton?.addEventListener('click', hideModal);
        backdrop?.addEventListener('click', hideModal);
    });
</script>
@endpush
