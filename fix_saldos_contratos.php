<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contrato;
use App\Models\ContratoCuota;

echo "Iniciando corrección de saldos de contratos...\n";

$contratos = Contrato::where('activo', true)->where('estado', '!=', 'liquidado')->get();

foreach ($contratos as $contrato) {
    // Calcular el capital restante real
    $sumaAbonosCapital = $contrato->cuotas()->sum('abono_capital');
    $capitalRestanteReal = max(0, (float)$contrato->capital - (float)$sumaAbonosCapital);

    // Actualizar total a pagar
    if (abs((float)$contrato->getRawOriginal('total_a_pagar') - $capitalRestanteReal) > 0.01) {
        echo "Contrato {$contrato->numero_contrato}: Corrigiendo total_a_pagar de {$contrato->getRawOriginal('total_a_pagar')} a {$capitalRestanteReal}\n";
        $contrato->total_a_pagar = $capitalRestanteReal;
        $contrato->save();
    }

    // Calcular la cuota fija correcta basada en el capital restante
    $cuotaFijaCorrecta = (float)$contrato->interes_porcentaje > 0 
        ? round($capitalRestanteReal * (float)$contrato->interes_porcentaje, 2)
        : (float)$contrato->cuota_fija;

    // Actualizar cuota_fija en el contrato si difiere
    if (abs((float)$contrato->cuota_fija - $cuotaFijaCorrecta) > 0.01) {
        echo "  => Corrigiendo cuota_fija de {$contrato->cuota_fija} a {$cuotaFijaCorrecta}\n";
        $contrato->cuota_fija = $cuotaFijaCorrecta;
        $contrato->save();
    }

    // Actualizar las cuotas que están pendientes, parciales o vencidas
    $cuotasARecalcular = $contrato->cuotas()->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])->get();
    
    foreach ($cuotasARecalcular as $cuota) {
        $montoCorrecto = $cuotaFijaCorrecta;
        $saldoCorrecto = max(0, $montoCorrecto - (float)$cuota->monto_pagado);

        $cambios = false;
        if (abs((float)$cuota->monto - $montoCorrecto) > 0.01) {
            $cuota->monto = $montoCorrecto;
            $cambios = true;
        }
        if (abs((float)$cuota->saldo - $saldoCorrecto) > 0.01) {
            $cuota->saldo = $saldoCorrecto;
            $cambios = true;
        }
        
        if ($cambios) {
            echo "  - Cuota {$cuota->numero_cuota} (Estatus: {$cuota->estatus}): Corrigiendo monto/saldo a {$montoCorrecto} / {$saldoCorrecto}\n";
            
            // Si el saldo es 0 y se pagó algo, debería ser pagado
            if ($saldoCorrecto <= 0 && (float)$cuota->monto_pagado > 0) {
                $cuota->estatus = 'pagado';
                echo "    - Cambiando estatus a PAGADO\n";
            }
            
            $cuota->save();
        }
    }
}

echo "Corrección finalizada.\n";
