@extends('layouts.app')
@section('title', 'Cobranza')
@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="font-weight:700; color:#1e293b;">Cobranza</h2>
        <button class="btn btn-primary" style="border-radius:8px;">+ Nuevo Registro</button>
    </div>
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th>Cliente</th>
                            <th>Factura</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cobranzas as $cob)
                            <tr>
                                <td>{{ $cob->cliente }}</td>
                                <td>{{ $cob->factura ?: '-' }}</td>
                                <td>{{ $cob->fecha_emision }}</td>
                                <td>{{ $cob->fecha_vencimiento ?: '-' }}</td>
                                <td class="text-end">{{ number_format($cob->monto_total, 2) }}</td>
                                <td class="text-end">{{ number_format($cob->monto_pagado, 2) }}</td>
                                <td>
                                    @php
                                        $bg = 'bg-secondary';
                                        if($cob->estado == 'pagado') $bg = 'bg-success';
                                        elseif($cob->estado == 'atrasado') $bg = 'bg-danger';
                                        elseif($cob->estado == 'pendiente') $bg = 'bg-warning text-dark';
                                    @endphp
                                    <span class="badge {{ $bg }}">{{ ucfirst($cob->estado) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No hay registros de cobranza.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
