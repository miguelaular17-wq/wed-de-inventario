@php
    $monto = (float) ($monto ?? 0);
    $lineas = $lineas ?? [];
    $titulo = $titulo ?? 'Descuentos';
@endphp
@if($monto == 0.0 || $lineas === [])
    ${{ number_format($monto, 2) }}
@else
    <button
        type="button"
        class="nomina-desc-link"
        data-titulo="{{ $titulo }}"
        data-lineas="{{ e(json_encode($lineas, JSON_UNESCAPED_UNICODE)) }}"
    >${{ number_format($monto, 2) }}</button>
@endif

@once
    @push('head')
    <style>
        .nomina-desc-link {
            background: none;
            border: 0;
            padding: 0;
            color: #1d4ed8;
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .nomina-desc-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 400;
            background: rgba(15, 23, 42, .45);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .nomina-desc-backdrop.is-open { display: flex; }
        .nomina-desc-modal {
            background: #fff;
            border-radius: 12px;
            width: min(460px, 100%);
            max-height: 80vh;
            overflow: auto;
            padding: 18px 20px;
            box-shadow: 0 20px 40px rgba(15,23,42,.2);
        }
        .nomina-desc-modal h3 { margin: 0 0 12px; font-size: 1.05rem; }
        .nomina-desc-modal table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .nomina-desc-modal td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .nomina-desc-modal .monto { text-align: right; font-weight: 700; white-space: nowrap; padding-left: 12px; }
        .nomina-desc-modal .tipo { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
        .nomina-desc-modal .cerrar { margin-top: 12px; }
    </style>
    @endpush
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('nomina-desc-modal')) return;
        const wrap = document.createElement('div');
        wrap.id = 'nomina-desc-modal';
        wrap.className = 'nomina-desc-backdrop';
        wrap.innerHTML = '<div class="nomina-desc-modal" role="dialog" aria-modal="true"><h3></h3><div class="nomina-desc-body"></div><button type="button" class="btn secondary cerrar">Cerrar</button></div>';
        document.body.appendChild(wrap);
        const titulo = wrap.querySelector('h3');
        const body = wrap.querySelector('.nomina-desc-body');
        function cerrar() { wrap.classList.remove('is-open'); }
        wrap.addEventListener('click', function (e) { if (e.target === wrap) cerrar(); });
        wrap.querySelector('.cerrar').addEventListener('click', cerrar);
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.nomina-desc-link');
            if (!btn) return;
            let lineas = [];
            try { lineas = JSON.parse(btn.getAttribute('data-lineas') || '[]'); } catch (err) { lineas = []; }
            titulo.textContent = btn.getAttribute('data-titulo') || 'Descuentos';
            if (!lineas.length) {
                body.innerHTML = '<p class="muted">No hay comentarios guardados para este descuento.</p>';
            } else {
                body.innerHTML = '<table>' + lineas.map(function (l) {
                    const comentario = (l.comentario || 'Sin comentario').replace(/</g, '&lt;');
                    const tipo = (l.tipo || 'Descuento').replace(/</g, '&lt;');
                    const monto = Number(l.monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    return '<tr><td><div class="tipo">' + tipo + '</div>' + comentario + '</td><td class="monto">$' + monto + '</td></tr>';
                }).join('') + '</table>';
            }
            wrap.classList.add('is-open');
        });
    });
    </script>
    @endpush
@endonce
