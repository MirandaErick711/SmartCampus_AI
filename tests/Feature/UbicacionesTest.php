<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Campus;
use App\Models\Incidencia;
use App\Models\Pabellon;
use App\Models\Ubicacion;
use App\Services\IncidenciaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class UbicacionesTest extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolSeeder::class, CatalogoSeeder::class]);
        Notification::fake();
    }

    public function test_el_catalogo_inicial_trae_5_pabellones_y_11_ambientes_y_no_se_duplica(): void
    {
        $this->assertSame(2, Campus::count());
        $this->assertSame(5, Pabellon::count());
        $this->assertSame(11, Ubicacion::count());

        $this->seed(CatalogoSeeder::class);

        $this->assertSame(11, Ubicacion::count());
    }

    public function test_solo_el_administrador_entra_a_ubicaciones(): void
    {
        $this->actingAs($this->usuario('administrador'))->get(route('ubicaciones.index'))->assertOk()->assertSee('Pabellón de Sistemas');

        foreach (['docente', 'operador', 'tecnico'] as $rol) {
            $u = $this->usuario($rol);
            $this->actingAs($u)->get(route('ubicaciones.index'))->assertForbidden();
            $this->actingAs($u)->post(route('ubicaciones.pabellon'), [])->assertForbidden();
        }
    }

    public function test_el_administrador_agrega_campus_pabellon_y_ambientes(): void
    {
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->post(route('ubicaciones.campus'), ['nombre' => 'Campus Sur'])->assertSessionHas('exito');
        $campus = Campus::where('nombre', 'Campus Sur')->firstOrFail();

        $this->actingAs($admin)->post(route('ubicaciones.pabellon'), ['campus_id' => $campus->id, 'nombre' => 'Pabellón F', 'descripcion' => 'Medicina', 'pisos' => 3])->assertSessionHas('exito');
        $pab = Pabellon::where('nombre', 'Pabellón F')->firstOrFail();

        $this->actingAs($admin)->post(route('ubicaciones.ubicacion'), ['pabellon_id' => $pab->id, 'piso' => 2, 'tipo' => 'aula', 'nombre' => 'Aula 201'])->assertSessionHas('exito');
        $this->assertTrue(Ubicacion::where('pabellon_id', $pab->id)->where('nombre', 'Aula 201')->exists());

        // aparece en el formulario de reportes
        $this->actingAs($this->usuario('docente'))->get(route('incidencias.create'))->assertSee('Pabellón F')->assertSee('Aula 201');
    }

    public function test_valida_duplicados_pisos_y_texto_peligroso(): void
    {
        $admin = $this->usuario('administrador');
        $pab = Pabellon::where('nombre', 'Pabellón de Sistemas')->first(); // 3 pisos

        $this->actingAs($admin)->post(route('ubicaciones.campus'), ['nombre' => 'Campus 1'])->assertSessionHasErrors('nombre', null, 'campus');
        $this->actingAs($admin)->post(route('ubicaciones.campus'), ['nombre' => '<script>alert(1)</script>'])->assertSessionHasErrors('nombre', null, 'campus');
        $this->actingAs($admin)->post(route('ubicaciones.pabellon'), ['campus_id' => $pab->campus_id, 'nombre' => 'Pabellón de Sistemas', 'pisos' => 2])->assertSessionHasErrors('nombre', null, 'pabellon');
        $this->actingAs($admin)->post(route('ubicaciones.ubicacion'), ['pabellon_id' => $pab->id, 'piso' => 9, 'tipo' => 'aula', 'nombre' => 'Aula 901'])->assertSessionHasErrors('piso', null, 'ubicacion');
        $this->actingAs($admin)->post(route('ubicaciones.ubicacion'), ['pabellon_id' => $pab->id, 'piso' => 2, 'tipo' => 'aula', 'nombre' => 'Aula 201'])->assertSessionHasErrors('nombre', null, 'ubicacion');
        $this->actingAs($admin)->post(route('ubicaciones.ubicacion'), ['pabellon_id' => $pab->id, 'piso' => 1, 'tipo' => 'hackeo', 'nombre' => 'Aula 105'])->assertSessionHasErrors('tipo', null, 'ubicacion');
    }

    public function test_no_se_elimina_un_ambiente_con_incidencias(): void
    {
        $admin = $this->usuario('administrador');
        $ub = Ubicacion::orderBy('id')->first();
        app(IncidenciaService::class)->crear($this->usuario('docente'), [
            'titulo' => 'Luminaria rota', 'descripcion' => 'La luminaria está rota y no enciende.',
            'ubicacion_id' => $ub->id, 'area_id' => Area::first()->id,
        ]);

        $this->actingAs($admin)->delete(route('ubicaciones.ubicacion.destroy', $ub))->assertSessionHas('error');
        $this->assertTrue(Ubicacion::whereKey($ub->id)->exists());
        $this->assertSame(1, Incidencia::count());

        $libre = Ubicacion::orderByDesc('id')->first();
        $this->actingAs($admin)->delete(route('ubicaciones.ubicacion.destroy', $libre))->assertSessionHas('exito');
        $this->assertFalse(Ubicacion::whereKey($libre->id)->exists());
    }

    public function test_no_se_elimina_un_pabellon_o_campus_con_contenido(): void
    {
        $admin = $this->usuario('administrador');
        $pab = Pabellon::first();

        $this->actingAs($admin)->delete(route('ubicaciones.pabellon.destroy', $pab))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('ubicaciones.campus.destroy', $pab->campus))->assertSessionHas('error');
        $this->assertTrue(Pabellon::whereKey($pab->id)->exists());
    }
}
