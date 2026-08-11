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
        .row-critico td { background-color: #fef2f2 !important; color: #b91c1c !important; font-weight: 600; cursor: pointer; }
        .row-moroso td { background-color: #fefce8 !important; color: #a16207 !important; font-weight: 600; cursor: pointer; }
        .row-reciente td { background-color: #f0fdf4 !important; color: #15803d !important; font-weight: 600; cursor: pointer; }
        .row-apartado td { background-color: #ffffff !important; color: #64748b !important; font-weight: 500; }
        .row-total td { background-color: #f1f5f9 !important; font-weight: 700; color: #0f172a !important; }

        /* Sub-rows for breakdown */
        .row-breakdown td { font-size: 0.82rem !important; font-weight: 500 !important; padding: 6px 16px !important; }
        .row-breakdown-critico td { background-color: #fff5f5 !important; color: #dc2626 !important; }
        .row-breakdown-moroso td { background-color: #fffbeb !important; color: #b45309 !important; }
        .row-breakdown-reciente td { background-color: #f0fdf4 !important; color: #166534 !important; }
        .row-breakdown-apartado td { background-color: #fafafa !important; color: #6b7280 !important; }

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
                                'CRITICO' => ['clientes' => 0, 'saldo' => 0, 'regulares' => 0, 'personales' => 0],
                                'MOROSO' => ['clientes' => 0, 'saldo' => 0, 'regulares' => 0, 'personales' => 0],
                                'RECIENTE' => ['clientes' => 0, 'saldo' => 0, 'regulares' => 0, 'personales' => 0],
                                'APARTADO' => ['clientes' => 0, 'saldo' => 0, 'regulares' => 0, 'personales' => 0],
                            ];
                            foreach($porEstatus as $e) {
                                if (isset($mapEstatus[$e->estatus])) {
                                    $mapEstatus[$e->estatus]['clientes'] = $e->total_clientes;
                                    $mapEstatus[$e->estatus]['saldo'] = $e->total_saldo;
                                    $mapEstatus[$e->estatus]['regulares'] = $e->regulares ?? 0;
                                    $mapEstatus[$e->estatus]['personales'] = $e->personales ?? 0;
                                }
                            }
                        @endphp

                        @foreach($mapEstatus as $estatus => $datos)
                            @php
                                $porcentaje = $gran_total_saldo > 0 ? round(($datos['saldo'] / $gran_total_saldo) * 100) : 0;
                                $class = 'row-' . strtolower($estatus);
                                $hasBreakdown = in_array($estatus, ['CRITICO','MOROSO','RECIENTE','APARTADO']);
                            @endphp
                            <tr class="{{ $class }} estatus-toggle-row" data-estatus="{{ $estatus }}"
                                onclick="toggleBreakdown('{{ $estatus }}')"
                                title="Clic para ver desglose por tipo de cliente">
                                <td style="text-align: left; font-weight: bold;">
                                    {{ $estatus }}
                                    @if($hasBreakdown)
                                        <span id="arrow-{{ $estatus }}" style="margin-left:6px; font-size:0.75rem; opacity:0.7;">▶</span>
                                    @endif
                                </td>
                                <td style="text-align: center;">{{ $datos['clientes'] }}</td>
                                <td style="text-align: right;">{{ number_format($datos['saldo'], 2, ',', '.') }}</td>
                                <td style="text-align: right;">{{ $porcentaje }}%</td>
                            </tr>
                            @if($hasBreakdown)
                            {{-- Breakdown sub-rows (hidden by default) --}}
                            <tr id="breakdown-{{ $estatus }}-regulares" class="row-breakdown row-breakdown-{{ strtolower($estatus) }}" style="display:none;">
                                <td style="text-align: left; padding-left: 32px;">↳ 👔 Regulares</td>
                                <td style="text-align: center;">{{ $datos['regulares'] }}</td>
                                <td style="text-align: right;">—</td>
                                <td style="text-align: right;">—</td>
                            </tr>
                            <tr id="breakdown-{{ $estatus }}-personales" class="row-breakdown row-breakdown-{{ strtolower($estatus) }}" style="display:none;">
                                <td style="text-align: left; padding-left: 32px;">↳ 🏷️ Personal</td>
                                <td style="text-align: center;">{{ $datos['personales'] }}</td>
                                <td style="text-align: right;">—</td>
                                <td style="text-align: right;">—</td>
                            </tr>
                            @endif
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
                <label style="font-weight: 600; color: #4b5563; font-size: 0.9rem;">Estatus:</label>
                <select name="filtro_estatus" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; outline: none; background: #fff; min-width: 140px;">
                    <option value="">Todos</option>
                    <option value="CRITICO" {{ ($filtro_estatus ?? '') === 'CRITICO' ? 'selected' : '' }}>🔴 Crítico</option>
                    <option value="MOROSO" {{ ($filtro_estatus ?? '') === 'MOROSO' ? 'selected' : '' }}>🟡 Moroso</option>
                    <option value="RECIENTE" {{ ($filtro_estatus ?? '') === 'RECIENTE' ? 'selected' : '' }}>🟢 Reciente</option>
                    <option value="APARTADO" {{ ($filtro_estatus ?? '') === 'APARTADO' ? 'selected' : '' }}>⚪ Apartado</option>
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

            @if(($filtro_sede ?? '') || ($buscar_cliente ?? '') || ($fecha_desde ?? '') || ($fecha_hasta ?? '') || ($filtro_estatus ?? '') || (($mostrar_clientes ?? 'todos') !== 'todos'))
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
                                <div style="margin-top: 4px; font-size: 0.75rem;">
                                    <span style="cursor: pointer; {{ $c->nota_anclada ? 'color: #ea580c; font-weight: 600;' : 'color: #9ca3af;' }}" onclick="event.stopPropagation(); abrirModalNota('{{ $c->id_documento }}', '{{ htmlspecialchars(addslashes($c->nota_anclada ?? '')) }}')">
                                        📌 {{ $c->nota_anclada ? $c->nota_anclada : 'Añadir nota...' }}
                                    </span>
                                </div>
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
                                <button type="button" class="btn secondary" style="padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 4px; border: 1px solid #93c5fd; background-color: #eff6ff; cursor: pointer; color: #1e40af; margin-left: 4px;" onclick="abrirModalLlamadas('{{ $c->codigo }}', '{{ htmlspecialchars($c->cliente) }}')">
                                    📞 Llamadas
                                </button>
                                <button type="button" class="btn secondary" style="padding: 2px 8px; font-size: 0.7rem; font-weight: 600; border-radius: 4px; border: 1px solid #fbbf24; background-color: #fef3c7; cursor: pointer; color: #d97706; margin-left: 4px;" onclick="abrirModalEstadoCuenta('{{ $c->numero_documento }}', '{{ htmlspecialchars($c->cliente) }}')">
                                    📝 Edo. Cuenta
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="padding: 30px; text-align: center; color: #6c757d;">
                                No hay clientes registrados para esta selección.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nota Anclada -->
<div id="modal-nota" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 400px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); position: relative;">
        <div style="background-color: #ea580c; color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">📌 Nota Anclada</h3>
            <button type="button" onclick="cerrarModalNota()" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 20px; background: #f8fafc;">
            <form id="formNota" onsubmit="guardarNotaAnclada(event)">
                <input type="hidden" id="nota_id_documento">
                <textarea id="nota_texto" rows="3" placeholder="Escribe aquí una nota para este caso..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; resize: vertical; margin-bottom: 15px; font-size: 0.9rem;"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="cerrarModalNota()" style="padding: 8px 16px; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="padding: 8px 16px; background: #ea580c; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Llamadas -->
<div id="modal-llamadas" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 640px; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 60px -10px rgba(0,0,0,0.3); position: relative; max-height: 90vh; display: flex; flex-direction: column;">
        <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size:0.78rem; opacity:0.8; margin-bottom:2px;">📞 Historial de</div>
                <h3 id="modalLlamadasTitle" style="margin: 0; font-size: 1.1rem; font-weight: 700;">Llamadas</h3>
            </div>
            <button type="button" onclick="cerrarModalLlamadas()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.2rem; cursor: pointer; line-height: 1; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center;">&times;</button>
        </div>
        
        <div style="padding: 18px 20px; background: #f0f4ff; border-bottom: 1px solid #dbeafe;">
            <h4 style="margin: 0 0 12px 0; font-size: 0.9rem; color: #1e3a8a; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">➕ Registrar Nueva Llamada</h4>
            <form id="formNuevaLlamada" onsubmit="guardarLlamada(event)">
                <input type="hidden" id="llamada_codigo_cliente">
                <div style="margin-bottom: 10px;">
                    <textarea id="llamada_descripcion" required rows="3" placeholder="Resumen de lo conversado en la llamada..." style="width: 100%; padding: 10px 12px; border: 1px solid #93c5fd; border-radius: 8px; box-sizing: border-box; resize: vertical; font-size: 0.9rem; outline: none; transition: border 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#93c5fd'"></textarea>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="datetime-local" id="llamada_fecha" required style="padding: 8px 12px; border: 1px solid #93c5fd; border-radius: 8px; flex: 1; min-width: 160px; outline: none;">
                    <button type="submit" style="padding: 9px 20px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.9rem; white-space: nowrap; box-shadow: 0 2px 8px rgba(37,99,235,0.3);">Guardar</button>
                </div>
            </form>
        </div>

        <div style="padding: 16px 20px; flex: 1; overflow-y: auto; background: #f8fafc;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h4 style="margin: 0; font-size: 0.85rem; color: #475569; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">📋 Historial</h4>
                <span id="llamadas-count" style="font-size: 0.78rem; color: #94a3b8;"></span>
            </div>
            <div id="historialLlamadasList" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- Cargando... -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Estado Cuenta -->
<div id="modal-estado-cuenta" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center;">
    <div class="panel modal-box" style="width: 95%; max-width: 800px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); position: relative; max-height: 90vh; display: flex; flex-direction: column;">
        <div style="background-color: #d97706; color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalEstadoCuentaTitle" style="margin: 0; font-size: 1.1rem; font-weight: 600;">Estado de Cuenta</h3>
            <button type="button" onclick="cerrarModalEstadoCuenta()" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        
        <div style="padding: 20px; flex: 1; overflow-y: auto; background: #f8fafc;">
            <div id="estadoCuentaList" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- Cargando... -->
            </div>
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
    function formatDateLocal(date) {
        const d = new Date(date);
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0,16);
    }

    function abrirModalLlamadas(codigo, cliente) {
        document.getElementById('modal-llamadas').style.display = 'flex';
        document.getElementById('modalLlamadasTitle').innerText = 'Llamadas: ' + cliente;
        document.getElementById('llamada_codigo_cliente').value = codigo;
        document.getElementById('formNuevaLlamada').reset();
        document.getElementById('llamada_fecha').value = formatDateLocal(new Date());
        cargarLlamadas(codigo);
    }

    function cerrarModalLlamadas() {
        document.getElementById('modal-llamadas').style.display = 'none';
    }

    function abrirModalNota(id_documento, nota) {
        document.getElementById('nota_id_documento').value = id_documento;
        document.getElementById('nota_texto').value = nota;
        document.getElementById('modal-nota').style.display = 'flex';
        setTimeout(() => document.getElementById('nota_texto').focus(), 100);
    }

    function cerrarModalNota() {
        document.getElementById('modal-nota').style.display = 'none';
    }

    async function guardarNotaAnclada(e) {
        e.preventDefault();
        const id_documento = document.getElementById('nota_id_documento').value;
        const nota = document.getElementById('nota_texto').value;

        try {
            const btn = e.target.querySelector('button[type="submit"]');
            const originalText = btn.innerText;
            btn.innerText = 'Guardando...';
            btn.disabled = true;

            const res = await fetch(`{{ route('cobranza.guardar_nota') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id_documento, nota })
            });

            if (res.ok) {
                window.location.reload();
            } else {
                alert('Error al guardar la nota');
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error al guardar');
        }
    }

    async function cargarLlamadas(codigo) {
        const list = document.getElementById('historialLlamadasList');
        const countEl = document.getElementById('llamadas-count');
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><div style="font-size:1.5rem;margin-bottom:6px;">⏳</div>Cargando...</div>';
        
        try {
            const res = await fetch(`/cobranza/${encodeURIComponent(codigo)}/llamadas`);
            const data = await res.json();
            
            if (data.length === 0) {
                if (countEl) countEl.textContent = '0 registros';
                list.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8; border: 2px dashed #e2e8f0; border-radius:12px;"><div style="font-size:2rem;margin-bottom:8px;">📵</div><div style="font-weight:600;">Sin llamadas registradas</div><div style="font-size:0.82rem;margin-top:4px;">Registra la primera llamada arriba</div></div>';
                return;
            }

            if (countEl) countEl.textContent = data.length + ' registro' + (data.length !== 1 ? 's' : '');
            
            let html = '';
            data.forEach(ll => {
                const fecha = new Date(ll.fecha_llamada + 'Z').toLocaleString('es-VE', { year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
                const userName = ll.user ? ll.user.name : 'Desconocido';
                const initials = userName.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                html += `
                <div style="background:white; padding:14px 16px; border-radius:10px; border:1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:50%; background: linear-gradient(135deg,#3b82f6,#2563eb); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.78rem; flex-shrink:0;">${initials}</div>
                            <div>
                                <div style="font-weight:700; font-size:0.88rem; color:#1e293b;">${userName}</div>
                                <div style="font-size:0.75rem; color:#94a3b8;">📅 ${fecha}</div>
                            </div>
                        </div>
                        <button onclick="eliminarLlamada(${ll.id}, this)" title="Eliminar llamada" style="background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; border-radius:6px; cursor:pointer; font-size:0.75rem; padding:3px 8px; white-space:nowrap;">🗑 Borrar</button>
                    </div>
                    <div style="font-size:0.9rem; color:#334155; white-space:pre-wrap; line-height:1.5; background:#f8fafc; padding:10px 12px; border-radius:8px; border-left: 3px solid #3b82f6;">${ll.descripcion}</div>
                </div>`;
            });
            list.innerHTML = html;
        } catch (e) {
            list.innerHTML = '<div style="color:red; padding:10px; text-align:center;">⚠️ Error al cargar historial.</div>';
        }
    }

    async function guardarLlamada(e) {
        e.preventDefault();
        const codigo = document.getElementById('llamada_codigo_cliente').value;
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = '⏳ Guardando...';

        try {
            const res = await fetch(`/cobranza/${encodeURIComponent(codigo)}/llamadas`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    descripcion: document.getElementById('llamada_descripcion').value,
                    fecha_llamada: document.getElementById('llamada_fecha').value
                })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('llamada_descripcion').value = '';
                cargarLlamadas(codigo);
            } else {
                alert('Error al guardar');
            }
        } catch (err) {
            alert('Error de conexión');
        }
        btn.disabled = false;
        btn.innerText = 'Guardar';
    }

    async function eliminarLlamada(id, btn) {
        if (!confirm('¿Eliminar esta llamada del historial?')) return;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';
        try {
            const res = await fetch(`/cobranza/llamadas/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                const codigo = document.getElementById('llamada_codigo_cliente').value;
                cargarLlamadas(codigo);
            } else {
                alert('Error al eliminar');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            alert('Error de conexión');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function abrirModalEstadoCuenta(numeroDocumento, cliente) {
        document.getElementById('modal-estado-cuenta').style.display = 'flex';
        document.getElementById('modalEstadoCuentaTitle').innerText = 'Estado de Cuenta: ' + cliente;
        const list = document.getElementById('estadoCuentaList');
        list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">Cargando detalles...</div>';
        
        if (!numeroDocumento) {
             list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No hay número de documento disponible para esta factura.</div>';
             return;
        }

        try {
            const res = await fetch(`/cobranza/${encodeURIComponent(numeroDocumento)}/estado-cuenta`);
            const data = await res.json();
            
            if (data.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No hay registros detallados sincronizados para esta factura aún. <br><small>Recuerde activar el módulo de cobranzas en el sincronizador de la tienda.</small></div>';
                return;
            }
            
            let html = `
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background-color: #f1f5f9; color: #475569; text-align: left;">
                            <th style="padding: 8px; border-bottom: 2px solid #e2e8f0;">Fecha</th>
                            <th style="padding: 8px; border-bottom: 2px solid #e2e8f0;">Detalle</th>
                            <th style="padding: 8px; border-bottom: 2px solid #e2e8f0; text-align: right;">Artículos ($)</th>
                            <th style="padding: 8px; border-bottom: 2px solid #e2e8f0; text-align: right;">Abonos ($)</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            let saldoAcumulado = 0;
            
            data.forEach(mov => {
                const isAbono = mov.tipo_fila === 2 || mov.tipo_documento === 'ABONO';
                const fecha = mov.fecha_emision ? new Date(mov.fecha_emision).toLocaleDateString('es-VE') : '';
                const descripcion = mov.detalle || (isAbono ? 'Pago/Abono' : 'Artículo/Cargo');
                
                let montoCargo = '';
                let montoAbono = '';
                
                if (!isAbono) {
                    const r = parseFloat(mov.total_renglon || 0);
                    montoCargo = r.toFixed(2);
                    saldoAcumulado += r;
                } else {
                    const a = parseFloat(mov.total_abono || mov.total_renglon || 0);
                    montoAbono = a.toFixed(2);
                    saldoAcumulado -= a;
                }
                
                html += `
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${fecha}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">${descripcion}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #dc2626;">${montoCargo ? '$ ' + montoCargo : ''}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a;">${montoAbono ? '$ ' + montoAbono : ''}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding: 12px 8px; text-align: right; font-weight: bold; border-top: 2px solid #cbd5e1;">Saldo Restante:</td>
                            <td style="padding: 12px 8px; text-align: right; font-weight: bold; color: ${saldoAcumulado > 0 ? '#dc2626' : '#16a34a'}; border-top: 2px solid #cbd5e1;">$ ${saldoAcumulado.toFixed(2)}</td>
                        </tr>
                    </tfoot>
                </table>
            `;
            
            list.innerHTML = html;
        } catch (e) {
            console.error(e);
            list.innerHTML = '<div style="color:red; padding:10px;">Error al cargar el estado de cuenta.</div>';
        }
    }

    function cerrarModalEstadoCuenta() {
        document.getElementById('modal-estado-cuenta').style.display = 'none';
    }

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
    function toggleBreakdown(estatus) {
        const rowReg = document.getElementById('breakdown-' + estatus + '-regulares');
        const rowPer = document.getElementById('breakdown-' + estatus + '-personales');
        const arrow  = document.getElementById('arrow-' + estatus);
        if (!rowReg) return;
        const isOpen = rowReg.style.display !== 'none';
        rowReg.style.display = isOpen ? 'none' : '';
        rowPer.style.display = isOpen ? 'none' : '';
        if (arrow) arrow.textContent = isOpen ? '▶' : '▼';
    }
</script>
@endpush
