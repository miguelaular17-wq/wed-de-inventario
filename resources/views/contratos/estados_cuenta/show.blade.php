@extends('layouts.app')
@section('title', 'Estado de cuenta · '.$intermediario->nombre)
@section('content')
@php
    $tot = ['capital' => $filas->sum('capital'), 'intereses' => $filas->sum('intereses'), 'total' => $filas->sum('total')];
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<style>
    .ec-tabla { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .ec-tabla thead th { background: #9ca3af; color: #fff; text-transform: uppercase; font-size: .75rem; padding: 9px 10px; text-align: left; }
    .ec-tabla td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; }
    .ec-tabla .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ec-tabla tfoot td { font-weight: 800; border-top: 2px solid #475569; background: #f8fafc; }
    .ec-tabla tfoot td.deuda { background: #fef08a; }
    .ec-add-cab { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .ec-add-barra { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .ec-add-barra input { flex: 1; min-width: 240px; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .92rem; }
    .ec-btn-sec { padding: 8px 12px; background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: .85rem; }
    .ec-lista { border: 1px solid #e2e8f0; border-radius: 10px; max-height: 320px; overflow-y: auto; background: #fff; }
    .ec-op { display: grid; grid-template-columns: 22px 100px 1fr auto 110px; gap: 10px; align-items: center; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: .88rem; }
    .ec-op[hidden] { display: none; }
    .ec-op:last-of-type { border-bottom: 0; }
    .ec-op:hover { background: #f8fafc; }
    .ec-op.marcado { background: #ecfdf5; }
    .ec-op input { width: 16px; height: 16px; cursor: pointer; }
    .ec-op-num { font-family: ui-monospace, monospace; color: #1d4ed8; font-weight: 600; font-size: .82rem; }
    .ec-op-cliente { font-weight: 600; color: #0f172a; }
    .ec-op-cliente small { display: block; font-weight: 500; color: #b45309; font-size: .75rem; }
    .ec-op-estatus { font-size: .68rem; font-weight: 800; padding: 2px 8px; border-radius: 999px; }
    .ec-op-deuda { text-align: right; font-weight: 700; color: #b91c1c; font-variant-numeric: tabular-nums; }
    .ec-add-pie { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; gap: 10px; }
</style>
<div style="padding: 20px; max-width: 1150px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <div>
            <div class="muted" style="font-size:.8rem; font-weight:700; text-transform:uppercase;">Estado de cuenta</div>
            <h2 style="color: var(--blue); font-weight: 800; margin: 2px 0 0;">{{ $intermediario->nombre }}</h2>
            <div class="muted">{{ collect([$intermediario->documento, $intermediario->telefono])->filter()->implode(' · ') ?: 'Sin cédula registrada' }}</div>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('contratos.estados_cuenta.index') }}" style="padding: 8px 16px; background: #e0f2fe; color: #0284c7; border-radius: 6px; text-decoration: none; font-weight: 500;">← Estados de cuenta</a>
            <a href="{{ route('contratos.estados_cuenta.pdf', $intermediario) }}" target="_blank" rel="noopener" style="padding: 8px 16px; background: var(--blue); color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">📄 Estado de cuenta en PDF</a>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#d1e7dd; color:#0f5132; padding:12px 16px; border-radius:8px; margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <div class="panel" style="padding:0; overflow:auto; margin-bottom:16px;">
        <table class="ec-tabla">
            <thead>
                <tr><th style="width:110px;">Fecha venc.</th><th>Descripción</th><th class="num">Capital</th><th class="num">Total intereses</th><th class="num">Total deuda</th><th style="width:90px;">Estatus</th><th style="width:40px;"></th></tr>
            </thead>
            <tbody>
                @forelse($filas as $n => $f)
                    <tr>
                        <td>{{ $f['vence']?->format('d/m/Y') ?? '—' }}</td>
                        <td><strong>{{ $n + 1 }} - {{ $f['descripcion'] }}</strong>
                            <a href="{{ route('contratos.show', $f['contrato']->id) }}" class="muted" style="font-size:.78rem; margin-left:4px;">{{ $f['contrato']->numero_contrato }}</a></td>
                        <td class="num">{{ $m($f['capital']) }}</td>
                        <td class="num">{{ $m($f['intereses']) }}</td>
                        <td class="num" style="font-weight:700;">{{ $m($f['total']) }}</td>
                        <td>
                            <span style="font-size:.7rem; font-weight:800; padding:2px 8px; border-radius:999px; {{ $f['estatus'] === 'VENCIDO' ? 'background:#fee2e2; color:#991b1b;' : 'background:#dbeafe; color:#1e40af;' }}">{{ $f['estatus'] }}</span>
                            @if($f['dias_atraso'] > 0)<div class="muted" style="font-size:.72rem;">{{ $f['dias_atraso'] }}d atraso</div>@endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('contratos.estados_cuenta.quitar', [$intermediario, $f['contrato']]) }}" onsubmit="return confirm('¿Quitar {{ $f['contrato']->numero_contrato }} de este estado de cuenta? El contrato no se borra.');">
                                @csrf @method('DELETE')
                                <button type="submit" title="Quitar del estado de cuenta" style="border:0; background:#fee2e2; color:#b91c1c; border-radius:6px; padding:3px 8px; cursor:pointer; font-weight:700;">×</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="text-align:center; padding:26px;">Todavía no tiene contratos. Agrégalos abajo.</td></tr>
                @endforelse
            </tbody>
            @if($filas->isNotEmpty())
                <tfoot>
                    <tr><td></td><td style="text-align:right;">TOTALES</td><td class="num">{{ $m($tot['capital']) }}</td><td class="num">{{ $m($tot['intereses']) }}</td><td class="num deuda">{{ $m($tot['total']) }}</td><td colspan="2"></td></tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="panel" style="padding:16px; margin-bottom:16px;">
        <form method="POST" action="{{ route('contratos.estados_cuenta.asignar', $intermediario) }}" id="ec-form">
            @csrf
            <div class="ec-add-cab">
                <h3 style="margin:0; font-size:1rem;">Agregar contratos a {{ $intermediario->nombre }}</h3>
                <span class="muted" style="font-size:.82rem;">Marca los contratos y pulsa «Agregar». Si uno ya estaba con otra persona, pasa a esta.</span>
            </div>
            <div class="ec-add-barra">
                <input type="search" id="ec-filtro" placeholder="🔍 Buscar por cliente o número de contrato…" autocomplete="off">
                <button type="button" id="ec-marcar" class="ec-btn-sec">Marcar los visibles</button>
                <button type="button" id="ec-limpiar" class="ec-btn-sec">Desmarcar</button>
            </div>
            <div class="ec-lista" id="ec-lista">
                @forelse($disponibles as $f)
                    @php $c = $f['contrato']; @endphp
                    <label class="ec-op" data-texto="{{ mb_strtolower($c->numero_contrato.' '.$c->cliente.' '.($c->intermediario?->nombre ?? ''), 'UTF-8') }}">
                        <input type="checkbox" name="contratos[]" value="{{ $c->id }}">
                        <span class="ec-op-num">{{ $c->numero_contrato }}</span>
                        <span class="ec-op-cliente">{{ $c->cliente }}
                            @if($c->intermediario)<small>hoy en: {{ $c->intermediario->nombre }}</small>@endif
                        </span>
                        <span class="ec-op-estatus" style="{{ $f['estatus'] === 'VENCIDO' ? 'background:#fee2e2; color:#991b1b;' : 'background:#dbeafe; color:#1e40af;' }}">{{ $f['estatus'] }}</span>
                        <span class="ec-op-deuda">${{ number_format($f['total'], 2) }}</span>
                    </label>
                @empty
                    <div class="muted" style="padding:16px; text-align:center;">No hay más contratos vigentes para agregar.</div>
                @endforelse
                <div class="muted" id="ec-sin-resultados" style="padding:16px; text-align:center; display:none;">Ningún contrato coincide con la búsqueda.</div>
            </div>
            <div class="ec-add-pie">
                <span id="ec-contador" class="muted">Ninguno marcado</span>
                <button type="submit" id="ec-agregar" disabled style="padding:9px 18px; background:#059669; color:#fff; border:0; border-radius:8px; font-weight:700; cursor:pointer;">+ Agregar</button>
            </div>
        </form>
    </div>

    <details class="panel" style="padding:12px 16px;">
        <summary style="cursor:pointer; font-weight:700;">Editar datos de la persona</summary>
        <form method="POST" action="{{ route('contratos.estados_cuenta.update', $intermediario) }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:10px;">
            @csrf @method('PUT')
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Nombre *</label><input type="text" name="nombre" required maxlength="200" value="{{ $intermediario->nombre }}" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:240px;"></div>
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Cédula / RIF</label><input type="text" name="documento" maxlength="40" value="{{ $intermediario->documento }}" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:150px;"></div>
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Teléfono</label><input type="text" name="telefono" maxlength="60" value="{{ $intermediario->telefono }}" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:150px;"></div>
            <button type="submit" style="padding:9px 16px; background:var(--blue); color:#fff; border:0; border-radius:6px; font-weight:700; cursor:pointer;">Guardar</button>
        </form>
        <form method="POST" action="{{ route('contratos.estados_cuenta.destroy', $intermediario) }}" onsubmit="return confirm('¿Quitar a {{ $intermediario->nombre }}? Sus contratos NO se borran, solo dejan de estar agrupados.');" style="margin-top:10px;">
            @csrf @method('DELETE')
            <button type="submit" style="padding:6px 12px; background:#fff; color:#b91c1c; border:1px solid #fecaca; border-radius:6px; font-weight:600; cursor:pointer;">Quitar esta persona</button>
        </form>
    </details>
</div>
<script>
(function () {
    const opciones = [...document.querySelectorAll('#ec-lista .ec-op')];
    const filtro = document.getElementById('ec-filtro');
    const contador = document.getElementById('ec-contador');
    const agregar = document.getElementById('ec-agregar');
    const contar = () => {
        const n = opciones.filter(o => o.querySelector('input').checked).length;
        contador.textContent = n === 0 ? 'Ninguno marcado' : (n === 1 ? '1 contrato marcado' : n + ' contratos marcados');
        agregar.disabled = n === 0;
        agregar.style.opacity = n === 0 ? .5 : 1;
        opciones.forEach(o => o.classList.toggle('marcado', o.querySelector('input').checked));
    };
    filtro?.addEventListener('input', () => {
        const q = filtro.value.trim().toLowerCase();
        let visibles = 0;
        opciones.forEach(o => { const ver = q === '' || o.dataset.texto.includes(q); o.hidden = !ver; if (ver) visibles++; });
        document.getElementById('ec-sin-resultados').style.display = visibles === 0 && opciones.length ? '' : 'none';
    });
    document.getElementById('ec-marcar')?.addEventListener('click', () => { opciones.forEach(o => { if (!o.hidden) o.querySelector('input').checked = true; }); contar(); });
    document.getElementById('ec-limpiar')?.addEventListener('click', () => { opciones.forEach(o => { o.querySelector('input').checked = false; }); contar(); });
    opciones.forEach(o => o.querySelector('input').addEventListener('change', contar));
    contar();
})();
</script>
@endsection
