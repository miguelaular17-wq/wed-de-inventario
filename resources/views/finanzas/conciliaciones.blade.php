@extends('layouts.app')
@section('title', 'Conciliación Bancaria Inteligente')
@section('content')
<div style="padding: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h2 style="margin: 0; font-size: 1.5rem; color: #1a4273; font-weight: 600;">Conciliación Bancaria Inteligente</h2>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" onclick="document.getElementById('uploadModal').style.display = 'flex'" style="background-color: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                Añadir Movimientos
            </button>
            
            <form action="{{ route('finanzas.conciliaciones') }}" method="GET" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                <select name="banco_filtro" style="padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; color: #334155; min-width: 150px;">
                    <option value="">Todos los Bancos</option>
                    @foreach($bancos as $b)
                        <option value="{{ $b }}" {{ request('banco_filtro') == $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
                <button type="submit" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 10px 15px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Filtrar
                </button>
            </form>

            @if($lineas->count() > 0)
            <form action="{{ route('finanzas.conciliaciones.clear') }}" method="POST" onsubmit="return confirm('¿Seguro que deseas borrar todas las líneas y empezar de cero?');">
                @csrf
                <button style="background-color: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">Limpiar Todo</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div style="background-color: #dcfce7; color: #166534; padding: 16px; border-radius: 8px; margin-bottom: 24px;">{{ session('success') }}</div>
    @endif


    
    <!-- RESULTS ZONE -->
    @php
        $bancos_activos = collect([])
            ->concat($faltan_sistema->pluck('banco'))
            ->concat($conciliados->pluck('banco'))
            ->concat($faltan_banco->pluck('banco'))
            ->concat($egresos_ayer->pluck('banco'))
            ->map(function($b) { return strtoupper(trim($b)); })
            ->filter()
            ->unique()
            ->sort();
    @endphp

    @foreach($bancos_activos as $banco_actual)
    <div style="margin-bottom: 50px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
        <h3 style="font-size: 1.3rem; color: #1e293b; font-weight: 800; margin-bottom: 20px; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 5px;"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
            BANCO: {{ $banco_actual }}
        </h3>
        
        <div style="display: flex; flex-wrap: wrap; gap: 24px;">
            <!-- FALTAN EN SISTEMA -->
            <div style="flex: 1 1 100%;">
                <div style="background: white; border-radius: 12px; border-top: 4px solid #ef4444; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden;">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: white;">
                        <h5 style="margin: 0; color: #ef4444; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg>
                            Falta en Sistema (Gastos del Banco no registrados)
                        </h5>
                    </div>
                    <div>
                        <table style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead style="background-color:#fef2f2; color: #7f1d1d;">
                                <tr>
                                    <th style="padding: 12px 20px; font-weight: 600;">Fecha</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Descripción</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Referencia</th>
                                    <th style="padding: 12px 20px; font-weight: 600; text-align: right;">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $faltan_sistema_banco = $faltan_sistema->filter(function($i) use ($banco_actual) { return strtoupper(trim($i->banco)) == $banco_actual; }); @endphp
                                @foreach($faltan_sistema_banco as $linea)
                                    <tr style="border-top: 1px solid #fee2e2;">
                                        <td style="padding: 12px 20px;">{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                        <td style="padding: 12px 20px;">{{ $linea->descripcion }}</td>
                                        <td style="padding: 12px 20px;">{{ $linea->referencia }}</td>
                                        <td style="padding: 12px 20px; font-weight: 700; color: #ef4444; text-align: right;">{{ number_format($linea->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                                @if($faltan_sistema_banco->count() == 0)
                                    <tr><td colspan="4" style="padding: 24px; text-align: center; color: #64748b;">No hay faltantes en el sistema.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="flex: 1 1 calc(50% - 12px); min-width: 350px;">
                <!-- CONCILIADOS -->
                <div style="background: white; border-radius: 12px; border-top: 4px solid #10b981; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); height: 100%; overflow: hidden;">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: white; display: flex; justify-content: space-between; align-items: center;">
                        <h5 style="margin: 0; color: #10b981; font-weight: 700;">Emparejados Correctamente</h5>
                        <a href="{{ route('finanzas.conciliaciones.reporte', ['banco_filtro' => $banco_actual]) }}" style="background-color: #10b981; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Descargar Excel
                        </a>
                    </div>
                    <div>
                        <table style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead style="background-color:#ecfdf5; color: #065f46;">
                                <tr>
                                    <th style="padding: 12px 20px; font-weight: 600;">Fecha</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Descripción del Banco</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Tipo Gasto</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Motivo</th>
                                    <th style="padding: 12px 20px; font-weight: 600; text-align: right;">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $conciliados_banco = $conciliados->filter(function($i) use ($banco_actual) { return strtoupper(trim($i->banco)) == $banco_actual; }); @endphp
                                @foreach($conciliados_banco as $linea)
                                    <tr style="border-top: 1px solid #d1fae5;">
                                        <td style="padding: 12px 20px;">{{ \Carbon\Carbon::parse($linea->fecha)->format('d/m/Y') }}</td>
                                        <td style="padding: 12px 20px;">{{ $linea->descripcion }}</td>
                                        <td style="padding: 12px 20px; color: #065f46; font-size: 0.9em;">
                                            {{ $linea->flujoCaja ? ($linea->flujoCaja->tipo_gasto ?: $linea->flujoCaja->categoria_egreso) : '-' }}
                                        </td>
                                        <td style="padding: 12px 20px; color: #065f46; font-size: 0.9em;">
                                            {{ $linea->flujoCaja ? $linea->flujoCaja->motivo : '-' }}
                                        </td>
                                        <td style="padding: 12px 20px; text-align: right;">{{ number_format($linea->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                                @if($conciliados_banco->count() == 0)
                                    <tr><td colspan="5" style="padding: 24px; text-align: center; color: #64748b;">No hay emparejamientos.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="flex: 1 1 calc(50% - 12px); min-width: 350px;">
                <!-- FALTAN EN BANCO (TRANSITO) -->
                <div style="background: white; border-radius: 12px; border-top: 4px solid #f59e0b; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); height: 100%; overflow: hidden;">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: white;">
                        <h5 style="margin: 0; color: #d97706; font-weight: 700;">Tránsito (En Sistema, no en Banco)</h5>
                    </div>
                    <div>
                        <table style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead style="background-color:#fffbeb; color: #92400e;">
                                <tr>
                                    <th style="padding: 12px 20px; font-weight: 600;">Fecha</th>
                                    <th style="padding: 12px 20px; font-weight: 600;">Concepto</th>
                                    <th style="padding: 12px 20px; font-weight: 600; text-align: right;">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $faltan_banco_banco = $faltan_banco->filter(function($i) use ($banco_actual) { return strtoupper(trim($i->banco)) == $banco_actual; }); @endphp
                                @foreach($faltan_banco_banco as $flujo)
                                    <tr style="border-top: 1px solid #fef3c7;">
                                        <td style="padding: 12px 20px;">{{ \Carbon\Carbon::parse($flujo->fecha)->format('d/m/Y') }}</td>
                                        <td style="padding: 12px 20px;">{{ $flujo->concepto }} <span style="display:block; font-size:0.8rem; color:#64748b;">{{ $flujo->referencia }}</span></td>
                                        <td style="padding: 12px 20px; text-align: right; font-weight: 700; color: #d97706;">{{ number_format($flujo->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                                @if($faltan_banco_banco->count() == 0)
                                    <tr><td colspan="3" style="padding: 24px; text-align: center; color: #64748b;">No hay transacciones en tránsito.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- EGRESOS DEL DIA ANTERIOR -->
        <div style="margin-top: 30px;">
            <h3 style="font-size: 1.1rem; color: #1a4273; font-weight: 700; text-transform: uppercase; margin-bottom: 15px;">EGRESOS DEL DÍA ANTERIOR PENDIENTES - {{ $banco_actual }}</h3>
            <table style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <thead style="background-color: #f1f5f9; color: #1e293b; text-align: left; border-bottom: 1px solid #cbd5e1;">
                    <tr>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem;">Fecha</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem;">Titular</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem;">Ref.</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem;">Tipo Gasto</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem;">Motivo</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem; text-align: right;">USD</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem; text-align: right;">Tasa</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem; text-align: right;">Dif. Camb.</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem; text-align: right;">BS</th>
                        <th style="padding: 12px 16px; font-weight: 600; font-size: 0.85rem; text-align: right;">Comisión</th>
                    </tr>
                </thead>
                <tbody>
                    @php $egresos_ayer_banco = $egresos_ayer->filter(function($i) use ($banco_actual) { return strtoupper(trim($i->banco)) == $banco_actual; }); @endphp
                    @foreach($egresos_ayer_banco as $egreso)
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem;">{{ \Carbon\Carbon::parse($egreso->fecha)->format('d/m/Y') }}</td>
                        <td style="padding: 12px 16px; color: #64748b; font-size: 0.9rem;">{{ $egreso->titular ?: '-' }}</td>
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem; font-weight: 600;">{{ $egreso->referencia ?: '-' }}</td>
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem;">{{ $egreso->tipo_gasto ?: '-' }}</td>
                        <td style="padding: 12px 16px; color: #64748b; font-size: 0.9rem;">{{ $egreso->motivo ?: '-' }}</td>
                        <td style="padding: 12px 16px; color: #0f172a; font-size: 0.9rem; font-weight: 700; text-align: right;">{{ $egreso->monto_usd ? '$'.number_format($egreso->monto_usd, 2) : '-' }}</td>
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem; text-align: right;">{{ $egreso->tasa_cambio ? number_format($egreso->tasa_cambio, 2) : '-' }}</td>
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem; text-align: right;">{{ $egreso->diferencial_cambiario ? number_format($egreso->diferencial_cambiario, 2) : '-' }}</td>
                        <td style="padding: 12px 16px; color: #1a4273; font-size: 0.9rem; font-weight: 700; text-align: right;">Bs.{{ number_format($egreso->monto_bs, 2) }}</td>
                        <td style="padding: 12px 16px; color: #334155; font-size: 0.9rem; text-align: right;">{{ $egreso->comision ? number_format($egreso->comision, 2) : '-' }}</td>
                    </tr>
                    @endforeach
                    @if($egresos_ayer_banco->count() == 0)
                    <tr>
                        <td colspan="10" style="padding: 24px; text-align: center; color: #64748b; font-size: 0.9rem;">No hay egresos pendientes para este banco.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

<!-- UPLOAD MODAL -->
<div id="uploadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <form action="{{ route('finanzas.conciliaciones.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h5 style="margin: 0; font-weight: 700; color: #1a4273; font-size: 1.25rem;">Subir Archivo del Banco</h5>
                <button type="button" onclick="document.getElementById('uploadModal').style.display = 'none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <div style="padding: 24px;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Selecciona el Banco:</label>
                    <select name="banco_seleccionado" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; font-family: inherit;">
                        <option value="">-- Seleccione el Banco --</option>
                        @foreach($bancos as $banco)
                            <option value="{{ $banco }}">{{ $banco }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Archivo (Excel, CSV o Foto):</label>
                    <div style="display: flex;">
                        <label style="background: #f1f5f9; color: #334155; padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 8px 0 0 8px; cursor: pointer; margin: 0; font-weight: 500; white-space: nowrap;">
                            Elegir Archivos
                            <input type="file" name="file[]" multiple accept=".csv,.xls,.xlsx,image/jpeg,image/png,image/jpg" required id="csvUploadInput" onchange="document.getElementById('csvFileName').value = this.files.length > 1 ? this.files.length + ' archivos seleccionados' : (this.files[0] ? this.files[0].name : '');" style="display: none;">
                        </label>
                        <input type="text" id="csvFileName" placeholder="Ningún archivo" readonly style="flex: 1; min-width: 0; padding: 10px; border: 1px solid #cbd5e1; border-left: none; border-radius: 0 8px 8px 0; background: white; color: #64748b; overflow: hidden; text-overflow: ellipsis; font-family: inherit;">
                    </div>
                    <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 15px; margin-bottom: 0;">
                    El sistema detectará automáticamente las columnas de Excel/CSV, o usará IA para leer tu foto.
                </p></div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; border-radius: 0 0 12px 12px;">
                <button type="button" onclick="document.getElementById('uploadModal').style.display = 'none'" style="background: white; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; color: #475569; font-family: inherit;">Cancelar</button>
                <button type="submit" style="background-color: #1a4273; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: inherit;">Subir y Analizar</button>
            </div>
        </form>
    </div>
</div>

@endsection
