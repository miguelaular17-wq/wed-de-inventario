<div id="asistente" class="asistente" data-url="{{ route('asistente.consultar') }}" data-historial="{{ route('asistente.mensajes') }}">
    <button type="button" class="asistente-abrir" data-asistente-abrir aria-expanded="false" aria-label="Asistente">
        <span class="asistente-muneco" aria-hidden="true">
            <span class="asistente-pelo"></span>
            <span class="asistente-cara">
                <span class="asistente-ojo"></span>
                <span class="asistente-ojo"></span>
                <span class="asistente-boca"></span>
            </span>
        </span>
        <span class="asistente-nombre">Asistente</span>
    </button>
    <section class="asistente-panel" hidden>
        <header class="asistente-head">
            <span class="asistente-avatar" aria-hidden="true"></span>
            <div>
                <strong>Asistente</strong>
                <p>Pregunta, rellena un formulario o adjunta una imagen.</p>
            </div>
            <button type="button" class="asistente-cerrar" data-asistente-cerrar aria-label="Cerrar">×</button>
        </header>
        <div class="asistente-log" data-asistente-log></div>
        <form class="asistente-form" data-asistente-form>
            <div class="asistente-imagen" data-asistente-imagen hidden></div>
            <div class="asistente-composer">
                <textarea name="mensaje" rows="2" maxlength="4000" placeholder="Escribe tu pregunta…" required></textarea>
                <div class="asistente-acciones">
                    <label class="asistente-adjuntar">
                        Imagen
                        <input type="file" accept="image/*" data-asistente-file hidden>
                    </label>
                    <button class="btn primary" type="submit">Enviar</button>
                </div>
            </div>
        </form>
    </section>
</div>
<script src="/js/asistente.js?v={{ filemtime(public_path('js/asistente.js')) }}"></script>
