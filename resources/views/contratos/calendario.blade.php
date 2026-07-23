@extends('layouts.app')
@section('title', 'Calendario de Vencimientos')
@section('content')
<div style="padding: 20px; max-width: 1200px; margin: 0 auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h2 style="color: var(--blue); font-weight: 700; margin: 0;">📅 Calendario de Vencimientos</h2>
        <a href="{{ route('contratos.index') }}" style="padding: 8px 16px; background: #e0f2fe; color: #0284c7; border-radius: 6px; text-decoration: none; font-weight: 500;">← Dashboard</a>
    </div>

    {{-- Navegación de meses --}}
    <div style="display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 24px;">
        <a href="{{ route('contratos.calendario', ['mes' => $mesAnterior->month, 'anio' => $mesAnterior->year]) }}"
           style="padding: 8px 14px; background: #f1f5f9; color: #475569; border-radius: 6px; text-decoration: none; font-weight: 500;">← {{ $mesAnterior->translatedFormat('M') }}</a>
        <h3 style="margin: 0; color: var(--blue); font-size: 1.3rem; text-transform: uppercase;">
            {{ $inicio->translatedFormat('F Y') }}
        </h3>
        <a href="{{ route('contratos.calendario', ['mes' => $mesSiguiente->month, 'anio' => $mesSiguiente->year]) }}"
           style="padding: 8px 14px; background: #f1f5f9; color: #475569; border-radius: 6px; text-decoration: none; font-weight: 500;">{{ $mesSiguiente->translatedFormat('M') }} →</a>
    </div>

    {{-- Calendario grid --}}
    <div class="panel" style="padding: 16px; overflow: hidden;">
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e2e8f0;">
            {{-- Días de la semana --}}
            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia)
                <div style="background: #f8fafc; padding: 10px; text-align: center; font-weight: 700; color: #475569; font-size: 0.85rem;">{{ $dia }}</div>
            @endforeach

            {{-- Blanks para alinear el primer día --}}
            @php
                $primerDia = $inicio->copy()->startOfMonth();
                // Lunes=1 ... Domingo=7 en Carbon. Necesitamos poner blanks antes.
                $blanksBefore = ($primerDia->dayOfWeekIso - 1);
                $ultimoDia = $inicio->copy()->endOfMonth()->day;
                $hoy = now()->toDateString();
            @endphp

            @for($i = 0; $i < $blanksBefore; $i++)
                <div style="background: #fafafa; min-height: 100px;"></div>
            @endfor

            {{-- Días del mes --}}
            @for($d = 1; $d <= $ultimoDia; $d++)
                @php
                    $fechaStr = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
                    $cuotasDia = $cuotas[$fechaStr] ?? collect();
                    $esHoy = $fechaStr === $hoy;
                    $tieneVencidas = $cuotasDia->where('estatus', 'vencido')->count() > 0;
                    $tienePendientes = $cuotasDia->whereIn('estatus', ['pendiente', 'parcial'])->count() > 0;

                    $bgColor = '#ffffff';
                    if ($esHoy) $bgColor = '#eff6ff';
                    if ($tieneVencidas) $bgColor = '#fef2f2';
                @endphp
                <div style="background: {{ $bgColor }}; min-height: 100px; padding: 6px; position: relative; border: {{ $esHoy ? '2px solid #3b82f6' : 'none' }};">
                    <div style="font-weight: {{ $esHoy ? '700' : '500' }}; color: {{ $esHoy ? '#3b82f6' : '#334155' }}; font-size: 0.85rem; margin-bottom: 4px;">
                        {{ $d }}
                        @if($esHoy)<span style="font-size: 0.7rem; color: #3b82f6;">HOY</span>@endif
                    </div>

                    @foreach($cuotasDia->take(3) as $cuo)
                        @php
                            $dotColor = match($cuo->estatus) {
                                'vencido' => '#dc2626',
                                'parcial' => '#f59e0b',
                                default => '#3b82f6',
                            };
                        @endphp
                        <a href="{{ route('contratos.show', $cuo->contrato_id) }}" title="{{ $cuo->contrato->cliente ?? '' }} - ${{ number_format($cuo->saldo, 2) }}"
                           style="display: block; background: {{ $dotColor }}15; border-left: 3px solid {{ $dotColor }}; padding: 2px 6px; margin-bottom: 3px; border-radius: 0 4px 4px 0; text-decoration: none; font-size: 0.7rem; color: {{ $dotColor }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ Str::limit($cuo->contrato->cliente ?? '—', 15) }} · ${{ number_format($cuo->saldo, 0) }}
                        </a>
                    @endforeach

                    @if($cuotasDia->count() > 3)
                        <div style="font-size: 0.65rem; color: #64748b; text-align: center;">+{{ $cuotasDia->count() - 3 }} más</div>
                    @endif
                </div>
            @endfor

            {{-- Blanks finales --}}
            @php
                $totalCells = $blanksBefore + $ultimoDia;
                $blanksAfter = (7 - ($totalCells % 7)) % 7;
            @endphp
            @for($i = 0; $i < $blanksAfter; $i++)
                <div style="background: #fafafa; min-height: 100px;"></div>
            @endfor
        </div>
    </div>

    {{-- Leyenda --}}
    <div style="display: flex; gap: 20px; margin-top: 16px; flex-wrap: wrap; justify-content: center;">
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem;">
            <span style="width: 14px; height: 14px; border-radius: 50%; background: #dc2626; display: inline-block;"></span> Vencido
        </div>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem;">
            <span style="width: 14px; height: 14px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span> Parcial
        </div>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem;">
            <span style="width: 14px; height: 14px; border-radius: 50%; background: #3b82f6; display: inline-block;"></span> Pendiente
        </div>
    </div>
</div>
@endsection
