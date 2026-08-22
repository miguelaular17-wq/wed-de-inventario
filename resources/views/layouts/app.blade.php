<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <title>@yield('title', 'Nexo PD')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}">
    @if(config('inventario.tutorial_enabled'))
    <link rel="stylesheet" href="/css/onboarding-tour.css">
    @endif
    @stack('head')
    <style>
        /* Pagination Styles */
        .pagination { display: flex; flex-direction: row; flex-wrap: wrap; padding-left: 0; list-style: none; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-item { display: inline-block; }
        .page-item .page-link { position: relative; display: flex; align-items: center; justify-content: center; padding: 8px 14px; color: #2563eb; background-color: #fff; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all 0.2s; min-width: 38px; }
        .page-item.active .page-link { z-index: 3; color: #fff; background-color: #2563eb; border-color: #2563eb; }
        .page-item.disabled .page-link, .page-item.disabled span.page-link { color: #64748b; pointer-events: none; background-color: #f8fafc; border-color: #e2e8f0; }
        .page-item:not(.active):not(.disabled) .page-link:hover { background-color: #f1f5f9; }
    </style>
</head>
<body>
@auth
    @include('partials.header')
@endauth

<main>
    @if (!request()->routeIs('login', 'register'))
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
    @endif
    @yield('content')
</main>

<script src="/js/sync-poll.js"></script>
<script src="/js/auto-filters.js"></script>
<script src="/js/header-nav.js?v={{ filemtime(public_path('js/header-nav.js')) }}"></script>
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

<script>
    // Keep-alive ping: sends a lightweight request to the server every 10 minutes
    // to prevent Render's free tier from going to sleep while a user has a tab open.
    setInterval(function() {
        fetch('/login').then(function(r) {
            console.log('Keep-alive ping sent:', r.status);
        }).catch(function(err) {
            console.error('Keep-alive ping failed:', err);
        });
    }, 600000); // 10 minutes (600,000 ms)
</script>
<script>
    // Global formatting and parsing for numbers with thousand separators
    window.parseLocalNumber = function(val) {
        if (typeof val === 'number') return val;
        if (!val) return 0;
        val = val.toString().trim();
        if (val === '') return 0;

        // Caso 1: tiene punto Y coma → punto=miles, coma=decimal  (ej: "74.440,00" → 74440)
        if (val.includes('.') && val.includes(',')) {
            return parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
        }

        // Caso 2: solo coma, sin punto → coma=decimal (ej: "744,4" → 744.4)
        if (val.includes(',') && !val.includes('.')) {
            return parseFloat(val.replace(',', '.')) || 0;
        }

        // Caso 3: solo punto, sin coma → distinguir si es decimal o miles
        if (val.includes('.') && !val.includes(',')) {
            // Si el punto está seguido de exactamente 3 dígitos al final → es separador de miles
            // Ej: "74.440" → 74440 | "1.000" → 1000
            // Si no → es decimal. Ej: "744.4" → 744.4 | "744.40" → 744.40
            if (/\.\d{3}$/.test(val)) {
                return parseFloat(val.replace(/\./g, '')) || 0;
            } else {
                return parseFloat(val) || 0;
            }
        }

        return parseFloat(val) || 0;
    };
    
    window.formatLocalNumber = function(val) {
        let num = parseFloat(val) || 0;
        return num.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };

    // Attach global focus/blur listeners for all formatted inputs
    document.addEventListener('focus', function(e) {
        if (e.target.matches('input.editable-input, input.report-input, input.currency-input, input[data-field="bs_tc"], input[data-field="bs_disponibles"], input[data-field="usd_tc"], input[data-field="usd_disp"]')) {
            if (e.target.type === 'text') {
                let val = window.parseLocalNumber(e.target.value);
                // On focus, show decimal as comma without thousands separator to ease editing
                e.target.value = val === 0 ? '' : val.toFixed(2).replace('.', ',');
            }
        }
    }, true);

    document.addEventListener('blur', function(e) {
        if (e.target.matches('input.editable-input, input.report-input, input.currency-input, input[data-field="bs_tc"], input[data-field="bs_disponibles"], input[data-field="usd_tc"], input[data-field="usd_disp"]')) {
            if (e.target.type === 'text') {
                let val = window.parseLocalNumber(e.target.value);
                e.target.value = window.formatLocalNumber(val);
            }
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input.editable-input, input.report-input, input.currency-input, input[data-field="bs_tc"], input[data-field="bs_disponibles"], input[data-field="usd_tc"], input[data-field="usd_disp"]').forEach(input => {
            if(input.type === 'text') {
                let val = window.parseLocalNumber(input.value);
                input.value = window.formatLocalNumber(val);
            }
        });
    });
</script>
</body>
</html>
