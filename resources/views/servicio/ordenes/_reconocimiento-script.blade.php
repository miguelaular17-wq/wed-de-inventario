<style>
    .rm-reconocimiento {
        border: 0;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        cursor: pointer;
        max-width: 200px;
    }
    .rm-reconocimiento option, .rm-reconocimiento optgroup { background: #fff; color: #1e293b; }
    .rm-reconocimiento:disabled { opacity: .6; cursor: wait; }
    tr.rm-movida td { opacity: .45; transition: opacity .3s; }
    tr.rm-movida td:has(.rm-reconocimiento) { opacity: 1; }
    .rm-aviso { font-size: .7rem; color: #64748b; margin-top: 3px; max-width: 200px; }
    #rm-dialog-motivo { border: 0; border-radius: 12px; padding: 20px 22px; width: min(360px, 92vw); box-shadow: 0 20px 50px rgba(15, 23, 42, .25); }
    #rm-dialog-motivo::backdrop { background: rgba(15, 23, 42, .4); }
    #rm-dialog-motivo h3 { margin: 0 0 4px; font-size: 1rem; }
    #rm-dialog-motivo label.rm-opcion { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 8px; cursor: pointer; }
    #rm-dialog-motivo label.rm-opcion:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
    #rm-dialog-motivo .rm-dialog-botones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
</style>

<dialog id="rm-dialog-motivo">
    <form method="dialog">
        <h3>Reconocido por la marca</h3>
        <p class="muted" style="margin:0;font-size:.85rem;">¿Cuál es el motivo?</p>
        @foreach(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA as $key => $label)
            <label class="rm-opcion">
                <input type="radio" name="motivo" value="{{ $key }}" required>
                {{ $label }}
            </label>
        @endforeach
        <div class="rm-dialog-botones">
            <button type="submit" value="cancelar" class="btn secondary" formnovalidate>Cancelar</button>
            <button type="submit" value="ok" class="btn primary">Guardar</button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        const colores = @json(\App\Models\StOrden::COLORES_RECONOCIMIENTO_GARANTIA);
        const motivos = @json(\App\Models\StOrden::MOTIVOS_RECONOCIMIENTO_GARANTIA);
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const dialog = document.getElementById('rm-dialog-motivo');
        const formMotivo = dialog.querySelector('form');

        const pedirMotivo = () => new Promise((resolve) => {
            formMotivo.reset();
            dialog.returnValue = '';
            dialog.addEventListener('close', () => {
                resolve(dialog.returnValue === 'ok' ? formMotivo.elements.motivo.value || null : null);
            }, { once: true });
            dialog.showModal();
        });

        const pintar = (select, motivo) => {
            const [fondo, texto] = colores[motivo] || ['#f1f5f9', '#475569'];
            select.style.background = fondo;
            select.style.color = texto;
        };

        const guardar = async (select, motivo) => {
            const resp = await fetch(select.dataset.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ motivo }),
            });
            if (!resp.ok) throw new Error();
        };

        document.querySelectorAll('.rm-reconocimiento').forEach((select) => {
            select.addEventListener('change', async () => {
                const anterior = select.dataset.actual || '';
                const enMarca = select.dataset.modo === 'marca';
                let motivo = select.value || null;

                if (enMarca && select.value === 'RECONOCIDO') {
                    motivo = await pedirMotivo();
                    if (!motivo) {
                        select.value = anterior;
                        return;
                    }
                }

                pintar(select, motivo);
                select.disabled = true;
                try {
                    await guardar(select, motivo);
                    select.dataset.actual = select.value;
                    const sale = enMarca ? motivo !== null : motivo === null;
                    select.closest('tr')?.classList.toggle('rm-movida', sale);
                    const aviso = select.parentElement.querySelector('.rm-aviso');
                    if (aviso) {
                        aviso.textContent = !sale ? ''
                            : enMarca ? motivos[motivo] + ' · pasa a Garantías reconocidas al recargar.'
                            : 'Vuelve al reporte por marca al recargar.';
                    }
                } catch (e) {
                    select.value = anterior;
                    pintar(select, enMarca ? null : anterior);
                    alert('No se pudo guardar. Intenta de nuevo.');
                } finally {
                    select.disabled = false;
                }
            });
        });
    })();
</script>
