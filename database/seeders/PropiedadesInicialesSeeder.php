<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PropiedadesInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $propiedades = [
            ['codigo' => 'PAT-001', 'nombre' => 'Casa Doña Emilia',                          'tipo' => 'casa'],
            ['codigo' => 'PAT-002', 'nombre' => 'Casa Cristal',                               'tipo' => 'casa'],
            ['codigo' => 'PAT-003', 'nombre' => 'Casa La España',                             'tipo' => 'casa'],
            ['codigo' => 'PAT-004', 'nombre' => 'Apartamento Balcones',                       'tipo' => 'apartamento'],
            ['codigo' => 'PAT-005', 'nombre' => 'Local Centro Palacio Ofertas',               'tipo' => 'local'],
            ['codigo' => 'PAT-006', 'nombre' => 'Local Centro Peluquería',                    'tipo' => 'local'],
            ['codigo' => 'PAT-007', 'nombre' => 'Local Doral',                                'tipo' => 'local'],
            ['codigo' => 'PAT-008', 'nombre' => 'Local Sambil Esquina Farmatodo',             'tipo' => 'local'],
            ['codigo' => 'PAT-009', 'nombre' => 'Local Sambil Frente Mango Bajito',          'tipo' => 'local'],
            ['codigo' => 'PAT-010', 'nombre' => 'Local Centro Antigua Pernia',               'tipo' => 'local'],
            ['codigo' => 'PAT-011', 'nombre' => 'Local Frente Planta Eléctrica Motos AVA',   'tipo' => 'local'],
            ['codigo' => 'PAT-012', 'nombre' => 'Apartamento Salamar',                        'tipo' => 'apartamento'],
            ['codigo' => 'PAT-013', 'nombre' => 'Apartamento Mirasol',                        'tipo' => 'apartamento'],
            ['codigo' => 'PAT-014', 'nombre' => 'Casa Ollarvides',                            'tipo' => 'casa'],
            ['codigo' => 'PAT-015', 'nombre' => 'Casa Puerta Maraven Diagonal Castelo',       'tipo' => 'casa'],
            ['codigo' => 'PAT-016', 'nombre' => 'Casa General Pelayo',                        'tipo' => 'casa'],
            ['codigo' => 'PAT-017', 'nombre' => 'Terreno Puerta Maraven',                     'tipo' => 'terreno'],
            ['codigo' => 'PAT-018', 'nombre' => 'Terreno General Pelayo',                     'tipo' => 'terreno'],
            ['codigo' => 'PAT-019', 'nombre' => 'Terreno Cercado Puerta Maraven',             'tipo' => 'terreno'],
            ['codigo' => 'PAT-020', 'nombre' => 'Casa Puerta Maraven Residencial',            'tipo' => 'casa'],
            ['codigo' => 'PAT-021', 'nombre' => 'Town House Cristal 1',                       'tipo' => 'casa'],
            ['codigo' => 'PAT-022', 'nombre' => 'Town House Cristal 2',                       'tipo' => 'casa'],
            ['codigo' => 'PAT-023', 'nombre' => 'Town House Terranova',                       'tipo' => 'casa'],
            ['codigo' => 'PAT-024', 'nombre' => 'Town House Clínica Amefalcon',               'tipo' => 'casa'],
            ['codigo' => 'PAT-025', 'nombre' => 'Local Centro Zapatería Rocky',               'tipo' => 'local'],
            ['codigo' => 'PAT-026', 'nombre' => 'Terreno Terrazas Club de Golf',              'tipo' => 'terreno'],
            ['codigo' => 'PAT-027', 'nombre' => 'Terreno Grande Guanadito',                   'tipo' => 'terreno'],
            ['codigo' => 'PAT-028', 'nombre' => 'Terreno Esquina Santa Irene',                'tipo' => 'terreno'],
            ['codigo' => 'PAT-029', 'nombre' => 'Galpón Doña Emilia',                         'tipo' => 'galpón'],
            ['codigo' => 'PAT-030', 'nombre' => 'Galpón Bella Vista',                         'tipo' => 'galpón'],
            ['codigo' => 'PAT-031', 'nombre' => 'Local Virtudes',                             'tipo' => 'local'],
            ['codigo' => 'PAT-032', 'nombre' => 'Lotes Terrenos Doña Emilia',                 'tipo' => 'terreno'],
            ['codigo' => 'PAT-033', 'nombre' => 'Apartamento San Román',                      'tipo' => 'apartamento'],
            ['codigo' => 'PAT-034', 'nombre' => 'Casa Judibana',                              'tipo' => 'casa'],
            ['codigo' => 'PAT-035', 'nombre' => 'Casa La Pastora',                            'tipo' => 'casa'],
            ['codigo' => 'PAT-036', 'nombre' => 'Apartamento Mirasol Familiar',               'tipo' => 'apartamento'],
            ['codigo' => 'PAT-037', 'nombre' => 'Casa Esquina Santa Irene',                   'tipo' => 'casa'],
            ['codigo' => 'PAT-038', 'nombre' => 'Terreno Clínica Paraguana',                  'tipo' => 'terreno'],
            ['codigo' => 'PAT-039', 'nombre' => 'Terreno Ferretería Bleu',                    'tipo' => 'terreno'],
            ['codigo' => 'PAT-040', 'nombre' => 'Local Tienda Palacio Tecnología',            'tipo' => 'local'],
        ];

        foreach ($propiedades as $p) {
            DB::table('pat_propiedades')->updateOrInsert(
                ['codigo' => $p['codigo']],
                [
                    'codigo'           => $p['codigo'],
                    'nombre'           => $p['nombre'],
                    'tipo'             => $p['tipo'],
                    'estado'           => 'disponible',
                    'propietario'      => 'Por registrar',
                    'responsable'      => null,
                    'direccion'        => null,
                    'ubicacion'        => null,
                    'fotos'            => null,
                    'fecha_adquisicion'=> null,
                    'valor_inversion'  => null,
                    'observaciones'    => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]
            );
        }

        $this->command->info('✅ 40 propiedades iniciales cargadas.');
    }
}
