document.addEventListener('DOMContentLoaded', function () {
    const configs = [];

    function applyFilterMeta(form, container) {
        const metaEl = container.querySelector('#ajax-filter-meta');
        if (!metaEl) {
            return;
        }

        let meta;
        try {
            meta = JSON.parse(metaEl.textContent);
        } catch (e) {
            return;
        }

        if (Array.isArray(meta.subcategorias)) {
            const sub = form.querySelector('[name="subcategoria"]');
            if (sub) {
                const selected = meta.selectedSubcategoria || 'Ninguno';
                sub.innerHTML = '<option value="Ninguno">Todas</option>';
                meta.subcategorias.forEach(function (value) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    if (value === selected) {
                        opt.selected = true;
                    }
                    sub.appendChild(opt);
                });
                const cat = form.querySelector('[name="categoria"]');
                sub.disabled = cat && cat.value === 'Ninguno';
            }
        }

        if (typeof meta.reqFiltersVisible === 'boolean') {
            form.querySelectorAll('.req-filter').forEach(function (el) {
                el.style.display = meta.reqFiltersVisible ? '' : 'none';
            });
        }
    }

    function bindPagination(config, container) {
        container.querySelectorAll('.pagination-bar a.pagination-btn').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                fetchPartial(config, new URL(link.href), false);
            });
        });
    }

    async function fetchPartial(config, url, resetPage) {
        const target = document.querySelector(config.target);
        if (!target) {
            config.form.submit();
            return;
        }

        if (resetPage) {
            url.searchParams.delete('page');
        }

        target.classList.add('is-loading');

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Partial': 'content',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                window.location.href = url.toString();
                return;
            }

            target.innerHTML = await response.text();
            history.replaceState(null, '', url.toString());
            applyFilterMeta(config.form, target);
            bindPagination(config, target);
            document.dispatchEvent(new CustomEvent('content:refresh', {
                detail: { target: config.target },
            }));
        } catch (e) {
            window.location.href = url.toString();
        } finally {
            target.classList.remove('is-loading');
        }
    }

    function buildUrl(form, resetPage) {
        const url = new URL(form.action || window.location.href, window.location.origin);
        const params = new URLSearchParams(new FormData(form));
        if (resetPage) {
            params.delete('page');
        }
        url.search = params.toString();
        return url;
    }

    document.querySelectorAll('form[data-auto-filter]').forEach(function (form) {
        const defaultDelay = parseInt(form.dataset.autoFilterDelay || '400', 10);
        const ajaxTarget = form.dataset.autoFilterTarget || '';
        let timer = null;

        const config = {
            form: form,
            target: ajaxTarget,
            delay: defaultDelay,
        };
        configs.push(config);

        function submit(delay, resetPage) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (ajaxTarget) {
                    fetchPartial(config, buildUrl(form, resetPage !== false), resetPage !== false);
                    return;
                }
                form.submit();
            }, delay ?? defaultDelay);
        }

        form.querySelectorAll('select:not([multiple]), input[type="checkbox"], input[type="radio"], input[type="date"]').forEach(function (el) {
            el.addEventListener('change', function () {
                submit(defaultDelay, true);
            });
        });

        form.querySelectorAll('select[multiple]').forEach(function (el) {
            const multiDelay = parseInt(el.dataset.autoFilterDelay || '900', 10);
            el.addEventListener('change', function () {
                submit(multiDelay, true);
            });
        });

        form.querySelectorAll('input[type="search"], input[type="text"], input[type="number"]').forEach(function (el) {
            el.addEventListener('input', function () {
                submit(defaultDelay, true);
            });
        });

        if (ajaxTarget) {
            const target = document.querySelector(ajaxTarget);
            if (target) {
                bindPagination(config, target);
            }
        }
    });

    window.AutoFilter = {
        rebind(targetSelector) {
            const config = configs.find(function (entry) {
                return entry.target === targetSelector;
            });
            const target = document.querySelector(targetSelector);
            if (!config || !target) {
                return;
            }
            applyFilterMeta(config.form, target);
            bindPagination(config, target);
        },
    };
});
