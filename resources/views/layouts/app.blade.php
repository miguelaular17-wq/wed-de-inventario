<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario Multisede')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if(config('inventario.tutorial_enabled'))
    <link rel="stylesheet" href="{{ asset('css/onboarding-tour.css') }}">
    @endif
    @stack('head')
</head>
<body>
<header>
    <div class="wrap">
        <div>
            <strong>Inventario Multisede</strong>
            @if(session('sede_local'))
                <span class="badge" data-tour="sede-badge">Sede: {{ session('sede_local') }}</span>
            @endif
            @auth
                @if(auth()->user()->isAdmin())
                    <span class="badge">Admin</span>
                @endif
            @endauth
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            @auth
                @if(auth()->user()->isAdmin())
                    <nav>
                        <a href="{{ route('admin.dashboard') }}" data-tour="admin-dashboard" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Admin</a>
                        <a href="{{ route('admin.movimientos.index') }}" data-tour="admin-movimientos" class="{{ request()->routeIs('admin.movimientos.*') ? 'active' : '' }}">Movimientos</a>
                        <a href="{{ route('admin.import.create') }}" data-tour="admin-import" class="{{ request()->routeIs('admin.import.*') ? 'active' : '' }}">Importar</a>
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Usuarios</a>
                    </nav>
                @endif
            @endauth
            @if(session('sede_local'))
                <nav data-tour="nav-main">
                    <a href="{{ route('ventas.index') }}" data-tour="nav-ventas" class="{{ request()->routeIs('ventas.*') ? 'active' : '' }}">Ventas</a>
                    <a href="{{ route('inventario.index') }}" data-tour="nav-inventario" class="{{ request()->routeIs('inventario.*') ? 'active' : '' }}">Inventario</a>
                    <a href="{{ route('requisicion.form') }}" data-tour="nav-export" class="{{ request()->routeIs('requisicion.*') ? 'active' : '' }}">Exportar</a>
                </nav>
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.sedes.index') }}" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Cambiar sede</a>
                    @endif
                @endauth
            @endif
            @guest
                <a href="{{ route('login') }}" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Iniciar sesión</a>
                <a href="{{ route('register') }}" class="btn" style="padding:6px 12px;font-size:.85rem;">Registrarse</a>
            @else
                @if(config('inventario.tutorial_enabled'))
                <form method="POST" action="{{ route('tutorial.restart') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn secondary tour-help-btn" title="Ver tutorial guiado">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Ayuda
                    </button>
                </form>
                @endif
                <span class="badge">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Salir</button>
                </form>
            @endguest
        </div>
    </div>
</header>

<main>
    @if (session('status'))
        <div class="success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>

<script src="{{ asset('js/auto-filters.js') }}"></script>
@if(config('inventario.tutorial_enabled'))
@auth
<script>
window.__TOUR__ = {
    startStep: {{ (int) (auth()->user()->tutorial_step ?? -1) }},
    forceStart: {{ request()->boolean('tour') ? 'true' : 'false' }},
    isAdmin: {{ auth()->user()->isAdmin() ? 'true' : 'false' }},
    hasSede: {{ session('sede_local') ? 'true' : 'false' }},
    currentPage: @json(optional(request()->route())->getName() ?? ''),
    advanceUrl: @json(route('tutorial.advance')),
    completeUrl: @json(route('tutorial.complete')),
    routes: {
        ventas: @json(route('ventas.index')),
        inventario: @json(route('inventario.index')),
        export: @json(route('requisicion.form')),
        admin: @json(route('admin.dashboard')),
    },
};
</script>
<script src="{{ asset('js/onboarding-tour.js') }}"></script>
@endauth
@endif
@stack('scripts')
</body>
</html>
