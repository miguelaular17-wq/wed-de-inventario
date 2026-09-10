@extends('layouts.app')

@section('title', 'Tesorería')

@push('head')
<style>
.tesoreria-page { max-width: 100%; min-width: 0; }
.tesoreria-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.tesoreria-head h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.tesoreria-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.tesoreria-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    margin-bottom: 16px;
    padding: 14px 16px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
}
.tesoreria-filter label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.tesoreria-filter select,
.tesoreria-filter input[type="date"] {
    min-width: 180px;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-weight: 600;
    color: #1e293b;
    background: #fff;
}
.tesoreria-filter button {
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    background: #1a4480;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
.tesoreria-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
}
.tesoreria-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
    border: 1px solid #e2e8f0;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}
.tesoreria-card h2 { font-size: 1.05rem; font-weight: 600; color: #334155; margin: 0 0 12px; }
.tesoreria-table-wrap {
    overflow-x: auto;
    max-width: 100%;
    max-height: min(520px, 70vh);
}
.tesoreria-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 0.85rem;
}
.tesoreria-table th {
    padding: 8px 10px;
    color: #64748b;
    font-weight: 600;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    position: sticky;
    top: 0;
    background: #fff;
}
.tesoreria-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tesoreria-table .col-date { width: 92px; }
.tesoreria-table .col-monto { width: 120px; color: #059669; font-weight: 600; white-space: nowrap; }
.tesoreria-table .col-lote { width: 90px; }
.tesoreria-table .col-acc { width: 76px; text-align: right; white-space: nowrap; overflow: visible; }
.tesoreria-table .col-acc form { display: inline; }
.tesoreria-empty { text-align: center; color: #94a3b8; padding: 16px !important; white-space: normal; }
</style>
@endpush

@section('content')
<div class="tesoreria-page">
    <div class="tesoreria-head">
        <h1>Panel de Tesorería</h1>
        <div class="tesoreria-actions">
            <button type="button" onclick="document.getElementById('modalBanco').style.display='flex'" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: #3b82f6; color: white;">
                + Registrar Ingreso Banco
            </button>
            <button type="button" onclick="document.getElementById('modalPos').style.display='flex'" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: #10b981; color: white;">
                + Registrar Lote POS
            </button>
        </div>
    </div>

    @if(session('success'))
    <div style="padding: 16px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 16px; font-weight: 500;">
        {{ session('success') }}
    </div>
    @endif

    <form method="GET" action="{{ route('tesoreria.dashboard') }}" class="tesoreria-filter">
        <label>
            Desde
            <input type="date" name="desde" value="{{ $desde ?? '' }}">
        </label>
        <label>
            Hasta
            <input type="date" name="hasta" value="{{ $hasta ?? '' }}">
        </label>
        <button type="submit">Filtrar</button>
        @if(($desde ?? '') || ($hasta ?? ''))
            <a href="{{ route('tesoreria.dashboard') }}" style="font-size: 0.85rem; font-weight: 600; color: #2563eb; text-decoration: none; padding-bottom: 8px;">Quitar filtro</a>
        @endif
    </form>

    <div class="tesoreria-grid">
        <div class="tesoreria-card">
            <h2>Últimos Ingresos (Bancos)</h2>
            <div class="tesoreria-table-wrap">
                <table class="tesoreria-table">
                    <thead>
                        <tr>
                            <th class="col-date">Fecha</th>
                            <th>Banco</th>
                            <th class="col-monto">Monto</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ingresosBancos as $ingreso)
                        <tr>
                            <td class="col-date">{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</td>
                            <td>{{ $ingreso->banco }}</td>
                            <td class="col-monto">${{ number_format($ingreso->monto, 2) }}</td>
                            <td title="{{ $ingreso->lote_referencia }}">{{ $ingreso->lote_referencia ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="tesoreria-empty">No hay ingresos registrados</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tesoreria-card">
            <h2>Últimos Lotes POS</h2>
            <div class="tesoreria-table-wrap">
                <table class="tesoreria-table">
                    <colgroup>
                        <col style="width: 92px">
                        <col>
                        <col style="width: 90px">
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
                            <td title="{{ $lote->banco }}{{ $lote->titular ? ' / '.$lote->titular : '' }}">{{ $lote->banco }}{{ $lote->titular ? ' / '.$lote->titular : '' }}</td>
                            <td>{{ $lote->lote_referencia }}</td>
                            <td class="col-monto">Bs. {{ number_format($lote->monto, 2, ',', '.') }}</td>
                            <td title="{{ $lote->descripcion }}">{{ $lote->descripcion ?: '—' }}</td>
                            <td class="col-acc">
                                <button type="button" onclick="editLotePos({{ $lote->id }}, @js($lote->banco), @js($lote->titular), '{{ \Carbon\Carbon::parse($lote->fecha)->format('Y-m-d') }}', @js($lote->lote_referencia), {{ $lote->monto }}, @js($lote->descripcion))" style="background: none; border: none; color: #3b82f6; cursor: pointer; padding: 4px;" title="Editar">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </button>
                                <form action="{{ route('tesoreria.lote_pos.destroy', $lote->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este lote POS?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px;" title="Eliminar">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="tesoreria-empty">{{ ($desde ?? '') || ($hasta ?? '') ? 'No hay lotes POS en el rango de fechas' : 'No hay lotes POS registrados' }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ingreso Banco -->
<div id="modalBanco" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 450px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Registrar Ingreso (Banco)</h3>
            <button type="button" onclick="document.getElementById('modalBanco').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form action="{{ route('tesoreria.ingreso_banco.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Banco</label>
                <select name="banco" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                    <option value="">Seleccione un banco...</option>
                    <option value="Binance">Binance</option>
                    <option value="Zelle">Zelle</option>
                    <option value="Pago Movil">Pago Móvil</option>
                </select>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Documento Excel (.xlsx, .xls)</label>
                <input type="file" name="comprobante" required accept=".xlsx, .xls" style="width: 100%; padding: 8px; border: 1px dashed #cbd5e1; border-radius: 6px; outline: none; background: #f8fafc;">
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Guardar Ingreso</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Lote POS -->
<div id="modalPos" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 450px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Registrar Lote POS</h3>
            <button type="button" onclick="document.getElementById('modalPos').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form action="{{ route('tesoreria.lote_pos.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Banco</label>
                <select name="banco" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                    <option value="">Seleccione un banco...</option>
                    <option value="BANESCO">BANESCO</option>
                    <option value="MERCANTIL">MERCANTIL</option>
                    <option value="VENEZUELA">VENEZUELA</option>
                    <option value="TESORO">TESORO</option>
                    <option value="BNC">BNC</option>
                    <option value="BBVA">BBVA</option>
                    <option value="PROVINCIAL">PROVINCIAL</option>
                    <option value="BANCAMIGA">BANCAMIGA</option>
                    <option value="BANCARIBE">BANCARIBE</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Titular</label>
                <select name="titular" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                    <option value="">Seleccione un titular...</option>
                    <option value="JRZ">JRZ</option>
                    <option value="DORAL">DORAL</option>
                    <option value="LNACEH">LNACEH</option>
                    <option value="NUNES">NUNES</option>
                    <option value="EURONISSI">EURONISSI</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Fecha</label>
                <input type="date" name="fecha" required value="{{ date('Y-m-d') }}" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Lote o Referencia</label>
                <input type="text" name="lote_referencia" required placeholder="Ej. Lote 12345" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Monto</label>
                <input type="number" step="0.01" name="monto" required placeholder="0.00" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Descripción (Opcional)</label>
                <textarea name="descripcion" rows="3" placeholder="Detalles adicionales..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Guardar Lote POS</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Lote POS -->
<div id="modalEditPos" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 450px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Editar Lote POS</h3>
            <button type="button" onclick="document.getElementById('modalEditPos').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>
        <form id="edit_form_pos" method="POST">
            @csrf
            @method('PUT')
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Banco</label>
                <select name="banco" id="edit_pos_banco" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                    <option value="">Seleccione...</option>
                    <option value="BANESCO">BANESCO</option>
                    <option value="MERCANTIL">MERCANTIL</option>
                    <option value="BANCAMIGA">BANCAMIGA</option>
                    <option value="BNC">BNC</option>
                    <option value="BANCARIBE">BANCARIBE</option>
                    <option value="VENEZUELA">VENEZUELA</option>
                    <option value="TESORO">TESORO</option>
                    <option value="BBVA">BBVA</option>
                    <option value="PROVINCIAL">PROVINCIAL</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Titular</label>
                <select name="titular" id="edit_pos_titular" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                    <option value="">Seleccione un titular...</option>
                    <option value="JRZ">JRZ</option>
                    <option value="DORAL">DORAL</option>
                    <option value="LNACEH">LNACEH</option>
                    <option value="NUNES">NUNES</option>
                    <option value="EURONISSI">EURONISSI</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Fecha</label>
                <input type="date" name="fecha" id="edit_pos_fecha" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Lote o Referencia</label>
                <input type="text" name="lote_referencia" id="edit_pos_lote" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Monto</label>
                <input type="number" step="0.01" name="monto" id="edit_pos_monto" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Descripción (Opcional)</label>
                <textarea name="descripcion" id="edit_pos_desc" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="submit" style="flex: 1; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Actualizar Lote POS</button>
            </div>
        </form>
    </div>
</div>

<script>
function partesCuentaPos(banco, titular) {
    const known = ['BANCAMIGA', 'BANCARIBE', 'BANESCO', 'MERCANTIL', 'VENEZUELA', 'TESORO', 'BBVA', 'BNC', 'PROVINCIAL'];
    const b = (banco || '').toUpperCase().trim();
    let t = (titular || '').toUpperCase().trim();
    for (const n of known) {
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
    document.getElementById('edit_form_pos').action = '/tesoreria/lote-punto-venta/' + id;
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
    document.getElementById('edit_pos_desc').value = desc;
}
</script>
@endsection
