<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Flujo de Caja</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; margin: 0; padding: 0; }
        .header { width: 100%; margin-bottom: 14px; }
        .header td { vertical-align: middle; border: none; padding: 0; }
        .header img { height: 58px; width: auto; }
        .header h2 { margin: 0; color: #1a4273; font-size: 16px; }
        .header p { margin: 3px 0 0; color: #64748b; font-size: 10px; }
        .section-title { font-size: 12px; font-weight: bold; color: #fff; background: #1a4273; margin-top: 14px; margin-bottom: 0; padding: 6px 8px; }
        table.mov { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; }
        table.mov th, table.mov td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; word-wrap: break-word; }
        table.mov th { background-color: #e2e8f0; color: #1e293b; font-weight: bold; font-size: 8px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-danger { color: #b91c1c; }
        .muted { color: #64748b; font-size: 8px; }
        .total-row { background-color: #f1f5f9; font-weight: bold; }
        .desglose { width: 92%; margin: 0 0 10px 8%; border-collapse: collapse; }
        .desglose th { background: #fff7ed; color: #9a3412; font-size: 7px; text-transform: uppercase; border: 1px solid #fed7aa; padding: 3px 5px; }
        .desglose td { border: 1px solid #fed7aa; padding: 3px 5px; font-size: 8px; background: #fffbeb; }
        .desglose .tot { background: #ffedd5; font-weight: bold; }
        .empty { text-align: center; color: #64748b; margin-top: 40px; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td style="width: 80px;">
            @if(is_file(public_path('logo.png')))
                <img src="{{ public_path('logo.png') }}" alt="Logo">
            @endif
        </td>
        <td>
            <h2>Palacio de los Detalles — Reporte de Flujo de Caja</h2>
            <p>Desde {{ date('d/m/Y', strtotime($data['fecha_desde'])) }} hasta {{ date('d/m/Y', strtotime($data['fecha_hasta'])) }}</p>
            @if(!empty($data['q']))
                <p>Filtro: "{{ $data['q'] }}"</p>
            @endif
        </td>
        <td style="width: 140px; text-align: right; color: #64748b; font-size: 9px;">
            Generado<br>{{ now()->format('d/m/Y H:i') }}
        </td>
    </tr>
</table>

@php
    $secciones = [
        ['egreso_realizado', 'EGRESOS REALIZADOS', $data['egresos'], $data['tot_egresos_usd'], $data['tot_egresos_dif'], $data['tot_egresos_bs'], $data['tot_egresos_com'], true, true],
        ['otros_egresos', 'OTROS EGRESOS (AVANCES Y CAMBIOS)', $data['otros'], $data['tot_otros_usd'], $data['tot_otros_dif'], $data['tot_otros_bs'], $data['tot_otros_com'], true, true],
        ['traslados', 'TRASLADOS', $data['traslados'], $data['tot_traslados_usd'], 0, $data['tot_traslados_bs'], $data['tot_traslados_com'], true, false],
        ['egreso_divisas', 'EGRESOS EN DIVISAS', $data['divisas'], $data['tot_divisas_usd'], 0, 0, 0, false, false],
    ];
@endphp

@foreach($secciones as [$cat, $titulo, $rows, $totUsd, $totDif, $totBs, $totCom, $conBs, $conDif])
    @if(in_array($cat, $data['selected_cats'], true) && $rows->count() > 0)
        <div class="section-title">{{ $titulo }}</div>
        <table class="mov">
            <thead>
                <tr>
                    <th style="width: 8%">Fecha</th>
                    <th style="width: 24%">Origen → Destino</th>
                    <th style="width: 12%">Tipo gasto</th>
                    <th style="width: 16%">Motivo</th>
                    <th style="width: 10%" class="text-right">USD</th>
                    @if($conDif)<th style="width: 10%" class="text-right">Dif. camb.</th>@endif
                    @if($conBs)
                        <th style="width: 10%" class="text-right">Bs</th>
                        <th style="width: 10%" class="text-right">Comisión</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $mov)
                    <tr>
                        <td>{{ $mov->fecha ? date('d/m/Y', strtotime((string) $mov->fecha)) : '-' }}</td>
                        <td>
                            <strong>{{ $mov->banco ?: '-' }}</strong><br>
                            <span class="muted">{{ $mov->titular ?: '' }}</span>
                            @if($mov->banco_receptor || $mov->titular_receptor)
                                <br>→ <strong>{{ $mov->banco_receptor }}</strong><br>
                                <span class="muted">{{ $mov->titular_receptor }}</span>
                            @endif
                        </td>
                        <td>{{ $mov->tipo_gasto ?: '-' }}</td>
                        <td>{{ $mov->motivo ?: '-' }}</td>
                        <td class="text-right">{{ $mov->monto_usd ? '$'.number_format((float) $mov->monto_usd, 2) : '-' }}</td>
                        @if($conDif)
                            <td class="text-right text-danger">{{ $mov->diferencial_cambiario ? number_format((float) $mov->diferencial_cambiario, 2) : '-' }}</td>
                        @endif
                        @if($conBs)
                            <td class="text-right">{{ $mov->monto_bs ? 'Bs. '.number_format((float) $mov->monto_bs, 2) : '-' }}</td>
                            <td class="text-right">{{ $mov->comision ? number_format((float) $mov->comision, 2) : '-' }}</td>
                        @endif
                    </tr>
                    @if(!empty($mov->desglose) && is_array($mov->desglose))
                        <tr>
                            <td colspan="{{ 5 + ($conDif ? 1 : 0) + ($conBs ? 2 : 0) }}" style="padding: 0; border: none;">
                                <table class="desglose">
                                    <thead>
                                        <tr>
                                            <th>Cédula / RIF / Nombre</th>
                                            <th>Sede</th>
                                            <th>Tipo gasto</th>
                                            <th class="text-right">USD</th>
                                            <th class="text-right">Bs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $sumBs = 0;
                                            $sumUsd = 0;
                                        @endphp
                                        @foreach($mov->desglose as $item)
                                            @php
                                                $itemBs = (float) ($item['monto'] ?? 0);
                                                $itemUsd = (float) ($item['monto_usd'] ?? 0);
                                                $sumBs += $itemBs;
                                                $sumUsd += $itemUsd;
                                            @endphp
                                            <tr>
                                                <td>{{ $item['cedula'] ?? '-' }}</td>
                                                <td>{{ $item['sede'] ?? '-' }}</td>
                                                <td>{{ $item['tipo_gasto'] ?? '-' }}</td>
                                                <td class="text-right">{{ $itemUsd > 0 ? '$'.number_format($itemUsd, 2) : '-' }}</td>
                                                <td class="text-right">Bs. {{ number_format($itemBs, 2) }}</td>
                                            </tr>
                                        @endforeach
                                        <tr class="tot">
                                            <td colspan="3" class="text-right">Total desglose</td>
                                            <td class="text-right">{{ $sumUsd > 0 ? '$'.number_format($sumUsd, 2) : '-' }}</td>
                                            <td class="text-right">Bs. {{ number_format($sumBs, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">TOTALES</td>
                    <td class="text-right">${{ number_format((float) $totUsd, 2) }}</td>
                    @if($conDif)
                        <td class="text-right text-danger">${{ number_format((float) $totDif, 2) }}</td>
                    @endif
                    @if($conBs)
                        <td class="text-right">Bs. {{ number_format((float) $totBs, 2) }}</td>
                        <td class="text-right">Bs. {{ number_format((float) $totCom, 2) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>
    @endif
@endforeach

@if($data['egresos']->count() == 0 && $data['otros']->count() == 0 && $data['traslados']->count() == 0 && $data['divisas']->count() == 0)
    <p class="empty">No se encontraron registros para los filtros seleccionados.</p>
@endif

</body>
</html>
