@extends('layouts.app')

@section('title', 'Configuración de nómina')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Configuración</h1>
            <p class="muted" style="margin:4px 0 0;">Ajustes globales de nómina. Aplican a todos los empleados.</p>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px; max-width:900px;">
        <h3>Inasistencias y horas extras</h3>
        <p class="muted">Valor por día: salario mensual ÷ 30. Si el salario registrado es quincenal, primero se multiplica por 2. Solo el valor de la hora extra es global.</p>
        <form method="POST" action="{{ route('nomina.configuracion.update') }}" class="nomina-form-grid">
            @csrf @method('PUT')
            <div class="field">
                <label>Valor por hora extra</label>
                <input type="number" step="0.01" min="0" name="valor_hora_extra" value="{{ number_format($valorHoraExtra, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Descuento de respaldo para datos antiguos (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="descuento_venta_pct" value="{{ number_format($descuentoVentaPct, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Comisión supervisor de sede (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_supervisor_pct" value="{{ number_format($comisionSupervisorPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Comisión supervisor de equipo / Marketing (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_marketing_pct" value="{{ number_format($comisionMarketingPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Telefonía (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_telefonia_pct" value="{{ number_format($comisionTelefoniaPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Resto de categorías (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_otros_pct" value="{{ number_format($comisionOtrosPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Retención sobre comisión (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="retencion_comision_pct" value="{{ number_format($retencionComisionPct, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Participación Servicio Técnico (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="comision_servicio_tecnico_pct" value="{{ number_format($comisionServicioTecnicoPct, 2, '.', '') }}" required>
            </div>
            <div class="field field-wide muted">Ventas propias usan el total facturado (cantidad × precio de venta), no el neto de Profit. El supervisor cobra solo equipo o sede, nunca ventas propias. Servicio técnico no cambia.</div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button class="btn primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Grupos de comisión (ventas propias)</h3>
        <p class="muted">Telefonía al {{ number_format($comisionTelefoniaPct, 2) }}%. Todo lo demás, incluidas categorías desconocidas, al {{ number_format($comisionOtrosPct, 2) }}%.</p>
        <div class="nomina-split">
            <div>
                <h4>Telefonía</h4>
                <ul>
                    @forelse($categoriasTelefonia as $item)
                        <li>{{ $item->categoria }}</li>
                    @empty
                        <li class="muted">Sin categorías. Corre las migraciones.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h4>Otros</h4>
                <ul>
                    @forelse($categoriasOtros as $item)
                        <li>{{ $item->categoria }}</li>
                    @empty
                        <li class="muted">Sin categorías. Corre las migraciones.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Reglas antiguas por producto</h3>
        <p class="muted">
            Ya no se usan para vendedores. El motor actual reparte por grupo de categoría.
            Puedes desactivar las reglas viejas.
        </p>

        <form method="POST" action="{{ route('nomina.configuracion.reglas.store') }}" class="nomina-form-grid" id="regla-comision-form">
            @csrf
            <div class="field"><label>Nombre de la regla</label><input name="nombre" value="{{ old('nombre') }}" required></div>
            <div class="field">
                <label>Nivel</label>
                <select name="nivel" id="regla-nivel" required>
                    <option value="PRODUCTO" @selected(old('nivel', 'PRODUCTO') === 'PRODUCTO')>Producto</option>
                    <option value="SUBCATEGORIA" @selected(old('nivel') === 'SUBCATEGORIA')>Subcategoría</option>
                    <option value="CATEGORIA" @selected(old('nivel') === 'CATEGORIA')>Categoría</option>
                    <option value="GENERAL" @selected(old('nivel') === 'GENERAL')>General</option>
                </select>
            </div>
            <div class="field" id="regla-producto-field">
                <label>Productos</label>
                <input type="search" id="regla-producto-buscar" placeholder="Buscar por código o nombre…" autocomplete="off" style="margin-bottom:6px;">
                <select name="producto_ids[]" id="regla-producto" multiple size="7">
                    @foreach($productosComision as $producto)
                        <option value="{{ $producto->id }}" @selected(in_array((string) $producto->id, array_map('strval', old('producto_ids', [])), true))>
                            {{ $producto->codigo }} — {{ $producto->nombre }}
                        </option>
                    @endforeach
                </select>
                <small class="muted">Mantén Ctrl presionado y haz clic para seleccionar varios.</small>
            </div>
            <div class="field" id="regla-categoria-field">
                <label>Categorías</label>
                <select name="categorias[]" id="regla-categoria" multiple size="7">
                    @foreach($categoriasComision as $categoria)
                        <option value="{{ $categoria }}" @selected(in_array($categoria, old('categorias', []), true))>{{ $categoria }}</option>
                    @endforeach
                </select>
                <small class="muted">Mantén Ctrl presionado y haz clic para seleccionar varias.</small>
            </div>
            <div class="field" id="regla-subcategoria-field">
                <label>Subcategorías</label>
                <select name="subcategorias[]" id="regla-subcategoria" multiple size="7">
                    @foreach($subcategoriasComision as $categoria => $subcategorias)
                        <optgroup label="{{ $categoria }}">
                            @foreach($subcategorias as $subcategoria)
                                @php($valorSubcategoria = json_encode(['categoria' => $categoria, 'subcategoria' => $subcategoria]))
                                <option value="{{ $valorSubcategoria }}" @selected(in_array($valorSubcategoria, old('subcategorias', []), true))>
                                    {{ $categoria }} — {{ $subcategoria }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <small class="muted">Mantén Ctrl presionado y haz clic para seleccionar varias.</small>
            </div>
            <div class="field"><label>Porcentaje</label><input type="number" name="porcentaje" value="{{ old('porcentaje') }}" step="0.0001" min="0" max="100" required></div>
            <div class="field">
                <label>Base</label>
                <select name="base_comisionable" required>
                    <option value="NETO" @selected(old('base_comisionable', 'NETO') === 'NETO')>Neto después del descuento configurado</option>
                    <option value="TOTAL" @selected(old('base_comisionable') === 'TOTAL')>Total vendido</option>
                    <option value="MARGEN" @selected(old('base_comisionable') === 'MARGEN')>Margen/ganancia</option>
                </select>
            </div>
            <div class="field"><label>Vigente desde</label><input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required></div>
            <div class="field"><label>Vigente hasta</label><input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}"></div>
            <div class="field" style="display:flex;align-items:flex-end;"><button class="btn primary" type="submit">Agregar regla</button></div>
        </form>

        <div class="table-wrap" style="margin-top:18px;">
            <table class="data-table">
                <thead>
                    <tr><th>Regla</th><th>Nivel</th><th>Alcance</th><th>Base</th><th>%</th><th>Vigencia</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($reglasComision as $regla)
                        <tr>
                            <td>{{ $regla->nombre }}</td>
                            <td>{{ $regla->nivel }}</td>
                            <td>
                                {{ $regla->codigo_producto
                                    ?: ($regla->subcategoria
                                        ? $regla->categoria.' — '.$regla->subcategoria
                                        : ($regla->categoria ?: 'Toda venta')) }}
                            </td>
                            <td>{{ $regla->base_comisionable }}</td>
                            <td>{{ number_format($regla->porcentaje, 4) }}%</td>
                            <td>{{ $regla->fecha_inicio?->format('d/m/Y') }} — {{ $regla->fecha_fin?->format('d/m/Y') ?: 'Sin fin' }}</td>
                            <td><span class="tag {{ $regla->activo ? 'ok' : '' }}">{{ $regla->activo ? 'ACTIVA' : 'INACTIVA' }}</span></td>
                            <td>
                                @if($regla->activo)
                                    <form method="POST" action="{{ route('nomina.configuracion.reglas.destroy', $regla) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn" type="submit" onclick="return confirm('¿Desactivar esta regla?')">Desactivar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No hay reglas configuradas. Sin reglas, las ventas propias generan comisión $0.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const nivel = document.getElementById('regla-nivel');
    const productoField = document.getElementById('regla-producto-field');
    const categoriaField = document.getElementById('regla-categoria-field');
    const subcategoriaField = document.getElementById('regla-subcategoria-field');
    const producto = document.getElementById('regla-producto');
    const productoBuscar = document.getElementById('regla-producto-buscar');
    const categoria = document.getElementById('regla-categoria');
    const subcategoria = document.getElementById('regla-subcategoria');

    function actualizarNivel() {
        const esProducto = nivel.value === 'PRODUCTO';
        const esSubcategoria = nivel.value === 'SUBCATEGORIA';
        const esCategoria = nivel.value === 'CATEGORIA';

        productoField.style.display = esProducto ? '' : 'none';
        categoriaField.style.display = esCategoria ? '' : 'none';
        subcategoriaField.style.display = esSubcategoria ? '' : 'none';
        producto.disabled = !esProducto;
        producto.required = esProducto;
        categoria.disabled = !esCategoria;
        categoria.required = esCategoria;
        subcategoria.disabled = !esSubcategoria;
        subcategoria.required = esSubcategoria;
    }

    nivel.addEventListener('change', actualizarNivel);
    productoBuscar.addEventListener('input', function () {
        const busqueda = productoBuscar.value.trim().toLocaleLowerCase('es');
        Array.from(producto.options).forEach(function (option, index) {
            option.hidden = index > 0 && busqueda !== ''
                && !option.textContent.toLocaleLowerCase('es').includes(busqueda);
        });
    });
    actualizarNivel();
});
</script>
@endpush
