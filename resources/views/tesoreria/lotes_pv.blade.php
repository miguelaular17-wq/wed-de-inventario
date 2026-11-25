@extends('layouts.app')

@section('title', 'Lotes de PV')

@php
    $bancos = config('cuadre_caja.bancos_punto_venta', []);
    $titulares = config('cuadre_caja.titulares_punto_venta', []);
@endphp

@push('head')
<style>
.tesoreria-page { max-width: 100%; min-width: 0; }
.tesoreria-head {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; flex-wrap: wrap; margin-bottom: 16px;
}
.tesoreria-head h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.tesoreria-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.tesoreria-filter {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
    margin-bottom: 16px; padding: 14px 16px; background: #eff6ff;
    border: 1px solid #bfdbfe; border-radius: 12px;
}
.tesoreria-filter label {
    display: flex; flex-direction: column; gap: 4px; font-size: 0.75rem;
    font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em;
}
.tesoreria-filter input[type="search"],
.tesoreria-filter input[type="date"] {
    min-width: 160px; padding: 8px 12px; border: 1px solid #cbd5e1;
    border-radius: 8px; font-weight: 600; color: #1e293b; background: #fff;
}
.tesoreria-filter button {
    padding: 8px 14px; border: none; border-radius: 8px;
    background: #1a4480; color: #fff; font-weight: 600; cursor: pointer;
}
.tesoreria-card {
    background: #fff; border-radius: 12px; padding: 16px 18px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05); border: 1px solid #e2e8f0;
    min-width: 0; max-width: 100%; overflow: hidden;
}
.tesoreria-card h2 { font-size: 1.05rem; font-weight: 600; color: #334155; margin: 0 0 12px; }
.tesoreria-table-wrap { overflow-x: auto; max-width: 100%; max-height: min(70vh, 640px); }
.tesoreria-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 0.85rem; }
.tesoreria-table th {
    padding: 8px 10px; color: #64748b; font-weight: 600; text-align: left;
    border-bottom: 2px solid #e2e8f0; white-space: nowrap; position: sticky; top: 0; background: #fff;
}
.tesoreria-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.tesoreria-table .col-monto { text-align: right; font-weight: 700; white-space: nowrap; }
.tesoreria-table .col-acc { width: 76px; white-space: nowrap; }
.tesoreria-empty { text-align: center; color: #94a3b8; padding: 28px 12px !important; }
.btn-pv {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
    border: none; border-radius: 8px; background: #059669; color: #fff;
    font-weight: 700; cursor: pointer; font-size: .88rem;
}
.modal-pv {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);
}
.modal-pv-box {
    background: #fff; border-radius: 12px; width: 100%; max-width: 450px;
    padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1);
}
.modal-pv label { display: block; font-size: .875rem; font-weight: 600; color: #475569; margin-bottom: 6px; }
.modal-pv input, .modal-pv select, .modal-pv textarea {
    width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;
}
.modal-pv .field { margin-bottom: 16px; }
</style>
@endpush

@section('content')
<div class="tesoreria-page">
    <div class="tesoreria-head">
        <div>
            <h1>Lotes de PV</h1>
            <p class="muted" style="margin:4px 0 0;">Lotes de punto de venta (incluye los que vienen del arqueo de caja).</p>
        </div>
        <div class="tesoreria-actions">
            <button type="button" class="btn-pv" onclick="document.getElementById('modalPos').style.display='flex'">+ Registrar lote</button>
        </div>
    </div>

    @if(session('success'))
        <div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" action="{{ route('tesoreria.lote_pos.index') }}" class="tesoreria-filter">
        <label>Desde
            <input type="date" name="desde" value="{{ $desde ?? '' }}">
        </label>
        <label>Hasta
            <input type="date" name="hasta" value="{{ $hasta ?? '' }}">
        </label>
        <label>Lote / referencia
            <input type="search" name="ref" value="{{ $ref ?? '' }}" placeholder="Nº de lote">
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <div class="tesoreria-card">
        <h2>{{ $lotesPuntos->count() }} lote(s)</h2>
        <div class="tesoreria-table-wrap">
            <table class="tesoreria-table">
                <colgroup>
                    <col style="width: 92px">
                    <col>
                    <col style="width: 100px">
                    <col style="width: 130px">
                    <col>
                    <col style="width: 76px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Banco / Titular</th>
                        <th>Lote/Ref</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                        <th class="col-acc">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lotesPuntos as $lote)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                            <td title="{{ $lote->banco }}{{ $lote->titular ? ' / '.$lote->titular : '' }}">
                                {{ $lote->banco }}{{ $lote->titular ? ' / '.$lote->titular : '' }}
                            </td>
                            <td>{{ $lote->lote_referencia }}</td>
                            <td class="col-monto">Bs. {{ number_format($lote->monto, 2, ',', '.') }}</td>
                            <td title="{{ $lote->descripcion }}">{{ $lote->descripcion ?: '—' }}</td>
                            <td class="col-acc">
                                <button type="button"
                                    onclick="editLotePos({{ $lote->id }}, @js($lote->banco), @js($lote->titular), '{{ \Carbon\Carbon::parse($lote->fecha)->format('Y-m-d') }}', @js($lote->lote_referencia), {{ $lote->monto }}, @js($lote->descripcion))"
                                    style="background:none;border:none;color:#3b82f6;cursor:pointer;padding:4px;" title="Editar">✎</button>
                                <form action="{{ route('tesoreria.lote_pos.destroy', $lote->id) }}" method="POST" style="display:inline;"
                                      onsubmit="return confirm('¿Eliminar este lote POS?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:4px;" title="Eliminar">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tesoreria-empty">
                                {{ ($desde ?? '') || ($hasta ?? '') || ($ref ?? '') ? 'No hay lotes con esos filtros.' : 'No hay lotes POS registrados.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalPos" class="modal-pv">
    <div class="modal-pv-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;font-size:1.2rem;font-weight:700;">Registrar lote POS</h3>
            <button type="button" onclick="document.getElementById('modalPos').style.display='none'" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <form action="{{ route('tesoreria.lote_pos.store') }}" method="POST">
            @csrf
            <div class="field">
                <label>Banco</label>
                <select name="banco" required>
                    <option value="">Seleccione…</option>
                    @foreach($bancos as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Titular</label>
                <select name="titular" required>
                    <option value="">Seleccione…</option>
                    @foreach($titulares as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha" required value="{{ date('Y-m-d') }}">
            </div>
            <div class="field">
                <label>Lote o referencia</label>
                <input type="text" name="lote_referencia" required placeholder="Ej. 12345">
            </div>
            <div class="field">
                <label>Monto (Bs)</label>
                <input type="number" step="0.01" name="monto" required placeholder="0.00">
            </div>
            <div class="field" style="margin-bottom:24px;">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" rows="3"></textarea>
            </div>
            <button type="submit" class="btn-pv" style="width:100%;justify-content:center;">Guardar lote</button>
        </form>
    </div>
</div>

<div id="modalEditPos" class="modal-pv">
    <div class="modal-pv-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;font-size:1.2rem;font-weight:700;">Editar lote POS</h3>
            <button type="button" onclick="document.getElementById('modalEditPos').style.display='none'" style="background:none;border:none;font-size:1.5rem;color:#94a3b8;cursor:pointer;">&times;</button>
        </div>
        <form id="edit_form_pos" method="POST">
            @csrf
            @method('PUT')
            <div class="field">
                <label>Banco</label>
                <select name="banco" id="edit_pos_banco" required>
                    <option value="">Seleccione…</option>
                    @foreach($bancos as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Titular</label>
                <select name="titular" id="edit_pos_titular" required>
                    <option value="">Seleccione…</option>
                    @foreach($titulares as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha" id="edit_pos_fecha" required>
            </div>
            <div class="field">
                <label>Lote o referencia</label>
                <input type="text" name="lote_referencia" id="edit_pos_lote" required>
            </div>
            <div class="field">
                <label>Monto (Bs)</label>
                <input type="number" step="0.01" name="monto" id="edit_pos_monto" required>
            </div>
            <div class="field" style="margin-bottom:24px;">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" id="edit_pos_desc" rows="3"></textarea>
            </div>
            <button type="submit" style="width:100%;padding:12px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Actualizar</button>
        </form>
    </div>
</div>

<script>
const bancosPv = @json($bancos);
function partesCuentaPos(banco, titular) {
    const b = (banco || '').toUpperCase().trim();
    let t = (titular || '').toUpperCase().trim();
    for (const n of bancosPv) {
        if (b === n || b.startsWith(n + ' ') || b.includes(n)) {
            const resto = b.replace(n, '').trim();
            if (!t && resto) t = resto;
            return [n, t];
        }
    }
    return [b, t];
}
function editLotePos(id, banco, titular, fecha, lote, monto, desc) {
    document.getElementById('modalEditPos').style.display = 'flex';
    document.getElementById('edit_form_pos').action = @json(url('/tesoreria/lote-punto-venta')) + '/' + id;
    const [bancoNorm, titularNorm] = partesCuentaPos(banco, titular);
    document.getElementById('edit_pos_banco').value = bancoNorm;
    const selTit = document.getElementById('edit_pos_titular');
    if (titularNorm && ![...selTit.options].some(o => o.value === titularNorm)) {
        selTit.add(new Option(titularNorm, titularNorm, true, true));
    }
    selTit.value = titularNorm;
    document.getElementById('edit_pos_fecha').value = fecha;
    document.getElementById('edit_pos_lote').value = lote;
    document.getElementById('edit_pos_monto').value = monto;
    document.getElementById('edit_pos_desc').value = desc || '';
}
</script>
@endsection
