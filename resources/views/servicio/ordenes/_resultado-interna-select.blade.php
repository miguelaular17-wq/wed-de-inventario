@php
    $resultadoActual = (string) ($orden->resultado_interna ?? '');
    [$fondo, $texto] = \App\Models\StOrden::COLORES_RESULTADO_INTERNA[$resultadoActual] ?? ['#f1f5f9', '#475569'];
@endphp
<select class="ri-resultado"
    data-url="{{ route('servicio.ordenes.resultado_interna', $orden) }}"
    data-actual="{{ $resultadoActual }}"
    data-tecnico="{{ $orden->reparado_por }}"
    style="background:{{ $fondo }};color:{{ $texto }};">
    <option value="">Sin estado</option>
    @foreach(\App\Models\StOrden::RESULTADOS_INTERNA as $clave => $etiqueta)
        <option value="{{ $clave }}" @selected($resultadoActual === $clave)>{{ $clave === \App\Models\StOrden::RESULTADO_REPARADO ? 'Reparado (técnico)' : $etiqueta }}</option>
    @endforeach
</select>
<div class="ri-aviso">{{ $resultadoActual === \App\Models\StOrden::RESULTADO_REPARADO && $orden->reparador ? 'Por '.$orden->reparador->name : '' }}</div>
