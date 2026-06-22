<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario Multisede')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css">
    @if(config('inventario.tutorial_enabled'))
    <link rel="stylesheet" href="/css/onboarding-tour.css">
    @endif
    @stack('head')
</head>
<body>
<header>
    <div class="wrap">
        <div>
            <strong>Inventario Multisede</strong>
            @if(session('sede_local') && auth()->user() && auth()->user()->hasAccessToSedeViews())
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
                <nav style="display:flex; gap:8px;">
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" data-tour="admin-dashboard" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Admin</a>
                        <a href="{{ route('admin.movimientos.index') }}" data-tour="admin-movimientos" class="{{ request()->routeIs('admin.movimientos.*') ? 'active' : '' }}">Movimientos</a>
                        <a href="{{ route('admin.import.create') }}" data-tour="admin-import" class="{{ request()->routeIs('admin.import.*') ? 'active' : '' }}">Importar</a>
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Usuarios</a>
                    @elseif(auth()->user()->isGerente())
                        <a href="{{ route('admin.movimientos.index') }}" class="{{ request()->routeIs('admin.movimientos.*') ? 'active' : '' }}">Movimientos</a>
                        <a href="{{ route('gerente.dashboard') }}" class="{{ request()->routeIs('gerente.dashboard') ? 'active' : '' }}">Requisiciones</a>
                    @elseif(auth()->user()->isComprador() || auth()->user()->isMarketing())
                        <a href="{{ route('comprador.dashboard') }}" class="{{ request()->routeIs('comprador.dashboard') ? 'active' : '' }}">
                            {{ auth()->user()->isMarketing() ? 'Marketing' : 'Compras' }}
                        </a>
                    @endif

                    @if(auth()->user()->hasAccessToSedeViews() && session('sede_local'))
                        <a href="{{ route('ventas.index') }}" data-tour="nav-ventas" class="{{ request()->routeIs('ventas.*') ? 'active' : '' }}">Ventas</a>
                        <a href="{{ route('inventario.index') }}" data-tour="nav-inventario" class="{{ request()->routeIs('inventario.*') ? 'active' : '' }}">Inventario</a>
                        <a href="{{ route('requisicion.form') }}" data-tour="nav-export" class="{{ request()->routeIs('requisicion.*') ? 'active' : '' }}">Exportar</a>
                    @endif
                </nav>

                @if(auth()->user()->isComprador())
                    {{-- Comprador always sees the sede button --}}
                    @if(session('sede_local'))
                        <form method="POST" action="{{ route('sede.change') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">📍 {{ session('sede_local') }} · Cambiar</button>
                        </form>
                    @else
                        <a href="{{ route('sede.select') }}" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">📍 Seleccionar sede</a>
                    @endif
                @elseif(auth()->user()->hasAccessToSedeViews() && session('sede_local'))
                    @if(in_array(auth()->user()->role, ['supervisor', 'telefonia'], true))
                        <span class="btn secondary" style="padding:6px 12px;font-size:.85rem;cursor:default;opacity:0.85;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);">📍 {{ session('sede_local') }}</span>
                    @else
                        <form method="POST" action="{{ route('sede.change') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:.85rem;">Cambiar sede</button>
                        </form>
                    @endif
                @endif

                <!-- Notification Bell Dropdown -->
                <div style="position:relative;" class="notification-dropdown-container">
                    <a href="{{ route('notifications.index') }}" class="notification-bell" style="position:relative; display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.15); color:#fff; text-decoration:none; cursor:pointer;" title="Ver notificaciones">
                        🔔
                        @php 
                            $unreadCount = auth()->user()->notifications()->unread()->count(); 
                            $latestNotifications = auth()->user()->notifications()->latest()->take(5)->get();
                        @endphp
                        @if($unreadCount > 0)
                            <span style="position:absolute; top:-4px; right:-4px; background:#ef4444; color:#fff; font-size:0.65rem; font-weight:700; border-radius:50%; padding:2px 5px; min-width:14px; text-align:center; line-height:1.2; border: 1.5px solid #1a4480;">
                                {{ $unreadCount }}
                            </span>
                        @endif
                    </a>

                    <div class="notification-dropdown" style="display:none; position:absolute; right:0; top:100%; margin-top:8px; width:320px; background:#fff; border-radius:8px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1); border:1px solid #e2e8f0; z-index:50;">
                        <div style="padding:12px 16px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color:#1e293b; font-size:0.95rem;">Notificaciones</strong>
                            <a href="{{ route('notifications.index') }}" style="font-size:0.8rem; color:#3b82f6; text-decoration:none;">Ver todas</a>
                        </div>
                        <div style="max-height:300px; overflow-y:auto; background:#fff;">
                            @forelse($latestNotifications as $notification)
                                <div style="padding:12px 16px; border-bottom:1px solid #f1f5f9; background: {{ $notification->read_at ? '#fff' : '#f8fafc' }}; transition:background 0.2s;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                        <strong style="font-size:0.85rem; color:#334155;">
                                            {{ $notification->sender ? $notification->sender->name : 'Sistema' }}
                                        </strong>
                                        <span style="font-size:0.75rem; color:#64748b;">{{ $notification->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p style="margin:0; font-size:0.85rem; color:#475569; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                        {{ $notification->message }}
                                    </p>
                                </div>
                            @empty
                                <div style="padding:20px; text-align:center; color:#64748b; font-size:0.85rem;">
                                    No hay notificaciones
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endauth

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

<script src="/js/sync-poll.js"></script>
<script src="/js/auto-filters.js"></script>
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
<script src="/js/onboarding-tour.js"></script>
@endauth
@endif
@stack('scripts')

<script>
document.addEventListener("DOMContentLoaded", function() {
    const savedPath = sessionStorage.getItem('scrollPath');
    const scrollPos = sessionStorage.getItem('scrollPosition');
    const currentPath = window.location.pathname + window.location.search;

    function normalizePathForScroll(path) {
        if (!path) return '';
        // Strip exclude_codes parameters case-insensitively, with or without brackets
        let clean = path.replace(/&?exclude_codes(?:\[\]|%5b%5d|)=[^&]*/gi, '');
        // Clean up parameter separators
        clean = clean.replace(/\?&/, '?');
        if (clean.endsWith('?') || clean.endsWith('&')) {
            clean = clean.slice(0, -1);
        }
        return clean;
    }
    
    if (savedPath && scrollPos !== null && normalizePathForScroll(savedPath) === normalizePathForScroll(currentPath)) {
        const targetY = parseInt(scrollPos, 10);
        const intervals = [10, 50, 150, 300, 600, 1000];
        intervals.forEach(delay => {
            setTimeout(function() {
                window.scrollTo({
                    top: targetY,
                    behavior: 'instant'
                });
            }, delay);
        });
    }
    
    sessionStorage.removeItem('scrollPosition');
    sessionStorage.removeItem('scrollPath');

    function saveScroll() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
        sessionStorage.setItem('scrollPath', window.location.pathname + window.location.search);
    }

    function mergeGetFormAndNavigate(form) {
        if (form.method.toLowerCase() !== 'get') return false;
        
        const action = form.getAttribute('action') || window.location.pathname;
        const actionUrl = new URL(action, window.location.origin);
        
        // Start with all current query parameters in the address bar
        const params = new URLSearchParams(window.location.search);
        
        // Merge form data
        const formData = new FormData(form);
        
        let resetPage = false;
        let resetPageSS = false;
        
        for (const [key, value] of formData.entries()) {
            // Only merge if not empty/null or if it overrides
            params.set(key, value);
            
            // If filters on tab 1 change, we reset tab 1 page
            if (key === 'q' || key === 'categoria' || key === 'proveedor' || key === 'subcategoria' || key === 'status') {
                resetPage = true;
            }
            // If filters on tab 2 change, we reset tab 2 page
            if (key === 'ss_buscar' || key === 'ss_categoria' || key === 'ss_subcategoria' || key === 'ss_proveedor' || key === 'ss_sede' || key === 'ss_rotacion' || key === 'ss_sobrestock' || key === 'ss_estado' || key === 'ss_semaforo' || key === 'ss_min_dias' || key === 'ss_min_stock') {
                resetPageSS = true;
            }
        }
        
        if (resetPage) {
            params.delete('page');
        }
        if (resetPageSS) {
            params.delete('page_sobre_stock');
        }
        
        // Navigate
        window.location.href = actionUrl.pathname + '?' + params.toString();
        return true;
    }

    // Auto-save scroll position before unloading the page
    window.addEventListener('beforeunload', saveScroll);
    window.addEventListener('pagehide', saveScroll);

    document.addEventListener('submit', function(e) {
        saveScroll();
        const form = e.target;
        if (form.method.toLowerCase() === 'get' && !form.hasAttribute('data-auto-filter')) {
            const action = form.getAttribute('action') || window.location.href;
            const actionUrl = new URL(action, window.location.origin);
            if (actionUrl.origin === window.location.origin) {
                if (mergeGetFormAndNavigate(form)) {
                    e.preventDefault();
                }
            }
        }
    });

    // Intercept programmatic submit() calls to save scroll position and merge query parameters
    const originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        saveScroll();
        if (this.method.toLowerCase() === 'get' && !this.hasAttribute('data-auto-filter')) {
            const action = this.getAttribute('action') || window.location.href;
            const actionUrl = new URL(action, window.location.origin);
            if (actionUrl.origin === window.location.origin) {
                if (mergeGetFormAndNavigate(this)) {
                    return; // Stop native submit since we navigated programmatically
                }
            }
        }
        originalSubmit.apply(this, arguments);
    };

    // Global Toast Notification Helper
    window.showStatusMessage = function(message, isError = false) {
        let toastContainer = document.getElementById('startup-notifications-toast');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'startup-notifications-toast';
            toastContainer.style.cssText = 'position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:12px; pointer-events:none;';
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.cssText = `
            background: #fff;
            border-left: 4px solid ${isError ? '#ef4444' : '#10b981'};
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            width: 320px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            pointer-events: auto;
        `;
        toast.innerHTML = `
            <div style="flex:1; font-size:0.88rem; color:#1e293b; font-weight:500;">
                ${message}
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1.25rem; line-height:1; padding:0 0 0 12px; margin:-4px 0 0 0;">&times;</button>
        `;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    };

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href && link.href.startsWith(window.location.origin) && !link.hash) {
            saveScroll();
        }

        // Notification dropdown toggle
        const dropdownContainer = e.target.closest('.notification-dropdown-container');
        const allDropdowns = document.querySelectorAll('.notification-dropdown');
        
        if (dropdownContainer) {
            const dropdown = dropdownContainer.querySelector('.notification-dropdown');
            const isVisible = dropdown.style.display === 'block';
            
            // Close all other dropdowns
            allDropdowns.forEach(d => d.style.display = 'none');
            
            if (!isVisible && e.target.closest('.notification-bell')) {
                e.preventDefault(); // Prevent navigating to index if clicking the bell to open dropdown
                dropdown.style.display = 'block';
            }
        } else {
            // Click outside, close dropdown
            allDropdowns.forEach(d => d.style.display = 'none');
        }
    });
});
</script>
@auth
    @php
        $unreadNotifications = auth()->user()->notifications()->unread()->latest()->take(3)->get();
    @endphp
    @if($unreadNotifications->count() > 0)
        <div id="startup-notifications-toast" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:12px; pointer-events:none;">
            <style>
                @keyframes slideInRight {
                    from { transform: translateX(120%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .toast-notification {
                    background: #fff;
                    border-left: 4px solid #3b82f6;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
                    width: 320px;
                    padding: 16px;
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    animation: slideInRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                    pointer-events: auto;
                }
            </style>
            @foreach($unreadNotifications as $notification)
                <div class="toast-notification">
                    <div style="flex:1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong style="font-size:0.85rem; color:#1e293b;">
                                {{ $notification->sender ? $notification->sender->name : 'Sistema' }}
                            </strong>
                            <span style="font-size:0.75rem; color:#64748b;">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <p style="margin:0; font-size:0.85rem; color:#475569; line-height:1.4;">
                            {{ Str::limit($notification->message, 100) }}
                        </p>
                    </div>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1.25rem; line-height:1; padding:0 0 0 12px; margin:-4px 0 0 0;">&times;</button>
                </div>
            @endforeach
            @if(auth()->user()->notifications()->unread()->count() > 3)
                <div class="toast-notification" style="justify-content:center; padding:12px; border-left-color:#64748b; cursor:pointer;" onclick="window.location.href='{{ route('notifications.index') }}'">
                    <span style="font-size:0.85rem; color:#3b82f6; font-weight:600;">Ver {{ auth()->user()->notifications()->unread()->count() - 3 }} notificaciones más...</span>
                </div>
            @endif
        </div>
        <script>
            // Auto dismiss the toasts after 10 seconds to not block the screen
            setTimeout(() => {
                const toastContainer = document.getElementById('startup-notifications-toast');
                if(toastContainer) {
                    toastContainer.style.opacity = '0';
                    toastContainer.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => toastContainer.remove(), 500);
                }
            }, 10000);
        </script>
    @endif
@endauth
</body>
</html>
