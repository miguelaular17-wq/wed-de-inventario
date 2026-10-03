@php
    $concepto = $concepto ?? 'bono';
    $activo = old('concepto') === $concepto;
    $formatos = [
        'deduccion' => 'Deducción',
        'mercancia' => 'Mercancía',
        'PERDIDA' => 'Pérdida',
        'DANO' => 'Daño',
        'OTRO' => 'Otro',
    ];
@endphp
<form method="POST" action="{{ route('nomina.comisiones.ajustes.store', $periodo) }}" class="com-alta" data-buscar-empleados="{{ route('nomina.comisiones.empleados', $periodo) }}">
    @csrf
    <input type="hidden" name="concepto" value="{{ $concepto }}">
    <input type="hidden" name="empleado_id" value="{{ $activo ? old('empleado_id') : '' }}">
    <div class="com-alta-grid">
        <label class="com-alta-empleado">
            <span>Empleado</span>
            <input type="text" name="empleado_nombre" value="{{ $activo ? old('empleado_nombre') : '' }}" placeholder="Nombre o cédula" autocomplete="off" required>
            <div class="com-alta-lista" hidden></div>
        </label>
        @if($concepto === 'descuento')
            <label>
                <span>Tipo</span>
                <select name="formato">
                    @foreach($formatos as $valor => $texto)
                        <option value="{{ $valor }}" @selected($activo && old('formato', 'deduccion') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>
            <span>Monto $</span>
            <input type="number" name="monto" step="0.01" min="0.01" value="{{ $activo ? old('monto') : '' }}" required>
        </label>
        <label>
            <span>{{ $concepto === 'faltante' ? 'Detalle' : 'Motivo' }}</span>
            <input type="text" name="motivo" maxlength="500" value="{{ $activo ? old('motivo') : '' }}" placeholder="{{ $concepto === 'prestamo' || $concepto === 'faltante' ? 'Opcional' : 'Obligatorio' }}" @if(! in_array($concepto, ['prestamo', 'faltante'], true)) required @endif>
        </label>
        <button class="btn primary" type="submit">Registrar</button>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:.78rem;">
        @if($concepto === 'bono')
            Bonificación de comisión: empleado, monto y motivo. Entra en esta quincena.
        @elseif($concepto === 'prestamo')
            Crea el préstamo (monto y motivo) y lo deja para descontar de esta quincena.
        @elseif($concepto === 'faltante')
            Solo cajero, supervisor o call center. Monto y detalle. Se descuenta de esta quincena.
        @else
            Deducción y mercancía piden motivo. Pérdida, daño u otro usan el mismo monto y un detalle opcional.
        @endif
    </p>
</form>
