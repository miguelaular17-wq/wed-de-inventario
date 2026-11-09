<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Faltantes de caja por cobrar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 0; }
        .cab { display: table; width: 100%; margin-bottom: 10px; }
        .cab > div { display: table-cell; vertical-align: middle; }
        h1 { font-size: 15px; margin: 0; color: #1e3a8a; }
        .sub { color: #64748b; font-size: 9px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a8a; color: #fff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .r { text-align: right; }
        .tot td { font-weight: bold; background: #f1f5f9; border-top: 2px solid #1e3a8a; }
        .det td { font-size: 8.5px; color: #475569; border-bottom: none; padding: 1px 6px 1px 18px; }
        .rojo { color: #b91c1c; }
    </style>
</head>
<body>
@php $dinero = fn ($v) => '$'.number_format((float) $v, 2); @endphp
<div class="cab">
    <div>
        <h1>Faltantes de caja por cobrar</h1>
        <div class="sub">
            Generado el {{ now()->format('d/m/Y H:i') }}
            @if($filtros['sede']) · Sede {{ $filtros['sede'] }} @endif
            @if($filtros['hasta']) · Registrados hasta {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }} @endif
            @if($filtros['estado'] === 'en_cuenta') · Solo en cuenta @elseif($filtros['estado'] === 'a_descontar') · Solo marcado para descontar @endif
        </div>
    </div>
    @if(is_file($logo))
        <div style="text-align:right;"><img src="{{ $logo }}" style="height:34px;"></div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th>Empleado</th><th>Cédula</th><th>Sede</th>
            <th class="r">En cuenta</th><th class="r">Desc. nómina</th><th class="r">Desc. comisión</th><th class="r">Total por cobrar</th>
        </tr>
    </thead>
    <tbody>
        @forelse($filas as $fila)
            @php $empleado = $fila['empleado']; @endphp
            <tr>
                <td><strong>{{ $empleado->nombre() }}</strong><br><span style="color:#64748b;font-size:8.5px;">{{ $empleado->nombreCargo() }}</span></td>
                <td>{{ $empleado->cedula() ?: '—' }}</td>
                <td>{{ $empleado->nombreSede() }}</td>
                <td class="r">{{ $dinero($fila['en_cuenta']) }}</td>
                <td class="r">{{ $dinero($fila['descontar_nomina']) }}</td>
                <td class="r">{{ $dinero($fila['descontar_comision']) }}</td>
                <td class="r rojo"><strong>{{ $dinero($fila['total']) }}</strong></td>
            </tr>
            @foreach($fila['registros'] as $r)
                <tr class="det">
                    <td colspan="6">{{ $r->fecha?->format('d/m/Y') }} · {{ $r->motivo ?: '—' }} · {{ $r->decision === \App\Models\Nomina\NominaComisionDescuento::DECISION_DESCONTAR ? $r->etiquetaDecision() : 'En cuenta' }}</td>
                    <td class="r">{{ $dinero($r->monto) }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="7" style="text-align:center;padding:16px;color:#64748b;">No hay faltantes por cobrar.</td></tr>
        @endforelse
        @if($filas->isNotEmpty())
            <tr class="tot">
                <td colspan="3">Total · {{ $totales['personas'] }} personas</td>
                <td class="r">{{ $dinero($totales['en_cuenta']) }}</td>
                <td class="r">{{ $dinero($totales['descontar_nomina']) }}</td>
                <td class="r">{{ $dinero($totales['descontar_comision']) }}</td>
                <td class="r rojo">{{ $dinero($totales['total']) }}</td>
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
