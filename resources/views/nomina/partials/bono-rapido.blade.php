@php
    $monto = round((float) ($monto ?? 0), 2);
    $bonoMeta = round((float) ($bonoMeta ?? 0), 2);
    $total = round($monto + $bonoMeta, 2);
    $puede = (bool) ($puede ?? false);
    $periodo = $periodo ?? null;
    $empleado = $empleado ?? null;
@endphp
@if($puede && $periodo && $empleado)
    <form method="POST"
          action="{{ route('nomina.comisiones.bono_rapido', [$periodo, $empleado]) }}"
          class="com-bono-rapido"
          data-monto-actual="{{ number_format($monto, 2, '.', '') }}">
        @csrf
        <button type="button" class="com-bono-valor" title="Clic para editar bono manual (sin meta)">${{ number_format($total, 2) }}</button>
        <input type="number"
               name="monto"
               class="com-bono-input"
               step="0.01"
               min="0"
               value="{{ number_format($monto, 2, '.', '') }}"
               hidden
               inputmode="decimal">
    </form>
@else
    ${{ number_format($total, 2) }}
@endif
@if($bonoMeta > 0)
    <div class="muted" data-bono-meta="1" style="font-size:.72rem;color:#0f766e;margin-top:2px;" title="Valor de productos meta × unidades vendidas">
        Meta ${{ number_format($bonoMeta, 2) }}
    </div>
@endif
