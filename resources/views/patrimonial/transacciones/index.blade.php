@extends('layouts.app')
@section('title', 'Gastos y Mantenimiento')
@section('content')

<div style="max-width:1300px; margin:0 auto; padding:24px 20px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <div>
            <div style="font-size:0.8rem; color:#64748b; margin-bottom:4px;">
                <a href="{{ route('patrimonial.dashboard') }}" style="color:#2563eb; text-decoration:none;">🏢 Patrimonial</a> → Transacciones
            </div>
            <h1 style="font-size:1.4rem; font-weight:700; color:#1e293b; margin:0;">💳 Gastos y Mantenimiento</h1>
        </div>
        <a href="{{ route('patrimonial.reportes.mensual') }}?mes={{ $mes }}&anio={{ $anio }}" style="display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; font-weight:600; font-size:0.88rem; text-decoration:none; background:#2563eb; color:#fff;">
            📄 Reporte Mensual
        </a>
    </div>

    {{-- MES NAV --}}
    @php
        $mesPrev = \Carbon\Carbon::create($anio, $mes)->subMonth();
        $mesSig  = \Carbon\Carbon::create($anio, $mes)->addMonth();
    @endphp
    <div style="display:flex; gap:10px; align-items:center; margin-bottom:18px; flex-wrap:wrap;">
        <a href="?mes={{ $mesPrev->month }}&anio={{ $mesPrev->year }}" style="padding:7px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">← Anterior</a>
        <span style="font-weight:700; font-size:1rem; color:#1e293b;">{{ \Carbon\Carbon::create($anio, $mes)->translatedFormat('F Y') }}</span>
        <a href="?mes={{ $mesSig->month }}&anio={{ $mesSig->year }}" style="padding:7px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-decoration:none; color:#334155; font-size:0.85rem; font-weight:600;">Siguiente →</a>
    </div>

    {{-- TOTALES --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:14px; margin-bottom:24px;">
        @foreach([
            ['💵','Ingresos',$totales['ingresos'],'#059669','#d1fae5'],
            ['💸','Gastos',$totales['gastos'],'#dc2626','#fee2e2'],
            ['🤝','Comisiones',$totales['comisiones'],'#d97706','#fef3c7'],
            ['📊','Balance',$totales['balance'], $totales['balance']>=0?'#059669':'#dc2626', $totales['balance']>=0?'#d1fae5':'#fee2e2'],
        ] as [$icon, $label, $val, $color, $bg])
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="font-size:0.75rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; margin-bottom:6px;">{{ $icon }} {{ $label }}</div>
            <div style="font-size:1.3rem; font-weight:700; color:{{ $color }};">${{ number_format($val, 2) }}</div>
        </div>
        @endforeach
    </div>

    <div style="display:grid; grid-template-columns:1fr 1.6fr; gap:20px; align-items:start;">

        {{-- FORMULARIO --}}
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            <div style="padding:14px 18px; border-bottom:1px solid #f1f5f9; background:#f8fafc;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:700; color:#334155;">+ Nueva Transacción</h3>
            </div>
            <div style="padding:18px;">
                <form action="{{ route('patrimonial.transacciones.store') }}" method="POST" id="form-tx">
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
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Tipo *</label>
                                <select name="tipo" required id="tx_tipo" onchange="actualizarCategorias()"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                    <option value="ingreso">💵 Ingreso</option>
                                    <option value="gasto">💸 Gasto</option>
                                    <option value="comision">🤝 Comisión</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Categoría *</label>
                                <select name="categoria" required id="tx_cat"
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Descripción</label>
                            <input type="text" name="descripcion" placeholder="Detalle de la transacción..."
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Monto *</label>
                                <input type="number" name="monto" step="0.01" min="0" required
                                    style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Moneda</label>
                                <select name="moneda" style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit;">
                                    <option value="usd">USD $</option>
                                    <option value="bs">Bs</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="font-size:0.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:4px;">Fecha *</label>
                            <input type="date" name="fecha" value="{{ now()->toDateString() }}" required
                                style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:7px; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                        </div>
                        <button type="submit" style="padding:9px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:600; font-size:0.9rem; cursor:pointer;">💾 Registrar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- LISTADO + RESUMEN POR PROPIEDAD --}}
        <div style="display:flex; flex-direction:column; gap:16px;">

            {{-- RESUMEN --}}
            @if($resumenProps->isNotEmpty())
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc; font-size:0.9rem; font-weight:700; color:#334155;">
                    📊 Balance por Propiedad
                </div>
                @foreach($resumenProps as $res)
                <div style="padding:10px 16px; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                    <span style="font-size:0.88rem; font-weight:600; color:#334155;">{{ $res['nombre'] }}</span>
                    <div style="display:flex; gap:12px; font-size:0.82rem;">
                        <span style="color:#059669;">+${{ number_format($res['ingresos'], 2) }}</span>
                        <span style="color:#dc2626;">-${{ number_format($res['gastos'], 2) }}</span>
                        <span style="font-weight:700; color:{{ $res['balance'] >= 0 ? '#059669' : '#dc2626' }};">=${{ number_format($res['balance'], 2) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- TRANSACCIONES --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background:#f8fafc; font-size:0.9rem; font-weight:700; color:#334155;">
                    📋 Transacciones del Mes
                </div>
                @forelse($transacciones as $tx)
                <div style="padding:11px 16px; border-bottom:1px solid #f8fafc; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#334155;">{{ $tx->descripcion ?: $tx->categoria }}</div>
                        <div style="font-size:0.75rem; color:#94a3b8;">{{ $tx->propiedad->nombre ?? '—' }} · {{ optional($tx->fecha)->format('d/m/Y') }}</div>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span style="font-weight:700; font-size:0.9rem; color:{{ $tx->tipo === 'ingreso' ? '#059669' : ($tx->tipo === 'gasto' ? '#dc2626' : '#d97706') }};">
                            {{ $tx->tipo === 'ingreso' ? '+' : '-' }}${{ number_format($tx->monto, 2) }}
                        </span>
                        <span style="padding:2px 8px; border-radius:12px; font-size:0.72rem; font-weight:700;
                            background:{{ $tx->tipo === 'ingreso' ? '#d1fae5' : ($tx->tipo === 'gasto' ? '#fee2e2' : '#fef3c7') }};
                            color:{{ $tx->tipo === 'ingreso' ? '#065f46' : ($tx->tipo === 'gasto' ? '#991b1b' : '#92400e') }};">
                            {{ ucfirst($tx->tipo) }}
                        </span>
                        <form method="POST" action="{{ route('patrimonial.transacciones.destroy', $tx) }}"
                              onsubmit="return confirm('¿Eliminar esta transacción?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:3px 7px; background:#fff; color:#dc2626; border:1px solid #fca5a5; border-radius:5px; cursor:pointer; font-size:0.72rem;">🗑️</button>
                        </form>
                    </div>
                </div>
                @empty
                <div style="padding:30px; text-align:center; color:#94a3b8; font-size:0.88rem;">Sin transacciones este mes.</div>
                @endforelse
                @if($transacciones->hasPages())
                <div style="padding:12px 16px;">{{ $transacciones->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const categorias = @json(\App\Models\Patrimonial\PatTransaccion::categorias());

function actualizarCategorias() {
    const tipo = document.getElementById('tx_tipo').value;
    const select = document.getElementById('tx_cat');
    select.innerHTML = '';
    (categorias[tipo] || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        select.appendChild(opt);
    });
}
actualizarCategorias();
</script>
@endpush
@endsection
