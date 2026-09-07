@php
    $checklistItems = $checklistRecepcion ?? config('servicio_tecnico.checklist_recepcion', []);
    $inspeccionOld = old('inspeccion', []);
@endphp
<div style="margin-top:12px;">
    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem;">Checklist de recepción *</label>
    <p class="muted" style="margin:0 0 10px;font-size:.8rem;">Marca cómo llegó el equipo. Esto sale en el documento de recepción.</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;">
        @foreach($checklistItems as $clave => $etiqueta)
            @php $valor = $inspeccionOld[$clave]['estado'] ?? old('inspeccion.'.$clave.'.estado'); @endphp
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;">
                <div style="font-size:.82rem;font-weight:500;margin-bottom:6px;">{{ $etiqueta }}</div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:.78rem;">
                    <label><input type="radio" name="inspeccion[{{ $clave }}][estado]" value="ok" @checked($valor === 'ok')> OK</label>
                    <label><input type="radio" name="inspeccion[{{ $clave }}][estado]" value="dano" @checked($valor === 'dano')> Daño</label>
                    <label><input type="radio" name="inspeccion[{{ $clave }}][estado]" value="na" @checked($valor === 'na')> N/A</label>
                </div>
            </div>
        @endforeach
    </div>
</div>
