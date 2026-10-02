<div id="asistente" class="asistente" data-url="{{ route('asistente.consultar') }}" data-historial="{{ route('asistente.mensajes') }}">
    <button type="button" class="asistente-abrir" data-asistente-abrir aria-expanded="false">Asistente</button>
    <section class="asistente-panel" hidden>
        <header class="asistente-head">
            <div>
                <strong>Asistente</strong>
                <p>Pregunta sobre esta página, rellena un formulario o pide un anticipo de nómina. Puedes adjuntar una imagen.</p>
            </div>
            <button type="button" class="asistente-cerrar" data-asistente-cerrar aria-label="Cerrar">×</button>
        </header>
        <div class="asistente-log" data-asistente-log></div>
        <form class="asistente-form" data-asistente-form>
            <div class="asistente-imagen" data-asistente-imagen hidden></div>
            <textarea name="mensaje" rows="2" maxlength="4000" placeholder="Ej: rellena el cliente con la foto" required></textarea>
            <div class="asistente-acciones">
                <label class="asistente-adjuntar">
                    Imagen
                    <input type="file" accept="image/*" data-asistente-file hidden>
                </label>
                <button class="btn primary" type="submit">Enviar</button>
            </div>
        </form>
    </section>
</div>
<script src="/js/asistente.js?v={{ filemtime(public_path('js/asistente.js')) }}"></script>
