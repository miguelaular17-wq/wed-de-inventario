@php
    $categorias = $categorias ?? config('servicio_tecnico.categorias_reparacion', []);
    $iconos = config('servicio_tecnico.categorias_reparacion_iconos', []);
    $name = $name ?? 'categoria';
    $selected = $selected ?? old($name, $value ?? 'otro');
    $required = $required ?? false;
    $allowEmpty = $allowEmpty ?? false;
    $style = $style ?? 'width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;';
@endphp
<select name="{{ $name }}" @if($required) required @endif style="{{ $style }}">
    @if($allowEmpty)
        <option value="">— Sin categoría —</option>
    @endif
    @foreach($categorias as $key => $label)
        <option value="{{ $key }}" @selected((string) $selected === (string) $key)>
            {{ ($iconos[$key] ?? '📦').' '.$label }}
        </option>
    @endforeach
    @if($selected && ! array_key_exists($selected, $categorias))
        <option value="{{ $selected }}" selected>{{ $selected }}</option>
    @endif
</select>
