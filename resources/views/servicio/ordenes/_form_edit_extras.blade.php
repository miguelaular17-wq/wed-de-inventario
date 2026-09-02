@if(isset($orden))
    <hr style="border:none;border-top:1px dashed #e2e8f0;margin:24px 0;">
    <h3 style="margin:0 0 12px;">Costos y presupuesto</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Presupuesto ($)</label>
            <input type="number" step="0.01" min="0" name="presupuesto" value="{{ old('presupuesto', $orden->presupuesto ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Mano de obra ($)</label>
            <input type="number" step="0.01" min="0" name="costo_mano_obra" value="{{ old('costo_mano_obra', $orden->costo_mano_obra ?? '') }}" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Repuestos ($)</label>
            <input type="number" step="0.01" min="0" name="costo_refacciones" value="{{ old('costo_refacciones', $orden->costo_refacciones ?? '') }}" readonly style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#f8fafc;">
            <span class="muted" style="font-size:.75rem;">Se calcula al guardar según las líneas de abajo.</span>
        </div>
    </div>

    @if($orden->excedePresupuesto())
        <div style="background:#fffbeb;border:1px solid #fcd34d;padding:10px 14px;border-radius:8px;margin-bottom:12px;color:#92400e;">
            ⚠️ Los costos superan el presupuesto. Si vas a marcar como entregado, confirma con el cliente.
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <input type="checkbox" name="confirmar_exceso" value="1" @checked(old('confirmar_exceso'))>
            El cliente aprobó el monto adicional
        </label>
    @endif

    @if(!empty($puedeTransferir))
        <div style="margin-bottom:16px;">
            <label style="display:block;font-weight:500;margin-bottom:4px;font-size:0.9rem;">Transferir a sede</label>
            <select name="sede_destino" style="width:100%;max-width:280px;padding:8px;border:1px solid #ccc;border-radius:6px;background:white;">
                <option value="">— Sin cambio —</option>
                @foreach($sedes as $sede)
                    @if($sede !== $orden->sede)
                        <option value="{{ $sede }}" @selected(old('sede_destino') === $sede)>{{ $sede }}</option>
                    @endif
                @endforeach
            </select>
            @if($orden->repuestos_descontados_at)
                <p class="muted" style="font-size:.8rem;margin-top:4px;">No se puede transferir con repuestos ya descontados.</p>
            @endif
        </div>
    @endif

    @if(!$orden->repuestos_descontados_at && isset($repuestosDisponibles))
        <h3 style="margin:0 0 12px;">Repuestos del inventario</h3>
        @if($repuestosDisponibles->isEmpty())
            <p class="muted">No hay repuestos activos en esta sede. Un supervisor puede cargarlos en Repuestos.</p>
        @else
            <div id="repuestos-lines">
                @php $oldRepuestos = old('repuestos', $orden->repuestosLineas->map(fn($l) => ['repuesto_id' => $l->repuesto_id, 'cantidad' => $l->cantidad])->values()->all()); @endphp
                @forelse($oldRepuestos as $idx => $linea)
                    <div class="repuesto-line" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                        <select name="repuestos[{{ $idx }}][repuesto_id]" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:6px;">
                            <option value="">— Seleccionar —</option>
                            @foreach($repuestosDisponibles as $rep)
                                <option value="{{ $rep->id }}" @selected((int)($linea['repuesto_id'] ?? 0) === $rep->id)>
                                    {{ $rep->nombre }} (stock: {{ $rep->stock }})
                                </option>
                            @endforeach
                        </select>
                        <input type="number" min="1" name="repuestos[{{ $idx }}][cantidad]" value="{{ $linea['cantidad'] ?? 1 }}" style="width:80px;padding:8px;border:1px solid #ccc;border-radius:6px;">
                        <button type="button" class="btn secondary" onclick="this.closest('.repuesto-line').remove()">✕</button>
                    </div>
                @empty
                    <div class="repuesto-line" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                        <select name="repuestos[0][repuesto_id]" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:6px;">
                            <option value="">— Seleccionar —</option>
                            @foreach($repuestosDisponibles as $rep)
                                <option value="{{ $rep->id }}">{{ $rep->nombre }} (stock: {{ $rep->stock }})</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" name="repuestos[0][cantidad]" value="1" style="width:80px;padding:8px;border:1px solid #ccc;border-radius:6px;">
                        <button type="button" class="btn secondary" onclick="this.closest('.repuesto-line').remove()">✕</button>
                    </div>
                @endforelse
            </div>
            <button type="button" class="btn secondary" id="add-repuesto-line" style="margin-top:8px;">+ Agregar repuesto</button>
            <template id="repuesto-line-template">
                <div class="repuesto-line" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                    <select data-name="repuesto_id" style="flex:1;padding:8px;border:1px solid #ccc;border-radius:6px;">
                        <option value="">— Seleccionar —</option>
                        @foreach($repuestosDisponibles as $rep)
                            <option value="{{ $rep->id }}">{{ $rep->nombre }} (stock: {{ $rep->stock }})</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" data-name="cantidad" value="1" style="width:80px;padding:8px;border:1px solid #ccc;border-radius:6px;">
                    <button type="button" class="btn secondary" onclick="this.closest('.repuesto-line').remove()">✕</button>
                </div>
            </template>
            @push('scripts')
            <script>
            document.getElementById('add-repuesto-line')?.addEventListener('click', function() {
                const container = document.getElementById('repuestos-lines');
                const tpl = document.getElementById('repuesto-line-template');
                if (!container || !tpl) return;
                const idx = container.querySelectorAll('.repuesto-line').length;
                const node = tpl.content.firstElementChild.cloneNode(true);
                node.querySelector('[data-name="repuesto_id"]').name = 'repuestos[' + idx + '][repuesto_id]';
                node.querySelector('[data-name="cantidad"]').name = 'repuestos[' + idx + '][cantidad]';
                container.appendChild(node);
            });
            </script>
            @endpush
            <p class="muted" style="font-size:.8rem;margin-top:8px;">Al marcar la orden como <strong>Listo</strong>, el stock se descuenta en el servidor.</p>
        @endif
    @endif
@endif
