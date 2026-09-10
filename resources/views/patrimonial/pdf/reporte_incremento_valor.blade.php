<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Incremento de Valor de las Propiedades</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        .header { width: 100%; border-bottom: 3px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 16px; }
        .header td { vertical-align: middle; }
        .header img { width: 85px; }
        h1 { margin: 0; text-align: center; color: #1e3a8a; font-size: 17px; text-transform: uppercase; }
        .subtitle { text-align: center; color: #64748b; margin-top: 4px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { width: 33.33%; padding: 9px; text-align: center; border: 1px solid #dbeafe; background: #eff6ff; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .value { margin-top: 3px; font-size: 14px; font-weight: bold; }
        .property-title { background: #1e3a8a; color: #fff; padding: 7px 10px; margin-top: 12px; font-size: 11px; font-weight: bold; }
        .values { width: 100%; border-collapse: collapse; }
        .values td { width: 33.33%; padding: 7px 9px; border: 1px solid #dbeafe; background: #f8fafc; }
        .detail { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .detail th { background: #dbeafe; color: #1e3a8a; padding: 6px; text-align: left; border: 1px solid #bfdbfe; }
        .detail td { padding: 6px; border: 1px solid #e2e8f0; }
        .right { text-align: right !important; }
        .orange { color: #d97706; }
        .green { color: #059669; }
        .note { padding: 8px 10px; margin-bottom: 14px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; font-size: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:100px;"><img src="{{ public_path('logo.png') }}" alt="Logo"></td>
            <td>
                <h1>Incremento de Valor de las Propiedades</h1>
                <div class="subtitle">Remodelaciones acumuladas hasta {{ $fecha_corte->translatedFormat('F Y') }}</div>
            </td>
            <td style="width:100px; text-align:right; color:#64748b;">{{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="note">
        Estimación contable: valor anterior + remodelaciones registradas. Este resultado no sustituye un avalúo comercial.
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Valor anterior</div><div class="value">${{ number_format($totales['valor_anterior'], 2) }}</div></td>
            <td><div class="label">Remodelaciones</div><div class="value orange">${{ number_format($totales['inversion_remodelaciones'], 2) }}</div></td>
            <td><div class="label">Valor aproximado</div><div class="value green">${{ number_format($totales['valor_aproximado'], 2) }}</div></td>
        </tr>
    </table>

    @forelse($filas as $fila)
        @php $propiedad = $fila['propiedad']; @endphp
        <div class="property-title">{{ $propiedad->nombre }} — {{ $propiedad->codigo }}</div>
        <table class="values">
            <tr>
                <td><span class="label">Valor anterior</span><br><strong>${{ number_format($fila['valor_anterior'], 2) }}</strong></td>
                <td><span class="label">Remodelaciones</span><br><strong class="orange">${{ number_format($fila['inversion_remodelaciones'], 2) }}</strong></td>
                <td><span class="label">Valor aproximado</span><br><strong class="green">${{ number_format($fila['valor_aproximado'], 2) }}</strong></td>
            </tr>
        </table>
        <table class="detail">
            <thead>
                <tr><th>Fecha</th><th>Descripción</th><th>Observaciones</th><th class="right">Inversión</th></tr>
            </thead>
            <tbody>
                @foreach($fila['remodelaciones'] as $remodelacion)
                    <tr>
                        <td>{{ $remodelacion->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $remodelacion->descripcion ?: 'Remodelación' }}</td>
                        <td>{{ $remodelacion->observaciones ?: '—' }}</td>
                        <td class="right orange"><strong>${{ number_format($remodelacion->monto, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="text-align:center; color:#64748b;">No hay remodelaciones registradas hasta este período.</p>
    @endforelse

    <div class="footer">Gestión Patrimonial — Incremento de valor — Confidencial</div>
</body>
</html>
