document.addEventListener('DOMContentLoaded', function () {
    const nav = document.querySelector('[data-app-nav]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const collapseToggle = document.querySelector('[data-sidebar-collapse]');
    const collapseStorageKey = 'nexo-sidebar-collapsed';

    function setSidebarCollapsed(collapsed, persist) {
        if (window.innerWidth <= 980) return;
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (collapseToggle) {
            collapseToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            collapseToggle.setAttribute('aria-label', collapsed ? 'Mostrar menú completo' : 'Ocultar textos del menú');
            collapseToggle.title = collapsed ? 'Mostrar menú completo' : 'Mostrar solo iconos';
        }
        if (persist !== false) {
            localStorage.setItem(collapseStorageKey, collapsed ? '1' : '0');
        }
        if (collapsed) closeDrops();
    }

    function closeDrops(except) {
        document.querySelectorAll('[data-nav-drop]').forEach(function (drop) {
            if (drop !== except) {
                drop.classList.remove('is-open');
                const btn = drop.querySelector('[aria-expanded]');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function setSidebarOpen(open) {
        if (!nav) return;
        nav.classList.toggle('is-open', open);
        document.body.classList.toggle('sidebar-open', open);
        if (toggle) {
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (!open) closeDrops();
    }

    if (toggle && nav) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            setSidebarOpen(!nav.classList.contains('is-open'));
        });
    }

    if (collapseToggle && nav) {
        setSidebarCollapsed(localStorage.getItem(collapseStorageKey) === '1', false);
        collapseToggle.addEventListener('click', function () {
            setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setSidebarOpen(false);
        });
    }

    document.querySelectorAll('[data-nav-drop]').forEach(function (drop) {
        const trigger = drop.querySelector('.nav-drop-btn, .nav-user-btn, .notification-bell');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            if (trigger.classList.contains('notification-bell')) {
                e.preventDefault();
            }
            e.stopPropagation();
            if (window.innerWidth > 980 && document.body.classList.contains('sidebar-collapsed') && trigger.classList.contains('nav-drop-btn')) {
                setSidebarCollapsed(false);
            }
            const willOpen = !drop.classList.contains('is-open');
            closeDrops(drop);
            drop.classList.toggle('is-open', willOpen);
            if (trigger.hasAttribute('aria-expanded')) {
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-nav-drop]')) {
            closeDrops();
        }
        if (nav && toggle && window.innerWidth <= 980 && !e.target.closest('.app-sidebar') && !e.target.closest('[data-nav-toggle]')) {
            setSidebarOpen(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDrops();
            setSidebarOpen(false);
        }
    });
});
