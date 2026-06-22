@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="page-header">
    <h1>Ventas — {{ $sede }}</h1>
    <p class="lead">
        Mostrando {{ $rows->count() }} de {{ $calculatedCount }} productos calculados
        @if ($rows->lastPage() > 1)
            · Página {{ $rows->currentPage() }}/{{ $rows->lastPage() }}
        @endif
        .
    </p>
</div>

<form id="filters-form" method="GET" class="filter-bar" data-auto-filter data-auto-filter-delay="350" data-auto-filter-target="#ventas-content" data-tour="ventas-filters">
    <div class="field field-wide">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Producto o código…" autocomplete="off">
    </div>
    <div class="field">
        <label for="categoria-select">Categoría</label>
        <select name="categoria" id="categoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($categorias as $cat)
                <option value="{{ $cat }}" @selected($filters['categoria'] === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="subcategoria-select">Subcategoría</label>
        <select name="subcategoria" id="subcategoria-select">
            <option value="Ninguno">Todas</option>
            @foreach ($subcategorias as $sub)
                <option value="{{ $sub }}" @selected($filters['subcategoria'] === $sub)>{{ $sub }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="accion-select">Acción</label>
        <select name="accion" id="accion-select">
            @foreach ($accionesCombo as $acc)
                <option value="{{ $acc }}" @selected($filters['accion'] === $acc)>{{ $acc === 'Ninguno' ? 'Todas' : $acc }}</option>
            @endforeach
        </select>
    </div>
    <div class="field req-filter" @if(!$reqFiltersVisible) style="display:none" @endif>
        <label for="req_opc">Sede (OPC)</label>
        <select name="req_opc" id="req_opc">
            <option value="Todos">Todos</option>
            @foreach ($sedesOpc as $s)
                <option value="{{ $s }}" @selected($filters['req_opc'] === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="field req-filter" @if(!$reqFiltersVisible) style="display:none" @endif>
        <label for="req_color">Estado requisición</label>
        <select name="req_color" id="req_color">
            @foreach (config('inventario.req_colores') as $c)
                <option value="{{ $c }}" @selected($filters['req_color'] === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="tiempo_pronostico">Pronóstico (días)</label>
        <input type="number" id="tiempo_pronostico" name="tiempo_pronostico" min="1" max="365" value="{{ $tiempoPronostico }}">
    </div>
</form>

<div id="ventas-content" class="ajax-content">
    @include('ventas._content')
</div>
@endsection

@push('scripts')
<script>
(function () {
    const syncInterval = @json((int) config('inventario.sync_interval_ms', 60000));
    const cat = document.getElementById('categoria-select');
    const sub = document.getElementById('subcategoria-select');

    if (cat && sub) {
        function updateSubDisabled() {
            sub.disabled = (cat.value === 'Ninguno');
        }
        updateSubDisabled();
        cat.addEventListener('change', updateSubDisabled);
    }

    let since = @json($stockUpdatedAt);
    const filterForm = document.getElementById('filters-form');
    const contentRoot = document.getElementById('ventas-content');

    async function reloadContent() {
        if (!contentRoot) return;

        const url = new URL(window.location.href);
        contentRoot.classList.add('is-loading');
        try {
            const r = await fetch(url.toString(), {
                headers: {
                    'X-Partial': 'content',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!r.ok) return;
            contentRoot.innerHTML = await r.text();
            if (window.AutoFilter) {
                window.AutoFilter.rebind('#ventas-content');
            }
        } finally {
            contentRoot.classList.remove('is-loading');
        }
    }

    async function syncVentas() {
        if (!since) {
            return;
        }

        const url = new URL(@json(route('ventas.sync')).replace(/&amp;/g, '&'), window.location.origin);
        url.searchParams.set('since', since);
        new FormData(filterForm).forEach((value, key) => url.searchParams.set(key, value));

        try {
            const r = await fetch(url.toString());
            if (!r.ok) {
                return;
            }

            const j = await r.json();
            if (!j.changed) {
                return;
            }

            since = j.updated_at;
            await reloadContent();
        } catch (e) {
            // ignore transient sync issues
        }
    }

    if (since && window.AppSyncPoll) {
        window.AppSyncPoll.start(syncVentas, syncInterval);
    }
})();
</script>
@endpush
