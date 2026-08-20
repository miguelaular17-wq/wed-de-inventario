<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clientes = [
            // Ordenados según lista oficial
            ['cedula' => '19648944', 'nombre' => 'CARLOS GOMEZ'],
            ['cedula' => '28501943', 'nombre' => 'GIOVANNY GUTIERREZ'],
            ['cedula' => '28766068', 'nombre' => 'MIGUEL AULAR'],
            ['cedula' => '31422012', 'nombre' => 'JHATORY LUQUEZ'],
            ['cedula' => '32431800', 'nombre' => 'DANIELA DIAZ'],
            ['cedula' => '31234500', 'nombre' => 'GABRIELA GARCIA'],
            ['cedula' => '28766449', 'nombre' => 'AMBAR CUAURO'],
            ['cedula' => '28369458', 'nombre' => 'ALEJANDRA SALAS'],  // DORAL
            ['cedula' => '28771171', 'nombre' => 'WENDY AÑEZ'],
            ['cedula' => '31560045', 'nombre' => 'CAROLINA QUERO'],
            ['cedula' => '31234439', 'nombre' => 'IRAIDA DIAZ'],
            ['cedula' => '26598293', 'nombre' => 'JOSMARLYN VELASQUEZ'],
            ['cedula' => '26930931', 'nombre' => 'DANIEL SARMIENTO'],
            ['cedula' => '27671436', 'nombre' => 'FRANKLIN SANCHEZ'],
            ['cedula' => '32565039', 'nombre' => 'MARIANGELA DIAZ'],   // EGRESOS
            ['cedula' => '30248629', 'nombre' => 'EMILY PULGAR'],
            ['cedula' => '31629048', 'nombre' => 'GEORGINA ZAVALA'],
            ['cedula' => '25010760', 'nombre' => 'ANGEL JIMENEZ'],
            ['cedula' => '28695505', 'nombre' => 'ANDREA CHIQUITO'],
            ['cedula' => '27000000', 'nombre' => 'JORGELIS ROMAN'],
            ['cedula' => '31603843', 'nombre' => 'DANIBET ALMEIDA'],
            ['cedula' => '31852005', 'nombre' => 'JUAN PABLO'],
            ['cedula' => '28772921', 'nombre' => 'WANDA VENTURA'],
            ['cedula' => '28369082', 'nombre' => 'ANDREA AGUILAR'],
            ['cedula' => '31240780', 'nombre' => 'FELIX VARGAS'],      // DEPOSITO
            ['cedula' => '31243511', 'nombre' => 'VALENTINA ROMAN'],
            ['cedula' => '27924798', 'nombre' => 'YESSICA SOUSA'],
            ['cedula' => '26056789', 'nombre' => 'JOSE SENIOR'],
            ['cedula' => '31246440', 'nombre' => 'MELANNY CARRASQUERO'],
            ['cedula' => '28369458', 'nombre' => 'ALEJANDRA SALAS'],   // ZAMORA
            ['cedula' => '18426259', 'nombre' => 'ANDRES QUEVEDO'],
            ['cedula' => '31000000', 'nombre' => 'GRATEBOL SARA'],
            ['cedula' => '27844739', 'nombre' => 'IVANIA SANCHEZ'],
            ['cedula' => '31486406', 'nombre' => 'ADRIANNY MARTINEZ'],
            ['cedula' => '28789767', 'nombre' => 'JHORALSY NAVAS'],
            ['cedula' => '29000000', 'nombre' => 'ANDRUELYS HURTADO'],
            ['cedula' => '27420927', 'nombre' => 'JAVIER LEAL'],       // INVENTARIO
            ['cedula' => '31629048', 'nombre' => 'JHOSMARLYN SANCHEZ'],
            ['cedula' => '23675095', 'nombre' => 'OSWALDO BERMUDEZ'],
            ['cedula' => '27420921', 'nombre' => 'OSCAR PALOMO'],      // SAMBIL
            ['cedula' => '31234081', 'nombre' => 'YORGUELIS VELASQUEZ'],
            ['cedula' => '28000000', 'nombre' => 'MILENA GUTIERRES'],
            ['cedula' => '19944238', 'nombre' => 'FRANCIS MARIN'],     // VIRTUDES
            ['cedula' => '30000000', 'nombre' => 'HANNAH ROBERTIS'],   // MORALES
            ['cedula' => '26309612', 'nombre' => 'OCTAVIO LUGO'],
            ['cedula' => '32247446', 'nombre' => 'HANNAH ROBERTIS'],
            ['cedula' => '29000000', 'nombre' => 'HADZAY VENTURA'],
            ['cedula' => '28772951', 'nombre' => 'ANGELES PELAYO'],    // RENUNCIA
            ['cedula' => '31499611', 'nombre' => 'BRISNEIDYS SALAZAR'],
            ['cedula' => '31390000', 'nombre' => 'CARLOS EDUARDO REYES'],
            ['cedula' => '16439637', 'nombre' => 'ZAMBRANO DEYBE'],
            ['cedula' => '31726001', 'nombre' => 'FRANCO MANCINI'],    // RENUNCIA
            ['cedula' => '31243511', 'nombre' => 'VALENTINA ROMAN'],
            ['cedula' => '31475999', 'nombre' => 'JUAN OLLARVES'],
            ['cedula' => '30390783', 'nombre' => 'MARIAGREN MANAURE'],
            ['cedula' => '31000000', 'nombre' => 'CARLOS MENDEZ'],
            ['cedula' => '31305961', 'nombre' => 'DARIANNYS OLLARVES'],  // RENUNCIA
            ['cedula' => '31901317', 'nombre' => 'ISLENIA NARANJO'],   // Call center / REPOSO
            ['cedula' => '28382238', 'nombre' => 'MARIA PETIT'],       // Call center
            ['cedula' => '28343500', 'nombre' => 'MARIA RODRIGUEZ'],   // Marketing
            ['cedula' => '28273000', 'nombre' => 'BARBARA LOPEZ'],     // Call center
            ['cedula' => '32089028', 'nombre' => 'EILEEN ABREU'],      // CENTRO
            ['cedula' => '25470483', 'nombre' => 'ABEL YAJURIS'],      // Call center
            ['cedula' => '31310326', 'nombre' => 'JAIR ALEXANDER AVILA'],
            ['cedula' => '22898423', 'nombre' => 'EDWARD MAVO'],       // COMPRAS
            ['cedula' => '22153644', 'nombre' => 'PAOLA COLINA'],      // COMPRAS
            ['cedula' => '20552705', 'nombre' => 'MARIA POLANCO'],     // CONTABILIDAD
            ['cedula' => '16198138', 'nombre' => 'CARLOS RUIZ'],       // CONTABILIDAD
            ['cedula' => '24306562', 'nombre' => 'DAYANA LOPEZ'],      // ADMINISTRACION
            ['cedula' => '21155688', 'nombre' => 'PATRICIA COLINA'],   // Marketing
            ['cedula' => '24325868', 'nombre' => 'GUSMALIS MEDINA'],   // TESORERIA
            ['cedula' => '26058100', 'nombre' => 'JOSE SEMECO'],       // Finanzas
            ['cedula' => '26000000', 'nombre' => 'DAYANA BUSTILLO'],   // CUENTAS POR COBRAR
            ['cedula' => '17500110', 'nombre' => 'DIANA BUSTILLO'],    // RRHH
            ['cedula' => '18632452', 'nombre' => 'CARLOS SEMECO'],     // COMPRAS
            ['cedula' => '28715593', 'nombre' => 'VALENTINA GONZALEZ'],// Marketing
            ['cedula' => '26218256', 'nombre' => 'MANUEL RUIZ'],
            ['cedula' => '80000000', 'nombre' => 'JOSEPH BRACHO'],     // S.T.
            ['cedula' => '25402263', 'nombre' => 'BRANDON SANCHEZ'],   // INVENTARIO
            ['cedula' => '30657986', 'nombre' => 'RICARDO GIMENEZ'],   // DEPOSITO
            ['cedula' => '20796305', 'nombre' => 'FADYS TORO'],        // INVENTARIO
            ['cedula' => '20797535', 'nombre' => 'JONATHAN SANCHEZ'],  // DEPOSITO
            ['cedula' => '32453733', 'nombre' => 'DAVID SARMIENTO'],   // DEPOSITO
            ['cedula' => '31240780', 'nombre' => 'FELIX VARGAS'],      // EGRESOS / RENUNCIA
            ['cedula' => '31240780', 'nombre' => 'ANDRES VELASQUEZ'],  // SAMBIL
            ['cedula' => '27005739', 'nombre' => 'RAMIRO COELLO'],     // DEPOSITO
            ['cedula' => '26309378', 'nombre' => 'LUIS GARCIA'],       // FLOTA
            ['cedula' => '25126829', 'nombre' => 'JOSE MOLINA'],       // FLOTA
            ['cedula' => '28000000', 'nombre' => 'ROGELIO COLINA'],    // FLOTA
            ['cedula' => '14478485', 'nombre' => 'JOHAN LANG'],        // MANTENIMIENTO (SOLDADOR)
            ['cedula' => '29000000', 'nombre' => 'CRISTO GOMEZ'],      // MANTENIMIENTO
            ['cedula' => '29000000', 'nombre' => 'JOHAN ACEVEDO'],     // MANTENIMIENTO
            ['cedula' => '31930120', 'nombre' => 'MARIA SALERO'],      // NUNES
            ['cedula' => '28697172', 'nombre' => 'VICTOR RODRIGUEZ'],  // DEPOSITO
            ['cedula' => '28000000', 'nombre' => 'ANA RODRIGUEZ'],     // DORAL
            ['cedula' => '32688631', 'nombre' => 'GERARDO ATACHO'],    // DORAL
            ['cedula' => '32631659', 'nombre' => 'JEULIMAR MEDINA'],
            ['cedula' => '32443525', 'nombre' => 'EMILYS MADRIEL'],    // FARMACIA
            ['cedula' => '32059313', 'nombre' => 'MARIANGEL ZAMARRIPA'],// VIRTUDES
            ['cedula' => '26723115', 'nombre' => 'VALERY MARQUEZ'],
            ['cedula' => '29000000', 'nombre' => 'JETSY NARANJO'],     // VIRTUDES
            ['cedula' => '32453764', 'nombre' => 'MADELEINE MORALES'], // VIRTUDES (INICIO 04-08)
            ['cedula' => '31142295', 'nombre' => 'FRANNEL VIS PEREIRA'],// VIRTUDES (INICIO 08-08)
            ['cedula' => '36892751', 'nombre' => 'EMMA SANCHEZ'],      // VIRTUDES (INICIO 09-08)
            // Clientes adicionales del seeder anterior
            ['cedula' => '21625159', 'nombre' => 'YOSMARY ALVAREZ'],
            ['cedula' => '22834272', 'nombre' => 'ANGERBERTH CARRASQUERO'],
            ['cedula' => '24305298', 'nombre' => 'ROSA MUJICA'],
            ['cedula' => '24525137', 'nombre' => 'ROGELIO REFUNJOL'],
            ['cedula' => '24525502', 'nombre' => 'MARIA NUÑEZ'],
            ['cedula' => '24703210', 'nombre' => 'JOSE LEONARDO JEREZ'],
            ['cedula' => '25402912', 'nombre' => 'RAIDY BURDZY'],
            ['cedula' => '25402984', 'nombre' => 'SAMUEL CUAURO'],
            ['cedula' => '25605678', 'nombre' => 'KARLA GUACARAN'],
            ['cedula' => '26057994', 'nombre' => 'GLAYVIC GUANIPA'],
            ['cedula' => '26058324', 'nombre' => 'MARENA MUNDO'],
            ['cedula' => '26656218', 'nombre' => 'ELIMAR VARGAS'],
            ['cedula' => '26656789', 'nombre' => 'DANIEL SENIOR'],
            ['cedula' => '27384562', 'nombre' => 'MILLENNIA GUTIERREZ'],
            ['cedula' => '27811139', 'nombre' => 'FREYGLING SANCHEZ'],
            ['cedula' => '27961215', 'nombre' => 'FATIMA SIVIRA'],
            ['cedula' => '28046742', 'nombre' => 'JAIR AVILA'],
            ['cedula' => '28046938', 'nombre' => 'XIOMAR ZAVALA'],
            ['cedula' => '28046996', 'nombre' => 'OSMEIRY GUANIPA'],
            ['cedula' => '28340171', 'nombre' => 'MELANY CARRASQUERO'],
            ['cedula' => '28363420', 'nombre' => 'LUCAS LOZADA'],
            ['cedula' => '28369068', 'nombre' => 'JOSEMAR MAVAREZ'],
            ['cedula' => '28369203', 'nombre' => 'PATRICIA COLINA'],
            ['cedula' => '28501917', 'nombre' => 'ANAIS HERMOSO'],
            ['cedula' => '28522183', 'nombre' => 'JENNIFER AÑEZ'],
            ['cedula' => '28557461', 'nombre' => 'SANTIAGO STANIC'],
            ['cedula' => '28632884', 'nombre' => 'OSCAR RIERA'],
            ['cedula' => '28668114', 'nombre' => 'MARIA TORREALBA'],
            ['cedula' => '28679820', 'nombre' => 'SANTIAGO CHIRINOS'],
            ['cedula' => '28679958', 'nombre' => 'ANDRES VELASCO'],
            ['cedula' => '28719664', 'nombre' => 'JOSE BERMUDEZ'],
            ['cedula' => '28719993', 'nombre' => 'CHIQUINQUIRA REYES'],
            ['cedula' => '28723018', 'nombre' => 'BARBARA LOPEZ'],
            ['cedula' => '28745747', 'nombre' => 'ALEXCA RUIZ'],
            ['cedula' => '28767362', 'nombre' => 'ALEXANDRA RAMIREZ'],
            ['cedula' => '28771189', 'nombre' => 'ABRIL CALDERA'],
            ['cedula' => '28774187', 'nombre' => 'MAYRA CALLES'],
            ['cedula' => '28774297', 'nombre' => 'YAMILETH GOMEZ'],
            ['cedula' => '28775039', 'nombre' => 'MARIA MARTINEZ'],
            ['cedula' => '28776417', 'nombre' => 'ISAMAR ROMAN'],
            ['cedula' => '28777935', 'nombre' => 'EVELIN SARMIENTO'],
            ['cedula' => '28961961', 'nombre' => 'ALONSO BERMUDEZ'],
            ['cedula' => '30295527', 'nombre' => 'JERRY VILLAROEL'],
            ['cedula' => '30400951', 'nombre' => 'HEILY LOPEZ'],
            ['cedula' => '30409228', 'nombre' => 'ANGELES PAULINA SALAS'],
            ['cedula' => '30674573', 'nombre' => 'GENESIS ZERPA'],
            ['cedula' => '30847203', 'nombre' => 'ANAMAR FLORES'],
            ['cedula' => '30986468', 'nombre' => 'JESUS MUJICA'],
            ['cedula' => '30986863', 'nombre' => 'MARLYN CHAVEZ'],
            ['cedula' => '31037336', 'nombre' => 'EMERLYS CHIRINO'],
            ['cedula' => '31037651', 'nombre' => 'KAILEEN GONZALEZ'],
            ['cedula' => '31150484', 'nombre' => 'JOSE BORGES'],
            ['cedula' => '31176254', 'nombre' => 'KATHERINE HERNANDEZ'],
            ['cedula' => '31421931', 'nombre' => 'JULIO BRICEÑO'],
            ['cedula' => '31626078', 'nombre' => 'NAHOMIS SEDILLO'],
            ['cedula' => '31789963', 'nombre' => 'CARLOS REYES'],
            ['cedula' => '31925148', 'nombre' => 'LUISCARLY MORA'],
            ['cedula' => '31943520', 'nombre' => 'MARIA RAMONES'],
            ['cedula' => '32027142', 'nombre' => 'DAGMAR JEREZ'],
            ['cedula' => '32387615', 'nombre' => 'RACHEL VILLASMIL'],
            ['cedula' => '32631662', 'nombre' => 'RUSSEL DEMESIO'],
            ['cedula' => '32825648', 'nombre' => 'ANGELA NAVA'],
            ['cedula' => '34409228', 'nombre' => 'PAULINA SALAS'],
            ['cedula' => '38039395', 'nombre' => 'FRANKLIN POLANCO'],
            ['cedula' => '24595145', 'nombre' => 'ALEXANDRA SALAS'],
            ['cedula' => '28177145', 'nombre' => 'MARIELENIS MUNDO'],
            ['cedula' => '28766301', 'nombre' => 'DANIELA CHIRINOS'],
            ['cedula' => '28767502', 'nombre' => 'EDILIANNYS PALOMO'],
            ['cedula' => '17135001', 'nombre' => 'CRISTAL MAVO'],
            ['cedula' => '18447009', 'nombre' => 'AURILES LUGO'],
            ['cedula' => '18632725', 'nombre' => 'DHAMELYS REYES'],
            ['cedula' => '22605085', 'nombre' => 'YOSMARY ALVAREZ'],
            ['cedula' => '28501917', 'nombre' => 'ANAIS HERMOSO'],
            ['cedula' => '28632884', 'nombre' => 'OSCAR RIERA'],
        ];

        foreach ($clientes as $cliente) {
            \App\Models\Cliente::updateOrCreate(
                ['cedula' => $cliente['cedula']],
                ['nombre' => $cliente['nombre']]
            );
        }
    }
}
