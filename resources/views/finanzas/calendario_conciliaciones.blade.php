@extends('layouts.app')

@section('title', 'Calendario de conciliaciones')

@push('head')
<style>
.cal-page { padding: 28px; font-family: 'Inter', sans-serif; background: #f1f5f9; min-height: 100vh; }
.cal-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.06); overflow: hidden; }
.cal-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; background: #0f172a; color: white; flex-wrap: wrap; }
.cal-head h3 { margin: 0; font-size: 1.15rem; font-weight: 800; }
.cal-nav { display: flex; align-items: center; gap: 10px; }
.cal-nav a { color: white; text-decoration: none; background: rgba(255,255,255,.12); border-radius: 8px; padding: 6px 12px; font-weight: 700; }
.cal-resumen { color: rgba(255,255,255,.75); font-size: 0.85rem; }
.cal-body { padding: 16px 20px 20px; }
.cal-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-bottom: 16px; }
.cal-form label { display: flex; flex-direction: column; gap: 4px; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.select-banco { padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 0.95rem; color: #334155; background: white; min-width: 160px; }
.btn-submit { background: linear-gradient(135deg,#2563eb,#1d4ed8); color: white; padding: 10px 22px; border: none; border-radius: 9px; font-weight: 700; cursor: pointer; font-family: inherit; }
.cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
.cal-dow { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-align: center; padding-bottom: 4px; }
.cal-day { min-height: 78px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px; background: #f8fafc; }
.cal-day.cal-out { background: transparent; border-color: transparent; }
.cal-day.cal-on { background: #ecfdf5; border-color: #a7f3d0; }
.cal-num { font-size: 0.78rem; font-weight: 800; color: #334155; }
.cal-count { margin-top: 6px; font-size: 0.72rem; font-weight: 700; color: #047857; line-height: 1.3; }
.cal-cuentas { margin-top: 18px; display: flex; flex-direction: column; gap: 10px; }
.cal-cuenta { display: flex; justify-content: space-between; gap: 12px; align-items: center; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 12px; flex-wrap: wrap; }
.cal-cuenta.ok { border-color: #86efac; background: #f0fdf4; }
.cal-cuenta.parcial { border-color: #fcd34d; background: #fffbeb; }
.cal-banco { font-weight: 800; color: #0f172a; }
.cal-titular { color: #475569; font-size: 0.88rem; font-weight: 600; }
.cal-link { color: inherit; text-decoration: none; display: inline-flex; gap: 6px; align-items: baseline; }
.cal-link:hover .cal-banco { text-decoration: underline; }
.cal-estado { font-size: 0.75rem; font-weight: 800; border-radius: 999px; padding: 3px 8px; }
.cal-estado.ok { background: #dcfce7; color: #166534; }
.cal-estado.parcial { background: #fef3c7; color: #92400e; }
.cal-estado.pend { background: #f1f5f9; color: #64748b; }
.cal-rango { font-size: 0.8rem; color: #334155; display: flex; align-items: center; gap: 8px; }
.cal-pdf { background: #1a4480; border: 1px solid #1a4480; color: white; border-radius: 7px; padding: 3px 8px; font-size: 0.75rem; font-weight: 700; text-decoration: none; }
.cal-pdf:hover { background: #153a6e; }
.cal-quitar { background: white; border: 1px solid #fecaca; color: #b91c1c; border-radius: 7px; padding: 3px 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
.cal-sinmov-btn { background: white; border: 1px solid #cbd5e1; color: #475569; border-radius: 7px; padding: 3px 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
.cal-sinmov { margin-top: 18px; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
.cal-sinmov summary { cursor: pointer; font-size: 0.82rem; font-weight: 800; color: #64748b; }
.cal-sinmov-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 10px; margin-top: 8px; border-radius: 10px; background: #f8fafc; color: #64748b; }
.cal-dias { display: flex; gap: 2px; flex-wrap: wrap; max-width: 280px; }
.cal-dot { width: 8px; height: 8px; border-radius: 2px; background: #e2e8f0; }
.cal-dot.on { background: #10b981; }
.cal-movs { margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap; }
.cal-mov { font-size: 0.74rem; font-weight: 700; border-radius: 7px; padding: 3px 8px; border: 1px solid; text-decoration: none; }
.cal-mov.ok { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.cal-mov.falta { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.cal-mov.transito { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.cal-mov.vacio { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; }
.alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-weight: 500; text-align: center; }
</style>
@endpush

@section('content')
@php $cal = $calendarioConciliacion; @endphp
<div class="cal-page">
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">{{ session('error') }}</div>
    @endif

    <div class="cal-card">
        <div class="cal-head">
            <div>
                <h3>Calendario {{ $cal['titulo'] }}</h3>
                <div class="cal-resumen">{{ $cal['completas'] }} de {{ $cal['total'] }} cuentas conciliadas el mes completo</div>
            </div>
            <div class="cal-nav">
                <a href="{{ route('finanzas.calendario_conciliaciones.pdf', ['cal_mes' => $cal['mes']]) }}">Descargar PDF</a>
                <a href="{{ route('finanzas.calendario_conciliaciones', ['cal_mes' => $cal['anterior']]) }}">←</a>
                <a href="{{ route('finanzas.calendario_conciliaciones', ['cal_mes' => $cal['siguiente']]) }}">→</a>
            </div>
        </div>
        <div class="cal-body">
            <form class="cal-form" action="{{ route('finanzas.conciliaciones.periodos.store') }}" method="POST">
                @csrf
                <label>Banco
                    <select name="banco" id="calBanco" class="select-banco" required onchange="filtrarTitularesCalendario(this.value)">
                        <option value="">Seleccione</option>
                        @foreach($bancos as $b)
                            <option value="{{ $b }}">{{ $b }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Titular
                    <select name="titular" id="calTitular" class="select-banco" required disabled>
                        <option value="">Seleccione el banco</option>
                    </select>
                </label>
                <label>Desde
                    <input type="date" name="fecha_desde" class="select-banco" value="{{ $cal['inicio'] }}" required style="min-width:140px;">
                </label>
                <label>Hasta
                    <input type="date" name="fecha_hasta" class="select-banco" value="{{ $cal['fin'] }}" required style="min-width:140px;">
                </label>
                <button type="submit" class="btn-submit">Marcar conciliado</button>
            </form>
            @if($errors->any())
                <div class="alert-success" style="background:#fef2f2;color:#991b1b;border-color:#fca5a5;">{{ $errors->first() }}</div>
            @endif

            <div class="cal-grid">
                @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dow)
                    <div class="cal-dow">{{ $dow }}</div>
                @endforeach
                @foreach($cal['celdas'] as $celda)
                    <div class="cal-day {{ $celda['en_mes'] ? '' : 'cal-out' }} {{ count($celda['cuentas']) ? 'cal-on' : '' }}" title="{{ implode(' | ', $celda['cuentas']) }}">
                        @if($celda['en_mes'])
                            <div class="cal-num">{{ $celda['dia'] }}</div>
                            @if(count($celda['cuentas']))
                                <div class="cal-count">{{ count($celda['cuentas']) }} {{ count($celda['cuentas']) === 1 ? 'cuenta' : 'cuentas' }}</div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="cal-cuentas">
                @forelse($cal['filas'] as $fila)
                    @php
                        $estado = $fila['completo'] ? 'ok' : ($fila['cubiertos'] > 0 ? 'parcial' : 'pend');
                        $estadoTexto = $fila['completo'] ? 'Conciliado' : ($fila['cubiertos'] > 0 ? 'Parcial' : 'Pendiente');
                    @endphp
                    <div class="cal-cuenta {{ $estado === 'pend' ? '' : $estado }}">
                        <div>
                            <a class="cal-link" href="{{ route('finanzas.conciliaciones', [
                                'fecha_desde' => $cal['inicio'],
                                'fecha_hasta' => $cal['fin'],
                                'banco_filtro' => $fila['banco'],
                                'titular_filtro' => $fila['titular'],
                            ]) }}">
                                <span class="cal-banco">{{ $fila['banco'] }}</span>
                                <span class="cal-titular">· {{ $fila['titular'] }}</span>
                            </a>
                            <div style="margin-top:6px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <span class="cal-estado {{ $estado }}">{{ $estadoTexto }}</span>
                                @if(! $fila['completo'])
                                    <form action="{{ route('finanzas.conciliaciones.periodos.sin_movimientos') }}" method="POST" style="margin:0;"
                                          onsubmit="return confirm('¿Quitar {{ $fila['banco'] }} · {{ $fila['titular'] }} de {{ $cal['titulo'] }} por no tener movimientos?');">
                                        @csrf
                                        <input type="hidden" name="banco" value="{{ $fila['banco'] }}">
                                        <input type="hidden" name="titular" value="{{ $fila['titular'] }}">
                                        <input type="hidden" name="mes" value="{{ $cal['mes'] }}">
                                        <button type="submit" class="cal-sinmov-btn">Sin movimientos</button>
                                    </form>
                                @endif
                                @foreach($fila['rangos'] as $rango)
                                    <span class="cal-rango">
                                        {{ \Carbon\Carbon::parse($rango['desde'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($rango['hasta'])->format('d/m/Y') }}
                                        <a class="cal-pdf" target="_blank" rel="noopener"
                                           title="Descargar el documento de conciliación de este rango"
                                           href="{{ route('finanzas.conciliaciones.reporte-banco', [
                                               'banco' => $fila['banco'],
                                               'titular' => $fila['titular'],
                                               'fecha_desde' => \Carbon\Carbon::parse($rango['desde'])->toDateString(),
                                               'fecha_hasta' => \Carbon\Carbon::parse($rango['hasta'])->toDateString(),
                                           ]) }}">PDF</a>
                                        <form action="{{ route('finanzas.conciliaciones.periodos.destroy', $rango['id']) }}" method="POST" onsubmit="return confirm('¿Quitar esta marca?');" style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cal-quitar">Quitar</button>
                                        </form>
                                    </span>
                                @endforeach
                            </div>
                            <div class="cal-movs" data-clave="{{ $fila['clave'] }}" data-url="{{ route('finanzas.conciliaciones', [
                                    'fecha_desde' => $cal['inicio'],
                                    'fecha_hasta' => $cal['fin'],
                                    'banco_filtro' => $fila['banco'],
                                    'titular_filtro' => $fila['titular'],
                                ]) }}">
                                <span class="cal-mov vacio">Contando movimientos…</span>
                            </div>
                        </div>
                        <div class="cal-dias" title="Días conciliados del mes">
                            @foreach($fila['dias'] as $cubierto)
                                <span class="cal-dot {{ $cubierto ? 'on' : '' }}"></span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="alert-info" style="margin:0;">No hay cuentas bancarias para mostrar en el calendario.</div>
                @endforelse
            </div>

            @if(! empty($cal['sin_movimientos']))
                <details class="cal-sinmov">
                    <summary>Sin movimientos en {{ $cal['titulo'] }} ({{ count($cal['sin_movimientos']) }})</summary>
                    @foreach($cal['sin_movimientos'] as $cuenta)
                        <div class="cal-sinmov-item">
                            <span><strong>{{ $cuenta['banco'] }}</strong> · {{ $cuenta['titular'] }}</span>
                            <form action="{{ route('finanzas.conciliaciones.periodos.destroy', $cuenta['cierre_id']) }}" method="POST" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cal-sinmov-btn">Volver a mostrar</button>
                            </form>
                        </div>
                    @endforeach
                </details>
            @endif
        </div>
    </div>
</div>
<script>
// Movimientos del banco por conciliar y egresos en tránsito de cada cuenta (se piden aparte porque tardan).
(function () {
    const cajas = document.querySelectorAll('.cal-movs[data-clave]');
    if (!cajas.length) return;
    const bs = n => 'Bs. ' + Number(n || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const entero = n => Number(n || 0).toLocaleString('es-VE');
    const chip = (clase, texto, titulo, url) => {
        const el = document.createElement(url ? 'a' : 'span');
        el.className = 'cal-mov ' + clase;
        el.textContent = texto;
        el.title = titulo;
        if (url) el.href = url;
        return el;
    };

    fetch(@json(route('finanzas.calendario_conciliaciones.movimientos', ['cal_mes' => $cal['mes']])), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(datos => {
            cajas.forEach(caja => {
                const m = datos[caja.dataset.clave] || { banco_total: 0, banco_pendientes: 0, banco_pendientes_bs: 0, transito: 0, transito_bs: 0 };
                const url = caja.dataset.url;
                caja.innerHTML = '';
                if (!m.banco_total) {
                    caja.appendChild(chip('vacio', 'Banco: sin extracto', 'No hay extracto del banco cargado para este mes'));
                } else if (!m.banco_pendientes) {
                    caja.appendChild(chip('ok', '✓ Banco en 0 · ' + entero(m.banco_total) + ' conciliados', 'Todos los movimientos del banco del mes están conciliados', url));
                } else {
                    caja.appendChild(chip('falta', 'Banco: faltan ' + entero(m.banco_pendientes) + ' de ' + entero(m.banco_total) + ' · ' + bs(m.banco_pendientes_bs), 'Movimientos del extracto del banco que todavía no se concilian', url));
                }
                if (!m.transito) {
                    caja.appendChild(chip('ok', '✓ Tránsito en 0', 'No hay egresos registrados esperando que aparezcan en el banco'));
                } else {
                    caja.appendChild(chip('transito', 'En tránsito: ' + entero(m.transito) + ' · ' + bs(m.transito_bs), 'Egresos registrados que el banco todavía no refleja', url));
                }
            });
        })
        .catch(() => {
            cajas.forEach(caja => { caja.innerHTML = '<span class="cal-mov vacio">No se pudieron contar los movimientos</span>'; });
        });
})();

const titularesPorBanco = @json($titularesPorBanco);

function filtrarTitularesCalendario(banco) {
    const selTit = document.getElementById('calTitular');
    if (!selTit) return;
    selTit.innerHTML = '<option value="">Seleccione el titular</option>';
    if (!banco || !titularesPorBanco[banco]) {
        selTit.disabled = true;
        return;
    }
    titularesPorBanco[banco].forEach(function(tit) {
        const opt = document.createElement('option');
        opt.value = tit;
        opt.textContent = tit;
        selTit.appendChild(opt);
    });
    selTit.disabled = false;
    if (titularesPorBanco[banco].length === 1) {
        selTit.value = titularesPorBanco[banco][0];
    }
}
</script>
@endsection
