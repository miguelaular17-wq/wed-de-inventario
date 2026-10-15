<form method="GET" action="{{ url()->current() }}" class="nomina-card gerencial-filtros" style="margin-bottom:14px;">
    <div class="nomina-form-grid">
        <div class="field">
            <label for="inc-desde">Desde</label>
            <input id="inc-desde" type="date" name="desde" value="{{ $periodo['desde'] }}" required>
        </div>
        <div class="field">
            <label for="inc-hasta">Hasta</label>
            <input id="inc-hasta" type="date" name="hasta" value="{{ $periodo['hasta'] }}" required>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn" type="submit">Aplicar</button>
        </div>
    </div>
</form>
