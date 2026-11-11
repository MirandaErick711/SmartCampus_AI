<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /** Los 5 roles del sistema (se puede ejecutar varias veces sin duplicar). */
    public function run(): void
    {
        $roles = [
            // slug, nombre, grupo de permisos, requiere aprobación, se elige al registrarse
            ['docente',        'Docente',        'reportante',    false, true],
            ['administrativo', 'Administrativo', 'reportante',    false, true],
            ['operador',       'Operador',       'operador',      true,  true],
            ['tecnico',        'Técnico',        'tecnico',       true,  true],
            ['administrador',  'Administrador',  'administrador', true,  false],
        ];

        foreach ($roles as [$slug, $nombre, $grupo, $aprobacion, $publico]) {
            Rol::updateOrCreate(['slug' => $slug], [
                'nombre'              => $nombre,
                'grupo'               => $grupo,
                'requiere_aprobacion' => $aprobacion,
                'registro_publico'    => $publico,
            ]);
        }
    }
}
