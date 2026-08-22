<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use Illuminate\Database\Seeder;

class NominaPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NominaCatalogSeeder::class);
        $sedes = NominaSede::query()->get()->keyBy('codigo');

        foreach (self::personal() as [$cedula, $nombre, $codigoSede]) {
            $cliente = Cliente::query()->updateOrCreate(
                ['cedula' => $cedula],
                ['nombre' => $nombre]
            );
            $sede = $sedes->get($codigoSede);

            $empleado = NominaEmpleado::query()->firstOrNew(['cliente_id' => $cliente->id]);
            $empleado->sede_id = $sede?->id;
            $empleado->sede = $codigoSede;
            $empleado->estado = 'ACTIVO';
            if (! $empleado->exists) {
                $empleado->salario_base = 0;
                $empleado->tipo_salario = 'QUINCENAL';
                $empleado->modo_comision = NominaEmpleado::COMISION_NINGUNA;
                $empleado->es_supervisor = false;
                $empleado->es_servicio_tecnico = false;
            }
            $empleado->save();
        }
    }

    /**
     * Fuente: RELACION DEL PERSONAL PALACIO DE LOS DETALLES.xlsx.
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    public static function personal(): array
    {
        return [
            ['19648944', 'CARLOS GOMEZ', 'CENTRO'],
            ['28501943', 'GEOVANNI GUTIERREZ', 'CENTRO'],
            ['18479961', 'FRANCISCO AULAR', 'CENTRO'],
            ['31422012', 'JHATARY LUQUEZ', 'CENTRO'],
            ['32431800', 'DANIELA DIAZ', 'CENTRO'],
            ['31234942', 'GABRIELA GARCIA', 'CENTRO'],
            ['28766449', 'AMBAR CUAURO', 'CENTRO'],
            ['31475493', 'YOSILIS LUGO', 'CENTRO'],
            ['28369045', 'YOSEMAR MAVAREZ', 'CENTRO'],
            ['28771171', 'WENDY AÑEZ', 'CENTRO'],
            ['31560045', 'CAROLINA QUERO', 'CENTRO'],
            ['34430262', 'IRAIDA DIAZ', 'CENTRO'],
            ['26598293', 'JOSMARLY VELAZQUEZ', 'DORAL'],
            ['26930051', 'JORGE DIAZ', 'CENTRO'],
            ['32553909', 'DANIEL SARMIENTO', 'CENTRO'],
            ['27811139', 'FRAILING SANCHEZ', 'VIRTUDES'],
            ['32565039', 'MARIANGELA DIAZ', 'EGRESOS'],
            ['30248629', 'EMILY PULGAR', 'DORAL'],
            ['31629058', 'GEORGINA ZAVALA', 'DORAL'],
            ['25010760', 'ANGEL JIMENEZ', 'DORAL'],
            ['28695505', 'ANDREA CHIQUITO', 'DORAL'],
            ['27273261', 'YORGELIS ROMAN', 'DORAL'],
            ['31603843', 'DANIBEI ALMEIDA', 'DORAL'],
            ['31852005', 'JUAN PABLO', 'VIRTUDES'],
            ['28772921', 'WANDA VENTURA', 'DORAL'],
            ['28776417', 'ISAMAR ROMAN', 'DORAL'],
            ['27924198', 'YESSICA SOUSA', 'DORAL'],
            ['26656789', 'JOSE SENIOR', 'DORAL'],
            ['28340171', 'MELANNY CARRASQUERO', 'DORAL'],
            ['28369458', 'ALEJANDRA SALAS', 'ZAMORA'],
            ['18426259', 'ANDRES QUEVEDO', 'ZAMORA'],
            ['33055326', 'GRATEROL SARA', 'ZAMORA'],
            ['27844739', 'IVANIA SANCHEZ', 'ZAMORA'],
            ['31446406', 'ADRIANNY MARTINEZ', 'ZAMORA'],
            ['28769767', 'JHONALSY NAVAS', 'ZAMORA'],
            ['28769916', 'ANDRUELYS HURTADO', 'ZAMORA'],
            ['27420927', 'JAVIER LEAL', 'INVENTARIO'],
            ['31629048', 'JHOSMARLYN SANCHEZ', 'ZAMORA'],
            ['23675095', 'OSWALDO BERMUDEZ', 'ZAMORA'],
            ['27420921', 'OSCAR PALOMO', 'SAMBIL'],
            ['31234681', 'YORGELIS VELASQUEZ', 'SAMBIL'],
            ['19944238', 'FRANCIS MARIN', 'VIRTUDES'],
            ['26309612', 'OCTAVIO LUGO', 'VIRTUDES'],
            ['32247446', 'HANNAD ROBERTIS', 'MOVISTAR'],
            ['33600138', 'HADZAY VENTURA', 'VIRTUDES'],
            ['31489611', 'BRISNEIDYS SALAZAR', 'VIRTUDES'],
            ['31789963', 'CARLOS EDUARDO REYES', 'VIRTUDES'],
            ['16439637', 'ZAMBRANO DEYBE', 'VIRTUDES'],
            ['31240573', 'VALENTINA MARIN', 'VIRTUDES'],
            ['30490783', 'MARIARGEN ZAVARCE', 'VIRTUDES'],
            ['30490950', 'CARLOS MENDEZ', 'DEPOSITO'],
            ['31901317', 'ISLENA NARANJO', 'CALL_CENTER'],
            ['28382238', 'MARIA PETIT', 'CALL_CENTER'],
            ['31943520', 'MARIA RAMONES', 'MARKETING'],
            ['28723018', 'BARBARA LOPEZ', 'CALL_CENTER'],
            ['32689028', 'EILEEN ABREU', 'CENTRO'],
            ['25470483', 'ABEL YAJURI', 'MARKETING'],
            ['28046742', 'JAIR ALEXANDER AVILA', 'CALL_CENTER'],
            ['22898423', 'EDWAR MAVO', 'COMPRAS'],
            ['21155688', 'PAOLA COLINA', 'COMPRAS'],
            ['20552705', 'MARIA POLANCO', 'CONTABILIDAD'],
            ['16198138', 'CARLOS RUIZ', 'CONTABILIDAD'],
            ['32027142', 'DAGMAR JEREZ', 'ADMINISTRACION'],
            ['28369203', 'PATRICIA COLINA', 'MARKETING'],
            ['24305868', 'GUSMALYS MEDINA', 'TESORERIA'],
            ['26058100', 'JOSE SEMECO', 'FINANZAS'],
            ['24306562', 'DAYANA LOPEZ', 'CUENTAS_COBRAR'],
            ['17500110', 'DIANA BUSTILLO', 'RRHH'],
            ['18632452', 'CARLOS SEMECO', 'COMPRAS'],
            ['28715593', 'VALENTINA GONZALEZ', 'MARKETING'],
            ['28766068', 'MIGUEL AULAR', 'MARKETING'],
            ['26058437', 'JOSEPH BRACHO', 'SIN_ASIGNAR'],
            ['26218256', 'MANUEL RUIZ', 'INVENTARIO'],
            ['25402263', 'BRANDON SANCHEZ', 'INVENTARIO'],
            ['30657986', 'RICARDO GIMENEZ', 'INVENTARIO'],
            ['20796305', 'FADYS TORO', 'INVENTARIO'],
            ['20797535', 'JONATHAN SANCHEZ', 'DEPOSITO'],
            ['32453733', 'DAVID SARMIENTO', 'DEPOSITO'],
            ['28679958', 'ANDRES VELASCO', 'SAMBIL'],
            ['31508033', 'RAMIRO COELLO', 'DEPOSITO'],
            ['24166691', 'LUIS GARCIA', 'FLOTA'],
            ['25126829', 'JOSE MOLINA', 'FLOTA'],
            ['9510978', 'GREGORIO COLINA', 'FLOTA'],
            ['14478485', 'JOHAN LANOI', 'MANTENIMIENTO'],
            ['14208321', 'FAUSTO GOITIA', 'MANTENIMIENTO'],
            ['31536274', 'JOHAN ALEJANDRO', 'MANTENIMIENTO'],
            ['31930120', 'MARIA SALERO', 'NUNES'],
            ['28767172', 'VICTOR RODRIGUEZ', 'DEPOSITO'],
            ['28026549', 'ANA RODRIGUEZ', 'DORAL'],
            ['32688831', 'GERARDO ATACHO', 'DORAL'],
            ['32531659', 'JEULIMAR MEDINA', 'DORAL'],
            ['32443525', 'EMILIO CASTRILLO', 'ZAMORA'],
            ['30059313', 'MARIANGEL ZAMARRIPA', 'VIRTUDES'],
            ['26723116', 'VALERY MARQUEZ', 'VIRTUDES'],
            ['31447163', 'JETSY NAVARRO', 'VIRTUDES'],
            ['32453764', 'MADELEINE MORALES', 'VIRTUDES'],
            ['32144295', 'FRANNELVIS PEREIRA', 'VIRTUDES'],
            ['30892751', 'EMMA SANCHEZ', 'VIRTUDES'],
            ['25402089', 'MORA YINMAIRI', 'CENTRO'],
        ];
    }
}
