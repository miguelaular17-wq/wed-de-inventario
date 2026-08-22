@php
    $placeholder = $placeholder ?? 'Todas';
    $selected = $selected ?? '';
    $unidades = $unidades ?? collect();
    $sedesGrupo = $unidades->where('tipo', '!=', 'AREA');
    $areasGrupo = $unidades->where('tipo', 'AREA');
@endphp
<option value="">{{ $placeholder }}</option>
@if($sedesGrupo->isNotEmpty())
    <optgroup label="Sedes">
        @foreach($sedesGrupo as $unidad)
            <option value="{{ $unidad->id }}" @selected($selected == $unidad->id)>{{ $unidad->nombre }}</option>
        @endforeach
    </optgroup>
@endif
@if($areasGrupo->isNotEmpty())
    <optgroup label="Áreas (sin sede de tienda)">
        @foreach($areasGrupo as $unidad)
            <option value="{{ $unidad->id }}" @selected($selected == $unidad->id)>{{ $unidad->nombre }}</option>
        @endforeach
    </optgroup>
@endif
