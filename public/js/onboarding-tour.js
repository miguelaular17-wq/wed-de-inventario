(function () {
    if (!window.__TOUR__) return;

    const cfg = window.__TOUR__;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const STEPS = [
        {
            id: 0,
            page: '*',
            title: '¡Bienvenido!',
            text: 'Te guiaremos por las funciones principales de Inventario Multisede: ventas, requisiciones personalizadas y exportación de reportes.',
            center: true,
            icon: '👋',
        },
        {
            id: 1,
            page: '*',
            selector: '[data-tour="sede-badge"]',
            title: 'Tu sede',
            text: 'Aquí ves la sede con la que estás trabajando. Todos los cálculos y movimientos se hacen respecto a esta ubicación.',
            requireSede: true,
        },
        {
            id: 2,
            page: '*',
            selector: '[data-tour="nav-main"]',
            title: 'Menú principal',
            text: 'Desde aquí accedes a Ventas, Inventario y Exportar. Son las tres secciones que usarás día a día.',
            requireSede: true,
        },
        {
            id: 3,
            page: 'ventas.index',
            route: cfg.routes.ventas,
            selector: '[data-tour="ventas-stats"]',
            title: 'Resumen de ventas',
            text: 'Muestra cuántos productos ves con los filtros actuales y el total calculado para tu sede.',
            requireSede: true,
        },
        {
            id: 4,
            page: 'ventas.index',
            route: cfg.routes.ventas,
            selector: '[data-tour="ventas-filters"]',
            title: 'Filtros automáticos',
            text: 'Busca por código o nombre, filtra por categoría, acción o pronóstico. Los filtros se aplican solos al escribir o cambiar.',
            requireSede: true,
        },
        {
            id: 5,
            page: 'ventas.index',
            route: cfg.routes.ventas,
            selector: '[data-tour="ventas-table"]',
            title: 'Tabla de ventas',
            text: 'Cada fila muestra stock, ventas recientes y la acción sugerida. Naranja = «Hacer requisición», verde = hay existencia en otra sede.',
            requireSede: true,
        },
        {
            id: 6,
            page: 'inventario.index',
            route: cfg.routes.inventario,
            selector: '[data-tour="inventario-filters"]',
            title: 'Inventario personalizado',
            text: 'Aquí pides productos manualmente cuando hay stock en otras sucursales. Usa los filtros para encontrar artículos rápido.',
            requireSede: true,
        },
        {
            id: 7,
            page: 'inventario.index',
            route: cfg.routes.inventario,
            selector: '[data-tour="inventario-grid"]',
            title: 'Tarjetas de producto',
            text: 'Haz clic en una tarjeta para abrir el diálogo de requisición manual: eliges sede origen, cantidad y confirmas. El stock se actualiza al instante.',
            requireSede: true,
        },
        {
            id: 8,
            page: 'requisicion.form',
            route: cfg.routes.export,
            selector: '[data-tour="export-type"]',
            title: 'Exportar reporte',
            text: 'Elige entre requisición automática (desde ventas) o personalizada (las que registraste manualmente en Inventario).',
            requireSede: true,
        },
        {
            id: 9,
            page: 'requisicion.form',
            route: cfg.routes.export,
            selector: '[data-tour="export-filters"]',
            title: 'Filtros de exportación',
            text: 'Define sede origen, categoría y exclusiones. Puedes excluir categorías enteras o productos individuales antes de generar el CSV.',
            requireSede: true,
        },
        {
            id: 10,
            page: 'requisicion.form',
            route: cfg.routes.export,
            selector: '[data-tour="export-actions"]',
            title: 'Generar CSV',
            text: 'Al exportar requisición automática, el sistema resta stock en origen y suma en tu sede. La personalizada solo descarga el archivo.',
            requireSede: true,
        },
        {
            id: 11,
            page: '*',
            title: '¡Listo!',
            text: 'Ya conoces lo esencial. Puedes volver a ver este tutorial cuando quieras con el botón «Ayuda» del menú superior.',
            center: true,
            icon: '✓',
            finish: true,
        },
    ];

    const ADMIN_STEPS = [
        {
            id: 100,
            page: 'admin.dashboard',
            route: cfg.routes.admin,
            selector: '[data-tour="admin-dashboard"]',
            title: 'Panel de administración',
            text: 'Como admin puedes importar el Excel multisede, ver movimientos de todas las sedes y gestionar usuarios.',
            adminOnly: true,
        },
        {
            id: 101,
            page: 'admin.dashboard',
            route: cfg.routes.admin,
            selector: '[data-tour="admin-import-btn"]',
            title: 'Importar Excel',
            text: 'Sube el archivo ExelMultiSede para actualizar productos, stock y ventas de todas las sucursales.',
            adminOnly: true,
        },
        {
            id: 102,
            page: '*',
            selector: '[data-tour="admin-movimientos"]',
            title: 'Historial de movimientos',
            text: 'Audita cada requisición: quién la hizo, cuántas unidades y entre qué sedes se movió el stock.',
            adminOnly: true,
        },
    ];

    function allSteps() {
        const steps = [...STEPS];
        if (cfg.isAdmin) {
            steps.splice(2, 0, ...ADMIN_STEPS);
        }
        return steps.map((s, i) => ({ ...s, index: i }));
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        });
    }

    function getVisibleSteps() {
        return allSteps().filter(function (s) {
            if (s.adminOnly && !cfg.isAdmin) return false;
            if (s.requireSede && !cfg.hasSede) return false;
            return true;
        });
    }

    class Tour {
        constructor() {
            this.steps = getVisibleSteps();
            this.current = Math.max(0, Math.min(cfg.startStep, this.steps.length - 1));
            this.overlay = null;
            this.spotlight = null;
            this.popover = null;
        }

        start() {
            if (cfg.startStep === -1) return;
            this.buildDom();
            this.showStep(this.current);
        }

        buildDom() {
            this.overlay = document.createElement('div');
            this.overlay.className = 'tour-overlay';
            this.overlay.innerHTML = '<div class="tour-backdrop"></div>';
            this.spotlight = document.createElement('div');
            this.spotlight.className = 'tour-spotlight';
            this.spotlight.style.display = 'none';
            this.popover = document.createElement('div');
            this.popover.className = 'tour-popover';
            this.popover.setAttribute('role', 'dialog');
            this.popover.setAttribute('aria-modal', 'true');
            this.overlay.appendChild(this.spotlight);
            this.overlay.appendChild(this.popover);
            document.body.appendChild(this.overlay);
            document.body.style.overflow = 'hidden';

            this.overlay.querySelector('.tour-backdrop').addEventListener('click', () => {});
        }

        destroy() {
            document.body.style.overflow = '';
            this.overlay?.remove();
        }

        showStep(index) {
            const step = this.steps[index];
            if (!step) {
                this.complete();
                return;
            }

            if (step.page !== '*' && step.page !== cfg.currentPage) {
                this.showNavigateStep(step, index);
                return;
            }

            if (step.center) {
                this.spotlight.style.display = 'none';
                this.renderPopover(step, index, null);
                this.centerPopover();
                return;
            }

            const el = document.querySelector(step.selector);
            if (!el) {
                if (step.requireSede && !cfg.hasSede) {
                    this.go(index + 1);
                    return;
                }
                this.renderPopover(step, index, null, 'Continúa en la siguiente pantalla con el botón «Ir ahora».');
                this.centerPopover();
                return;
            }

            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
            setTimeout(() => this.highlight(el, step, index), 300);
        }

        highlight(el, step, index) {
            const rect = el.getBoundingClientRect();
            const pad = 8;
            this.spotlight.style.display = 'block';
            this.spotlight.style.top = (rect.top - pad) + 'px';
            this.spotlight.style.left = (rect.left - pad) + 'px';
            this.spotlight.style.width = (rect.width + pad * 2) + 'px';
            this.spotlight.style.height = (rect.height + pad * 2) + 'px';
            this.renderPopover(step, index, el);
            this.positionPopover(rect);
        }

        showNavigateStep(step, index) {
            this.spotlight.style.display = 'none';
            const navStep = {
                ...step,
                title: 'Siguiente: ' + step.title,
                text: 'Para continuar el tutorial, ve a la sección indicada.',
                _navigate: true,
            };
            this.renderPopover(navStep, index, null);
            this.centerPopover();
        }

        renderPopover(step, index, targetEl, extra) {
            const total = this.steps.length;
            const isFirst = index === 0;
            const isLast = step.finish || index === total - 1;
            const dots = this.steps.map((_, i) =>
                '<span class="' + (i === index ? 'active' : '') + '"></span>'
            ).join('');

            let iconHtml = step.icon
                ? '<div class="tour-welcome-icon">' + step.icon + '</div>'
                : '';

            this.popover.innerHTML =
                '<div class="tour-popover-header">' +
                    '<span class="tour-popover-step">Paso ' + (index + 1) + ' de ' + total + '</span>' +
                    '<button type="button" class="tour-popover-skip" data-action="skip">Omitir tutorial</button>' +
                '</div>' +
                '<div class="tour-popover-body">' +
                    iconHtml +
                    '<h3>' + step.title + '</h3>' +
                    '<p>' + step.text + (extra ? ' ' + extra : '') + '</p>' +
                '</div>' +
                '<div class="tour-popover-footer">' +
                    '<div class="tour-progress-dots">' + dots + '</div>' +
                    '<div class="tour-popover-actions">' +
                        (isFirst ? '' : '<button type="button" class="tour-btn tour-btn-secondary" data-action="prev">Anterior</button>') +
                        (step._navigate
                            ? '<button type="button" class="tour-btn tour-btn-primary" data-action="go">Ir ahora →</button>'
                            : '<button type="button" class="tour-btn tour-btn-primary" data-action="next">' + (isLast ? 'Finalizar' : 'Siguiente') + '</button>') +
                    '</div>' +
                '</div>';

            this.popover.querySelector('[data-action="skip"]').onclick = () => this.complete();
            this.popover.querySelector('[data-action="prev"]')?.addEventListener('click', () => this.go(index - 1));
            this.popover.querySelector('[data-action="next"]')?.addEventListener('click', () => this.go(index + 1));
            this.popover.querySelector('[data-action="go"]')?.addEventListener('click', () => {
                window.location.href = step.route + '?tour=1';
            });
        }

        positionPopover(rect) {
            const pop = this.popover;
            const margin = 16;
            pop.style.top = '';
            pop.style.left = '';
            pop.style.bottom = '';
            pop.style.right = '';
            pop.style.transform = '';

            const popRect = pop.getBoundingClientRect();
            let top = rect.bottom + margin;
            let left = rect.left;

            if (top + popRect.height > window.innerHeight - margin) {
                top = rect.top - popRect.height - margin;
            }
            if (left + popRect.width > window.innerWidth - margin) {
                left = window.innerWidth - popRect.width - margin;
            }
            if (left < margin) left = margin;
            if (top < margin) top = margin;

            pop.style.top = top + 'px';
            pop.style.left = left + 'px';
        }

        centerPopover() {
            const pop = this.popover;
            pop.style.top = '50%';
            pop.style.left = '50%';
            pop.style.transform = 'translate(-50%, -50%)';
        }

        go(index) {
            if (index >= this.steps.length) {
                this.complete();
                return;
            }
            this.current = index;
            post(cfg.advanceUrl, { step: index }).catch(() => {});
            this.showStep(index);
        }

        complete() {
            post(cfg.completeUrl, {}).catch(() => {});
            this.destroy();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (cfg.startStep === -1 && !cfg.forceStart) return;
        const tour = new Tour();
        setTimeout(function () { tour.start(); }, 600);
    });
})();
