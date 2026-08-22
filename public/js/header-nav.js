document.addEventListener('DOMContentLoaded', function () {
    const nav = document.querySelector('[data-app-nav]');
    const toggle = document.querySelector('[data-nav-toggle]');

    function closeDrops(except) {
        document.querySelectorAll('[data-nav-drop]').forEach(function (drop) {
            if (drop !== except) {
                drop.classList.remove('is-open');
                const btn = drop.querySelector('[aria-expanded]');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (toggle && nav) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = !nav.classList.contains('is-open');
            nav.classList.toggle('is-open', open);
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (!open) closeDrops();
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
        if (nav && toggle && !e.target.closest('.app-header')) {
            nav.classList.remove('is-open');
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDrops();
            if (nav && toggle) {
                nav.classList.remove('is-open');
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
});
