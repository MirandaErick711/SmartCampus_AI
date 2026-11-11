<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Campus;
use App\Models\Categoria;
use App\Models\Pabellon;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

/**
 * Catálogos iniciales (ubicaciones reales de la universidad). Se puede ejecutar varias veces sin duplicar:
 *   php artisan db:seed --class=CatalogoSeeder
 * Más pabellones y aulas se agregan después desde la pantalla "Ubicaciones" (administrador).
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Académica', 'Laboratorio', 'Administrativa', 'Biblioteca', 'Servicios Generales', 'Áreas comunes'] as $a) {
            Area::firstOrCreate(['nombre' => $a]);
        }

        $categorias = [
            'Infraestructura' => ['Iluminación', 'Instalaciones eléctricas', 'Estructura y acabados'],
            'Tecnología'      => ['Conectividad', 'Equipos de cómputo'],
            'Equipamiento'    => ['Proyectores', 'Equipos de laboratorio'],
            'Servicios'       => ['Agua', 'Mantenimiento general'],
            'Seguridad'       => ['Puertas y accesos', 'Vigilancia'],
            'Limpieza'        => ['Residuos', 'Limpieza de ambientes'],
            'Mobiliario'      => ['Sillas y mesas', 'Pizarras y estantes'],
        ];
        foreach ($categorias as $nombre => $subs) {
            $cat = Categoria::firstOrCreate(['nombre' => $nombre]);
            foreach ($subs as $sub) {
                $cat->subcategorias()->firstOrCreate(['nombre' => $sub]);
            }
        }

        // Ubicaciones reales: campus => pabellón => [piso, tipo, nombre]
        $campus = [
            'Campus 1' => [
                'Pabellón de Sistemas' => [[3, 'aula', 'Aula 302'], [2, 'aula', 'Aula 201'], [1, 'laboratorio', 'Laboratorio D']],
                'Pabellón de Civil'    => [[2, 'aula', 'Aula C-201'], [1, 'laboratorio', 'Laboratorio de Estructuras']],
                'Pabellón de Enfermería' => [[1, 'laboratorio', 'Laboratorio de Simulación'], [2, 'aula', 'Aula E-202']],
            ],
            'Campus 2' => [
                'Pabellón de Agronomía' => [[1, 'laboratorio', 'Laboratorio de Suelos'], [2, 'aula', 'Aula AG-201']],
                'Pabellón de Derecho'   => [[1, 'otro', 'Sala de Audiencias'], [2, 'aula', 'Aula D-202']],
            ],
        ];
        foreach ($campus as $nombreCampus => $pabellones) {
            $c = Campus::firstOrCreate(['nombre' => $nombreCampus]);
            foreach ($pabellones as $nombrePab => $ambientes) {
                $pisos = max(array_column($ambientes, 0));
                $p = Pabellon::firstOrCreate(['campus_id' => $c->id, 'nombre' => $nombrePab], ['pisos' => $pisos]);
                foreach ($ambientes as [$piso, $tipo, $nombre]) {
                    Ubicacion::firstOrCreate(['pabellon_id' => $p->id, 'piso' => $piso, 'nombre' => $nombre], ['tipo' => $tipo]);
                }
            }
        }
    }
}
