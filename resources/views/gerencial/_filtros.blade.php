@php
    $modo = $modo ?? 'completo';
    $action = $action ?? route('gerencial.dashboard');
    $tiposMov = collect(['AJU', 'TRA', 'CAR', 'DES', 'INV', 'ENT', 'SAL', 'REP', 'CARDES'])
        ->merge($tipos ?? [])
        ->unique()
        ->values();
    $tipoLabels = [
        'AJU' => 'Ajuste',
        'TRA' => 'Traslado',
        'CAR' => 'Carga',
        'DES' => 'Descarga',
        'INV' => 'Inventario',
        'REP' => 'Reposición',
        'CARDES' => 'Carga/descarga',
        'ENT' => 'Entrada',
        'SAL' => 'Salida',
    ];
@endphp
<form method="GET" action="{{ $action }}" class="nomina-card gerencial-filtros">
    <div class="nomina-form-grid">
        <div class="field">
            <label>Período</label>
            <select name="preset" onchange="this.form.hasta.disabled = this.value !== 'personalizado'; this.form.desde.disabled = this.value !== 'personalizado';">
                <option value="mes" @selected($filtros['preset']==='mes')>Este mes</option>
                <option value="mes_anterior" @selected($filtros['preset']==='mes_anterior')>Mes anterior</option>
                <option value="quincena" @selected($filtros['preset']==='quincena')>Quincena actual</option>
                <option value="personalizado" @selected($filtros['preset']==='personalizado')>Rango</option>
            </select>
        </div>
        <div class="field">
            <label>Desde</label>
            <input type="date" name="desde" value="{{ $filtros['desde'] }}" @disabled($filtros['preset']!=='personalizado')>
        </div>
        <div class="field">
            <label>Hasta</label>
            <input type="date" name="hasta" value="{{ $filtros['hasta'] }}" @disabled($filtros['preset']!=='personalizado')>
        </div>
        <div class="field">
            <label>Sede</label>
            <select name="sede">
                <option value="todas">Todas las tiendas</option>
                @foreach($sedes as $sede)
                    <option value="{{ $sede }}" @selected($filtros['sede']===$sede)>{{ $sede }}</option>
                @endforeach
            </select>
        </div>
        @if(in_array($modo, ['completo', 'valorizados', 'rentabilidad'], true))
            <div class="field">
                <label>Categoría</label>
                <select name="categoria">
                    <option value="">Todas</option>
                    @foreach($catalogos['categorias'] as $cat)
                        <option value="{{ $cat }}" @selected($filtros['categoria']===$cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if(in_array($modo, ['completo', 'devoluciones', 'rentabilidad'], true))
            <div class="field">
                <label>Vendedor</label>
                <select name="vendedor">
                    <option value="">Todos</option>
                    @foreach($catalogos['vendedores'] as $vend)
                        <option value="{{ $vend }}" @selected($filtros['vendedor']===$vend)>{{ $vend }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($modo !== 'ajustes')
            <div class="field">
                <label>Producto</label>
                <input name="producto" value="{{ $filtros['producto'] }}" placeholder="Código o nombre">
            </div>
        @endif
        @if($modo === 'ajustes')
            <div class="field">
                <label>Tipo de movimiento</label>
                <select name="tipo">
                    <option value="">Todos</option>
                    @foreach($tiposMov as $code)
                        <option value="{{ $code }}" @selected(($tipo ?? '')===$code)>
                            {{ $code }}@if(isset($tipoLabels[$code])) — {{ $tipoLabels[$code] }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        @if($modo === 'completo')
            <input type="hidden" name="ranking" value="{{ $filtros['ranking'] }}">
        @endif
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Aplicar</button>
        </div>
    </div>
    <p class="muted gerencial-filtros-hint">Los filtros de sede y período se mantienen al cambiar de dashboard.</p>
</form>
