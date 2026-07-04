@extends('layouts.app')

@section('title', 'Dashboard de Cobranza')

@section('content')
<div class="dashboard-container py-4" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h2 style="color: var(--blue); font-weight: 600; margin: 0;">Dashboard Global de Cobranza</h2>
        
        <div style="display: flex; gap: 10px;">
            <form action="{{ route('cobranza.limpiar') }}" method="POST" onsubmit="return confirm('¿Estás seguro que deseas eliminar los datos detallados de todos los clientes? Los indicadores de las tablas superiores se mantendrán intactos.');">
                @csrf
                <button type="submit" class="btn" style="background-color: #dc3545; border: none; padding: 10px 20px; font-weight: 600; cursor: pointer; color: white; border-radius: 6px;">
                    🗑️ Limpiar Clientes
                </button>
            </form>
            <button type="button" class="btn primary" onclick="openImportModal()" style="background-color: var(--blue); border: none; padding: 10px 20px; font-weight: 600; cursor: pointer; color: white; border-radius: 6px;">
                📄 Importar Excel
            </button>
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

    <style>
        .indicadores-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-family: Arial, sans-serif;
            font-size: 0.95rem;
        }
        .indicadores-table th {
            background-color: #dbeafe;
            color: #1e3a8a;
            padding: 8px 12px;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid #93c5fd;
        }
        .indicadores-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .row-critico { background-color: #ff0000; color: white !important; font-weight: bold; }
        .row-moroso { background-color: #ffff00; color: black !important; font-weight: bold; }
        .row-reciente { background-color: #92d050; color: black !important; font-weight: bold; }
        .row-apartado { background-color: white; color: black !important; }
        .row-total { background-color: #dbeafe; font-weight: bold; }
        
        .client-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 0.95rem;
        }
        .client-table th {
            color: var(--blue);
            font-weight: bold;
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .client-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
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
                            <tr>
                                <td class="{{ $class }}" style="text-align: left; border-right: 1px solid #ccc;">{{ $estatus }}</td>
                                <td class="{{ $class }}" style="text-align: center; border-right: 1px solid #ccc;">{{ $datos['clientes'] }}</td>
                                <td class="{{ $class }}" style="text-align: right; border-right: 1px solid #ccc;">{{ number_format($datos['saldo'], 2, ',', '.') }}</td>
                                <td class="{{ $class }}" style="text-align: right;">{{ $porcentaje }}%</td>
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

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 40px 0;">

    <!-- TABLA DETALLADA DE CLIENTES -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--blue); font-weight: 600; margin: 0;">Detalle de Clientes</h3>
        
        <form method="GET" action="{{ route('cobranza.index') }}" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <label style="font-weight: 600; color: #4b5563;">Filtrar por Sede:</label>
            <select name="filtro_sede" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff; min-width: 250px;">
                <option value="">Todas las Sedes</option>
                @foreach($sedes as $s)
                    <option value="{{ $s }}" {{ $filtro_sede === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            @if($filtro_sede)
                <a href="{{ route('cobranza.index') }}" class="btn secondary" style="padding: 8px 12px; font-size: 0.85rem; border-radius: 6px; text-decoration: none;">Ver Todas</a>
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
                        <th style="text-align: right;">SALDO BS</th>
                        <th style="text-align: right;">SALDO USD</th>
                        <th style="text-align: center;">FECHA EMISIÓN</th>
                        <th style="text-align: center;">ESTATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes_lista as $c)
                        <tr>
                            <td style="padding-left: 12px;">{{ $c->codigo }}</td>
                            <td>{{ $c->cliente }}</td>
                            <td style="text-align: right; font-weight: 500; color: #4b5563;">Bs. {{ number_format($c->saldo_bs, 2, ',', '.') }}</td>
                            <td style="text-align: right; font-weight: 600; color: #198754;">$ {{ number_format($c->saldo_usd, 2, ',', '.') }}</td>
                            <td style="text-align: center; color: #6b7280;">{{ $c->fecha_emision ? date('d/m/Y', strtotime($c->fecha_emision)) : '-' }}</td>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 30px; text-align: center; color: #6c757d;">
                                No hay clientes registrados para esta selección. Sube un archivo Excel para llenarlo.
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
</script>
@endpush
