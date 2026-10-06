(function () {
    const root = document.getElementById('asistente');
    if (!root) return;

    const abrir = root.querySelector('[data-asistente-abrir]');
    const cerrar = root.querySelector('[data-asistente-cerrar]');
    const panel = root.querySelector('.asistente-panel');
    const log = root.querySelector('[data-asistente-log]');
    const form = root.querySelector('[data-asistente-form]');
    const file = root.querySelector('[data-asistente-file]');
    const preview = root.querySelector('[data-asistente-imagen]');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const abiertoKey = 'nexo-asistente-abierto';
    let mensajes = [];
    let historialListo = false;

    function setAbierto(abierto, enfocar) {
        panel.toggleAttribute('hidden', !abierto);
        root.classList.toggle('is-open', abierto);
        abrir.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        try {
            sessionStorage.setItem(abiertoKey, abierto ? '1' : '0');
        } catch (e) {}
        if (abierto && enfocar) form.querySelector('textarea').focus();
    }

    if (sessionStorage.getItem(abiertoKey) === '1') {
        setAbierto(true);
    }

    fetch(root.dataset.historial, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (response) {
        return response.ok ? response.json() : { mensajes: [] };
    }).then(function (json) {
        if (historialListo) return;
        historialListo = true;
        mensajes = Array.isArray(json.mensajes) ? json.mensajes : [];
        mensajes.forEach(function (mensaje) {
            pintar(mensaje.rol, mensaje.texto);
        });
    }).catch(function () {
        historialListo = true;
    });

    abrir.addEventListener('click', function () {
        setAbierto(true, true);
    });

    cerrar.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        setAbierto(false);
    });

    file.addEventListener('change', function () {
        const imagen = file.files && file.files[0];
        if (!imagen) {
            preview.hidden = true;
            preview.textContent = '';
            return;
        }
        preview.hidden = false;
        preview.textContent = 'Imagen: ' + imagen.name;
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const textarea = form.querySelector('textarea');
        const texto = textarea.value.trim();
        if (!texto) return;

        historialListo = true;
        agregar('usuario', texto);
        textarea.value = '';
        const espera = pintar('asistente', 'Pensando…');
        const boton = form.querySelector('button[type="submit"]');
        boton.disabled = true;

        const data = new FormData();
        data.append('mensaje', texto);
        const pagina = contexto();
        data.set('pagina[titulo]', pagina.titulo);
        data.set('pagina[url]', pagina.url);
        data.set('pagina[texto]', pagina.texto);
        pagina.campos.forEach(function (campo, i) {
            data.append('pagina[campos][' + i + '][clave]', campo.clave);
            data.append('pagina[campos][' + i + '][etiqueta]', campo.etiqueta);
            data.append('pagina[campos][' + i + '][tipo]', campo.tipo);
            data.append('pagina[campos][' + i + '][valor]', campo.valor);
            campo.opciones.forEach(function (opcion) {
                data.append('pagina[campos][' + i + '][opciones][]', opcion);
            });
        });
        if (file.files && file.files[0]) {
            data.append('imagen', file.files[0]);
        }

        fetch(root.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        }).then(function (response) {
            return response.json().then(function (json) {
                return { ok: response.ok, json: json };
            });
        }).then(function (result) {
            espera.remove();
            if (!result.ok) {
                agregar('asistente', textoError(result.json));
                return;
            }
            const rellenados = aplicar(result.json.rellenar || []);
            let respuesta = result.json.respuesta || 'Listo.';
            if (rellenados.length) {
                respuesta += '\n\nRellené: ' + rellenados.join(', ') + '. Revisa y guarda tú; no cambio la página.';
            }
            agregar('asistente', respuesta);
            file.value = '';
            preview.hidden = true;
            preview.textContent = '';
        }).catch(function () {
            espera.remove();
            agregar('asistente', 'No pude conectar con el asistente.');
        }).finally(function () {
            boton.disabled = false;
        });
    });

    function textoError(json) {
        if (json && json.error) return json.error;
        if (json && json.message && json.message !== 'The given data was invalid.') return json.message;
        if (json && json.errors) {
            const primero = Object.values(json.errors).flat().find(Boolean);
            if (primero) return primero;
        }
        return 'No pude responder.';
    }

    function agregar(rol, texto) {
        mensajes.push({ rol: rol, texto: texto });
        mensajes = mensajes.slice(-40);
        pintar(rol, texto);
    }

    function pintar(rol, texto) {
        const burbuja = document.createElement('div');
        burbuja.className = 'asistente-msg asistente-msg-' + (rol === 'usuario' ? 'usuario' : 'asistente');
        if (texto === 'Pensando…') burbuja.classList.add('is-espera');
        burbuja.textContent = texto;
        log.appendChild(burbuja);
        log.scrollTop = log.scrollHeight;
        return burbuja;
    }

    function contexto() {
        const main = document.querySelector('main');
        let texto = '';
        if (main) {
            const copia = main.cloneNode(true);
            copia.querySelectorAll('#asistente, script, style').forEach(function (nodo) { nodo.remove(); });
            texto = (copia.innerText || '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim().slice(0, 6000);
        }
        return {
            titulo: (document.title || '').slice(0, 180),
            url: (location.pathname + location.search).slice(0, 200),
            texto: texto,
            campos: campos(),
        };
    }

    function campos() {
        const vistos = {};
        const lista = [];
        document.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (lista.length >= 80) return;
            if (el.closest('#asistente')) return;
            const tipo = (el.type || el.tagName || '').toLowerCase();
            if (['hidden', 'password', 'file', 'submit', 'button', 'reset', 'image'].indexOf(tipo) !== -1) return;
            const clave = el.name || el.id;
            if (!clave || vistos[clave] || !/^[A-Za-z0-9_\-\[\]\.]{1,80}$/.test(clave)) return;
            vistos[clave] = true;
            const opciones = [];
            if (el.tagName === 'SELECT') {
                Array.from(el.options).slice(0, 30).forEach(function (opcion) {
                    const textoOpcion = (opcion.textContent || '').trim();
                    if (textoOpcion) opciones.push(textoOpcion.slice(0, 80));
                });
            }
            lista.push({
                clave: clave,
                etiqueta: etiqueta(el).slice(0, 80),
                tipo: tipo.slice(0, 20),
                valor: (el.value || '').slice(0, 120),
                opciones: opciones,
            });
        });
        return lista;
    }

    function etiqueta(el) {
        if (el.id) {
            const porFor = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(el.id) : el.id) + '"]');
            if (porFor) return (porFor.innerText || '').trim();
        }
        const caja = el.closest('.field, label, .form-group');
        const label = caja ? caja.querySelector('label') : null;
        return ((label && label.innerText) || el.getAttribute('aria-label') || el.placeholder || el.name || '').trim();
    }

    function aplicar(rellenar) {
        const nombres = [];
        (rellenar || []).forEach(function (item) {
            if (!item || !item.clave) return;
            const el = buscar(item.clave);
            if (!el || el.closest('#asistente')) return;
            const valor = String(item.valor ?? '');
            if (el.type === 'checkbox') {
                el.checked = /^(1|true|si|sí|on|yes)$/i.test(valor);
            } else if (el.type === 'radio') {
                const radio = document.querySelector('input[type="radio"][name="' + css(item.clave) + '"][value="' + css(valor) + '"]');
                if (radio) radio.checked = true;
            } else if (el.tagName === 'SELECT') {
                elegirOpcion(el, valor);
            } else if (el.type === 'date') {
                el.value = fechaIso(valor);
            } else {
                el.value = valor;
            }
            el.classList.add('asistente-marcado');
            nombres.push(etiqueta(el) || item.clave);
        });
        const primero = document.querySelector('.asistente-marcado');
        if (primero) primero.scrollIntoView({ block: 'center', behavior: 'smooth' });
        return nombres;
    }

    function buscar(clave) {
        return document.querySelector('[name="' + css(clave) + '"]')
            || document.getElementById(clave);
    }

    function elegirOpcion(select, valor) {
        const buscado = valor.trim().toLowerCase();
        const opcion = Array.from(select.options).find(function (item) {
            return item.value.toLowerCase() === buscado || (item.textContent || '').trim().toLowerCase() === buscado;
        });
        if (opcion) select.value = opcion.value;
    }

    function fechaIso(valor) {
        const match = valor.trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
        if (!match) return valor;
        return match[3] + '-' + match[2].padStart(2, '0') + '-' + match[1].padStart(2, '0');
    }

    function css(valor) {
        return window.CSS && CSS.escape ? CSS.escape(valor) : valor.replace(/"/g, '\\"');
    }
})();
