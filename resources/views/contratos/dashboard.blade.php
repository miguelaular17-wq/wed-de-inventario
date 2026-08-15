@extends('layouts.app')
@section('title', 'Dashboard de Contratos')
@section('content')
<div style="padding: 20px; max-width: 1400px; margin: 0 auto;">

    {{-- Header --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 10px;">
        <h2 style="color: var(--blue); font-weight: 700; margin: 0;">📊 Dashboard de Contratos</h2>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('contratos.lista') }}" style="padding: 10px 20px; background: var(--blue); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">📋 Ver Contratos</a>
            <a href="{{ route('contratos.calendario') }}" style="padding: 10px 20px; background: #7c3aed; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">📅 Calendario</a>
            <a href="{{ route('contratos.create') }}" style="padding: 10px 20px; background: #059669; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">+ Nuevo Contrato</a>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #d1e7dd; color: #0f5132; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background: #f8d7da; color: #842029; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">{{ session('error') }}</div>
    @endif

    {{-- KPI Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(59,130,246,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Contratos Activos</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $totalContratos }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(239,68,68,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Cuotas Vencidas</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $vencidas }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Vencen en 3 días</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $porVencer3 }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #ea580c 0%, #fb923c 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(251,146,60,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Vencen en 7 días</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $porVencer7 }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #0891b2 0%, #22d3ee 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(34,211,238,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Vencen en 15 días</div>
            <div style="font-size: 2rem; font-weight: 700;">{{ $porVencer15 }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(167,139,250,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Total Pendiente</div>
            <div style="font-size: 1.5rem; font-weight: 700;">${{ number_format($montoPendiente, 2) }}</div>
        </div>
        <div style="background: linear-gradient(135deg, #059669 0%, #34d399 100%); padding: 20px; border-radius: 14px; color: white; box-shadow: 0 4px 15px rgba(52,211,153,0.3);">
            <div style="font-size: 0.85rem; opacity: 0.85;">Cobrado este Mes</div>
            <div style="font-size: 1.5rem; font-weight: 700;">${{ number_format($cobradoMes, 2) }}</div>
        </div>
    </div>


    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        {{-- Alertas urgentes --}}
        <div class="panel" style="padding: 0; overflow: hidden;">
            <div style="background: #fef2f2; padding: 14px 20px; border-bottom: 2px solid #fecaca;">
                <h3 style="margin: 0; color: #b91c1c; font-size: 1rem;">🔔 Alertas Urgentes</h3>
            </div>
            <div style="max-height: 350px; overflow-y: auto;">
                @forelse($alertasHoy as $alerta)
                    <div style="padding: 10px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <a href="{{ route('contratos.show', $alerta->contrato_id) }}" style="font-weight: 600; color: var(--blue); text-decoration: none;">{{ $alerta->contrato->cliente ?? '—' }}</a>
                            <div style="font-size: 0.8rem; color: #64748b;">
                                Cuota #{{ $alerta->numero_cuota }} · Vence: {{ $alerta->fecha_vencimiento?->format('d/m/Y') }}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: #dc2626;">${{ number_format($alerta->saldo, 2) }}</div>
                            @if($alerta->diasAtraso() > 0)
                                <span style="font-size: 0.75rem; background: #fef2f2; color: #dc2626; padding: 2px 6px; border-radius: 4px;">{{ $alerta->diasAtraso() }} días vencida</span>
                            @else
                                <span style="font-size: 0.75rem; background: #fef9c3; color: #a16207; padding: 2px 6px; border-radius: 4px;">Vence hoy</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p style="padding: 20px; text-align: center; color: #94a3b8;">No hay alertas urgentes para hoy.</p>
                @endforelse
            </div>
        </div>

        {{-- Promesas de pago --}}
        <div class="panel" style="padding: 0; overflow: hidden;">
            <div style="background: #eff6ff; padding: 14px 20px; border-bottom: 2px solid #bfdbfe;">
                <h3 style="margin: 0; color: #1e40af; font-size: 1rem;">🤝 Promesas de Pago para Hoy</h3>
            </div>
            <div style="max-height: 350px; overflow-y: auto;">
                @forelse($promesasHoy as $promesa)
                    <div style="padding: 10px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <a href="{{ route('contratos.show', $promesa->contrato_id) }}" style="font-weight: 600; color: var(--blue); text-decoration: none;">{{ $promesa->contrato->cliente ?? '—' }}</a>
                            <div style="font-size: 0.8rem; color: #64748b;">
                                Prometió: {{ $promesa->fecha_prometida_pago?->format('d/m/Y') }} · {{ $promesa->comentarios }}
                            </div>
                        </div>
                        <div style="font-weight: 600; color: #1e40af;">{{ $promesa->usuario?->name ?? '—' }}</div>
                    </div>
                @empty
                    <p style="padding: 20px; text-align: center; color: #94a3b8;">No hay promesas de pago para hoy.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Indicadores mensuales --}}
    <div class="panel" style="padding: 20px;">
        <h3 style="margin-top: 0; color: var(--blue);">📈 Indicadores de Cobranza por Mes</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 10px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Mes</th>
                        <th style="padding: 10px 16px; text-align: right; font-weight: 600; color: #059669; border-bottom: 2px solid #e2e8f0;">Cobrado</th>
                        <th style="padding: 10px 16px; text-align: right; font-weight: 600; color: #dc2626; border-bottom: 2px solid #e2e8f0;">Vencido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($indicadoresMes as $ind)
                        <tr>
                            <td style="padding: 10px 16px; border-bottom: 1px solid #f1f5f9; font-weight: 500;">{{ $ind['mes'] }}</td>
                            <td style="padding: 10px 16px; text-align: right; border-bottom: 1px solid #f1f5f9; color: #059669; font-weight: 600;">${{ number_format($ind['cobrado'], 2) }}</td>
                            <td style="padding: 10px 16px; text-align: right; border-bottom: 1px solid #f1f5f9; color: #dc2626; font-weight: 600;">${{ number_format($ind['vencido'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
