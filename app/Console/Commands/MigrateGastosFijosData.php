<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GastoFijo;
use App\Models\GastoFijoConfig;
use App\Models\GastoFijoCustom;
use App\Models\GastoFijoOculto;
use App\Models\GastoFijoPago;

class MigrateGastosFijosData extends Command
{
    protected $signature = 'gastos:migrate-hardcoded';
    protected $description = 'Migrate hardcoded Gastos Fijos array into the DB';

    public function handle()
    {
        $this->info('Iniciando migracion de gastos fijos...');

        $tabla1 = [
            'filas' => [
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO TERRANOVA','costo'=>55.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO BALCONES','costo'=>10.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO BALCONES APPTO T4','costo'=>15.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO TERRAZAS CLUB GOLF','costo'=>25.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'AVISO DE COBRANZAS','empresa'=>'CONDOMINIO APPTO SALAMAR','costo'=>200.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO LOCAL SAMBIL L-26','costo'=>450.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO LOCAL SAMBIL L-94','costo'=>300.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'8','empresa'=>'CONDOMINIO LOCAL PA 14','costo'=>150.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'1 DE CADA MES','empresa'=>'CONDOMINIO MIRASOL','costo'=>70.00],
                ['servicio'=>'INTERNET','fecha'=>'17 DE CADA MES','empresa'=>'BESSER SOLUTIONS MIRASOL','costo'=>28.00],
                ['servicio'=>'ELECTRICIDAD','fecha'=>'','empresa'=>'','costo'=>0],
                ['servicio'=>'ELECTRICIDAD','fecha'=>'1ERO D/C MES','empresa'=>'CORPOELEC CASA PUERTA MARAVEN','costo'=>21.00],
            ],
        ];

        $tabla2 = [
            'filas' => [
                ['sede'=>'INVERSIONES DORAL PARAGUANÁ, C.A. PRINCIPAL J401722296','servicio'=>'INTERNET LOCALES PB-09 Y PB-10','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCALES PB-09 Y PB-10','fecha'=>'8','empresa'=>'CONDOMINIO CENTRO COMERCIAL DORAL','costo'=>380.00],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO LOCALES PB-09 Y PB-10','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCALES PB-09 Y PB-10','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>40.00],
                ['sede'=>'','servicio'=>'ALQUILER LOCALES PB-09 PB-10','fecha'=>'1 - 15 de cada mes','empresa'=>'DESCARGADORES MARITIMOS','costo'=>1100.00],
                ['sede'=>'','servicio'=>'PUBLICIDAD REDES','fecha'=>'1-5 de Cadmes','empresa'=>'ZINLI','costo'=>100.00],
                ['sede'=>'','servicio'=>'PUBLICIDAD REDES','fecha'=>'30','empresa'=>'GEEK ELECTRONICO (ADONIS)','costo'=>160.00],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'24','empresa'=>'EDUARDO VASQUEZ','costo'=>60.00],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'15','empresa'=>'EMIRO BRAVO','costo'=>60.00],
                ['sede'=>'','servicio'=>'PUBLICIDAD RADIAL','fecha'=>'1','empresa'=>'HIT FM (LENIS)','costo'=>80.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONOS CORPORATIVOS','fecha'=>'1','empresa'=>'CORPORACION DIGITEL','costo'=>200.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CHOFER','fecha'=>'15 DE CADA MES','empresa'=>'LUIS GARCIA','costo'=>10.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CHOFER','fecha'=>'17','empresa'=>'GREGORIO COLINA','costo'=>13.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO REDES','fecha'=>'10 de cada mes','empresa'=>'REDES DORAL','costo'=>5.00],
                ['sede'=>'','servicio'=>'MERCADO LIBRE','fecha'=>'5 de cada mes','empresa'=>'IMPUESTO MENSUAL POR VENTAS','costo'=>40.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'TU MARCA CLOUD MAI SERVICES, C.A.','fecha'=>'','empresa'=>'RICARDO MAITA','costo'=>55.00],

                ['sede'=>'INVERSIONES DORAL PARAGUANÁ, C.A. SUCURSAL SAMBIL J401722296','servicio'=>'CONDOMINIO LOCAL L-114','fecha'=>'31-15 de cada mes','empresa'=>'A.S. 20 PARAGUANÁ, C.A.','costo'=>200.00],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL L-114','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO ENCARGADO SAMBIL','fecha'=>'15 DE CADA MES','empresa'=>'AURELES LUGO','costo'=>5.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'INTERNET LOCAL L-114','fecha'=>'1-5 de Cada mes','empresa'=>'BESSER SOLUTIONS','costo'=>49.88],
                ['sede'=>'','servicio'=>'INTERNET LOCAL H-6','fecha'=>'15','empresa'=>'BESSER SOLUTIONS','costo'=>78.88],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL H-6','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00],

                ['sede'=>'LNACEH SPORT, C.A. PRINCIPAL J409254852','servicio'=>'CONDOMINIO LOCAL H-6','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>800.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL H-12','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>300.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL H-6','fecha'=>'','empresa'=>'INVERSIONES MILLENIUM','costo'=>1566.00],

                ['sede'=>'LNACEH SPORT, C.A. SUCURSAL BOLIVAR J409254852','servicio'=>'INTERNET LOCAL HADI 3000','fecha'=>'1 - 5 de Cada mes','empresa'=>'AIRTEK','costo'=>60.00],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO LOCAL HADI 3000','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL HADI 3000','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00],
                ['sede'=>'','servicio'=>'CUSTODIA LOCAL HADI 3000','fecha'=>'TODOS LOS LUNES','empresa'=>'POLICARUBANA','costo'=>30.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL HADI 3000','fecha'=>'1 - 11 de cada mes','empresa'=>'MOHAMED NAIMM','costo'=>1600.00],

                ['sede'=>'LNACEH SPORT, C.A. SUCURSAL ZAMORA J409254852','servicio'=>'RECARGA TELEFONO TELEFONIA ZAMORA','fecha'=>'12 de cada mes','empresa'=>'AURELES LUGO','costo'=>5.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO SUPERVISOR 2 ZAMORA','fecha'=>'13 de cada mes','empresa'=>'AURELES LUGO','costo'=>5.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO SUPERVISOR ZAMORA','fecha'=>'14 de cada mes','empresa'=>'CARLOS GOMEZ','costo'=>5.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO CAJA ZAMORA','fecha'=>'14 de cada mes','empresa'=>'CARLOS GOMEZ','costo'=>5.00],
                ['sede'=>'','servicio'=>'ALQUILER LOCAL SHANGHAI','fecha'=>'30 de cada mes','empresa'=>'JESUS SANCHEZ','costo'=>450.00],
                ['sede'=>'','servicio'=>'ELECTRICIDAD Y RELLENO SHANGHAI','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>180.00],
                ['sede'=>'','servicio'=>'ASEO URBANO LOCAL SHANGHAI','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00],
                ['sede'=>'','servicio'=>'AGUA LOCAL SHANGHAI','fecha'=>'AVISO DEL GESTOR','empresa'=>'HIDROFALCÓN','costo'=>15.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'INTERNET LOCAL SHANGHAI','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>60.00],

                ['sede'=>'OFICINAS ADMINISTRACION','servicio'=>'ELECTRICIDAD Y RELLENO LOCAL PA-22','fecha'=>'5 D/C MES','empresa'=>'CORPOELEC / PROTECNIA FALCON','costo'=>100.00],
                ['sede'=>'','servicio'=>'INTERNET LOCAL PA-22','fecha'=>'1-5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL PA-22','fecha'=>'8','empresa'=>'CONDOMINIO CENTRO COMERCIAL DORAL','costo'=>150.00],
                ['sede'=>'','servicio'=>'INTERNET LOCAL L-11','fecha'=>'15','empresa'=>'BESSER SOLUTIONS','costo'=>35.00],
                ['sede'=>'','servicio'=>'RECARGA TELEFONO ENCARGADO NUNES','fecha'=>'17 DE CADA MES','empresa'=>'NUNES STORE','costo'=>0.50],

                ['sede'=>'NUNES STORE, C.A. J501653879','servicio'=>'ASEO URBANO LOCAL L-11 / H-12','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>20.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL L-11','fecha'=>'31-15 de cada mes','empresa'=>'A.S. 20 PARAGUANÁ, C.A.','costo'=>800.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>25.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL H-12','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>500.00],

                ['sede'=>'GRUPO JRZ TECH ELECTRONICS, C.A. J501653895','servicio'=>'ELECTRICIDAD Y RELLENO DEPÓSITO','fecha'=>'1ERO D/C MES','empresa'=>'CORPOELEC','costo'=>2.50],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>50.00],
                ['sede'=>'','servicio'=>'INTERNET DEPÓSITO DOÑA EMILIA','fecha'=>'1 - 5 de cada mes','empresa'=>'AIRTEK','costo'=>60.00],
                ['sede'=>'','servicio'=>'CONDOMINIO LOCAL M1-2','fecha'=>'8-15 de cada mes','empresa'=>'CONDOMINIO CENTRO COMERCIAL VIRTUDES','costo'=>230.00],

                ['sede'=>'EURONISSI, C.A. J412919512 (TIENDA MOVISTAR)','servicio'=>'ASEO URBANO LOCAL M1-2','fecha'=>'AVISO DE SUMITCA','empresa'=>'SUMITCA','costo'=>12.00],
                ['sede'=>'','servicio'=>'MONITOREO Y SOPORTE SERVIDOR','fecha'=>'1 AL 5 DE CADA MES','empresa'=>'INFORMATICA UNIX','costo'=>25.00],
                ['sede'=>'','servicio'=>'INTERNET LOCAL M1-2','fecha'=>'1-5 de Cadmes','empresa'=>'BESSER SOLUTIONS','costo'=>67.60],

                ['sede'=>'GALPON BELLA VISTA V32089692','servicio'=>'INTERNET GALPON','fecha'=>'1 - 5 de Cada mes','empresa'=>'AIRTEK','costo'=>30.00],
            ],
        ];

        $tabla3 = [
            'filas' => [
                ['servicio'=>'RECARGA TELEFONICA DIGITEL','fecha'=>'1 de cada Mes','empresa'=>'ABONO A NRO TLF PERSONAL BS.2000','costo'=>20.00],
                ['servicio'=>'INTERNET','fecha'=>'3 de cada Mes','empresa'=>'BESSER SOLUTIONS DIRECTIVO','costo'=>28.00],
                ['servicio'=>'INTERNET','fecha'=>'3 de cada Mes','empresa'=>'BESSER SOLUTIONS MARIA FATIMA','costo'=>25.00],
                ['servicio'=>'CONDOMINIO','fecha'=>'1-5 de cada mes','empresa'=>'CONDOMINIO SAN ROMAN','costo'=>100.00],
                ['servicio'=>'POLIZA DE SEGUROS','fecha'=>'20 de cada mes','empresa'=>'MERCANTIL SEGUROS','costo'=>225.92],
                ['servicio'=>'AYUDA','fecha'=>'SABADO','empresa'=>'MARTA (TIA)','costo'=>160.00],
                ['servicio'=>'AYUDA','fecha'=>'VIERNES','empresa'=>'AGUSTIN JEREZ (PAPA)','costo'=>400.00],
                ['servicio'=>'AYUDA','fecha'=>'LUNES','empresa'=>'MARBETH JEREZ (HERMANA)','costo'=>400.00],
                ['servicio'=>'COLEGIO NAHOMI','fecha'=>'5 de cada mes','empresa'=>'U.E. NUESTRA SEÑORA DEL CARMEN','costo'=>120.00],
                ['servicio'=>'COLEGIO CESAR','fecha'=>'','empresa'=>'CENTRO CIVICO CARDON (U.E. COLEGIO)','costo'=>140.00],
                ['servicio'=>'TAREAS DIRIGIDAS CESAR','fecha'=>'','empresa'=>'','costo'=>50.00],
                ['servicio'=>'INGLES CESAR','fecha'=>'','empresa'=>'LANGUAGE CENTER','costo'=>60.00],
                ['servicio'=>'NATACION CESAR','fecha'=>'','empresa'=>'AQUA CLUB','costo'=>45.00],
            ],
        ];

        $tablas = [$tabla1, $tabla2, $tabla3];

        $configOverrides = GastoFijoConfig::all();
        $configMap = [];
        foreach ($configOverrides as $cfg) {
            $configMap["{$cfg->tabla_idx}_{$cfg->fila_idx}"] = $cfg;
        }

        $ocultosDb = GastoFijoOculto::all();
        $ocultosMap = [];
        foreach ($ocultosDb as $o) {
            $ocultosMap["{$o->tabla_idx}_{$o->fila_idx}"] = true;
        }

        foreach ($tablas as $tIdx => $tabla) {
            $orden = 0;
            $currentSede = '';

            foreach ($tabla['filas'] as $fIdx => $fila) {
                // Sede handling
                if (isset($fila['sede']) && $fila['sede'] !== '') {
                    $currentSede = $fila['sede'];
                }

                $fecha = $fila['fecha'] ?? '';
                $costo = $fila['costo'] ?? 0;

                $configKey = "{$tIdx}_{$fIdx}";
                if (isset($configMap[$configKey])) {
                    if ($configMap[$configKey]->fecha !== null && $configMap[$configKey]->fecha !== '') {
                        $fecha = $configMap[$configKey]->fecha;
                    }
                    if ($configMap[$configKey]->costo !== null) {
                        $costo = (float) $configMap[$configKey]->costo;
                    }
                }

                $visible = !isset($ocultosMap[$configKey]);

                $gasto = GastoFijo::create([
                    'grupo_id' => $tIdx,
                    'sede' => $currentSede,
                    'servicio' => $fila['servicio'] ?? '',
                    'fecha' => $fecha,
                    'empresa' => $fila['empresa'] ?? '',
                    'costo' => $costo,
                    'orden' => $orden++,
                    'visible' => $visible,
                ]);

                // Update payments for this row
                GastoFijoPago::where('tabla_idx', $tIdx)
                    ->where('fila_idx', $fIdx)
                    ->update(['gasto_fijo_id' => $gasto->id]);
            }
        }

        $this->info('Hardcoded data migracion completada.');

        // Custom rows migration
        $customs = GastoFijoCustom::orderBy('id')->get();
        foreach ($customs as $custom) {
            $gasto = GastoFijo::create([
                'grupo_id' => $custom->tabla_idx,
                'sede' => $custom->sede ?? '',
                'servicio' => $custom->servicio ?? '',
                'fecha' => $custom->fecha ?? '',
                'empresa' => $custom->empresa ?? '',
                'costo' => $custom->costo ?? 0,
                'orden' => 9999, // push to end
                'visible' => true,
            ]);

            // For custom rows, fila_idx is 50000 + id
            $customFilaIdx = 50000 + $custom->id;
            if ($customFilaIdx <= 32767) {
                GastoFijoPago::where('tabla_idx', $custom->tabla_idx)
                    ->where('fila_idx', $customFilaIdx)
                    ->update(['gasto_fijo_id' => $gasto->id]);
            }
        }

        $this->info('Custom data migracion completada.');
    }
}
