@php
    $monto = round((float) ($monto ?? 0), 2);
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
        <button type="button" class="com-bono-valor" title="Clic para editar bono">${{ number_format($monto, 2) }}</button>
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
    ${{ number_format($monto, 2) }}
@endif
