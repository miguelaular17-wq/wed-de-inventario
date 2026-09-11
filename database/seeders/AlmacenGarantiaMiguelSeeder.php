<?php

namespace Database\Seeders;

use App\Models\StEquipo;
use App\Models\StEquipoEvento;
use App\Models\StOrden;
use App\Models\StOrdenEvento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlmacenGarantiaMiguelSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::query()->where('email', 'miguelaular17@gmail.com')->first()
            ?? User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_GERENTE])->firstOrFail();
        $jorge = User::query()->where('email', 'jorged953@gmail.com')->first();
        $creadas = 0;
        $existentes = 0;

        DB::transaction(function () use ($usuario, $jorge, &$creadas, &$existentes) {
            foreach ($this->filas() as $fila) {
                [$clave, $codigo, $descripcion, $marca, $costo, $sedeOrigen, $sedeDestino, $imei, $serial, $empresa, $observacion, $tecnico] = $fila;
                $empresa = $empresa ? strtoupper($empresa) : null;
                $esGarantia = $empresa !== null;
                $recibido = $esGarantia && str_contains(strtoupper($observacion ?? ''), 'RECIB');
                $fechaEnvio = $this->fechaEnTexto($observacion) ?? Carbon::create(2026, 9, 1, 12);
                $fechaRecibido = $recibido
                    ? ($this->fechaEnTexto($this->textoDesdeRecibido($observacion)) ?? Carbon::create(2026, 9, 1, 12))
                    : null;
                $tecnicoId = str_contains(strtoupper($this->normalizar($tecnico ?? '')), 'JORGE') ? $jorge?->id : null;

                $equipo = StEquipo::query()->firstOrNew($imei ? ['imei' => $imei] : ['serial' => $serial]);
                $equipo->fill([
                    'imei' => $imei,
                    'serial' => $serial,
                    'marca' => $marca,
                    'modelo' => $descripcion,
                    'estado_actual' => $esGarantia && ! $recibido ? StEquipo::ESTADO_EN_TRANSITO : StEquipo::ESTADO_EN_TALLER,
                    'sede_actual' => $sedeDestino,
                    'tipo_dispositivo' => 'celular',
                    'atributos' => array_merge($equipo->atributos ?? [], [
                        'codigo_producto' => $codigo,
                        'costo_importado' => (float) $costo,
                        'sede_origen_importada' => $sedeOrigen,
                        'fuente' => 'Almacen de Garantia enviado a Miguel.xlsx',
                    ]),
                ]);
                $equipo->save();

                $marcaImportacion = '[IMPORT ALMACEN GARANTIA MIGUEL '.$clave.']';
                $orden = StOrden::query()
                    ->where('equipo_id', $equipo->id)
                    ->where('observaciones', $marcaImportacion)
                    ->first();

                if ($orden) {
                    $existentes++;
                    continue;
                }

                $orden = StOrden::crearEnSede([
                    'sede' => $sedeDestino,
                    'tipo_gestion' => $esGarantia ? StOrden::TIPO_GARANTIA : StOrden::TIPO_ST,
                    'tipo_dispositivo' => 'celular',
                    'equipo_id' => $equipo->id,
                    'cliente_nombre' => 'Sin cliente (almacén de garantía)',
                    'equipo' => $descripcion,
                    'imei' => $imei,
                    'serial' => $serial,
                    'falla' => $observacion ?: 'Equipo importado para revisión de Servicio Técnico.',
                    'estado' => $esGarantia ? StOrden::ESTADO_PENDIENTE : StOrden::ESTADO_EN_PROCESO,
                    'prioridad' => 'normal',
                    'fecha_ingreso' => $fechaEnvio->toDateString(),
                    'observaciones' => $marcaImportacion,
                    'valor_dispositivo' => (float) $costo,
                    'tecnico_id' => $tecnicoId,
                    'empresa_envio_garantia' => $empresa,
                    'estado_garantia_externa' => $esGarantia
                        ? ($recibido ? StOrden::GARANTIA_RECIBIDO : StOrden::GARANTIA_ENVIADO)
                        : null,
                    'motivo_envio_garantia' => $esGarantia ? 'Garantía / reparación' : null,
                    'observacion_envio_garantia' => $esGarantia ? ($observacion ?: 'Importado desde almacén de garantía') : null,
                    'garantia_enviado_at' => $esGarantia ? $fechaEnvio : null,
                    'garantia_enviado_por' => $esGarantia ? $usuario->id : null,
                    'garantia_recibido_at' => $fechaRecibido,
                    'garantia_recibido_por' => $fechaRecibido ? $usuario->id : null,
                    'atributos' => [
                        'codigo_producto' => $codigo,
                        'sede_origen_importada' => $sedeOrigen,
                        'tecnico_excel' => $this->normalizar($tecnico),
                    ],
                ], $usuario);

                if ($esGarantia) {
                    $this->eventoOrden($orden, $usuario, 'Equipo enviado a '.$empresa.'. '.$observacion, $fechaEnvio);
                    $this->eventoEquipo($equipo, $orden, $usuario, StEquipoEvento::TIPO_ENVIO, 'Enviado a garantía: '.$empresa, $observacion, $fechaEnvio);
                    if ($fechaRecibido) {
                        $this->eventoOrden($orden, $usuario, 'Equipo recibido nuevamente en sede '.$sedeDestino.'.', $fechaRecibido);
                        $this->eventoEquipo($equipo, $orden, $usuario, StEquipoEvento::TIPO_RECEPCION, 'Recibido de garantía externa', $observacion, $fechaRecibido);
                    }
                } else {
                    $this->eventoOrden($orden, $tecnicoId ? $jorge : $usuario, 'Importado a Servicio Técnico. '.($observacion ?: ''), Carbon::create(2026, 9, 1, 12));
                    $this->eventoEquipo($equipo, $orden, $tecnicoId ? $jorge : $usuario, StEquipoEvento::TIPO_REPARACION, 'En servicio técnico', $observacion, Carbon::create(2026, 9, 1, 12));
                }
                $creadas++;
            }
        });

        $this->command?->info("Almacén de garantía: {$creadas} órdenes creadas; {$existentes} ya existían.");
        $this->command?->warn('Se omitieron 2 filas conflictivas: IMEI 865026082266205 y 359897654532742 aparecían asociados a dos equipos distintos.');
    }

    private function eventoOrden(StOrden $orden, User $usuario, string $descripcion, Carbon $fecha): void
    {
        StOrdenEvento::query()->firstOrCreate([
            'orden_id' => $orden->id,
            'tipo' => StOrdenEvento::TIPO_GARANTIA_EXTERNA,
            'descripcion' => $descripcion,
        ], [
            'user_id' => $usuario->id,
            'created_at' => $fecha,
        ]);
    }

    private function eventoEquipo(StEquipo $equipo, StOrden $orden, User $usuario, string $tipo, string $titulo, ?string $descripcion, Carbon $fecha): void
    {
        StEquipoEvento::query()->firstOrCreate([
            'equipo_id' => $equipo->id,
            'orden_id' => $orden->id,
            'titulo' => $titulo,
        ], [
            'user_id' => $usuario->id,
            'sede' => $orden->sede,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'payload' => ['importacion' => 'almacen_garantia_miguel'],
            'created_at' => $fecha,
        ]);
    }

    private function fechaEnTexto(?string $texto): ?Carbon
    {
        if (! $texto || ! preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $texto, $m)) {
            return null;
        }
        $anio = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 2026;
        if ($anio < 100) {
            $anio += 2000;
        }

        return Carbon::create($anio, (int) $m[2], (int) $m[1], 12);
    }

    private function textoDesdeRecibido(?string $texto): ?string
    {
        if (! $texto || ($pos = stripos($texto, 'RECIB')) === false) {
            return null;
        }

        return substr($texto, $pos);
    }

    private function normalizar(?string $texto): ?string
    {
        return $texto === null ? null : str_replace('�', 'í', trim($texto));
    }

    /**
     * Columnas: clave, código, descripción, marca, costo, sede origen, sede destino,
     * IMEI, serial, empresa, observación y técnico.
     *
     * @return list<array<int, string|null>>
     */
    private function filas(): array
    {
        $csv = <<<'DATA'
354844858145264|8806099061821|SAMSUNG GALAXY A37 5G 6/128GB GREEN|SAMSUNG|260|CENTRO|CENTRO|354844858145264||GLOBAL FIT|En servicio Tecnico Global fit 30/08|
865026082266205|6932554445027|REDMI 15 8/256GB MIDNIGHT BLACK|XIAOMI|164|SAMBIL|CENTRO|865026082266205||GLOBAL FIT|Enviado 24/08 Sambil Giovanny Global Fit|
867754081103826|6932554444815|REDMI 15C 4/256GB NEGRO OCASO|XIAOMI|118|CENTRO|CENTRO|867754081103826||GLOBAL FIT|enviado a global fit 30/08/ RECIBIDO GLOBAL FIT|
864076082404208|6932554482367|REDMI NOTE 15 8/256GB PURPLE|XIAOMI|172|CENTRO|CENTRO|864076082404208||GLOBAL FIT|enviado a global fit el 05/08 por Carlos Gomez|
350057712517081|8806097058779|SAMSUNG GALAXY A36 5G 8/256GB LAVENDER|SAMSUNG|265|CENTRO|CENTRO|350057712517081||GLOBAL FIT|enviado a global fit el 28/08 por Carlos Gomez|
866277088485427|6932554455903|REDMI 15C 4/128GB VERDE MENTA|XIAOMI|91|CENTRO|CENTRO|866277088485427||GLOBAL FIT|enviado a global fit el 28/08 por Carlos Gomez/RECIBIDO GLOBAL FIT|
867754086490467|6932554455941|REDMI 15C 4/256GB VERDE MENTA|XIAOMI|118|CENTRO|CENTRO|867754086490467||GLOBAL FIT|enviado a global fit el 28/08 por Carlos Gomez/ RECIBIDO GLOBAL FIT|
867754083632525|6932554455941|REDMI 15C 4/256GB VERDE MENTA|XIAOMI|172|ZAMORA|CENTRO|867754083632525||GLOBAL FIT|enviado a global fit el 28/08 por Carlos Gomez|
865327077110204|6932554456016|REDMI 15C 4/128GB MOONLIGHT BLUE|XIAOMI|91|CENTRO|CENTRO|865327077110204||GLOBAL FIT|enviado a global fit el 28/08 por Carlos Gomez/ RECIBIDO GLOBAL FIT|
353078419988836|840493602332|MOTOROLA G06 4/64GB VERDE|MOTOROLA|88|JRZ|CENTRO|353078419988836||GLOBAL FIT|Enviar a global fit/ Fisico Jorge servicio tecnico|
355397379688923|8806095753164|SAMSUNG GALAXY A06 64GB/4GB DS BLACK|SAMSUNG|79.38|CENTRO|CENTRO|355397379688923||GLOBAL FIT|global fit 05/08 enviado|
350455587548704|8806095828886|SAMSUNG A16 4/128GB LTE BLACK|SAMSUNG|123|CENTRO|CENTRO|350455587548704||GLOBAL FIT|global fit 05/08 enviado/ RECIBIDO DE GLOBAL FIT 07/08/2026|
351468680880436|8806097667995|SAMSUNG GALAXY A17 4/128GB GRAY|SAMSUNG|151|CENTRO|CENTRO|351468680880436||GLOBAL FIT|global fit 05/08 enviado|
864656087145821|6936520877249|HONOR PLAY 10A 3/64GB MORADO ESTELAR|HONOR|85.5|CENTRO|CENTRO|864656087145821||HONOR|Entregado a Honor Pendiente por reconocer para que den NC|
867306088548048|6936520885145|HONOR MAGIC 8 LITE 8/256GB LITE GREEN|HONOR|342|VIRTUDES|CENTRO|867306088548048||HONOR|Entregado a Honor Pendiente por reconocer para que den NC|
359055976024043|4894947045387|INFINIX HOT 50 PRO 8+256GB SLEEK BLACK|INFINIX|133|CENTRO|CENTRO|359055976024043|||Dev cliente en sistema 2025 mes Junio|
351095813215097|195949036118|IPHONE 15 128GB PINK DS|IPHONE S/NUEVOS|573|CENTRO|CENTRO|351095813215097|||entregado a Hermana de leo para USA/ no esta el fisico|
354423233613062|195949805066|IPHONE 16 PRO MAX 256GB DESERT TITANIUM ESIM|IPHONE S/NUEVOS|820|CENTRO|CENTRO|354423233613062|||Imei inactivo no esta en uso. Problemas en placa/ no esta el fisico|
357113740108519|8806097661481|SAMSUNG GALAXY A07 4/128GB BLACK|SAMSUNG|101|VIRTUDES|CENTRO|357113740108519||GLOBAL FIT|Mal funcionamiento de la camara reportado al grupo/ RECIBIDO DE GLOBAL FIT 07/08/2026|
352041713701512|8806097058021|SAMSUNG GALAXY A36 5G 8/256GB AWESOME WHITE|SAMSUNG|265|CENTRO|CENTRO|352041713701512|||posiblemente en global fit|
868820088930948|6932554425296|REDMI A5 3/64GB OCEAN BLUE|XIAOMI|70|CENTRO|CENTRO|868820088930948|||Posiblemente en global fit|
352884191712257|4894947092565|INFINIX HOT 60 PRO PLUS 8/256GB SLEEK BLACK|INFINIX|190|JRZ|CENTRO|352884191712257|||FISICO SERVICIO TECNICO JORGE|Jorge Díaz
359897654532742|4894947105272|TECNO SPARK GO 3 4/128GB BLACK|TECNO SPARK|94|VIRTUDES|CENTRO|359897654532742|||FISICO SERVICIO TECNICO JORGE|Jorge Díaz
359207791111436|4894947084508|INFINIX SMART 10 4/128GB IRIS BLUE|INFINIX|74|CENTRO|CENTRO|359207791111436|||FISICO SERVICIO TECNICO JORGE|Jorge Díaz
353458580884784|4894947093784|INFINIX HOT 60I 8/256GB MEADOW GREEN|INFINIX|109|CENTRO|CENTRO|353458580884784|||FISICO SERVICIO TECNICO JORGE|Jorge Díaz
350808237687233|IPHONE14128GAZUL|IPHONE 14 128GB CLASE A AZUL|IPHONE S/NUEVOS|287|DORAL|CENTRO|350808237687233|||Reparacion Servicio tecnico deiby|DEYBE ZAMBRANO
353990992111307|IPH13PRO250G|IPHONE 13 PRO 256GB CLASE A GRIS|IPHONE S/NUEVOS|368|CENTRO|CENTRO|353990992111307|||Reparacion Servicio tecnico Deiby|DEYBE ZAMBRANO
15PROMAX256GWHITESIM|15PROMAX256GWHITESIM|IPHONE 15 PRO MAX 256GB WHITE TITANIUM ESIM|IPHONE S/NUEVOS|1021|JRZ|CENTRO||15PROMAX256GWHITESIM||Reparacion Servicio tecnico deiby problema de imei|DEYBE ZAMBRANO
350057712518022|8806097058779|SAMSUNG GALAXY A36 5G 8/256GB LAVENDER|SAMSUNG|265|DORAL|CENTRO|350057712518022||GLOBAL FIT|Reparacion Servicio tecnico Jorge/ RECIBIDO GLOBAL FIT 07/08|Jorge Díaz
863954073602587|6932554407650|REDMI NOTE 14 6/128GB OCEAN BLUE|XIAOMI|242|CENTRO|CENTRO|863954073602587||||Jorge Díaz
863954073613261|6932554407650|REDMI NOTE 14 6/128GB OCEAN BLUE|XIAOMI|242|CENTRO|CENTRO|863954073613261||||Jorge Díaz
869507071203743|6932554405236|POCO X7 8/256GB BLACK|XIAOMI|197|CENTRO|CENTRO|869507071203743||||Jorge Díaz
866939085664409|6932554466688|REDMI 15C 8/256GB MIDNIGTH BLACK|XIAOMI|135|CENTRO|CENTRO|866939085664409||||Jorge Díaz
866277082986404|6932554444853|REDMI 15C 4/128GB NEGRO OCASO|XIAOMI|182|CENTRO|CENTRO|866277082986404||||Jorge Díaz
866277083070166|6932554444853|REDMI 15C 4/128GB NEGRO OCASO|XIAOMI|182|CENTRO|CENTRO|866277083070166||||Jorge Díaz
863231086379705|6932554481209|POCO M8 PRO 5G 8/256GB BLACK|XIAOMI|233|CENTRO|CENTRO|863231086379705||||Jorge Díaz
861415079057541|6932554405366|POCO X7 12/512GB GREEN|XIAOMI|226|ZAMORA|CENTRO|861415079057541||||Jorge Díaz
350701670470441|848958044635|BLU G45 12/128GB BLUE|BLU|85|JRZ|CENTRO|350701670470441||TECNOTROPOLIS|Enviado Tecnotroplis 01/09/26 Edward.|
354741530144287|848958044581|BLU K2 8/64GB BLACK|BLU|66|JRZ|CENTRO|354741530144287||TECNOTROPOLIS|Enviado Tecnotroplis 01/09/26 Edward.|
350486502116152|4894947096334|TECNO SPARK 40 PRO+ 8/256GB NEBULA BLACK|TECNO SPARK|156|JRZ|CENTRO|350486502116152||TECNOTROPOLIS|Enviado Tecnotroplis 01/09/26 Edward.|
350486502116533|4894947096334|TECNO SPARK 40 PRO+ 8/256GB NEBULA BLACK|TECNO SPARK|156|JRZ|CENTRO|350486502116533||TECNOTROPOLIS|Enviado Tecnotroplis 01/09/26 Edward.|
354741530394783|848958044598|BLU K2 8/64GB BLUE|BLU|67|JRZ|CENTRO|354741530394783||TECNOTROPOLIS|Enviado Tecnotroplis 01/09/26 Edward.|
353458584190576|4894947093784|INFINIX HOT 60I 8/256GB MEADOW GREEN|INFINIX|109|VIRTUDES|CENTRO|353458584190576||TOTALINK|FISICO SERVICIO TECNICO JORGE|
354553701433724|4894947104657|ITEL A100C 3+64GB BLACK A6611L|ITEL|69|JRZ|CENTRO|354553701433724||TOTALINK|Garantia TOTAL LINK. Fisico con Jorge Servicio tecnico|
354553701658577|4894947104657|ITEL A100C 3+64GB BLACK A6611L|ITEL|69|JRZ|CENTRO|354553701658577||TOTALINK|Garantia TOTAL LINK. Fisico con Jorge Servicio tecnico|
354553704830561|4894947127120|ITEL A100CS 3+128GB WHITE|ITEL|79|JRZ|CENTRO|354553704830561||TOTALINK|Garantia TOTAL LINK. Fisico con Jorge Servicio tecnico|
352764282465231|4894947115776|ITEL A200 3+128GB NIGHTLY BLUE|ITEL|93|JRZ|CENTRO|352764282465231||TOTALINK|Garantia TOTAL LINK. Fisico con Jorge Servicio tecnico|
DATA;

        return collect(explode("\n", trim($csv)))
            ->map(fn (string $linea) => array_map(fn (string $valor) => $valor === '' ? null : $this->normalizar($valor), explode('|', $linea)))
            ->all();
    }
}
