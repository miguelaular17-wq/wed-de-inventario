@extends('layouts.app')
@section('title', 'Propiedades')
@section('content')

<style>
.pat-wrap { max-width: 1400px; margin: 0 auto; padding: 24px 20px; }
.pat-page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
.pat-page-header h1 { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin: 0; }
.pat-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 0.88rem; text-decoration: none; cursor: pointer; border: none; transition: all 0.18s; }
.pat-btn-primary { background: #2563eb; color: #fff; }
.pat-btn-primary:hover { background: #1a4480; color: #fff; }
.pat-btn-outline { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
.pat-btn-outline:hover { border-color: #2563eb; color: #2563eb; }
.pat-btn-sm { padding: 5px 10px; font-size: 0.78rem; }

.pat-filters { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.pat-filters select, .pat-filters input { padding: 7px 12px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.88rem; font-family: inherit; color: #334155; }
.pat-filters select:focus, .pat-filters input:focus { outline: none; border-color: #2563eb; }

.pat-prop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
.pat-prop-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
}
.pat-prop-card:hover { box-shadow: 0 8px 24px rgba(37,99,235,0.1); transform: translateY(-2px); border-color: #bfdbfe; }
.pat-prop-foto { width: 100%; height: 160px; object-fit: cover; background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #93c5fd; }
.pat-prop-foto img { width: 100%; height: 100%; object-fit: cover; }
.pat-prop-body { padding: 16px; flex: 1; }
.pat-prop-codigo { font-size: 0.75rem; color: #94a3b8; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px; }
.pat-prop-nombre { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; line-height: 1.3; }
.pat-prop-tipo { font-size: 0.8rem; color: #64748b; margin-bottom: 10px; }
.pat-estado-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.pat-prop-footer { padding: 12px 16px; border-top: 1px solid #f1f5f9; display: flex; gap: 8px; }

.estado-disponible { background: #d1fae5; color: #065f46; }
.estado-alquilado  { background: #dbeafe; color: #1d4ed8; }
.estado-uso_propio { background: #ede9fe; color: #5b21b6; }
.estado-remodelacion { background: #fef3c7; color: #92400e; }
.estado-no_disponible { background: #fee2e2; color: #991b1b; }

.pat-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.pat-empty-icon { font-size: 3rem; margin-bottom: 12px; }
</style>

<div class="pat-wrap">
    <div class="pat-page-header">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Propiedades
            </div>
            <h1>🏘️ Propiedades</h1>
        </div>
        <a href="{{ route('patrimonial.propiedades.create') }}" class="pat-btn pat-btn-primary">
            + Nueva Propiedad
        </a>
    </div>

    {{-- FILTROS --}}
    <form method="GET" class="pat-filters">
        <div>
            <label style="font-size:0.78rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre, código, propietario..." style="width:220px;">
        </div>
        <div>
            <label style="font-size:0.78rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Tipo</label>
            <select name="tipo">
                <option value="">Todos los tipos</option>
                @foreach($tipos as $t)
                    <option value="{{ $t }}" {{ request('tipo') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.78rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Estado</label>
            <select name="estado">
                <option value="">Todos los estados</option>
                @foreach($estados as $e)
                    <option value="{{ $e }}" {{ request('estado') == $e ? 'selected' : '' }}>{{ \App\Models\Patrimonial\Propiedad::estadoLabel($e) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="pat-btn pat-btn-primary">Filtrar</button>
        <a href="{{ route('patrimonial.propiedades.index') }}" class="pat-btn pat-btn-outline">Limpiar</a>
    </form>

    {{-- RESULTADO --}}
    <div style="font-size:0.85rem; color:#64748b; margin-bottom:14px;">
        {{ $propiedades->total() }} propiedades encontradas
    </div>

    @if($propiedades->isEmpty())
        <div class="pat-empty">
            <div class="pat-empty-icon">🏚️</div>
            <div style="font-weight:600; font-size:1rem; margin-bottom:8px;">Sin propiedades registradas</div>
            <div style="margin-bottom:20px; font-size:0.9rem;">Comienza agregando la primera propiedad del portafolio.</div>
            <a href="{{ route('patrimonial.propiedades.create') }}" class="pat-btn pat-btn-primary">+ Nueva Propiedad</a>
        </div>
    @else
        <div class="pat-prop-grid">
            @foreach($propiedades as $prop)
            <div class="pat-prop-card">
                <div class="pat-prop-foto">
                    @if($prop->fotos && count($prop->fotos) > 0)
                        <img src="{{ filter_var($prop->fotos[0], FILTER_VALIDATE_URL) ? $prop->fotos[0] : asset('storage/' . $prop->fotos[0]) }}" alt="{{ $prop->nombre }}">
                    @else
                        @php
                        $iconos = ['casa'=>'🏠','apartamento'=>'🏢','local'=>'🏪','galpón'=>'🏭','terreno'=>'🌿','condominio'=>'🏙️','vehículo'=>'🚗'];
                        @endphp
                        {{ $iconos[$prop->tipo] ?? '🏢' }}
                    @endif
                </div>
                <div class="pat-prop-body">
                    <div class="pat-prop-codigo">{{ $prop->codigo }}</div>
                    <div class="pat-prop-nombre">{{ $prop->nombre }}</div>
                    <div class="pat-prop-tipo">{{ ucfirst($prop->tipo) }}
                        @if($prop->ubicacion) · {{ $prop->ubicacion }} @endif
                    </div>
                    <span class="pat-estado-pill estado-{{ $prop->estado }}">
                        {{ \App\Models\Patrimonial\Propiedad::estadoLabel($prop->estado) }}
                    </span>
                    @if($prop->propietario && $prop->propietario !== 'Por registrar')
                        <div style="font-size:0.78rem; color:#94a3b8; margin-top:8px;">👤 {{ $prop->propietario }}</div>
                    @endif
                </div>
                <div class="pat-prop-footer">
                    <a href="{{ route('patrimonial.propiedades.show', $prop) }}" class="pat-btn pat-btn-outline pat-btn-sm" style="flex:1; justify-content:center;">Ver</a>
                    <a href="{{ route('patrimonial.propiedades.edit', $prop) }}" class="pat-btn pat-btn-outline pat-btn-sm" style="flex:1; justify-content:center;">Editar</a>
                </div>
            </div>
            @endforeach
        </div>

        <div style="margin-top:24px;">
            {{ $propiedades->links() }}
        </div>
    @endif
</div>
@endsection
