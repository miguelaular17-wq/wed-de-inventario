@php
    $motivoActual = (string) ($orden->estado_empresa_garantia ?? '');
    [$fondo, $texto] = \App\Models\StOrden::COLORES_RECONOCIMIENTO_GARANTIA[$motivoActual] ?? ['#f1f5f9', '#475569'];
    $valorActual = $modo === 'marca' ? ($motivoActual !== '' ? 'RECONOCIDO' : '') : $motivoActual;
@endphp
<select class="rm-reconocimiento"
    data-url="{{ route('servicio.ordenes.garantia.reconocimiento', $orden) }}"
    data-modo="{{ $modo }}"
    data-actual="{{ $valorActual }}"
    style="background:{{ $fondo }};color:{{ $texto }};">
    <option value="">Sin estado</option>
    @if($modo === 'marca')
        <option value="RECONOCIDO" @selected($valorActual === 'RECONOCIDO')>Reconocido por la marca</option>
    @else
        <optgroup label="Reconocido por la marca">
            @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
                <option value="{{ $key }}" @selected($motivoActual === $key)>{{ $label }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
<div class="rm-aviso"></div>
