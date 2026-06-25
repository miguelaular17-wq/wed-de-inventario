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
