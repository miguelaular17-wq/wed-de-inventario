@php
    $concepto = $concepto ?? 'extras';
    $activo = old('concepto') === $concepto;
    $formatos = [
        'deduccion' => 'Deducción',
        'mercancia' => 'Mercancía',
        'faltante' => 'Faltante de caja',
        'otra' => 'Otro descuento',
    ];
@endphp
<form method="POST" action="{{ route('nomina.periodos.movimientos.store', $periodo) }}" class="com-alta" data-buscar-empleados="{{ route('nomina.periodos.empleados', $periodo) }}">
    @csrf
    <input type="hidden" name="concepto" value="{{ $concepto }}">
    <input type="hidden" name="empleado_id" value="{{ $activo ? old('empleado_id') : '' }}">
    <div class="com-alta-grid">
        <label class="com-alta-empleado">
            <span>Empleado</span>
            <input type="text" name="empleado_nombre" value="{{ $activo ? old('empleado_nombre') : '' }}" placeholder="Nombre o cédula" autocomplete="off" required>
            <div class="com-alta-lista" hidden></div>
        </label>
        @if($concepto === 'extras')
            <label>
                <span>Tipo</span>
                <select name="unidad">
                    <option value="HORAS" @selected(! $activo || old('unidad', 'HORAS') === 'HORAS')>Horas</option>
                    <option value="DIAS" @selected($activo && old('unidad') === 'DIAS')>Días (salario ÷ 30)</option>
                </select>
            </label>
            <label>
                <span>Cantidad</span>
                <input type="number" name="horas" step="0.25" min="0.25" value="{{ $activo ? old('horas') : '' }}" required>
            </label>
        @elseif($concepto === 'ias')
            <label>
                <span>Días</span>
                <input type="number" name="cantidad" step="0.5" min="0.5" value="{{ $activo ? old('cantidad', '1') : '1' }}" required>
            </label>
        @else
            @if($concepto === 'deduccion')
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
        @endif
        <label>
            <span>Motivo</span>
            <input type="text" name="motivo" maxlength="500" value="{{ $activo ? old('motivo') : '' }}" placeholder="{{ in_array($concepto, ['bono', 'deduccion'], true) ? 'Obligatorio' : 'Opcional' }}" @if(in_array($concepto, ['bono', 'deduccion'], true)) required @endif>
        </label>
        <button class="btn primary" type="submit">Registrar</button>
    </div>
    <p class="muted" style="margin:6px 0 0;font-size:.78rem;">
        @if($concepto === 'extras')
            Horas a la tarifa de la empresa, o días a salario ÷ 30. Entran al sueldo de esta quincena.
        @elseif($concepto === 'ias')
            Cada día descuenta salario ÷ 30. Solo una inasistencia por persona en el cierre de la quincena.
        @elseif($concepto === 'adelanto')
            Adelanto de sueldo: monto y motivo. Se descuenta en esta quincena.
        @elseif($concepto === 'bono')
            Bonificación de nómina: monto y motivo. Se suma al sueldo de esta quincena.
        @else
            Deducción, mercancía u otro piden motivo. El faltante de caja es para cajero, supervisor o call center y sale del sueldo.
        @endif
    </p>
</form>
