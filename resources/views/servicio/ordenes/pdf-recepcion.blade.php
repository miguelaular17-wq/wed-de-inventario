<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recepción de equipo · {{ $orden->codigo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 22px; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .logo { width: 64px; height: 64px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #555; }
        .box { border: 1px solid #bbb; padding: 8px 10px; margin-top: 8px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 2px 0; vertical-align: top; }
        .label { color: #555; width: 34%; }
        .check { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .check td { border: 1px solid #ddd; padding: 3px 6px; font-size: 10px; }
        .firmas { margin-top: 14px; width: 100%; }
        .firmas td { width: 50%; text-align: center; vertical-align: bottom; }
        .firma-img { height: 52px; }
        .linea { border-top: 1px solid #333; margin: 0 18px; padding-top: 4px; }
        .condiciones { white-space: pre-wrap; font-size: 9.5px; line-height: 1.35; }
        .ok { color: #047857; } .dano { color: #b91c1c; }
    </style>
</head>
<body>
    @php $backup = $backup ?? $orden->backupVigente(); @endphp
    <table class="header">
        <tr>
            <td style="width:80px;">
                @if(is_file($logo))
                    <img class="logo" src="{{ $logo }}" alt="Logo">
                @endif
            </td>
            <td>
                <h1>Recepción de equipo</h1>
                <div class="muted">Palacio de los Detalles · cómo se recibió el equipo</div>
                <div>Orden <strong>{{ $orden->codigo() }}</strong> · {{ $orden->etiquetaTipoDispositivo() }} · {{ $orden->etiquetaTipoGestion() }}{{ $orden->etiquetaRangoGarantia() ? ' · '.$orden->etiquetaRangoGarantia() : '' }} · {{ $orden->sede }}</div>
            </td>
            <td style="text-align:right;width:140px;">{{ $orden->fecha_ingreso?->format('d/m/Y') ?: now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    @if($orden->esCambioEnRango())
    <div class="box">
        <strong>Garantía en rango de cambio</strong>
        <div style="margin-top:4px;">Equipo de la empresa. Se cambia al cliente y se envía a garantía a nombre de Palacio de los Detalles.</div>
    </div>
    @elseif(! $orden->esReparacionInterna())
    <div class="box">
        <strong>Cliente</strong>
        <table class="grid" style="margin-top:4px;">
            <tr><td class="label">Nombre / cédula</td><td>{{ $orden->cliente_nombre }} · {{ $orden->cliente_cedula ?: '—' }}</td></tr>
            <tr><td class="label">Teléfono</td><td>{{ $orden->cliente_telefono ?: '—' }}</td></tr>
            @if($orden->esGarantia())
                <tr><td class="label">Garantía</td><td>La garantía es con la marca del equipo ({{ $orden->marcaEquipo() }}). Palacio de los Detalles actúa como intermediario; el equipo pertenece al cliente.</td></tr>
            @endif
        </table>
    </div>
    @endif

    <div class="box">
        <strong>Equipo recibido</strong>
        <table class="grid" style="margin-top:4px;">
            <tr><td class="label">Equipo</td><td>{{ $orden->equipoCelular?->etiqueta() ?: ($orden->equipo ?: '—') }}</td></tr>
            <tr><td class="label">IMEI / serial</td><td>{{ $orden->imei ?: '—' }} / {{ $orden->serial ?: '—' }}</td></tr>
            <tr><td class="label">Valor del dispositivo</td><td>{{ $orden->valor_dispositivo !== null ? '$'.number_format((float) $orden->valor_dispositivo, 2) : '—' }}</td></tr>
            @if($orden->atributo('almacenamiento'))
                <tr><td class="label">Almacenamiento</td><td>{{ $orden->atributo('almacenamiento') }}</td></tr>
            @endif
            @if($orden->atributo('tipo_impresora'))
                <tr><td class="label">Tipo de impresora</td><td>{{ config('servicio_tecnico.tipos_impresora.'.$orden->atributo('tipo_impresora'), $orden->atributo('tipo_impresora')) }}</td></tr>
            @endif
            @if($orden->atributo('serial_lente'))
                <tr><td class="label">Serial del lente</td><td>{{ $orden->atributo('serial_lente') }}</td></tr>
            @endif
            <tr><td class="label">Accesorios</td><td>{{ $orden->accesorios ?: 'Ninguno declarado' }}</td></tr>
            <tr><td class="label">Falla reportada</td><td>{{ $orden->falla ?: '—' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <strong>Checklist de recepción</strong>
        <table class="check">
            @foreach(array_chunk($orden->itemsInspeccionRecepcion(), 2) as $par)
                <tr>
                    @foreach($par as $item)
                        <td>
                            {{ $item['etiqueta'] }}:
                            <strong class="{{ $item['estado'] }}">{{ $orden->etiquetaEstadoInspeccion($item['estado']) }}</strong>
                        </td>
                    @endforeach
                    @if(count($par) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
        @if($orden->observaciones)
            <div style="margin-top:6px;"><span class="muted">Observaciones:</span> {{ $orden->observaciones }}</div>
        @endif
    </div>

    @if($backup)
        <div class="box">
            <strong>Celular de backup entregado</strong>
            <table class="grid" style="margin-top:4px;">
                <tr><td class="label">Marca / modelo</td><td>{{ trim(($backup->marca.' '.$backup->modelo)) ?: '—' }}</td></tr>
                <tr><td class="label">IMEI / serial</td><td>{{ $backup->imei ?: '—' }} / {{ $backup->serial ?: '—' }}</td></tr>
                <tr><td class="label">Estado físico</td><td>{{ $backup->estado_fisico ?: '—' }}</td></tr>
                <tr><td class="label">Accesorios</td><td>{{ $backup->accesorios ?: '—' }}</td></tr>
            </table>
            @if($backup->condiciones)
                <div class="condiciones" style="margin-top:6px;"><strong>Condiciones:</strong> {{ $backup->condiciones }}</div>
            @endif
        </div>
    @else
        <div class="box muted">No se entregó celular de backup. Este documento deja constancia de cómo se recibió el equipo.</div>
    @endif

    <table class="firmas">
        <tr>
            @unless($orden->esReparacionInterna())
            <td>
                @if($orden->firma_recepcion_cliente && str_starts_with($orden->firma_recepcion_cliente, 'data:image'))
                    <img class="firma-img" src="{{ $orden->firma_recepcion_cliente }}" alt="Firma cliente">
                @endif
                <div class="linea">{{ $orden->cliente_nombre }}<br><span class="muted">Firma del cliente</span></div>
            </td>
            @endunless
            <td @if($orden->esReparacionInterna()) colspan="2" @endif>
                @if($orden->firma_recepcion_empleado && str_starts_with($orden->firma_recepcion_empleado, 'data:image'))
                    <img class="firma-img" src="{{ $orden->firma_recepcion_empleado }}" alt="Firma empleado">
                @endif
                <div class="linea">{{ $orden->creador?->name ?: ($backup?->firma_empleado ?? '—') }}<br><span class="muted">Firma del empleado</span></div>
            </td>
        </tr>
    </table>
</body>
</html>
