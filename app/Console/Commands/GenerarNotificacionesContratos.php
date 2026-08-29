<?php

namespace App\Console\Commands;

use App\Models\ContratoCuota;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerarNotificacionesContratos extends Command
{
    protected $signature = 'contratos:notificar';
    protected $description = 'Genera notificaciones automáticas de vencimientos de cuotas de contratos';

    // Reglas: [días relativos => nombre clave, mensaje]
    // Valores negativos = antes del vencimiento, positivos = después
    private array $reglas = [
        -7  => ['tipo' => '7_antes',   'msg' => 'vence en 7 días'],
        -3  => ['tipo' => '3_antes',   'msg' => 'vence en 3 días'],
        0   => ['tipo' => 'dia',       'msg' => 'vence HOY'],
        1   => ['tipo' => '1_despues', 'msg' => 'tiene 1 día vencida'],
        7   => ['tipo' => '7_despues', 'msg' => 'tiene 7 días vencida'],
        15  => ['tipo' => '15_despues','msg' => 'tiene 15 días vencida'],
    ];

    public function handle(): int
    {
        $hoy      = Carbon::today();
        $creadas  = 0;

        // Obtener usuarios que deben recibir notificaciones (admin y cobranza)
        $destinatarios = User::whereIn('role', ['admin', 'cobranza', 'finanzas'])->pluck('id');

        foreach ($this->reglas as $dias => $config) {
            $fechaObjetivo = $hoy->copy()->addDays($dias); // si dias=-7, busca cuotas que vencen en 7 días

            // Para reglas "antes del vencimiento", buscamos fecha_vencimiento = hoy + abs(dias)
            // Para reglas "después del vencimiento", buscamos fecha_vencimiento = hoy - dias
            // Con la forma addDays($dias) y dias negativos: hoy + (-7) = hace 7 días? No.
            // Replanteemos: queremos que si la regla es -7 (7 días antes), la cuota vence en hoy+7
            // Si la regla es +1 (1 día después), la cuota venció ayer = hoy-1
            $fechaBuscada = $hoy->copy()->addDays(-$dias);
            // Ojo: addDays(-(-7)) = addDays(7), correcto: la cuota vence en 7 días
            // addDays(-(1)) = addDays(-1) = ayer, correcto: la cuota venció ayer

            $cuotas = ContratoCuota::with('contrato')
                ->deContratosVigentes()
                ->where('fecha_vencimiento', $fechaBuscada->toDateString())
                ->whereIn('estatus', ['pendiente', 'vencido', 'parcial'])
                ->get();

            foreach ($cuotas as $cuota) {
                if ($cuota->yaNotificado($config['tipo'])) continue;

                $contrato = $cuota->contrato;
                if (!$contrato || $contrato->esLiquidado()) continue;

                $mensaje = "Cuota #{$cuota->numero_cuota} del contrato {$contrato->numero_contrato} ({$contrato->cliente}) {$config['msg']}. Saldo: \${$cuota->saldo}";

                // Notificar al responsable del contrato y a los admins
                $ids = $destinatarios->toArray();
                if ($contrato->responsable_id && !in_array($contrato->responsable_id, $ids)) {
                    $ids[] = $contrato->responsable_id;
                }

                foreach ($ids as $userId) {
                    DB::table('notifications')->insert([
                        'id'              => Str::uuid(),
                        'type'            => 'App\Notifications\ContratoCuotaAlerta',
                        'notifiable_type' => 'App\Models\User',
                        'notifiable_id'   => $userId,
                        'data'            => json_encode([
                            'message'     => $mensaje,
                            'contrato_id' => $contrato->id,
                            'cuota_id'    => $cuota->id,
                            'tipo_alerta' => $config['tipo'],
                            'url'         => '/contratos/' . $contrato->id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $cuota->marcarNotificado($config['tipo']);
                $creadas++;
            }
        }

        // Actualizar cuotas vencidas que aún figuran como pendientes, acumulando la mora
        $cuotasVencidas = ContratoCuota::with('contrato')
            ->where('estatus', 'pendiente')
            ->where('fecha_vencimiento', '<', $hoy)
            ->get();

        foreach ($cuotasVencidas as $cuota) {
            $contrato = $cuota->contrato;
            if ($contrato && $contrato->esLiquidado()) {
                continue;
            }
            if ($contrato && $contrato->estado !== 'liquidado') {
                $saldoCuota = (float) $cuota->saldo;
                $nuevoTotal = (float) $contrato->total_a_pagar + $saldoCuota;
                
                $contrato->update(['total_a_pagar' => $nuevoTotal]);
                $cuota->update([
                    'estatus' => 'vencido',
                    'saldo' => $nuevoTotal // El usuario solicitó que la cuota muestre el total a pagar
                ]);
            } else {
                $cuota->update(['estatus' => 'vencido']);
            }
        }

        $this->info("Notificaciones creadas: {$creadas}. Cuotas vencidas actualizadas.");
        Log::info("[contratos:notificar] {$creadas} notificaciones creadas.");

        return self::SUCCESS;
    }
}
