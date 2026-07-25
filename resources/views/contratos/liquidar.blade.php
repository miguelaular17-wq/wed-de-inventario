@extends('layouts.app')

@section('content')
<div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <div>
        <h2 style="margin: 0; color: #1e293b; font-size: 1.8rem;">Liquidar Contrato #{{ $contrato->numero_contrato }}</h2>
        <p style="color: #64748b; margin-top: 5px;">Reestructurar deuda y generar nuevo contrato</p>
    </div>
    <a href="{{ route('contratos.show', $contrato->id) }}" style="padding: 8px 16px; background: #e2e8f0; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 500;">Volver</a>
</div>

<div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 800px; margin: 0 auto;">
    
    <div style="background: #f1f5f9; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #3b82f6;">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 1.1rem;">Deuda Actual Acumulada</h3>
        <p style="font-size: 1.8rem; font-weight: 700; color: #3b82f6; margin: 10px 0 0 0;">
            ${{ number_format($contrato->total_a_pagar, 2) }}
        </p>
        <p style="color: #64748b; margin-top: 5px; font-size: 0.9rem;">Este es el capital actual más todas las cuotas vencidas acumuladas.</p>
    </div>

    @if($errors->any())
        <div style="background: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('contratos.liquidar.store', $contrato->id) }}" method="POST">
        @csrf
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Nuevo Capital (Deuda a Financiar)</label>
                <input type="number" name="capital" step="0.01" value="{{ old('capital', $contrato->total_a_pagar) }}" required
                    style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Nuevo Porcentaje de Interés (Ej: 0.10 para 10%)</label>
                <input type="number" name="interes_porcentaje" step="0.001" min="0" value="{{ old('interes_porcentaje', 0) }}" required
                    style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
                <small style="color: #64748b; display: block; margin-top: 4px;">Puede ser 0 si se liquidará sin intereses.</small>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Número de Nuevas Cuotas</label>
                <input type="number" name="numero_cuotas" value="{{ old('numero_cuotas', 1) }}" required min="1"
                    style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Frecuencia</label>
                <select name="frecuencia" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; background: white;">
                    <option value="MENSUAL" {{ old('frecuencia') === 'MENSUAL' ? 'selected' : '' }}>MENSUAL</option>
                    <option value="QUINCENAL" {{ old('frecuencia') === 'QUINCENAL' ? 'selected' : '' }}>QUINCENAL</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Fecha de Inicio de Pago</label>
                <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required
                    style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="grid-column: 1 / -1;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #475569;">Observaciones de Liquidación</label>
                <textarea name="observaciones" rows="3" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem;">{{ old('observaciones') }}</textarea>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <a href="{{ route('contratos.show', $contrato->id) }}" style="padding: 10px 24px; background: white; border: 1px solid #cbd5e1; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 600;">Cancelar</a>
            <button type="submit" style="padding: 10px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">Confirmar Liquidación</button>
        </div>
    </form>
</div>
@endsection
