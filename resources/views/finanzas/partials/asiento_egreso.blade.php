{{-- Asiento contable del egreso, en $: el debe sale del egreso (cuenta, motivo y monto) y se reparte en uno o varios haber. $sufijo distingue el modal nuevo ('') del de editar ('_edit'). --}}
<div id="container_asiento{{ $sufijo }}" style="display: none; background: #fffbeb; padding: 14px 15px; border: 1px solid #fde68a; border-radius: 8px; margin-bottom: 15px;">
    <input type="hidden" name="asiento_activo" id="asiento_activo{{ $sufijo }}" value="0">
    <h4 style="margin: 0 0 8px; font-size: 0.95rem; color: #b45309;">Asiento contable</h4>
    <p style="margin: 0 0 10px; font-size: 0.78rem; color: #92400e;">Reparte el monto en uno o varios haber. Cada línea puede ir a otra sede o tipo de gasto.</p>

    {{-- Debe oculto: se sigue llenando desde el egreso para validar el cuadre --}}
    <div style="display: none;" aria-hidden="true">
        @foreach(['cuenta' => 'Cuenta', 'detalle' => 'Detalle', 'monto' => 'Monto'] as $campo => $ph)
            <input type="text" class="fa-debe-{{ $campo }}" placeholder="{{ $ph }}" readonly tabindex="-1">
        @endforeach
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
        <span style="font-size: 0.75rem; font-weight: 700; color: #92400e; letter-spacing: .4px;">HABER</span>
        <button type="button" onclick="faPartesIguales('{{ $sufijo }}')" style="padding: 2px 8px; background: white; color: #b45309; border: 1px solid #fcd34d; border-radius: 4px; cursor: pointer; font-size: 0.75rem;">Partes iguales</button>
    </div>
    <div id="haberes_asiento{{ $sufijo }}"></div>
    <button type="button" onclick="faAgregarHaber('{{ $sufijo }}')" style="margin-top: 2px; padding: 6px 12px; background: #f59e0b; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">+ Añadir haber</button>
    <div id="estado_asiento{{ $sufijo }}" style="margin-top: 8px; font-weight: 700; font-size: 0.85rem;"></div>
</div>
