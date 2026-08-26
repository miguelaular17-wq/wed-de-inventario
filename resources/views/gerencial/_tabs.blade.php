@php
    $u = auth()->user();
    $tabs = [];
    if ($u->canAccess('gerencial')) {
        $tabs[] = ['label' => 'Gerencial', 'route' => 'gerencial.dashboard'];
    }
    if ($u->canAccess('gerencial.rentabilidad')) {
        $tabs[] = ['label' => 'Rentabilidad', 'route' => 'gerencial.rentabilidad'];
    }
    if ($u->canAccess('gerencial.devoluciones')) {
        $tabs[] = ['label' => 'Devoluciones', 'route' => 'gerencial.devoluciones'];
    }
    if ($u->canAccess('gerencial.valorizados')) {
        $tabs[] = ['label' => 'Inventario', 'route' => 'gerencial.valorizados'];
    }
    if ($u->canAccess('gerencial.ajustes')) {
        $tabs[] = ['label' => 'Ajustes', 'route' => 'gerencial.ajustes'];
    }
    $qs = request()->except(['page', 'ranking', 'tipo', 'ver_detalle']);
@endphp
@if(count($tabs) > 1)
<nav class="gerencial-tabs">
    @foreach($tabs as $tab)
        <a href="{{ route($tab['route'], $qs) }}" class="{{ request()->routeIs($tab['route']) ? 'is-active' : '' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
@endif
