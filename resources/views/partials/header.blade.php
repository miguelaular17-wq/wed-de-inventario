@php
    $u = auth()->user();
    $u->loadMissing('extraPermissions');
    $nav = [];

    $link = function (string $label, string $url, bool $active, ?string $tour = null, bool $emphasis = false) {
        return compact('label', 'url', 'active', 'tour', 'emphasis') + ['type' => 'link'];
    };
    $drop = function (string $label, array $items) {
        $active = collect($items)->contains(fn ($item) => $item['active']);
        return ['type' => 'drop', 'label' => $label, 'active' => $active, 'items' => $items];
    };

    $sedeItems = [];
    if ($u->hasAccessToSedeViews() && session('sede_local')) {
        $sedeItems[] = $link('Ventas', route('ventas.index'), request()->routeIs('ventas.index'), 'nav-ventas');
        $sedeItems[] = $link('Mayor Demanda', route('ventas.mayor_demanda'), request()->routeIs('ventas.mayor_demanda'));
        $sedeItems[] = $link('Inventario', route('inventario.index'), request()->routeIs('inventario.*'), 'nav-inventario');
        $sedeItems[] = $link('Exportar', route('requisicion.form'), request()->routeIs('requisicion.*'), 'nav-export');
        if (! in_array($u->role, ['supervisor', 'telefonia'], true)) {
            $sedeItems[] = $link('Catálogo Visual', route('catalogo.index'), request()->routeIs('catalogo.*'), 'nav-catalogo', true);
        }
    }
    $isSedeStaff = in_array($u->role, ['supervisor', 'telefonia', 'sede'], true);

    $gerencialItems = [];
    if ($u->canAccess('gerencial')) {
        $gerencialItems[] = $link('Dashboard', route('gerencial.dashboard'), request()->routeIs('gerencial.dashboard'));
    }
    if ($u->canAccess('gerencial.rentabilidad')) {
        $gerencialItems[] = $link('Rentabilidad', route('gerencial.rentabilidad'), request()->routeIs('gerencial.rentabilidad'));
    }
    if ($u->canAccess('gerencial.devoluciones')) {
        $gerencialItems[] = $link('Devoluciones', route('gerencial.devoluciones'), request()->routeIs('gerencial.devoluciones'));
    }
    if ($u->canAccess('gerencial.valorizados')) {
        $gerencialItems[] = $link('Inventario', route('gerencial.valorizados'), request()->routeIs('gerencial.valorizados'));
    }
    if ($u->canAccess('gerencial.ajustes')) {
        $gerencialItems[] = $link('Ajustes', route('gerencial.ajustes'), request()->routeIs('gerencial.ajustes'));
    }

    if ($u->role === 'admin') {
        if ($gerencialItems) {
            $nav[] = count($gerencialItems) === 1 ? $gerencialItems[0] : $drop('Gerencial', $gerencialItems);
        }
        $nav[] = $link('Dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard'), 'admin-dashboard');
        $nav[] = $drop('Sistema', [
            $link('Movimientos', route('admin.movimientos.index'), request()->routeIs('admin.movimientos.*'), 'admin-movimientos'),
            $link('Productos', route('admin.productos.index'), request()->routeIs('admin.productos.*')),
            $link('Sync Logs', route('admin.sync_logs.index'), request()->routeIs('admin.sync_logs.*')),
            $link('Usuarios', route('admin.users.index'), request()->routeIs('admin.users.*')),
        ]);
    }

    if ($u->isGerente()) {
        $nav[] = $link('Gerencial', route('admin.dashboard'), request()->routeIs('admin.dashboard'));
        if ($gerencialItems) {
            $nav[] = count($gerencialItems) === 1 ? $gerencialItems[0] : $drop('Indicadores', $gerencialItems);
        }
    } elseif ($u->role !== 'admin' && $gerencialItems) {
        $nav[] = count($gerencialItems) === 1 ? $gerencialItems[0] : $drop('Gerencial', $gerencialItems);
    }

    if ($sedeItems) {
        if ($isSedeStaff) {
            foreach ($sedeItems as $item) {
                $nav[] = $item;
            }
        } else {
            $nav[] = $drop('Operación', $sedeItems);
        }
    }

    if ($u->isGerente()) {
        $nav[] = $link('Compras', route('comprador.dashboard'), request()->routeIs('comprador.*'));
        $nav[] = $drop('Finanzas', [
            $link('Flujo de Caja', route('finanzas.flujo_caja'), request()->routeIs('finanzas.flujo_caja*')),
            $link('Gastos Fijos', route('finanzas.gastos_fijos'), request()->routeIs('finanzas.gastos_fijos', 'finanzas.gastos_fijos.*')),
            $link('Conciliaciones', route('finanzas.conciliaciones'), request()->routeIs('finanzas.conciliaciones', 'finanzas.conciliaciones.*')),
            $link('Tesorería', route('tesoreria.dashboard'), request()->routeIs('tesoreria.*')),
        ]);
        $nav[] = $drop('Cobranza', [
            $link('Cobranza', route('cobranza.index'), request()->routeIs('cobranza.*')),
            $link('Contratos', route('contratos.index'), request()->routeIs('contratos.*')),
            $link('Patrimonial', route('patrimonial.dashboard'), request()->routeIs('patrimonial.*')),
        ]);
    } elseif ($u->role !== 'admin') {
        if ($u->canAccess('compras')) {
            $nav[] = $link(
                $u->isMarketing() ? 'Marketing' : 'Compras',
                route('comprador.dashboard'),
                request()->routeIs('comprador.*')
            );
        }
        if ($u->canAccess('finanzas.ver')) {
            $nav[] = $link('Flujo de Caja', route('finanzas.flujo_caja'), request()->routeIs('finanzas.flujo_caja*'));
            $nav[] = $link('Gastos Fijos', route('finanzas.gastos_fijos'), request()->routeIs('finanzas.gastos_fijos', 'finanzas.gastos_fijos.*'));
        }
        if ($u->canAccess('conciliaciones')) {
            $nav[] = $link('Conciliaciones', route('finanzas.conciliaciones'), request()->routeIs('finanzas.conciliaciones', 'finanzas.conciliaciones.*'));
        }
        if ($u->canAccess('cobranza')) {
            $nav[] = $link('Cobranza', route('cobranza.index'), request()->routeIs('cobranza.*'));
        }
        if ($u->canAccess('contratos')) {
            $nav[] = $link('Contratos', route('contratos.index'), request()->routeIs('contratos.*'));
        }
        if ($u->canAccess('patrimonial')) {
            $nav[] = $link('Patrimonial', route('patrimonial.dashboard'), request()->routeIs('patrimonial.*'));
        }
        if ($u->canAccess('tesoreria')) {
            $nav[] = $link('Tesorería', route('tesoreria.dashboard'), request()->routeIs('tesoreria.*'));
        }
    }

    if ($u->role !== 'admin' && $u->canAccess('nomina')) {
        $nav[] = $drop('Nómina', [
            $link('Períodos', route('nomina.periodos.index'), request()->routeIs('nomina.periodos.*')),
            $link('Comisiones', route('nomina.comisiones.index'), request()->routeIs('nomina.comisiones.*')),
            $link('Empleados', route('nomina.empleados.index'), request()->routeIs('nomina.empleados.*')),
            $link('Organigrama', route('nomina.organizacion'), request()->routeIs('nomina.organizacion')),
            $link('Sedes y áreas', route('nomina.sedes.index'), request()->routeIs('nomina.sedes.*')),
            $link('Cargos', route('nomina.cargos.index'), request()->routeIs('nomina.cargos.*')),
            $link('Configuración', route('nomina.configuracion.index'), request()->routeIs('nomina.configuracion.*')),
        ]);
    }

    $roleLabels = [
        'admin' => 'Admin',
        'gerente' => 'Gerente',
        'supervisor' => 'Supervisor',
        'telefonia' => 'Telefonía',
        'comprador' => 'Comprador',
        'sede' => 'Sede',
        'vendedor' => 'Vendedor',
        'marketing' => 'Marketing',
        'finanzas' => 'Finanzas',
        'cobranza' => 'Cobranza',
        'contabilidad' => 'Contabilidad',
        'auditor' => 'Auditor',
        'tesoreria' => 'Tesorería',
        'rrhh' => 'RRHH',
    ];
    $roleLabel = $roleLabels[$u->role] ?? ucfirst($u->role);
    $nameParts = preg_split('/\s+/', trim($u->name)) ?: [];
    $initials = strtoupper(mb_substr($nameParts[0] ?? 'U', 0, 1) . mb_substr($nameParts[1] ?? '', 0, 1));

    $unreadCount = $u->notifications()->unread()->count();
    $latestNotifications = $u->notifications()->with('sender')->latest()->take(5)->get();
@endphp

<header class="app-header">
    <div class="app-header-inner">
        <div class="app-brand">
            <a href="{{ url('/') }}" class="app-brand-link">
                <img src="{{ asset('logo.png') }}" alt="" class="app-logo" width="32" height="32">
                <strong>Nexo <span class="app-brand-pd">PD</span></strong>
            </a>
            @if(session('sede_local') && $u->hasAccessToSedeViews())
                <span class="badge app-sede-badge" data-tour="sede-badge">{{ session('sede_local') }}</span>
            @endif
            @if($u->isGerente())
                <span class="badge app-role-gerente">Gerente</span>
            @elseif($u->role === 'admin')
                <span class="badge">Admin</span>
                <span class="badge app-clock" id="server-clock" title="Hora del servidor (Caracas)">
                    {{ \Carbon\Carbon::now()->format('d/m/Y h:i:s A') }}
                </span>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        let serverTime = new Date("{{ \Carbon\Carbon::now()->format('Y/m/d H:i:s') }}");
                        const clock = document.getElementById('server-clock');
                        if (!clock) return;
                        setInterval(() => {
                            serverTime.setSeconds(serverTime.getSeconds() + 1);
                            const d = String(serverTime.getDate()).padStart(2, '0');
                            const m = String(serverTime.getMonth() + 1).padStart(2, '0');
                            const y = serverTime.getFullYear();
                            let hr = serverTime.getHours();
                            const min = String(serverTime.getMinutes()).padStart(2, '0');
                            const sec = String(serverTime.getSeconds()).padStart(2, '0');
                            const ampm = hr >= 12 ? 'PM' : 'AM';
                            hr = hr % 12 || 12;
                            clock.innerText = `${d}/${m}/${y} ${String(hr).padStart(2, '0')}:${min}:${sec} ${ampm}`;
                        }, 1000);
                    });
                </script>
            @endif
        </div>

        <nav class="app-nav" data-tour="nav-main" data-app-nav>
            @foreach($nav as $item)
                @if($item['type'] === 'link')
                    <a
                        href="{{ $item['url'] }}"
                        class="nav-link {{ $item['active'] ? 'active' : '' }} {{ !empty($item['emphasis']) ? 'nav-link-emphasis' : '' }}"
                        @if(!empty($item['tour'])) data-tour="{{ $item['tour'] }}" @endif
                    >{{ $item['label'] }}</a>
                @else
                    <div class="nav-drop {{ $item['active'] ? 'is-current' : '' }}" data-nav-drop>
                        <button type="button" class="nav-link nav-drop-btn {{ $item['active'] ? 'active' : '' }}" aria-expanded="false">
                            {{ $item['label'] }}
                            <svg class="nav-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 7.5 10 12l4.5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="nav-drop-menu">
                            @foreach($item['items'] as $sub)
                                <a
                                    href="{{ $sub['url'] }}"
                                    class="{{ $sub['active'] ? 'active' : '' }}"
                                    @if(!empty($sub['tour'])) data-tour="{{ $sub['tour'] }}" @endif
                                >{{ $sub['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="app-header-actions">
            @if($u->isComprador())
                @if(session('sede_local'))
                    <form method="POST" action="{{ route('sede.change') }}" class="app-inline-form">
                        @csrf
                        <button type="submit" class="btn secondary app-sede-btn">{{ session('sede_local') }} · Cambiar</button>
                    </form>
                @else
                    <a href="{{ route('sede.select') }}" class="btn secondary app-sede-btn">Seleccionar sede</a>
                @endif
            @elseif($u->hasAccessToSedeViews() && session('sede_local'))
                @if(in_array($u->role, ['supervisor', 'telefonia'], true))
                    <span class="btn secondary app-sede-btn app-sede-locked">{{ session('sede_local') }}</span>
                @else
                    <form method="POST" action="{{ route('sede.change') }}" class="app-inline-form">
                        @csrf
                        <button type="submit" class="btn secondary app-sede-btn">Cambiar sede</button>
                    </form>
                @endif
            @endif

            <div class="notification-dropdown-container nav-drop" data-nav-drop>
                <a href="{{ route('notifications.index') }}" class="notification-bell nav-icon-btn" title="Notificaciones">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    @if($unreadCount > 0)
                        <span class="nav-badge-count">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </a>
                <div class="notification-dropdown nav-drop-menu nav-drop-menu-right nav-drop-wide">
                    <div class="nav-drop-head">
                        <strong>Notificaciones</strong>
                        <a href="{{ route('notifications.index') }}">Ver todas</a>
                    </div>
                    <div class="nav-notify-list">
                        @forelse($latestNotifications as $notification)
                            <div class="nav-notify-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                <div class="nav-notify-meta">
                                    <strong>{{ $notification->sender ? $notification->sender->name : 'Sistema' }}</strong>
                                    <span>{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                <p>{{ $notification->message }}</p>
                            </div>
                        @empty
                            <div class="nav-notify-empty">No hay notificaciones</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="nav-drop nav-drop-user" data-nav-drop>
                <button type="button" class="nav-user-btn" aria-expanded="false" title="{{ $u->name }}">
                    <span class="nav-user-avatar">{{ $initials }}</span>
                    <span class="nav-user-copy">
                        <span class="nav-user-name">{{ $u->name }}</span>
                        <span class="nav-user-role">{{ $roleLabel }}</span>
                    </span>
                    <svg class="nav-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 7.5 10 12l4.5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="nav-drop-menu nav-drop-menu-right">
                    <div class="nav-user-meta">
                        <strong>{{ $u->name }}</strong>
                        <span>{{ $roleLabel }}</span>
                    </div>
                    @if(config('inventario.tutorial_enabled'))
                        <form method="POST" action="{{ route('tutorial.restart') }}">
                            @csrf
                            <button type="submit" class="nav-menu-btn">Tutorial</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-logout-btn">Salir</button>
                    </form>
                </div>
            </div>

            <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-label="Abrir menú">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>
