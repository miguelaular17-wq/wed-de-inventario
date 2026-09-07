@php
    $padId = $padId ?? 'firma';
    $inputName = $inputName ?? 'firma';
    $label = $label ?? 'Firma';
    $valor = $valor ?? '';
@endphp
<div class="st-firma-pad" data-firma-pad="{{ $padId }}" style="margin-top:8px;">
    <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">{{ $label }}</label>
    <canvas width="520" height="140" style="width:100%;max-width:520px;height:140px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;touch-action:none;cursor:crosshair;"></canvas>
    <input type="hidden" name="{{ $inputName }}" value="{{ $valor }}">
    <div style="margin-top:6px;">
        <button type="button" class="btn secondary" data-firma-clear style="padding:4px 10px;font-size:.8rem;">Borrar firma</button>
    </div>
</div>
