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
                        <input type="hidden" name="estado" value="confirmada">
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
                        
                        @if($res->getSaldo() > 0)
                        <button onclick="abrirModalPagos({{ $res->id }}, '{{ htmlspecialchars($res->cliente_nombre) }}', {{ $res->getSaldo() }}, '{{ strtoupper($res->moneda) }}')"
                                style="padding:4px 8px; background:#fff; color:#059669; border:1px solid #6ee7b7; border-radius:6px; cursor:pointer; font-size:0.75rem;"
                                title="Registrar Pago">
                            💰 Pagos
                        </button>
                        @endif
                    </div>
                </div>
                
                @if($res->pagos->count() > 0)
                <div style="margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.8rem;">
                    <div style="font-weight: 600; color: #475569; margin-bottom: 6px;">Historial de Pagos:</div>
                    <ul style="margin: 0; padding-left: 20px; color: #64748b;">
                        @foreach($res->pagos as $pago)
                            <li>
                                {{ $pago->fecha_pago->format('d/m/Y') }}: 
                                <strong>{{ number_format($pago->monto_pagado, 2) }}</strong> 
                                ({{ $pago->forma_pago }}
                                @if($pago->tasa_cambio) | Tasa: {{ number_format($pago->tasa_cambio, 2) }} @endif)
                            </li>
                        @endforeach
                    </ul>
                    @if($res->getSaldo() <= 0)
                    <div style="margin-top: 6px; font-weight: 700; color: #059669;">
                        ✅ Pagado en su totalidad
                    </div>
                    @else
                    <div style="margin-top: 6px; font-weight: 700; color: #b91c1c;">
                        Saldo Pendiente: {{ number_format($res->getSaldo(), 2) }} {{ strtoupper($res->moneda) }}
                    </div>
                    @endif
                </div>
                @elseif($res->getSaldo() > 0)
                <div style="margin-top: 6px; font-weight: 700; color: #b91c1c; font-size: 0.8rem;">
                    Saldo Pendiente: {{ number_format($res->getSaldo(), 2) }} {{ strtoupper($res->moneda) }}
                </div>
                @endif
                
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

{{-- MODAL REGISTRAR PAGO --}}
<div id="modalPagos" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:100%; max-width:500px; border-radius:12px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h3 id="modalPagoTitle" style="margin:0 0 16px 0; font-size:1.1rem; color:#1e293b;">Registrar Pago</h3>
        
        <form id="formPago" method="POST" action="">
            @csrf
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Monto Pagado *</label>
                    <input type="number" name="monto_pagado" id="monto_pagado" step="0.01" min="0" required
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Fecha Pago *</label>
                    <input type="date" name="fecha_pago" required value="{{ date('Y-m-d') }}"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="font-size:0.8rem; font-weight:600; color:#475569;">Forma de Pago *</label>
                <select name="forma_pago" id="forma_pago" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;" onchange="toggleCamposPago()">
                    <option value="Transferencia BCV">Transferencia BCV</option>
                    <option value="Pago Móvil">Pago Móvil</option>
                    <option value="Zelle">Zelle</option>
                    <option value="Efectivo USD">Efectivo USD</option>
                    <option value="Binance">Binance</option>
                </select>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;" id="div_tasa_ref">
                <div id="div_tasa">
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Tasa de Cambio</label>
                    <input type="number" name="tasa_cambio" step="0.0001" placeholder="Ej: 36.50"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div id="div_referencia">
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Nro Referencia</label>
                    <input type="text" name="referencia"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;" id="div_bancos">
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Banco Origen</label>
                    <input type="text" name="banco_origen"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#475569;">Banco Destino</label>
                    <input type="text" name="banco_destino"
                           style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
                </div>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem; font-weight:600; color:#475569;">Comentario</label>
                <textarea name="comentario" rows="2" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;"></textarea>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" onclick="cerrarModalPagos()" style="padding:8px 16px; background:#f1f5f9; color:#475569; border:none; border-radius:6px; cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:8px 16px; background:#059669; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Guardar Pago</button>
            </div>
        </form>
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

function abrirModalPagos(idReserva, cliente, saldoP, moneda) {
    document.getElementById('modalPagoTitle').innerText = 'Registrar Pago - ' + cliente;
    document.getElementById('monto_pagado').value = saldoP.toFixed(2);
    document.getElementById('formPago').action = '/patrimonial/reservas/' + idReserva + '/pago';
    document.getElementById('modalPagos').style.display = 'flex';
    toggleCamposPago(); // Update fields based on initial selection
}

function cerrarModalPagos() {
    document.getElementById('modalPagos').style.display = 'none';
    document.getElementById('formPago').reset();
}

function toggleCamposPago() {
    const forma = document.getElementById('forma_pago').value;
    const divTasa = document.getElementById('div_tasa');
    const divRef = document.getElementById('div_referencia');
    const divBancos = document.getElementById('div_bancos');
    const divTasaRef = document.getElementById('div_tasa_ref');

    if (forma === 'Zelle' || forma === 'Binance') {
        // Solo Referencia
        divTasa.style.display = 'none';
        divRef.style.display = 'block';
        divBancos.style.display = 'none';
        divTasaRef.style.display = 'block';
        divTasaRef.style.gridTemplateColumns = '1fr';
    } else if (forma === 'Efectivo USD') {
        // Nada
        divTasaRef.style.display = 'none';
        divBancos.style.display = 'none';
    } else {
        // Transferencia BCV, Pago Móvil
        divTasaRef.style.display = 'grid';
        divTasaRef.style.gridTemplateColumns = '1fr 1fr';
        divTasa.style.display = 'block';
        divRef.style.display = 'block';
        divBancos.style.display = 'grid';
    }
}
</script>
@endpush
@endsection
