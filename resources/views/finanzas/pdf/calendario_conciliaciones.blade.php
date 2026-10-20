<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Calendario de conciliaciones - {{ $cal['titulo'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; }
        .header { display: table; width: 100%; margin-bottom: 16px; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .header-logo { display: table-cell; vertical-align: middle; width: 80px; }
        .header-logo img { height: 60px; width: auto; }
        .header-titles { display: table-cell; vertical-align: middle; padding-left: 12px; }
        .header h2 { margin: 0 0 4px 0; color: #1e3a8a; }
        .header p { margin: 0; color: #64748b; }
        .summary-box { border: 1px solid #e2e8f0; padding: 8px; margin-bottom: 14px; background: #f8fafc; }
        .summary-box table { width: 100%; }
        .summary-box td { text-align: center; font-weight: bold; font-size: 12px; }
        .summary-box td span { display: block; font-weight: normal; font-size: 9px; color: #64748b; margin-bottom: 3px; }
        .section-title { background: #f1f5f9; padding: 5px; font-weight: bold; margin: 16px 0 8px; border-left: 4px solid #1e3a8a; }
        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; vertical-align: top; }
        table.data-table th { background: #e2e8f0; }
        .estado { font-weight: bold; }
        .ok { color: #166534; }
        .parcial { color: #92400e; }
        .pend { color: #64748b; }
        .muted { color: #64748b; font-size: 9px; }
        .empty-row { text-align: center; font-style: italic; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $conciliadas = collect($cal['filas'])->where('completo', true)->count();
        $parciales = collect($cal['filas'])->filter(fn ($f) => ! $f['completo'] && $f['cubiertos'] > 0)->count();
        $pendientes = collect($cal['filas'])->where('cubiertos', 0)->count();
        $diasMes = \Carbon\Carbon::parse($cal['inicio'])->daysInMonth;
    @endphp

    <div class="header">
        @if(!empty($logoPath))
            <div class="header-logo"><img src="{{ $logoPath }}" alt="Logo"></div>
        @endif
        <div class="header-titles">
            <h2>Calendario de conciliaciones - {{ $cal['titulo'] }}</h2>
            <p>Del {{ \Carbon\Carbon::parse($cal['inicio'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($cal['fin'])->format('d/m/Y') }}</p>
            <p>Generado el {{ now()->format('d/m/Y H:i') }}@if($generadoPor) por {{ $generadoPor }}@endif</p>
        </div>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td class="ok"><span>CONCILIADAS</span>{{ $conciliadas }}</td>
                <td class="parcial"><span>PARCIALES</span>{{ $parciales }}</td>
                <td class="pend"><span>PENDIENTES</span>{{ $pendientes }}</td>
                <td><span>SIN MOVIMIENTOS</span>{{ count($cal['sin_movimientos']) }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">CUENTAS ({{ count($cal['filas']) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:18%;">Banco</th>
                <th style="width:20%;">Titular</th>
                <th style="width:12%;">Estado</th>
                <th style="width:10%;">Días</th>
                <th>Períodos marcados</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cal['filas'] as $fila)
                @php
                    $estado = $fila['completo'] ? 'ok' : ($fila['cubiertos'] > 0 ? 'parcial' : 'pend');
                    $estadoTexto = $fila['completo'] ? 'Conciliado' : ($fila['cubiertos'] > 0 ? 'Parcial' : 'Pendiente');
                @endphp
                <tr>
                    <td><strong>{{ $fila['banco'] }}</strong></td>
                    <td>{{ $fila['titular'] }}</td>
                    <td class="estado {{ $estado }}">{{ $estadoTexto }}</td>
                    <td>{{ $fila['cubiertos'] }} / {{ $diasMes }}</td>
                    <td>
                        @forelse($fila['rangos'] as $rango)
                            <div>
                                {{ \Carbon\Carbon::parse($rango['desde'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($rango['hasta'])->format('d/m/Y') }}
                                <span class="muted">
                                    · {{ $rango['marcado_por'] ?: 'sin usuario' }}@if($rango['marcado_el']), {{ $rango['marcado_el'] }}@endif
                                </span>
                            </div>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-row">No hay cuentas para este mes</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">SIN MOVIMIENTOS ({{ count($cal['sin_movimientos']) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:18%;">Banco</th>
                <th style="width:20%;">Titular</th>
                <th>Marcado por</th>
                <th style="width:20%;">Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cal['sin_movimientos'] as $cuenta)
                <tr>
                    <td><strong>{{ $cuenta['banco'] }}</strong></td>
                    <td>{{ $cuenta['titular'] }}</td>
                    <td>{{ $cuenta['marcado_por'] ?: 'sin usuario' }}</td>
                    <td>{{ $cuenta['marcado_el'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty-row">Ninguna cuenta marcada sin movimientos este mes</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
