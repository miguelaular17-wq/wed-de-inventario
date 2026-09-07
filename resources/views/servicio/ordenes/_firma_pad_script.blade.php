<script>
(function () {
    function bindPad(root) {
        const canvas = root.querySelector('canvas');
        const input = root.querySelector('input[type="hidden"]');
        const clearBtn = root.querySelector('[data-firma-clear]');
        if (!canvas || !input) return;
        const ctx = canvas.getContext('2d');
        let drawing = false;
        function pos(e) {
            const r = canvas.getBoundingClientRect();
            const src = e.touches ? e.touches[0] : e;
            return { x: (src.clientX - r.left) * (canvas.width / r.width), y: (src.clientY - r.top) * (canvas.height / r.height) };
        }
        function start(e) { drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function move(e) {
            if (!drawing) return;
            const p = pos(e);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#0f172a';
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            e.preventDefault();
        }
        function end() {
            if (!drawing) return;
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        }
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
        clearBtn?.addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            input.value = '';
        });
        const form = canvas.closest('form');
        form?.addEventListener('submit', function () {
            if (input.value === '' && ctx.getImageData(0, 0, canvas.width, canvas.height).data.some((v, i) => i % 4 === 3 && v > 0)) {
                input.value = canvas.toDataURL('image/png');
            }
        });
    }
    document.querySelectorAll('[data-firma-pad]').forEach(bindPad);
})();
</script>
