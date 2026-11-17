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
    .rs-aviso { font-size: .72rem; color: #15803d; font-weight: 600; margin-top: 3px; max-width: 220px; }
    #rs-dialog-tecnico { border: 0; border-radius: 12px; padding: 20px 22px; width: min(380px, 92vw); box-shadow: 0 20px 50px rgba(15, 23, 42, .25); }
    #rs-dialog-tecnico::backdrop { background: rgba(15, 23, 42, .4); }
    #rs-dialog-tecnico h3 { margin: 0 0 4px; font-size: 1rem; }
    #rs-dialog-tecnico select { width: 100%; margin-top: 10px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .9rem; }
    #rs-dialog-tecnico .rs-botones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
    tr.rs-movida td { opacity: .45; transition: opacity .3s; }
    tr.rs-movida td:has(.rs-resultado) { opacity: 1; }
    .rs-sale { font-size: .7rem; color: #64748b; margin-top: 2px; max-width: 220px; }
</style>

<dialog id="rs-dialog-tecnico">
    <form method="dialog">
        <h3>Equipo Reparado</h3>
        <p class="muted" style="margin:0;font-size:.85rem;">¿Qué técnico lo reparó?</p>
        <select name="tecnico" required>
            <option value="">Elige el técnico…</option>
            @foreach(($tecnicosReparacion ?? collect()) as $tecnico)
                <option value="{{ $tecnico['id'] }}">{{ $tecnico['nombre'] }} · {{ $tecnico['sede'] }}</option>
            @endforeach
        </select>
        <div class="rs-botones">
            <button type="submit" value="cancelar" class="btn secondary" formnovalidate>Cancelar</button>
            <button type="submit" value="ok" class="btn primary">Guardar</button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        const colores = @json(\App\Models\StOrden::COLORES_RESULTADO_ST);
        const etiquetas = @json(\App\Models\StOrden::RESULTADOS_ST);
        const REPARADO = @json(\App\Models\StOrden::RESULTADO_REPARADO);
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const dialog = document.getElementById('rs-dialog-tecnico');
        const form = dialog.querySelector('form');

        const pedirTecnico = (actual) => new Promise((resolve) => {
            form.reset();
            form.elements.tecnico.value = actual || '';
            dialog.returnValue = '';
            dialog.addEventListener('close', () => {
                resolve(dialog.returnValue === 'ok' ? form.elements.tecnico.value || null : null);
            }, { once: true });
            dialog.showModal();
        });

        const pintar = (select, resultado) => {
            const [fondo, texto] = colores[resultado] || ['#f1f5f9', '#475569'];
            select.style.background = fondo;
            select.style.color = texto;
        };

        document.querySelectorAll('.rs-resultado').forEach((select) => {
            select.addEventListener('change', async () => {
                const anterior = select.dataset.actual || '';
                const resultado = select.value || null;
                let tecnico = null;
                if (resultado === REPARADO) {
                    tecnico = await pedirTecnico(select.dataset.tecnico);
                    if (!tecnico) {
                        select.value = anterior;
                        return;
                    }
                }

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
                        body: JSON.stringify({ resultado, tecnico_id: tecnico }),
                    });
                    if (!resp.ok) throw new Error();
                    const data = await resp.json();
                    select.dataset.actual = select.value;
                    select.dataset.tecnico = tecnico || '';
                    if (aviso) aviso.textContent = data.tecnico ? 'Por ' + data.tecnico : '';
                    const enMarca = (select.dataset.modo || 'marca') === 'marca';
                    const sale = enMarca ? resultado !== null : resultado === null;
                    select.closest('tr')?.classList.toggle('rs-movida', sale);
                    let nota = select.parentElement.querySelector('.rs-sale');
                    if (!nota) {
                        nota = document.createElement('div');
                        nota.className = 'rs-sale';
                        select.parentElement.appendChild(nota);
                    }
                    nota.textContent = !sale
                        ? ''
                        : (enMarca
                            ? (etiquetas[resultado] || '') + ' · pasa a ST resueltos al recargar.'
                            : 'Vuelve al reporte por marca al recargar.');
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
