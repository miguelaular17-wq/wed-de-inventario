@extends('layouts.app')
@section('title', 'Nuevo Contrato')
@section('content')
<div style="padding: 20px; max-width: 800px; margin: 0 auto;">

    <a href="{{ route('contratos.lista') }}" style="color: #64748b; text-decoration: none; font-size: 0.85rem;">← Volver a lista</a>
    <h2 style="color: var(--blue); font-weight: 700; margin: 10px 0 24px;">📝 Nuevo Contrato</h2>

    <div class="panel" style="padding: 24px;">
        <form method="POST" action="{{ route('contratos.store') }}">
            @csrf

            @if($errors->any())
                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; color: #dc2626;">
                    <ul style="margin: 0; padding-left: 16px;">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Número de Contrato *</label>
                    <input type="text" name="numero_contrato" required value="{{ $numeroGenerado }}" readonly style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: #f8fafc; color: #64748b;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Cliente *</label>
                    <input type="text" name="cliente" required value="{{ old('cliente') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Garantía</label>
                    <input type="text" name="garantia" value="{{ old('garantia') }}" placeholder="Ej: CAMION" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Contacto</label>
                    <input type="text" name="contacto" value="{{ old('contacto') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Sede</label>
                    <input type="text" name="sede" value="{{ old('sede') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Asesor de Cobranza</label>
                    <select name="responsable_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                        <option value="">— Sin asignar —</option>
                        @foreach($asesores as $a)
                            <option value="{{ $a->id }}" {{ old('responsable_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr style="border: none; border-top: 1px dashed #e2e8f0; margin: 20px 0;">
            <h4 style="color: var(--blue); margin-bottom: 12px;">💰 Datos Financieros</h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Capital (USD) *</label>
                    <input type="number" step="0.01" name="capital" required value="{{ old('capital') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Interés % (mensual) *</label>
                    <input type="number" step="0.01" name="interes_porcentaje" required value="{{ old('interes_porcentaje', '0.08') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Cuota Fija (USD) *</label>
                    <input type="number" step="0.01" name="cuota_fija" required value="{{ old('cuota_fija') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Fecha de Inicio *</label>
                    <input type="date" name="fecha_inicio" required value="{{ old('fecha_inicio', now()->toDateString()) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Frecuencia *</label>
                    <select name="frecuencia" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                        <option value="MENSUAL" {{ old('frecuencia') == 'MENSUAL' ? 'selected' : '' }}>Mensual</option>
                        <option value="QUINCENAL" {{ old('frecuencia') == 'QUINCENAL' ? 'selected' : '' }}>Quincenal</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Número de Cuotas *</label>
                <input type="number" name="numero_cuotas" required min="1" max="360" value="{{ old('numero_cuotas', '12') }}" style="width: 200px; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                <span style="color: #64748b; font-size: 0.85rem; margin-left: 8px;">Las cuotas se generarán automáticamente</span>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Observaciones</label>
                <textarea name="observaciones" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">{{ old('observaciones') }}</textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <a href="{{ route('contratos.lista') }}" style="padding: 10px 24px; background: #94a3b8; color: white; border-radius: 6px; text-decoration: none;">Cancelar</a>
                <button type="submit" style="padding: 10px 24px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 1rem;">Crear Contrato</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const capitalInput = document.querySelector('input[name="capital"]');
    const interesInput = document.querySelector('input[name="interes_porcentaje"]');
    const cuotaInput = document.querySelector('input[name="cuota_fija"]');

    function calculateCuota() {
        const capital = parseFloat(capitalInput.value) || 0;
        const interes = parseFloat(interesInput.value) || 0;
        if (capital > 0 && interes > 0) {
            cuotaInput.value = (capital * interes).toFixed(2);
        }
    }

    capitalInput.addEventListener('input', calculateCuota);
    interesInput.addEventListener('input', calculateCuota);
});
</script>
@endsection
