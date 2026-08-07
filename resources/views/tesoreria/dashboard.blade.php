@extends('layouts.app')

@section('content')
<div class="container-fluid" style="padding: 24px; max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Panel de Tesorería</h1>
        <div style="display: flex; gap: 12px;">
            <button onclick="document.getElementById('modalBanco').style.display='flex'" class="btn-primary" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: #3b82f6; color: white; box-shadow: 0 4px 6px -1px rgba(59,130,246,0.5);">
                + Registrar Ingreso Banco
            </button>
            <button onclick="document.getElementById('modalPos').style.display='flex'" class="btn-primary" style="padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: #10b981; color: white; box-shadow: 0 4px 6px -1px rgba(16,185,129,0.5);">
                + Registrar Lote POS
            </button>
        </div>
    </div>

    @if(session('success'))
    <div style="padding: 16px; background: #d1fae5; color: #065f46; border-radius: 8px; margin-bottom: 24px; font-weight: 500;">
        {{ session('success') }}
    </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
        <!-- Lista de Bancos -->
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #334155; margin: 0 0 16px;">Últimos Ingresos (Bancos)</h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Fecha</th>
                            <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Banco</th>
                            <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Monto</th>
                            <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ingresosBancos as $ingreso)
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 8px; color: #334155;">{{ \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') }}</td>
                            <td style="padding: 12px 8px; font-weight: 500; color: #1e293b;">{{ $ingreso->banco }}</td>
                            <td style="padding: 12px 8px; color: #059669; font-weight: 600;">${{ number_format($ingreso->monto, 2) }}</td>
                            <td style="padding: 12px 8px; color: #475569; font-size: 0.9rem;">{{ $ingreso->lote_referencia ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="padding: 16px; text-align: center; color: #94a3b8;">No hay ingresos registrados</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lista Lotes POS -->
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #334155; margin: 0 0 16px;">Últimos Lotes POS</h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600;">Fecha</th>
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600;">Banco / Titular</th>
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600;">Lote/Ref</th>
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600;">Monto</th>
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600;">Descripción</th>
                            <th style="padding: 10px 4px; color: #64748b; font-weight: 600; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lotesPuntos as $lote)
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 4px; color: #334155;">{{ \Carbon\Carbon::parse($lote->fecha)->format('d/m/Y') }}</td>
                            <td style="padding: 10px 4px; color: #475569; font-weight: 500;">{{ $lote->banco }}</td>
                            <td style="padding: 10px 4px; font-weight: 500; color: #1e293b;">{{ $lote->lote_referencia }}</td>
                            <td style="padding: 10px 4px; color: #059669; font-weight: 600;">Bs. {{ number_format($lote->monto, 2, ',', '.') }}</td>
                            <td style="padding: 10px 4px; color: #64748b; font-size: 0.9rem;">{{ Str::limit($lote->descripcion ?: '-', 20) }}</td>
                            <td style="padding: 10px 4px; text-align: right;">
                                <button type="button" onclick="editLotePos({{ $lote->id }}, '{{ $lote->banco }}', '{{ $lote->titular }}', '{{ \Carbon\Carbon::parse($lote->fecha)->format('Y-m-d') }}', '{{ $lote->lote_referencia }}', {{ $lote->monto }}, '{{ addslashes($lote->descripcion) }}')" style="background: none; border: none; color: #3b82f6; cursor: pointer; padding: 4px;" title="Editar">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </button>
                                <form action="{{ route('tesoreria.lote_pos.destroy', $lote->id) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este lote POS?');" style="display: inline-block;">
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
                            <td colspan="4" style="padding: 16px; text-align: center; color: #94a3b8;">No hay lotes POS registrados</td>
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
                    <option value="BANESCO JRZ">BANESCO JRZ</option>
                    <option value="BANESCO DORAL">BANESCO DORAL</option>
                    <option value="BANESCO LNACEH">BANESCO LNACEH</option>
                    <option value="BANESCO NUNES">BANESCO NUNES</option>
                    <option value="BANESCO EURONISSI">BANESCO EURONISSI</option>
                    <option value="MERCANTIL JRZ">MERCANTIL JRZ</option>
                    <option value="VENEZUELA DORAL">VENEZUELA DORAL</option>
                    <option value="VENEZUELA LNACEH">VENEZUELA LNACEH</option>
                    <option value="VENEZUELA JRZ">VENEZUELA JRZ</option>
                    <option value="TESORO DORAL">TESORO DORAL</option>
                    <option value="TESORO LNACEH">TESORO LNACEH</option>
                    <option value="TESORO JRZ">TESORO JRZ</option>
                    <option value="BNC DORAL">BNC DORAL</option>
                    <option value="BNC LNACEH">BNC LNACEH</option>
                    <option value="BNC JRZ">BNC JRZ</option>
                    <option value="BBVA LNACEH">BBVA LNACEH</option>
                    <option value="PROVINCIAL JRZ">PROVINCIAL JRZ</option>
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
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 6px;">Titular (Opcional)</label>
                <input type="text" name="titular" id="edit_pos_titular" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
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
function editLotePos(id, banco, titular, fecha, lote, monto, desc) {
    document.getElementById('modalEditPos').style.display = 'flex';
    document.getElementById('edit_form_pos').action = '/tesoreria/lote-punto-venta/' + id;
    document.getElementById('edit_pos_banco').value = banco;
    document.getElementById('edit_pos_titular').value = titular;
    document.getElementById('edit_pos_fecha').value = fecha;
    document.getElementById('edit_pos_lote').value = lote;
    document.getElementById('edit_pos_monto').value = monto;
    document.getElementById('edit_pos_desc').value = desc;
}
</script>
@endsection
