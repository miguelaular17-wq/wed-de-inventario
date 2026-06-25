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
        @if(isset($movementStats['sincronizaciones']))
            <div class="stat-card">
                <div class="stat-value">{{ $movementStats['sincronizaciones'] }}</div>
                <div class="stat-label">Ventas sincronizadas</div>
            </div>
        @endif
    </div>
    <!-- Graphics/Charts Section -->
    <div style="display:grid;grid-template-columns:1fr;gap:20px;margin:25px 0;background:#fff;border:1px solid var(--border);border-radius:12px;padding:24px;box-shadow:0 2px 4px rgba(0,0,0,0.02);">
        <h3 style="margin-top:0;margin-bottom:15px;color:#1e293b;font-size:1.1rem;font-weight:600;display:flex;align-items:center;gap:8px;">
            <span>📊</span> Niveles de Existencia Total por Sede
        </h3>
        <div style="position:relative;height:280px;width:100%;">
            <canvas id="stockSedesChart"></canvas>
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
        <form method="POST" action="{{ route('admin.clear-cache') }}" style="display:inline;margin:0;">
            @csrf
            <button type="submit" class="btn secondary" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5;" onclick="return confirm('¿Seguro que deseas vaciar la caché y limpiar los archivos temporales?')">Limpiar Caché y Liberar RAM</button>
        </form>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('stockSedesChart').getContext('2d');
    const chartData = @json($chartData);
    
    const labels = Object.keys(chartData);
    const data = Object.values(chartData);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Unidades en existencia',
                data: data,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.75)', // JRZ
                    'rgba(16, 185, 129, 0.75)', // DORAL
                    'rgba(245, 158, 11, 0.75)', // VIRTUDES
                    'rgba(239, 68, 68, 0.75)',  // ZAMORA
                    'rgba(139, 92, 246, 0.75)', // CENTRO
                    'rgba(236, 72, 153, 0.75)'  // SAMBIL
                ],
                borderColor: [
                    'rgb(37, 99, 235)',
                    'rgb(5, 150, 105)',
                    'rgb(217, 119, 6)',
                    'rgb(220, 38, 38)',
                    'rgb(124, 58, 237)',
                    'rgb(219, 39, 119)'
                ],
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#475569',
                        font: { size: 11, weight: '600' }
                    }
                }
            }
        }
    });
});
</script>
@endpush

    @if($lastImport)
        <p class="muted" style="margin-top:16px;">Última actualización de stock: {{ $lastImport }}</p>
    @endif
</div>@endsection

@push('head')
<style>
    .stat-card { background:#eef3fb;border-radius:8px;padding:16px;text-align:center; }
    .stat-value { font-size:1.8rem;font-weight:700;color:var(--blue); }
    .stat-label { font-size:.85rem;color:#555;margin-top:4px; }
</style>
@endpush
