@extends('layouts.app')
@section('title', 'Editar Contrato')
@section('content')
<div style="padding: 20px; max-width: 800px; margin: 0 auto;">

    <a href="{{ route('contratos.show', $contrato->id) }}" style="color: #64748b; text-decoration: none; font-size: 0.85rem;">← Volver al contrato</a>
    <h2 style="color: var(--blue); font-weight: 700; margin: 10px 0 24px;">✏️ Editar Contrato — {{ $contrato->numero_contrato }}</h2>

    <div class="panel" style="padding: 24px;">
        <form method="POST" action="{{ route('contratos.update', $contrato->id) }}" enctype="multipart/form-data">
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
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Número de Contrato</label>
                    <input type="text" value="{{ $contrato->numero_contrato }}" disabled style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; color: #64748b;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Cliente *</label>
                    <input type="text" name="cliente" required value="{{ old('cliente', $contrato->cliente) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Garantía</label>
                    <input type="text" name="garantia" value="{{ old('garantia', $contrato->garantia) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Adjunto Garantía</label>
                    <input type="file" name="garantia_documento" accept="image/*,.pdf" style="width: 100%; padding: 5px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                    @if($contrato->garantia_documento)
                        <div style="margin-top: 4px; font-size: 0.85rem;">
                            <a href="{{ $contrato->garantia_documento }}" target="_blank" style="color: var(--blue); text-decoration: none;">Ver actual</a>
                        </div>
                    @endif
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $contrato->telefono) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Sede</label>
                    <input type="text" name="sede" value="{{ old('sede', $contrato->sede) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Capital ($)</label>
                    <input type="number" step="0.01" name="capital" value="{{ old('capital', $contrato->capital) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;" required>
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Interés % (Mensual)</label>
                    <input type="number" step="0.01" name="interes_porcentaje" value="{{ old('interes_porcentaje', $contrato->interes_porcentaje) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;" required>
                </div>
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Cuota Fija ($)</label>
                    <input type="number" step="0.01" name="cuota_fija" value="{{ old('cuota_fija', $contrato->cuota_fija) }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Asesor de Cobranza</label>
                    <select name="responsable_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; background: white;">
                        <option value="">— Sin asignar —</option>
                        @foreach($asesores as $a)
                            <option value="{{ $a->id }}" {{ old('responsable_id', $contrato->responsable_id) == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 0.9rem;">Observaciones</label>
                <textarea name="observaciones" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px;">{{ old('observaciones', $contrato->observaciones) }}</textarea>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $contrato->activo) ? 'checked' : '' }} style="width: 16px; height: 16px;">
                    <span style="font-weight: 500;">Contrato activo</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <a href="{{ route('contratos.show', $contrato->id) }}" style="padding: 10px 24px; background: #94a3b8; color: white; border-radius: 6px; text-decoration: none;">Cancelar</a>
                <button type="submit" style="padding: 10px 24px; background: var(--blue); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700;">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
@endsection
