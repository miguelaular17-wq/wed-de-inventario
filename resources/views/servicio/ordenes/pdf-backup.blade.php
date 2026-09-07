<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entrega de equipo de backup · {{ $orden->codigo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 28px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-right { text-align: right; }
        .logo { width: 72px; height: 72px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #555; }
        .box { border: 1px solid #ccc; border-radius: 6px; padding: 12px; margin-top: 12px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 4px 0; vertical-align: top; }
        .label { color: #555; width: 38%; }
        .firmas { margin-top: 36px; width: 100%; }
        .firmas td { width: 50%; text-align: center; padding-top: 48px; }
        .linea { border-top: 1px solid #333; margin: 0 24px; padding-top: 6px; }
        .condiciones { white-space: pre-wrap; font-size: 11px; line-height: 1.45; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if(is_file($logo))
                <img class="logo" src="{{ $logo }}" alt="Logo">
            @endif
        </div>
        <div class="header-right">
            <h1>Documento de entrega · Equipo de backup</h1>
            <div class="muted">Palacio de los Detalles</div>
            <div>Orden <strong>{{ $orden->codigo() }}</strong> · {{ $orden->etiquetaTipoGestion() }}</div>
            <div>{{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="box">
        <strong>Datos del cliente</strong>
        <table class="grid" style="margin-top:8px;">
            <tr><td class="label">Nombre</td><td>{{ $orden->cliente_nombre }}</td></tr>
            <tr><td class="label">Cédula</td><td>{{ $orden->cliente_cedula ?: '—' }}</td></tr>
            <tr><td class="label">Teléfono</td><td>{{ $orden->cliente_telefono ?: '—' }}</td></tr>
            <tr><td class="label">Sede</td><td>{{ $orden->sede }}</td></tr>
        </table>
    </div>

    <div class="box">
        <strong>Equipo del cliente (en servicio)</strong>
        <table class="grid" style="margin-top:8px;">
            <tr><td class="label">Equipo</td><td>{{ $orden->equipoCelular?->etiqueta() ?: ($orden->equipo ?: '—') }}</td></tr>
            <tr><td class="label">IMEI</td><td>{{ $orden->imei ?: '—' }}</td></tr>
            <tr><td class="label">Serial</td><td>{{ $orden->serial ?: '—' }}</td></tr>
            <tr><td class="label">Falla reportada</td><td>{{ $orden->falla ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <strong>Celular de backup entregado</strong>
        <table class="grid" style="margin-top:8px;">
            <tr><td class="label">Marca / modelo</td><td>{{ trim(($backup->marca.' '.$backup->modelo)) ?: '—' }}</td></tr>
            <tr><td class="label">IMEI / serial</td><td>{{ $backup->imei ?: '—' }} / {{ $backup->serial ?: '—' }}</td></tr>
            <tr><td class="label">Estado físico</td><td>{{ $backup->estado_fisico ?: '—' }}</td></tr>
            <tr><td class="label">Accesorios</td><td>{{ $backup->accesorios ?: '—' }}</td></tr>
            <tr><td class="label">Entregado</td><td>{{ $backup->entregado_at?->format('d/m/Y H:i') ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <strong>Condiciones de préstamo</strong>
        <div class="condiciones" style="margin-top:8px;">{{ $backup->condiciones }}</div>
    </div>

    <table class="firmas">
        <tr>
            <td>
                <div class="linea">{{ $backup->firma_cliente ?: $orden->cliente_nombre }}<br><span class="muted">Firma del cliente</span></div>
            </td>
            <td>
                <div class="linea">{{ $backup->firma_empleado ?: '—' }}<br><span class="muted">Firma del empleado</span></div>
            </td>
        </tr>
    </table>
</body>
</html>
