@extends('layouts.app')
@section('title', $propiedad->nombre)
@section('content')

<style>
.pat-show-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }
.pat-hero {
    background: linear-gradient(135deg, #1a4480, #2563eb);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.pat-hero h1 { margin: 0 0 4px; font-size: 1.5rem; font-weight: 700; }
.pat-hero-meta { font-size: 0.85rem; opacity: 0.85; display: flex; gap: 16px; flex-wrap: wrap; margin-top: 10px; }

.pat-tabs { display: flex; gap: 4px; background: #f1f5f9; border-radius: 10px; padding: 4px; margin-bottom: 20px; flex-wrap: wrap; }
.pat-tab { padding: 8px 16px; border-radius: 7px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: #64748b; transition: all 0.18s; }
.pat-tab.active { background: #fff; color: #2563eb; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.pat-tab-content { display: none; }
.pat-tab-content.active { display: block; }

.pat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
.pat-card-header { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.pat-card-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #334155; }
.pat-card-body { padding: 20px; }

.info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f8fafc; gap: 16px; }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; min-width: 130px; }
.info-value { font-size: 0.9rem; color: #334155; flex: 1; }

.pat-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; cursor: pointer; border: none; transition: all 0.18s; }
.pat-btn-primary { background: #2563eb; color: #fff; }
.pat-btn-primary:hover { background: #1a4480; }
.pat-btn-outline { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
.pat-btn-sm { padding: 5px 10px; font-size: 0.78rem; }
.pat-btn-danger { background: #fff; color: #dc2626; border: 1px solid #fca5a5; }
.pat-btn-danger:hover { background: #fee2e2; }

.pat-estado-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.estado-disponible { background: #d1fae5; color: #065f46; }
.estado-alquilado  { background: #dbeafe; color: #1d4ed8; }
.estado-uso_propio { background: #ede9fe; color: #5b21b6; }
.estado-remodelacion { background: #fef3c7; color: #92400e; }
.estado-no_disponible { background: #fee2e2; color: #991b1b; }

.balance-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
.balance-item { background: #f8fafc; border-radius: 10px; padding: 14px; text-align: center; border: 1px solid #e2e8f0; }
.balance-item .bal-val { font-size: 1.2rem; font-weight: 700; }
.balance-item .bal-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
</style>

<div class="pat-show-wrap">

    {{-- HERO --}}
    <div class="pat-hero">
        <div>
            <div style="font-size:0.78rem; opacity:0.7; margin-bottom:6px;">
                <a href="{{ route('patrimonial.propiedades.index') }}" style="color:rgba(255,255,255,0.8); text-decoration:none;">Propiedades</a> → {{ $propiedad->codigo }}
            </div>
            <h1>{{ $propiedad->nombre }}</h1>
            <div>
                <span class="pat-estado-pill estado-{{ $propiedad->estado }}">
                    {{ \App\Models\Patrimonial\Propiedad::estadoLabel($propiedad->estado) }}
                </span>
            </div>
            <div class="pat-hero-meta">
                <span>🏗️ {{ ucfirst($propiedad->tipo) }}</span>
                @if($propiedad->ubicacion) <span>📍 {{ $propiedad->ubicacion }}</span> @endif
                @if($propiedad->propietario) <span>👤 {{ $propiedad->propietario }}</span> @endif
            </div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('patrimonial.propiedades.edit', $propiedad) }}" class="pat-btn" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3);">✏️ Editar</a>
            <a href="{{ route('patrimonial.alquileres.create') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn" style="background:#fff; color:#2563eb;">+ Alquiler</a>
        </div>
    </div>

    {{-- BALANCE DEL MES --}}
    @php $mes = now()->month; $anio = now()->year; @endphp
    <div class="balance-bar">
        <div class="balance-item" style="border-color:#a7f3d0;">
            <div class="bal-val" style="color:#059669;">${{ number_format($balanceMes['ingresos'], 2) }}</div>
            <div class="bal-label">Ingresos este mes</div>
        </div>
        <div class="balance-item" style="border-color:#fca5a5;">
            <div class="bal-val" style="color:#dc2626;">${{ number_format($balanceMes['gastos'], 2) }}</div>
            <div class="bal-label">Gastos este mes</div>
        </div>
        <div class="balance-item" style="border-color:{{ $balanceMes['balance'] >= 0 ? '#a7f3d0' : '#fca5a5' }};">
            <div class="bal-val" style="color:{{ $balanceMes['balance'] >= 0 ? '#059669' : '#dc2626' }};">${{ number_format($balanceMes['balance'], 2) }}</div>
            <div class="bal-label">Balance Neto</div>
        </div>
    </div>

    {{-- TABS --}}
    <div class="pat-tabs">
        <button class="pat-tab active" onclick="showTab('info')">📋 Información</button>
        <button class="pat-tab" onclick="showTab('alquileres')">📄 Alquileres ({{ $propiedad->alquileres->count() }})</button>
        <button class="pat-tab" onclick="showTab('reservas')">📅 Reservas ({{ $propiedad->reservas->count() }})</button>
        <button class="pat-tab" onclick="showTab('inventario')">📦 Inventario ({{ $propiedad->inventarioItems->count() }})</button>
        <button class="pat-tab" onclick="showTab('llaves')">🔑 Llaves ({{ $propiedad->llaves->count() }})</button>
        <button class="pat-tab" onclick="showTab('documentos')">📁 Docs ({{ $propiedad->documentos->count() }})</button>
        <button class="pat-tab" onclick="showTab('transacciones')">💰 Gastos/Ingresos ({{ $propiedad->transacciones->count() }})</button>
    </div>

    {{-- INFO --}}
    <div id="tab-info" class="pat-tab-content active">
        <div class="pat-card">
            <div class="pat-card-body">
                <div class="info-row">
                    <span class="info-label">Código</span>
                    <span class="info-value">{{ $propiedad->codigo }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipo</span>
                    <span class="info-value">{{ ucfirst($propiedad->tipo) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Dirección</span>
                    <span class="info-value">{{ $propiedad->direccion ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Responsable</span>
                    <span class="info-value">{{ $propiedad->responsable ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha Adquisición</span>
                    <span class="info-value">{{ optional($propiedad->fecha_adquisicion)->format('d/m/Y') ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Inversión Inicial</span>
                    <span class="info-value">{{ $propiedad->valor_inversion ? '$' . number_format($propiedad->valor_inversion, 2) : '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Observaciones</span>
                    <span class="info-value">{{ $propiedad->observaciones ?? '—' }}</span>
                </div>
            </div>
        </div>

        @if($propiedad->fotos && count($propiedad->fotos) > 0)
        <div class="pat-card" style="margin-top:16px;">
            <div class="pat-card-header"><h3>📸 Fotos</h3></div>
            <div class="pat-card-body" style="display:flex; gap:10px; flex-wrap:wrap;">
                @foreach($propiedad->fotos as $foto)
                    <img src="{{ filter_var($foto, FILTER_VALIDATE_URL) ? $foto : asset('storage/' . $foto) }}" style="width:150px; height:110px; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ALQUILERES --}}
    <div id="tab-alquileres" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>📄 Alquileres Activos</h3>
                <a href="{{ route('patrimonial.alquileres.create') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-primary pat-btn-sm">+ Nuevo</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->alquileres as $alq)
                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
                        <div>
                            <div style="font-weight:700; font-size:0.95rem; color:#1e293b; margin-bottom:4px;">{{ $alq->inquilino_nombre }}</div>
                            <div style="font-size:0.82rem; color:#64748b;">
                                {{ $alq->tipo_canon === 'mensual' ? 'Canon mensual: $' . number_format($alq->canon_mensual, 2) : 'Canon quincenal: $' . number_format($alq->canon_quincenal, 2) }}
                                · Desde {{ optional($alq->fecha_inicio)->format('d/m/Y') }}
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <span style="padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700; background: {{ $alq->estado === 'activo' ? '#d1fae5' : '#fee2e2' }}; color: {{ $alq->estado === 'activo' ? '#065f46' : '#991b1b' }};">
                                {{ ucfirst($alq->estado) }}
                            </span>
                            <a href="{{ route('patrimonial.alquileres.show', $alq) }}" class="pat-btn pat-btn-outline pat-btn-sm">Ver</a>
                        </div>
                    </div>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin alquileres registrados.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- RESERVAS --}}
    <div id="tab-reservas" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>📅 Reservas Temporales</h3>
                <a href="{{ route('patrimonial.reservas.index') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-outline pat-btn-sm">Ver calendario</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->reservas->take(10) as $res)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8fafc; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem; color:#334155;">{{ $res->cliente_nombre }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">
                            {{ optional($res->fecha_entrada)->format('d/m/Y') }} → {{ optional($res->fecha_salida)->format('d/m/Y') }}
                            · {{ $res->getNoches() }} noches · ${{ number_format($res->getTotal(), 2) }}
                        </div>
                    </div>
                    <span style="padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;
                        background: {{ $res->estado === 'confirmada' ? '#dbeafe' : ($res->estado === 'completada' ? '#d1fae5' : '#fee2e2') }};
                        color: {{ $res->estado === 'confirmada' ? '#1d4ed8' : ($res->estado === 'completada' ? '#065f46' : '#991b1b') }};">
                        {{ ucfirst($res->estado) }}
                    </span>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin reservas registradas.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- INVENTARIO --}}
    <div id="tab-inventario" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>📦 Inventario</h3>
                <a href="{{ route('patrimonial.inventario.index') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-primary pat-btn-sm">Gestionar</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->inventarioItems as $item)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #f8fafc;">
                    <div style="font-size:0.9rem; color:#334155; font-weight:500;">{{ $item->articulo }}</div>
                    <div style="font-size:0.82rem; color:#64748b;">x{{ $item->cantidad }} · {{ $item->estado_articulo ?? 'N/A' }}</div>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin artículos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- LLAVES --}}
    <div id="tab-llaves" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>🔑 Control de Llaves</h3>
                <a href="{{ route('patrimonial.llaves.index') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-primary pat-btn-sm">Gestionar</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->llaves as $llave)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #f8fafc;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem; color:#334155;">{{ $llave->descripcion }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">📍 {{ $llave->ubicacion_actual ?? '—' }} · {{ $llave->responsable ?? '—' }}</div>
                    </div>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin llaves registradas.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- DOCUMENTOS --}}
    <div id="tab-documentos" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>📁 Documentos</h3>
                <a href="{{ route('patrimonial.documentos.index') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-primary pat-btn-sm">Gestionar</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->documentos as $doc)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #f8fafc;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem; color:#334155;">{{ $doc->getIcono() }} {{ $doc->nombre }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">{{ ucfirst($doc->tipo) }} · {{ $doc->getTamanoLegible() }}</div>
                    </div>
                    <a href="{{ asset('storage/' . $doc->ruta_archivo) }}" target="_blank" class="pat-btn pat-btn-outline pat-btn-sm">⬇ Descargar</a>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin documentos.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- TRANSACCIONES --}}
    <div id="tab-transacciones" class="pat-tab-content">
        <div class="pat-card">
            <div class="pat-card-header">
                <h3>💰 Historial de Gastos e Ingresos</h3>
                <a href="{{ route('patrimonial.transacciones.index') }}?propiedad_id={{ $propiedad->id }}" class="pat-btn pat-btn-outline pat-btn-sm">Ir a Gestión de Transacciones</a>
            </div>
            <div class="pat-card-body">
                @forelse($propiedad->transacciones->sortByDesc('fecha')->take(15) as $tx)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f8fafc;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem; color:#334155;">{{ $tx->categoria }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">{{ \Carbon\Carbon::parse($tx->fecha)->format('d/m/Y') }} · {{ $tx->descripcion ?? 'Sin descripción' }}</div>
                    </div>
                    <div style="font-weight:700; font-size:0.95rem; color:{{ $tx->tipo === 'ingreso' ? '#059669' : '#dc2626' }};">
                        {{ $tx->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($tx->monto, 2) }}
                    </div>
                </div>
                @empty
                    <p style="color:#94a3b8; text-align:center; padding:20px 0;">Sin transacciones registradas.</p>
                @endforelse
                
                @if($propiedad->transacciones->count() > 15)
                    <div style="text-align:center; margin-top:12px;">
                        <a href="{{ route('patrimonial.transacciones.index') }}?propiedad_id={{ $propiedad->id }}" style="font-size:0.85rem; color:#2563eb; font-weight:600; text-decoration:none;">Ver historial completo →</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- DELETE --}}
    <div style="margin-top:24px; text-align:right;">
        <form method="POST" action="{{ route('patrimonial.propiedades.destroy', $propiedad) }}"
              onsubmit="return confirm('¿Eliminar esta propiedad? Se eliminarán todos sus datos relacionados.')">
            @csrf @method('DELETE')
            <button type="submit" class="pat-btn pat-btn-danger">🗑️ Eliminar Propiedad</button>
        </form>
    </div>

</div>

@push('scripts')
<script>
function showTab(name) {
    document.querySelectorAll('.pat-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.pat-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>
@endpush
@endsection
