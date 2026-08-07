@extends('layouts.app')

@section('title', 'Dashboard de Cobranza')

@section('content')
<div class="dashboard-container py-4" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h2 style="color: var(--blue); font-weight: 600; margin: 0;">Dashboard Global de Cobranza</h2>
        
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('cobranza.pdf', ['mostrar_clientes' => request('mostrar_clientes', 'todos')]) }}" target="_blank" class="btn" style="background-color: #dc3545; border: none; padding: 10px 20px; font-weight: 600; cursor: pointer; color: white; border-radius: 6px; text-decoration: none;">
                📄 Descargar PDF
            </a>
            <form action="{{ route('cobranza.guardar_resumen') }}" method="POST">
                @csrf
                <button type="submit" class="btn" style="background-color: #198754; border: none; padding: 10px 20px; font-weight: 600; cursor: pointer; color: white; border-radius: 6px;">
                    💾 Guardar Resumen
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #d1e7dd; color: #0f5132; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #f8d7da; color: #842029; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            {{ session('error') }}
        </div>
    @endif

    @if(session('info'))
        <div style="background: #fff3cd; color: #664d03; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
            ⚠️ {{ session('info') }}
        </div>
    @endif

    <style>
        .indicadores-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.95rem;
        }
        .indicadores-table th {
            background-color: #f8fafc;
            color: #475569;
            padding: 12px 16px;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }
        .indicadores-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            transition: background-color 0.2s ease;
        }
        .indicadores-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Modern Row Status Colors */
        .row-critico td { background-color: #fef2f2 !important; color: #b91c1c !important; font-weight: 600; }
        .row-moroso td { background-color: #fefce8 !important; color: #a16207 !important; font-weight: 600; }
        .row-reciente td { background-color: #f0fdf4 !important; color: #15803d !important; font-weight: 600; }
        .row-apartado td { background-color: #ffffff !important; color: #64748b !important; font-weight: 500; }
        .row-total td { background-color: #f1f5f9 !important; font-weight: 700; color: #0f172a !important; }

        /* Left border accent */
        .row-critico td:first-child { box-shadow: inset 4px 0 0 0 #ef4444; }
        .row-moroso td:first-child { box-shadow: inset 4px 0 0 0 #eab308; }
        .row-reciente td:first-child { box-shadow: inset 4px 0 0 0 #22c55e; }
        
        .client-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            font-size: 0.95rem;
        }
        .client-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }
        .client-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            transition: all 0.2s ease;
        }
        .client-table tbody tr:hover td {
            background-color: #f8fafc;
        }
    </style>

    <div style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap; margin-bottom: 40px;">
        <!-- TABLA 1: POR SEDE -->
        <div style="flex: 1; min-width: 350px;">
            <h4 style="text-align: center; color: black; font-weight: bold; font-size: 1.1rem; margin-bottom: 10px;">
                INDICADORES DE COBRANZA POR SEDE AL<br>
                {{ date('d/m/Y') }}
            </h4>
            <div class="panel shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb;">
                <table class="indicadores-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">SEDE <span>▼</span></th>
                            <th>CLIENTE</th>
                            <th style="text-align: right;">SALDO</th>
                            <th style="text-align: right;">% GLOBAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($porSede as $s)
                            @php
                                $porcentaje = $gran_total_saldo > 0 ? round(($s->total_saldo / $gran_total_saldo) * 100) : 0;
                            @endphp
                            <tr>
                                <td style="text-align: left; text-transform: uppercase;">{{ str_replace('INVERSIONES DORAL PARAGUANA', 'DORAL', $s->sede_nombre) }}</td>
                                <td style="text-align: center;">{{ $s->total_clientes }}</td>
                                <td style="text-align: right;">{{ number_format($s->total_saldo, 2, ',', '.') }}</td>
                                <td style="text-align: right;">{{ $porcentaje }}%</td>
                            </tr>
                        @endforeach
                        <tr class="row-total">
                            <td style="text-align: left;">Total general</td>
                            <td style="text-align: center;">{{ $gran_total_clientes }}</td>
                            <td style="text-align: right;">{{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                            <td style="text-align: right;">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABLA 2: POR ESTATUS -->
        <div style="flex: 1; min-width: 350px;">
            <h4 style="text-align: center; color: black; font-weight: bold; font-size: 1.1rem; margin-bottom: 10px;">
                INDICADORES DE COBRANZA POR ESTATUS<br>
                AL {{ date('d/m/Y') }}
            </h4>
            <div class="panel shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb;">
                <table class="indicadores-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">ESTATUS <span>▼</span></th>
                            <th>CLIENTE</th>
                            <th style="text-align: right;">SALDO</th>
                            <th style="text-align: right;">% GLOBAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Inicializar estatus para que aparezcan siempre, aunque esten en 0
                            $mapEstatus = [
                                'CRITICO' => ['clientes' => 0, 'saldo' => 0],
                                'MOROSO' => ['clientes' => 0, 'saldo' => 0],
                                'RECIENTE' => ['clientes' => 0, 'saldo' => 0],
                                'APARTADO' => ['clientes' => 0, 'saldo' => 0],
                            ];
                            foreach($porEstatus as $e) {
                                if (isset($mapEstatus[$e->estatus])) {
                                    $mapEstatus[$e->estatus]['clientes'] = $e->total_clientes;
                                    $mapEstatus[$e->estatus]['saldo'] = $e->total_saldo;
                                }
                            }
                        @endphp

                        @foreach($mapEstatus as $estatus => $datos)
                            @php
                                $porcentaje = $gran_total_saldo > 0 ? round(($datos['saldo'] / $gran_total_saldo) * 100) : 0;
                                $class = 'row-' . strtolower($estatus);
                            @endphp
                            <tr class="{{ $class }}">
                                <td style="text-align: left; font-weight: bold;">{{ $estatus }}</td>
                                <td style="text-align: center;">{{ $datos['clientes'] }}</td>
                                <td style="text-align: right;">{{ number_format($datos['saldo'], 2, ',', '.') }}</td>
                                <td style="text-align: right;">{{ $porcentaje }}%</td>
                            </tr>
                        @endforeach

                        <tr class="row-total">
                            <td style="text-align: left;">Total general</td>
                            <td style="text-align: center;">{{ $gran_total_clientes }}</td>
                            <td style="text-align: right;">{{ number_format($gran_total_saldo, 2, ',', '.') }}</td>
                            <td style="text-align: right;">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TABLA COMPARATIVA SEMANAL -->
    @if(!empty($fechas_semanal) && count($fechas_semanal) > 1)
        <div style="margin-bottom: 40px; overflow-x: auto;">
            <h4 style="text-align: center; color: black; font-weight: bold; font-size: 1.1rem; margin-bottom: 10px;">
                COMPARATIVA SEMANAL DE EFECTIVIDAD
            </h4>
            <div class="panel shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb;">
                <table class="indicadores-table" style="text-align: center;">
                    <thead>
                        <tr style="background-color: #5b9bd5; color: white;">
                            <th style="background-color: #5b9bd5; color: white; border-right: 1px solid #fff;">ESTATUS</th>
                            @foreach($fechas_semanal as $index => $fecha)
                                <th style="background-color: #5b9bd5; color: white; border-right: 1px solid #fff;">LUNES<br>{{ $fecha }}</th>
                                @if($index > 0)
                                    <th style="background-color: #5b9bd5; color: white; border-right: 1px solid #fff;">% DE<br>EFECTIVIDAD<br>AL {{ $fecha }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($semanal_list as $row)
                            @php $class = 'row-' . strtolower($row['estatus']); @endphp
                            <tr class="{{ $class }}">
                                <td style="text-align: left; font-weight: bold;">{{ $row['estatus'] }}</td>
                                @foreach($row['lunes'] as $index => $data)
                                    <td style="text-align: right;">
                                        {{ $data['saldo'] > 0 ? number_format($data['saldo'], 2, ',', '.') : '-' }}
                                    </td>
                                    @if($index > 0)
                                        @php
                                            $efectColor = '#334155';
                                            if ($data['efectividad'] !== '-') {
                                                $efectVal = floatval(str_replace('%', '', $data['efectividad']));
                                                if ($efectVal > 0) $efectColor = '#dc2626';
                                                elseif ($efectVal < 0) $efectColor = '#16a34a';
                                            }
                                        @endphp
                                        <td style="text-align: right; font-weight: 600; color: {{ $efectColor }} !important;">
                                            {{ $data['efectividad'] }}
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="row-total">
                            <td style="text-align: left;">TOTALES</td>
                            @foreach($fechas_semanal as $index => $fecha)
                                @php
                                    $colTotal = 0;
                                    foreach($semanal_list as $row) {
                                        $colTotal += $row['lunes'][$index]['saldo'];
                                    }
                                @endphp
                                <td style="text-align: right;">
                                    {{ $colTotal > 0 ? number_format($colTotal, 2, ',', '.') : '-' }}
                                </td>
                                @if($index > 0)
                                    @php
                                        $prevTotal = 0;
                                        foreach($semanal_list as $row) {
                                            $prevTotal += $row['lunes'][$index-1]['saldo'];
                                        }
                                        $totalEfect = '-';
                                        if ($prevTotal > 0) {
                                            $totalEfect = round((($prevTotal - $colTotal) / $prevTotal) * 100, 0) . '%';
                                        }
                                    @endphp
                                    <td style="text-align: right;">{{ $totalEfect }}</td>
                                @endif
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 40px 0;">

    <!-- TABLA DETALLADA DE CLIENTES -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--blue); font-weight: 600; margin: 0;">Detalle de Clientes</h3>
        
        <form method="GET" action="{{ route('cobranza.index') }}" style="display: flex; gap: 10px; align-items: center; margin: 0; flex-wrap: wrap; justify-content: flex-end;">
            <input type="text" name="buscar_cliente" value="{{ $buscar_cliente ?? '' }}" placeholder="Buscar cliente..." style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff; min-width: 200px;">
            
            <div style="display: flex; align-items: center; gap: 5px;">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Desde:</label>
                <input type="date" name="fecha_desde" value="{{ $fecha_desde ?? '' }}" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;">
            </div>
            
            <div style="display: flex; align-items: center; gap: 5px;">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Hasta:</label>
                <input type="date" name="fecha_hasta" value="{{ $fecha_hasta ?? '' }}" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;">
            </div>

            <div style="display: flex; align-items: center; gap: 5px;">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Sede:</label>
                <select name="filtro_sede" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff; min-width: 150px;">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" {{ ($filtro_sede ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 5px;">
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Tipo Cliente:</label>
                <select name="mostrar_clientes" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff; min-width: 150px;">
                    <option value="todos" {{ ($mostrar_clientes ?? 'todos') === 'todos' ? 'selected' : '' }}>Todos</option>
                    <option value="regulares" {{ ($mostrar_clientes ?? 'todos') === 'regulares' ? 'selected' : '' }}>Solo Regulares</option>
                    <option value="personales" {{ ($mostrar_clientes ?? 'todos') === 'personales' ? 'selected' : '' }}>Solo Personales</option>
                </select>
            </div>
            
            <button type="submit" class="btn primary" style="background-color: var(--blue); border: none; padding: 8px 16px; font-weight: 600; cursor: pointer; color: white; border-radius: 6px;">
                Buscar
            </button>

            @if(($filtro_sede ?? '') || ($buscar_cliente ?? '') || ($fecha_desde ?? '') || ($fecha_hasta ?? '') || (($mostrar_clientes ?? 'todos') !== 'todos'))
                <a href="{{ route('cobranza.index') }}" class="btn secondary" style="padding: 8px 12px; font-size: 0.85rem; border-radius: 6px; text-decoration: none; background-color: #6c757d; color: white;">Limpiar Filtros</a>
            @endif
        </form>
    </div>

    <div class="panel shadow-sm" style="background: white; border-radius: 10px; overflow: hidden; padding: 20px; border: 1px solid #e5e7eb;">
        <div class="table-wrap" style="max-height: 500px; overflow-y: auto;">
            <table class="client-table" id="clientesTable">
                <thead>
                    <tr>
                        <th style="padding-left: 12px;">CÓDIGO</th>
                        <th>CLIENTE</th>
                        <th>SEDE</th>
                        <th style="text-align: right;">MONTO NETO</th>
                        <th style="text-align: right;">SALDO USD</th>
                        <th style="text-align: center;">FECHA EMISIÓN</th>
                        <th style="text-align: center;">DÍAS PREST.</th>
                        <th style="text-align: center;">ESTATUS</th>
                        <th style="text-align: center;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes_lista as $c)
                        @php
                            $dias = '-';
                            if ($c->fecha_emision) {
                                $dias = (int) round(\Carbon\Carbon::parse($c->fecha_emision)->diffInDays(now()));
                            }
                        @endphp
                        <tr class="client-row" data-codigo="{{ $c->codigo }}" data-cliente="{{ $c->cliente }}" data-personal="{{ $c->es_personal ? '1' : '0' }}" style="cursor: pointer;" title="Doble clic para marcar/desmarcar como personal">
                            <td style="padding-left: 12px;">{{ $c->codigo }}</td>
                            <td>
                                {{ $c->cliente }}
                                @if($c->es_personal)
                                    <span style="background: var(--blue); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 5px;">PERSONAL</span>
                                @endif
                            </td>
                            <td>{{ $c->sede ?? '-' }}</td>
                            <td style="text-align: right; font-weight: 500; color: #4b5563;">$ {{ number_format($c->monto_neto, 2, ',', '.') }}</td>
                            <td style="text-align: right; font-weight: 600; color: #198754;">$ {{ number_format($c->saldo_usd, 2, ',', '.') }}</td>
                            <td style="text-align: center; color: #6b7280;">{{ $c->fecha_emision ? date('d/m/Y', strtotime($c->fecha_emision)) : '-' }}</td>
                            <td style="text-align: center; color: #4b5563; font-weight: 500;">{{ $dias }}</td>
                            <td style="text-align: center;">
                                @if($c->estatus === 'CRITICO')
                                    <span style="background: #ff0000; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">CRÍTICO</span>
                                @elseif($c->estatus === 'MOROSO')
                                    <span style="background: #ffff00; color: black; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">MOROSO</span>
                                @elseif($c->estatus === 'RECIENTE')
                                    <span style="background: #92d050; color: black; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">RECIENTE</span>
                                @else
                                    <span style="background: #f3f4f6; color: black; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">APARTADO</span>
                                @endif
                            </td>
                            <td style="text-align: center;" onclick="event.stopPropagation();">
                                <button type="button" class="btn secondary" style="padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 4px; border: 1px solid #ccc; background-color: #f8f9fa; cursor: pointer; color: #333;" onclick="marcarPagado('{{ $c->id_documento }}', this)">
                                    Pagado
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 30px; text-align: center; color: #6c757d;">
                                No hay clientes registrados para esta selección.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Custom de Importación -->
<div id="import-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 500px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); position: relative;">
        <!-- Header del Modal -->
        <div style="background-color: var(--blue); color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Importar Saldos (Excel)</h3>
            <button type="button" onclick="closeImportModal()" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <!-- Cuerpo del Modal -->
        <form action="{{ route('cobranza.importar') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="padding: 20px;">
                <p style="margin-bottom: 15px; color: #4b5563; font-size: 0.95rem;">
                    Sube un archivo Excel con el formato de saldos de clientes.
                </p>
                <p class="small text-muted" style="font-size: 0.85rem; margin-bottom: 15px;">
                    El sistema buscará las columnas <strong>CÓDIGO</strong>, <strong>CLIENTE</strong>, <strong>SALDO $</strong> y <strong>MESES</strong> (o Antigüedad) para calcular automáticamente el estatus (Crítico, Moroso, Reciente).
                </p>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--blue);">Sede de Destino</label>
                    <select name="sede_nombre" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff;">
                        <option value="">Selecciona la sede...</option>
                        @foreach($sedes as $s)
                            <option value="{{ $s }}" {{ session('sede_local') === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--blue);">Archivo Excel (.xlsx, .xls)</label>
                    <input type="file" name="excel_file" accept=".xlsx, .xls" required style="width: 100%; padding: 10px; border: 1px dashed #ccc; border-radius: 6px; cursor: pointer; background: #f8fafc;">
                </div>
                
                <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; font-size: 0.85rem; line-height: 1.4;">
                    <strong>¡Atención!</strong> Al subir un nuevo archivo, se reemplazarán los saldos actuales de la sede seleccionada.
                </div>
            </div>
            
            <!-- Footer del Modal -->
            <div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeImportModal()" class="btn secondary" style="padding: 8px 16px; border-radius: 6px; cursor: pointer;">Cancelar</button>
                <button type="submit" class="btn primary" style="background-color: var(--blue); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Subir Archivo</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openImportModal() {
        const modal = document.getElementById('import-modal');
        modal.style.display = 'flex';
        // Evitar scroll en el body
        document.body.style.overflow = 'hidden';
    }

    function closeImportModal() {
        const modal = document.getElementById('import-modal');
        modal.style.display = 'none';
        // Restaurar scroll
        document.body.style.overflow = '';
    }

    // Cerrar si hace clic fuera de la caja blanca
    document.getElementById('import-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeImportModal();
        }
    });

    document.querySelectorAll('.client-row').forEach(row => {
        row.addEventListener('dblclick', function() {
            const codigo = this.dataset.codigo;
            const cliente = this.dataset.cliente;
            const isPersonal = this.dataset.personal === '1';
            
            const accion = isPersonal ? 'DESMARCAR' : 'MARCAR';
            
            if (confirm(`¿Deseas ${accion} al cliente ${cliente} (${codigo}) como PERSONAL para identificarlo en el reporte?`)) {
                fetch('{{ route('cobranza.marcar_personal') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ codigo: codigo, cliente: cliente })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error al procesar la solicitud.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error en la petición.');
                });
            }
        });
    });

    async function marcarPagado(idDocumento, btn) {
        if (!confirm('¿Estás seguro de querer marcar este documento como pagado manualmente? Esto lo ocultará de las estadísticas y listados de cobranza.')) {
            return;
        }

        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerText = '...';

        try {
            const response = await fetch("{{ route('cobranza.marcar_pagado') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id_documento: idDocumento })
            });

            const res = await response.json();
            if (res.success) {
                // Remove row and reload to update totals
                btn.closest('tr').remove();
                window.location.reload();
            } else {
                alert(res.message || 'Error al marcar como pagado');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        } catch (err) {
            console.error(err);
            alert('Error de conexión');
            btn.disabled = false;
            btn.innerText = originalText;
        }
    }
</script>
@endpush
