<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guía de documentos — Conciliación de egresos</title>
    <style>
        @page { margin: 26px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #1e3a5f; }
        h2 { font-size: 13px; margin: 16px 0 6px; color: #1e40af; border-bottom: 1px solid #bfdbfe; padding-bottom: 3px; }
        h3 { font-size: 11.5px; margin: 10px 0 4px; color: #334155; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 12px; }
        p { margin: 0 0 6px; }
        ul, ol { margin: 4px 0 8px 18px; padding: 0; }
        li { margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #eff6ff; color: #1e3a5f; font-weight: 700; }
        .tag { display: inline-block; border-radius: 4px; padding: 1px 7px; font-size: 9.5px; font-weight: 700; }
        .tag-azul { background: #dbeafe; color: #1d4ed8; }
        .tag-lila { background: #ede9fe; color: #5b21b6; }
        .tag-gris { background: #e2e8f0; color: #334155; }
        .btn-ejemplo {
            display: inline-block; border-radius: 6px; padding: 3px 10px; font-size: 10px; font-weight: 700;
            color: #fff; margin: 0 2px 2px 0;
        }
        .btn-azul { background: #2563eb; }
        .btn-lila { background: #7c3aed; }
        .btn-gris { background: #475569; }
        .caja { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin: 6px 0 10px; }
        .nota { color: #9a3412; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 7px 9px; margin: 8px 0; font-size: 10px; }
        .ok { color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 7px 9px; margin: 6px 0; font-size: 10px; }
        .no { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 7px 9px; margin: 6px 0; font-size: 10px; }
        .paso { font-weight: 700; color: #1e3a5f; }
        code { font-size: 10px; }
        .foot { margin-top: 16px; color: #94a3b8; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Guía de documentos — Conciliación de egresos</h1>
    <p class="meta">
        Mapa de los botones de la pantalla, qué archivo subir a cada uno y ejemplos reales.
        Generado {{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
        @if(!empty($generadoPor)) · {{ $generadoPor }} @endif
    </p>

    {{-- Mapa de botones --}}
    <h2>0. Los botones de la pantalla (de izquierda a derecha)</h2>
    <p>
        En la barra superior de <strong>Conciliación de egresos</strong> verás:
        <span class="btn-ejemplo btn-azul">Añadir Movimientos</span>
        <span class="btn-ejemplo btn-lila">Documentos adicionales ▾</span>
        <span class="btn-ejemplo btn-gris">Guía PDF</span>
        y luego las fechas / banco / Filtrar.
    </p>
    <table>
        <thead>
            <tr>
                <th>Botón</th>
                <th>Color</th>
                <th>Para qué sirve</th>
                <th>Qué archivo subir</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Añadir Movimientos</strong></td>
                <td><span class="tag tag-azul">Azul</span></td>
                <td>Cargar el extracto del banco (base de la conciliación)</td>
                <td>Estado de cuenta del portal del banco<br><em>ej.</em> <code>Estado_de_cuenta_….pdf</code> / <code>.xls</code> / <code>.csv</code></td>
            </tr>
            <tr>
                <td><strong>Documentos adicionales ▾</strong><br>→ Pago móvil (Banesco / Venezuela)</td>
                <td><span class="tag tag-lila">Lila</span></td>
                <td>Conciliar cobros por pago móvil de Cloud Mai</td>
                <td><code>Pagomovil banesco.xls</code><br><code>PAGO MOVIL BANESCO.xls</code><br><code>Pagomovil venezuela.xls</code></td>
            </tr>
            <tr>
                <td><strong>Documentos adicionales ▾</strong><br>→ Pagos manuales</td>
                <td><span class="tag tag-lila">Lila</span></td>
                <td>Conciliar lo que el cajero cargó a mano en Cloud</td>
                <td><code>Pagos Manuales.xls</code></td>
            </tr>
            <tr>
                <td><strong>Documentos adicionales ▾</strong><br>→ Cashea</td>
                <td><span class="tag tag-lila">Lila</span></td>
                <td>Conciliar cobros Cashea con cuentas BNC Cashea</td>
                <td><code>CASCHEA.xlsx</code></td>
            </tr>
            <tr>
                <td><strong>Guía PDF</strong></td>
                <td><span class="tag tag-gris">Gris</span></td>
                <td>Descargar esta guía</td>
                <td>— (no sube archivos)</td>
            </tr>
        </tbody>
    </table>

    <div class="nota">
        <strong>Orden recomendado:</strong> 1) Filtra fechas + banco · 2) sube el extracto con
        <em>Añadir Movimientos</em> · 3) luego los documentos adicionales (pago móvil / manuales / Cashea).
        En documentos adicionales primero verás un <strong>preview</strong>: no se guarda nada hasta que confirmes.
        El extracto sí importa líneas al confirmar la subida.
    </div>

    <div class="no">
        <strong>No mezclar:</strong> el Excel de pago móvil Cloud
        (<code>Pagomovil banesco.xls</code>) <u>no</u> va en «Añadir Movimientos».
        El extracto del banco <u>no</u> va en «Documentos adicionales».
    </div>

    {{-- 1. Extracto --}}
    <h2>1. Botón azul — Añadir Movimientos</h2>
    <p><span class="tag tag-azul">Botón</span> <strong>Añadir Movimientos</strong> (barra superior).</p>
    <p class="paso">Qué subir:</p>
    <p>El <strong>estado de cuenta / movimientos</strong> que descargas del portal del banco
        (Banesco, BNC, Mercantil, Venezuela, etc.).</p>
    <p><strong>Extensiones:</strong> <code>.csv</code> · <code>.txt</code> · <code>.xls</code> · <code>.xlsx</code> ·
        <code>.pdf</code> (Bancaribe) · imagen PNG/JPG (OCR según banco).</p>
    <p class="paso">Ejemplos de nombre:</p>
    <ul>
        <li><code>Estado_de_cuenta_AGUSTIN_JEREZ_2026_11_24.pdf</code></li>
        <li><code>BANESCO JRZ AL 10-08.xls</code></li>
        <li><code>MOV MERCANTIL JRZ AGOSTO.xlsx</code></li>
        <li><code>Conexión Bancaribe Personas.pdf</code></li>
    </ul>
    <p class="paso">Pasos:</p>
    <ol>
        <li>Elige banco y titular de la cuenta.</li>
        <li>Sube el archivo del extracto.</li>
        <li>Confirma: se crean las líneas del banco (abonos y cargos).</li>
    </ol>
    <div class="caja">
        <strong>Columnas típicas por banco</strong> (el sistema ya sabe el formato de cada uno):
        <table>
            <thead><tr><th>Banco</th><th>Columnas</th></tr></thead>
            <tbody>
                <tr><td>BANCAMIGA</td><td>Fecha, Referencia, Descripción, Débito, Crédito</td></tr>
                <tr><td>BANCARIBE</td><td>Fecha; Referencia; Descripción; Cargo; Abono (CSV ;)</td></tr>
                <tr><td>BANESCO</td><td>Fecha | Referencia | Descripción | Monto | Balance</td></tr>
                <tr><td>BBVA</td><td>Fecha; Ref; Desc; Importe; Saldo</td></tr>
                <tr><td>BNC</td><td>Fecha, Descripción, Ref, Debe, Haber (~desde fila 16)</td></tr>
                <tr><td>TESORO</td><td>Fecha, Referencia, Descripción, Cargo, Abono</td></tr>
                <tr><td>VENEZUELA</td><td>Fecha, Referencia, Concepto, Saldo, Monto</td></tr>
                <tr><td>MERCANTIL</td><td>Fecha, Ref, Descripción, Monto (~desde fila 6)</td></tr>
            </tbody>
        </table>
    </div>
    <div class="ok">
        <strong>Qué hace:</strong> carga la base de la conciliación. Después cruzas esas líneas con pago móvil,
        pagos manuales, Cashea, Flujo de caja y lotes. Los abonos LIQ.TARJETA / LIQUIDACION T/ van a
        <em>Lotes punto de venta</em> (pantalla aparte), no aquí.
    </div>

    {{-- 2. Pago móvil --}}
    <h2>2. Botón lila — Documentos adicionales → Pago móvil (Banesco / Venezuela)</h2>
    <p><span class="tag tag-lila">Menú</span> Documentos adicionales → <strong>Pago móvil (Banesco / Venezuela)</strong>.</p>
    <p class="paso">Qué subir:</p>
    <p>El reporte de <strong>Cloud Mai</strong> de cobros por pago móvil
        (título: <em>REPORTE ESPECIFICO / MENSUAL DE BANESCO</em> o <em>… DE BDV</em>).</p>
    <p><strong>Extensiones:</strong> <code>.xls</code> · <code>.xlsx</code></p>
    <p class="paso">Ejemplos de archivo:</p>
    <ul>
        <li><code>Pagomovil banesco.xls</code></li>
        <li><code>PAGO MOVIL BANESCO.xls</code></li>
        <li><code>Pagomovil venezuela.xls</code></li>
    </ul>
    <p class="paso">Pasos:</p>
    <ol>
        <li>Abre Documentos adicionales → Pago móvil.</li>
        <li>Elige el <strong>banco</strong> y el <strong>titular</strong> del extracto (ej. BANESCO · GRUPO JRZ).</li>
        <li>Sube el Excel de Cloud.</li>
        <li>Revisa el preview (cuántos cuadran) y confirma.</li>
    </ol>
    <p><strong>Cabeceras que debe traer el Excel:</strong></p>
    <ul>
        <li>Obligatorias: <code>NRO</code>, <code>FECHA</code>, <code>REFERENCIA PAGO</code>, <code>MONTO</code></li>
        <li>También suelen venir: RIF, ADMINISTRADOR, CAJERO, TERMINAL, LOCALIDAD, BANCO EMISOR, REFERENCIA BANCO, TIPO</li>
    </ul>
    <div class="ok">
        <strong>Qué hace:</strong> busca cada cobro en los abonos de pago móvil del extracto
        (misma referencia y monto, hasta 4 días después) y, al confirmar, los marca conciliados
        como ingresos de Tesorería.
    </div>
    <div class="no">
        <strong>No es:</strong> el extracto del banco, ni un Excel C2P de compra de divisas
        (columnas Pago / Importe / Monto USD). Si subes el archivo equivocado, el sistema avisa
        que no encontró columnas <em>Referencia Pago</em> / <em>Monto</em>.
    </div>

    {{-- 3. Pagos manuales --}}
    <h2>3. Botón lila — Documentos adicionales → Pagos manuales</h2>
    <p><span class="tag tag-lila">Menú</span> Documentos adicionales → <strong>Pagos manuales</strong>.</p>
    <p class="paso">Qué subir:</p>
    <p>El «<strong>Reporte específico de pagos manuales</strong>» de Cloud Mai
        (lo que el cajero registró a mano: transferencia, Zelle, Binance, etc.).</p>
    <p><strong>Extensiones:</strong> <code>.xls</code> · <code>.xlsx</code> · <code>.csv</code></p>
    <p class="paso">Ejemplo de archivo:</p>
    <ul>
        <li><code>Pagos Manuales.xls</code></li>
        <li>También: <code>cloud_pagos_banesco_….xls</code>, <code>cloud_pagos_zelle_….xls</code>, <code>cloud_pagos_binance_….xls</code></li>
    </ul>
    <p class="paso">Pasos:</p>
    <ol>
        <li>Abre Documentos adicionales → Pagos manuales.</li>
        <li>Sube el Excel (no pides banco: el sistema busca en todas las cuentas).</li>
        <li>Revisa el preview y confirma.</li>
    </ol>
    <p><strong>Cabeceras:</strong></p>
    <ul>
        <li>Obligatorias: <code>NRO</code>, <code>Banco Receptor</code>, <code>Ref</code>, <code>Monto Bs.</code>, <code>Fecha</code></li>
        <li>Opcionales: Banco (emisor), Terminal, Cajero, Monto USD / USDT, Fecha Operación</li>
    </ul>
    <div class="ok">
        <strong>Qué hace:</strong> cruza por referencia + monto.
        Si el receptor es «Banco Nacional» → cuentas en bolívares;
        si es Binance / Zelle / Mercantil Panamá → cuentas en divisas.
        En <strong>Conciliación divisas (USD)</strong> también puedes subir este mismo reporte
        junto al PDF/Excel de la cuenta: solo entran los cobros cuyo Banco Receptor coincide
        con la cuenta elegida (Mercantil Panamá, Binance o Zelle→Wells/Citizens/Amerant).
    </div>

    {{-- 4. Cashea --}}
    <h2>4. Botón lila — Documentos adicionales → Cashea</h2>
    <p><span class="tag tag-lila">Menú</span> Documentos adicionales → <strong>Cashea</strong>.</p>
    <p class="paso">Qué subir:</p>
    <p>El <strong>export de pagos de Cashea</strong> (cuotas cobradas por pago móvil a la cuenta Cashea).</p>
    <p><strong>Extensiones:</strong> <code>.xlsx</code> · <code>.xls</code> · <code>.csv</code></p>
    <p class="paso">Ejemplo de archivo:</p>
    <ul>
        <li><code>CASCHEA.xlsx</code></li>
    </ul>
    <p class="paso">Pasos:</p>
    <ol>
        <li>Abre Documentos adicionales → Cashea.</li>
        <li>Sube el export.</li>
        <li>Revisa el preview (cuadran / revisar / sin abono) y confirma.</li>
    </ol>
    <p><strong>Cabeceras:</strong></p>
    <ul>
        <li>Obligatorias: <code>Fecha</code>, <code>Referencia</code>, <code>Debito bs</code></li>
        <li>Opcionales: Usd, Status, Tipo pago, Nro cuota pagada, Orden, Factura, Sucursal, Tasa de cambio, Canal, Monto asignado</li>
    </ul>
    <div class="ok">
        <strong>Qué hace:</strong> agrupa cuotas del mismo pago móvil (misma fecha + referencia) y las cruza
        con abonos de cuentas Cashea (BNC · L.S. CASHEA / Doral Cashea). Ajusta filas de redondeo ~$0,0033.
    </div>

    {{-- Resumen --}}
    <h2>5. Resumen: botón → archivo</h2>
    <table>
        <thead>
            <tr>
                <th>Si tienes este archivo…</th>
                <th>Pulsa este botón</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Estado de cuenta / movimientos del banco</td>
                <td><span class="btn-ejemplo btn-azul">Añadir Movimientos</span></td>
            </tr>
            <tr>
                <td><code>Pagomovil banesco.xls</code> · <code>PAGO MOVIL BANESCO.xls</code> · <code>Pagomovil venezuela.xls</code></td>
                <td><span class="btn-ejemplo btn-lila">Documentos adicionales</span> → Pago móvil</td>
            </tr>
            <tr>
                <td><code>Pagos Manuales.xls</code> (también en Conciliación USD / Mercantil–Zelle–Binance)</td>
                <td><span class="btn-ejemplo btn-lila">Documentos adicionales</span> → Pagos manuales</td>
            </tr>
            <tr>
                <td><code>CASCHEA.xlsx</code></td>
                <td><span class="btn-ejemplo btn-lila">Documentos adicionales</span> → Cashea</td>
            </tr>
        </tbody>
    </table>

    <p class="foot">
        Conciliación de egresos · Guía operativa de botones y documentos.
        Lotes punto de venta / Mercantil se manejan en pantallas aparte.
    </p>
</body>
</html>
