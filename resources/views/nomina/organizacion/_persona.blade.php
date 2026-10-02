@php
    $raw = trim((string) $empleado->nombre());
    $partes = preg_split('/\s+/', $raw) ?: [];
    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '?', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    $bonito = mb_convert_case(mb_strtolower($raw, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    foreach ([' De ', ' Del ', ' Da ', ' La ', ' Las ', ' Los ', ' Y '] as $particula) {
        $bonito = str_replace($particula, mb_strtolower($particula, 'UTF-8'), $bonito);
    }
    $tono = $tono ?? 'piso';
    $compacto = ! empty($compacto);
@endphp
<a class="org-person org-person-{{ $tono }} {{ $compacto ? 'is-compact' : '' }}" href="{{ route('nomina.empleados.show', $empleado) }}">
    <span class="org-avatar" aria-hidden="true">{{ $iniciales }}</span>
    <span class="org-person-text">
        <strong>{{ $bonito }}</strong>
        @unless($compacto)
            <em>{{ $empleado->nombreCargo() }}</em>
        @endunless
    </span>
</a>
