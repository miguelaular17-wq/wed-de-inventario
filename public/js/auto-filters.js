document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-auto-filter]').forEach(function (form) {
        const defaultDelay = parseInt(form.dataset.autoFilterDelay || '400', 10);
        let timer = null;

        function submit(delay) {
            clearTimeout(timer);
            timer = setTimeout(function () { form.submit(); }, delay ?? defaultDelay);
        }

        form.querySelectorAll('select:not([multiple]), input[type="checkbox"], input[type="radio"], input[type="date"]').forEach(function (el) {
            el.addEventListener('change', function () {
                submit(defaultDelay);
            });
        });

        form.querySelectorAll('select[multiple]').forEach(function (el) {
            const multiDelay = parseInt(el.dataset.autoFilterDelay || '900', 10);
            el.addEventListener('change', function () {
                submit(multiDelay);
            });
        });

        form.querySelectorAll('input[type="search"], input[type="text"], input[type="number"]').forEach(function (el) {
            el.addEventListener('input', function () {
                submit(defaultDelay);
            });
        });
    });
});
