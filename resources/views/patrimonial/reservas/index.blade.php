@extends('layouts.app')
@section('title', 'Reservas Temporales')
@section('content')

<style>
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.cal-day { min-height: 80px; padding: 4px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; font-size: 0.75rem; position: relative; }
.cal-day.other-month { background: #f8fafc; opacity: 0.5; }
.cal-day.today { border-color: #2563eb; border-width: 2px; }
.cal-day-num { font-weight: 700; color: #475569; margin-bottom: 2px; }
.cal-reserva-bar { padding: 2px 4px; border-radius: 3px; margin-bottom: 1px; font-size: 0.68rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cal-header-day { text-align: center; font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 0; }
</style>

<div style="max-width:1300px; margin:0 auto; padding:24px 20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Reservas
            </div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">📅 Reservas Temporales</h1>
        </div>
    </div>

    {{-- NAV MES + FILTRO PROPIEDAD --}}
    @php
        $mesPrev = \Carbon\Carbon::create($anio, $mes)->subMonth();
        $mesSig  = \Carbon\Carbon::create($anio, $mes)->addMonth();
    @endphp
    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
        <a href="?mes={{ $mesPrev->month }}&anio={{ $mesPrev->year }}&propiedad_id={{ $propiedadId }}"
           style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">← Anterior</a>
        <span style="font-weight:700; font-size:1rem; color:#1e293b; min-width:150px; text-align:center;">
            {{ \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y') }}
        </span>
        <a href="?mes={{ $mesSig->month }}&anio={{ $mesSig->year }}&propiedad_id={{ $propiedadId }}"
           style="padding:8px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">Siguiente →</a>

        <form method="GET" style="display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="anio" value="{{ $anio }}">
            <select name="propiedad_id" style="padding:7px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;" onchange="this.form.submit()">
                <option value="">Todas las propiedades</option>
                @foreach($propiedades as $prop)
                    <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- CALENDARIO --}}
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:24px; padding:16px;">
        {{-- Cabeceras días --}}
        <div class="cal-grid" style="margin-bottom:4px;">
            @foreach(['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $dia)
                <div class="cal-header-day">{{ $dia }}</div>
            @endforeach
        </div>

        {{-- Días del mes --}}
        @php
            $firstDay   = $inicioMes->copy()->startOfMonth();
            $startWeek  = $firstDay->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
            $endMonth   = $inicioMes->copy()->endOfMonth();
            $endWeek    = $endMonth->copy()->endOfWeek(\Carbon\Carbon::SATURDAY);
            $today      = \Carbon\Carbon::today();
            $current    = $startWeek->copy();

            // Build lookup: date => reservas[]
            $reservasByDate = [];
            foreach ($reservasCalendario as $res) {
                $start = $res->fecha_entrada->copy();
                $end   = $res->fecha_salida->copy();
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $key = $d->toDateString();
                    $reservasByDate[$key][] = $res;
                }
            }
            $colors = ['#bfdbfe','#a7f3d0','#fde68a','#fecaca','#ddd6fe','#fed7aa'];
            $resColors = [];
            $ci = 0;
            foreach($reservasCalendario as $res) {
                $resColors[$res->id] = $colors[$ci % count($colors)];
                $ci++;
            }
        @endphp
        <div class="cal-grid">
            @while($current->lte($endWeek))
            @php $dateStr = $current->toDateString(); @endphp
            <div class="cal-day {{ $current->month != $mes ? 'other-month' : '' }} {{ $current->isSameDay($today) ? 'today' : '' }}">
                <div class="cal-day-num">{{ $current->day }}</div>
                @if(isset($reservasByDate[$dateStr]))
                    @foreach(collect($reservasByDate[$dateStr])->unique('id') as $res)
                    <div class="cal-reserva-bar" style="background:{{ $resColors[$res->id] ?? '#e2e8f0' }}; color:#1e293b;" title="{{ $res->cliente_nombre }}">
                        {{ $res->cliente_nombre }}
                    </div>
                    @endforeach
                @endif
            </div>
            @php $current->addDay(); @endphp
            @endwhile
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap:20px; align-items:start;">

        {{-- NUEVA RESERVA --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Nueva Reserva</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.reservas.store') }}" method="POST">
                    @csrf
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Propiedad *</label>
                            <select name="propiedad_id" required style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                <option value="">Seleccionar...</option>
                                @foreach($propiedades as $prop)
                                    <option value="{{ $prop->id }}" {{ $propiedadId == $prop->id ? 'selected' : '' }}>{{ $prop->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Cliente *</label>
                            <input type="text" name="cliente_nombre" required placeholder="Nombre completo"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Contacto</label>
                            <input type="text" name="cliente_contacto" placeholder="Teléfono / correo"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Entrada *</label>
                                <input type="date" name="fecha_entrada" required id="res_entrada"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;"
                                    onchange="calcularNoches()">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Salida *</label>
                                <input type="date" name="fecha_salida" required id="res_salida"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;"
                                    onchange="calcularNoches()">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Precio / Noche *</label>
                                <input type="number" name="precio_noche" id="res_precio" step="0.01" min="0" required
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;"
                                    onchange="calcularNoches()">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Moneda</label>
                                <select name="moneda" style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                    <option value="usd">USD $</option>
                                    <option value="bs">Bs</option>
                                </select>
                            </div>
                        </div>
                        <div id="res_preview" style="display:none; background:#f0fdf4; border:1px solid #a7f3d0; border-radius:8px; padding:10px; text-align:center; font-weight:700; color:#065f46;"></div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Estado</label>
                            <select name="estado" style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                <option value="confirmada">Confirmada</option>
                                <option value="completada">Completada</option>
                                <option value="cancelada">Cancelada</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Observaciones</label>
                            <textarea name="observaciones" rows="2"
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box; resize:vertical;"></textarea>
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">📅 Registrar Reserva</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTADO --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">📋 Reservas Registradas</h3>
            </div>
            @forelse($reservas as $res)
            <div style="padding:14px 18px; border-bottom:1px solid #f8fafc;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:700; color:#1e293b; font-size:0.9rem; margin-bottom:2px;">{{ $res->cliente_nombre }}</div>
                        <div style="font-size:0.8rem; color:#64748b;">{{ $res->propiedad->nombre ?? '—' }}</div>
                        <div style="font-size:0.82rem; color:#475569; margin-top:4px;">
                            📅 {{ optional($res->fecha_entrada)->format('d/m/Y') }} → {{ optional($res->fecha_salida)->format('d/m/Y') }}
                            · <strong>{{ $res->getNoches() }} noches</strong>
                            · ${{ number_format($res->getTotal(), 2) }} {{ strtoupper($res->moneda) }}
                        </div>
                    </div>
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <span style="padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;
                            background:{{ $res->estado === 'confirmada' ? '#dbeafe' : ($res->estado === 'completada' ? '#d1fae5' : '#fee2e2') }};
                            color:{{ $res->estado === 'confirmada' ? '#1d4ed8' : ($res->estado === 'completada' ? '#065f46' : '#991b1b') }};">
                            {{ ucfirst($res->estado) }}
                        </span>
                        <form method="POST" action="{{ route('patrimonial.reservas.destroy', $res) }}"
                              onsubmit="return confirm('¿Eliminar esta reserva?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:4px 8px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:6px; cursor:pointer; font-size:0.75rem;">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:30px; text-align:center; color:#94a3b8; font-size:0.88rem;">Sin reservas registradas.</div>
            @endforelse
            @if($reservas->hasPages())
            <div style="padding:12px 16px;">{{ $reservas->links() }}</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function calcularNoches() {
    const entrada = document.getElementById('res_entrada').value;
    const salida  = document.getElementById('res_salida').value;
    const precio  = parseFloat(document.getElementById('res_precio').value) || 0;
    const preview = document.getElementById('res_preview');

    if (entrada && salida) {
        const d1 = new Date(entrada), d2 = new Date(salida);
        const noches = Math.max(0, Math.round((d2 - d1) / 86400000));
        const total = noches * precio;
        if (noches > 0) {
            preview.style.display = 'block';
            preview.textContent = noches + ' noches · Total: $' + total.toFixed(2);
        } else {
            preview.style.display = 'none';
        }
    }
}
</script>
@endpush
@endsection
