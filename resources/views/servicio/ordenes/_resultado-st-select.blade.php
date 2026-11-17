@php
    $resultadoActual = (string) ($orden->resultado_interna ?? '');
    [$fondo, $texto] = \App\Models\StOrden::COLORES_RESULTADO_ST[$resultadoActual] ?? ['#f1f5f9', '#475569'];
@endphp
<select class="rs-resultado"
    data-url="{{ route('servicio.ordenes.resultado_st', $orden) }}"
    data-actual="{{ $resultadoActual }}"
    data-modo="{{ $modo ?? 'marca' }}"
    style="background:{{ $fondo }};color:{{ $texto }};">
    <option value="">Sin estado</option>
    @foreach(\App\Models\StOrden::RESULTADOS_ST as $clave => $etiqueta)
        <option value="{{ $clave }}" @selected($resultadoActual === $clave)>{{ $etiqueta }}</option>
    @endforeach
</select>
<div class="rs-aviso"></div>
