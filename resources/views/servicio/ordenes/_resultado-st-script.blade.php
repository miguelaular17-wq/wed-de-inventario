<style>
    .rs-resultado {
        border: 0;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        cursor: pointer;
        max-width: 220px;
    }
    .rs-resultado option { background: #fff; color: #1e293b; }
    .rs-resultado:disabled { opacity: .6; cursor: wait; }
    .rs-aviso { font-size: .7rem; color: #64748b; margin-top: 3px; max-width: 220px; }
    tr.rs-movida td { opacity: .45; transition: opacity .3s; }
    tr.rs-movida td:has(.rs-resultado) { opacity: 1; }
</style>
<script>
    (function () {
        const colores = @json(\App\Models\StOrden::COLORES_RESULTADO_ST);
        const etiquetas = @json(\App\Models\StOrden::RESULTADOS_ST);
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        const pintar = (select, resultado) => {
            const [fondo, texto] = colores[resultado] || ['#f1f5f9', '#475569'];
            select.style.background = fondo;
            select.style.color = texto;
        };

        document.querySelectorAll('.rs-resultado').forEach((select) => {
            select.addEventListener('change', async () => {
                const anterior = select.dataset.actual || '';
                const resultado = select.value || null;
                pintar(select, resultado);
                select.disabled = true;
                const aviso = select.parentElement.querySelector('.rs-aviso');
                try {
                    const resp = await fetch(select.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ resultado }),
                    });
                    if (!resp.ok) throw new Error();
                    select.dataset.actual = select.value;
                    const enMarca = (select.dataset.modo || 'marca') === 'marca';
                    const sale = enMarca ? resultado !== null : resultado === null;
                    select.closest('tr')?.classList.toggle('rs-movida', sale);
                    if (aviso) {
                        aviso.textContent = !sale
                            ? ''
                            : (enMarca
                                ? (etiquetas[resultado] || '') + ' · pasa a ST resueltos al recargar.'
                                : 'Vuelve al reporte por marca al recargar.');
                    }
                } catch (e) {
                    select.value = anterior;
                    pintar(select, anterior);
                    alert('No se pudo guardar. Intenta de nuevo.');
                } finally {
                    select.disabled = false;
                }
            });
        });
    })();
</script>
