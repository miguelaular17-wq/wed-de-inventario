<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Conformidad de reparación · {{ $orden->codigo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 22px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .logo { width: 64px; height: 64px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #555; }
        .box { border: 1px solid #bbb; padding: 8px 10px; margin-top: 10px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 3px 0; vertical-align: top; }
        .label { color: #555; width: 34%; }
        .firmas { margin-top: 28px; width: 100%; }
        .firmas td { width: 50%; text-align: center; vertical-align: bottom; }
        .firma-img { height: 52px; }
        .linea { border-top: 1px solid #333; margin: 0 18px; padding-top: 4px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width:80px;">
                @if(is_file($logo))
                    <img class="logo" src="{{ $logo }}" alt="Logo">
                @endif
            </td>
            <td>
                <h1>Hoja de conformidad</h1>
                <div class="muted">Palacio de los Detalles · entrega del equipo reparado</div>
                <div>Orden <strong>{{ $orden->codigo() }}</strong> · {{ $orden->etiquetaTipoGestion() }} · {{ $orden->sede }}</div>
            </td>
            <td style="text-align:right;width:140px;">{{ $orden->conformidad_at?->format('d/m/Y H:i') ?: now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="box">
        <strong>Cliente y equipo</strong>
        <table class="grid" style="margin-top:4px;">
            <tr><td class="label">Cliente</td><td>{{ $orden->esCambioEnRango() ? 'Equipo de la empresa (cambio en rango)' : ($orden->cliente_nombre.' · '.($orden->cliente_cedula ?: '—')) }}</td></tr>
            @if($orden->esGarantia() && ! $orden->esCambioEnRango())
            <tr><td class="label">Garantía</td><td>La garantía es con la marca del equipo ({{ $orden->marcaEquipo() }}). Palacio actúa como intermediario.</td></tr>
            @endif
            <tr><td class="label">Equipo</td><td>{{ $orden->equipoCelular?->etiqueta() ?: ($orden->equipo ?: '—') }}</td></tr>
            <tr><td class="label">IMEI / serial</td><td>{{ $orden->imei ?: '—' }} / {{ $orden->serial ?: '—' }}</td></tr>
            <tr><td class="label">Falla original</td><td>{{ $orden->falla ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <strong>Trabajo realizado</strong>
        <div style="margin-top:6px;">{{ $orden->conformidad_trabajo ?: ($orden->diagnostico ?: 'Equipo reparado y listo para entrega.') }}</div>
    </div>

    <div class="box">
        El cliente declara que recibió el equipo en funcionamiento, que se le explicó el trabajo realizado
        y que está conforme con el servicio. Cualquier garantía aplica según las políticas de Palacio de los Detalles.
    </div>

    <table class="firmas">
        <tr>
            <td>
                @if($orden->firma_conformidad_cliente && str_starts_with($orden->firma_conformidad_cliente, 'data:image'))
                    <img class="firma-img" src="{{ $orden->firma_conformidad_cliente }}" alt="Firma cliente">
                @endif
                <div class="linea">{{ $orden->cliente_nombre }}<br><span class="muted">Firma de conformidad del cliente</span></div>
            </td>
            <td>
                @if($orden->firma_conformidad_empleado && str_starts_with($orden->firma_conformidad_empleado, 'data:image'))
                    <img class="firma-img" src="{{ $orden->firma_conformidad_empleado }}" alt="Firma empleado">
                @endif
                <div class="linea">{{ $orden->tecnico?->name ?: ($orden->editor?->name ?: $orden->creador?->name) }}<br><span class="muted">Firma del técnico / empleado</span></div>
            </td>
        </tr>
    </table>
</body>
</html>
