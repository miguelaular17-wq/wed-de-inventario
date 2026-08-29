@php
    $empleado = $empleado ?? $cuota->prestamo->empleado;
    $saldo = $cuota->saldo();
    $plan = $plan ?? null;
    $marcado = $plan && $plan->isPendiente();
    $monto = $plan ? (float) $plan->monto : $saldo;
    $comisionable = $empleado->generaComision();
    $destino = $plan->destino ?? ($comisionable ? 'COMISION' : 'NOMINA');
@endphp
<div class="cuota-linea{{ $comisionable ? ' has-destino' : '' }}">
    <label class="cuota-linea-check">
        <input type="checkbox"
            class="cuota-check"
            name="descuentos[{{ $cuota->id }}][aplicar]"
            value="1"
            @checked($marcado)
            data-empleado="{{ $empleado->id }}"
            data-nombre="{{ $empleado->nombre() }}"
            data-cuota="{{ $cuota->numero }}"
            data-motivo="{{ $cuota->prestamo->motivo ?: 'Préstamo #'.$cuota->prestamo_id }}"
            data-max="{{ number_format($saldo, 2, '.', '') }}"
            onchange="sincronizarCuota(this)">
        <input type="hidden" name="descuentos[{{ $cuota->id }}][cuota_id]" value="{{ $cuota->id }}">
        @unless($comisionable)
            <input type="hidden" name="descuentos[{{ $cuota->id }}][destino]" value="NOMINA">
        @endunless
    </label>
    <div class="cuota-linea-info">
        <strong>#{{ $cuota->numero }}</strong>
        · {{ $cuota->fecha_programada?->format('d/m/Y') }}
        · saldo ${{ number_format($saldo, 2) }}
        <span class="muted">{{ $cuota->prestamo->motivo ?: 'Préstamo #'.$cuota->prestamo_id }}</span>
    </div>
    <div class="cuota-linea-monto">
        <label>Parcial $</label>
        <input type="number"
            class="cuota-monto"
            name="descuentos[{{ $cuota->id }}][monto]"
            min="0.01"
            max="{{ number_format($saldo, 2, '.', '') }}"
            step="0.01"
            value="{{ number_format($monto, 2, '.', '') }}"
            data-empleado="{{ $empleado->id }}"
            @disabled(! $marcado)
            oninput="actualizarRegistro()">
    </div>
    @if($comisionable)
        <div class="cuota-linea-destino">
            <label>Descontar de</label>
            <select name="descuentos[{{ $cuota->id }}][destino]" class="cuota-destino">
                <option value="NOMINA" @selected($destino === 'NOMINA')>Nómina</option>
                <option value="COMISION" @selected($destino === 'COMISION')>Comisión</option>
            </select>
        </div>
    @endif
</div>
