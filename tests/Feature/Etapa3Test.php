<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Incidencia;
use App\Models\Notificacion;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\IncidenciaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class Etapa3Test extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolSeeder::class, CatalogoSeeder::class]);
        Notification::fake();
    }

    private function crear(User $autor, array $extra = []): Incidencia
    {
        return app(IncidenciaService::class)->crear($autor, array_merge([
            'titulo'       => 'Luminaria parpadea en el aula',
            'descripcion'  => 'La luminaria del techo parpadea y a veces no funciona desde ayer.',
            'ubicacion_id' => Ubicacion::orderBy('id')->first()->id,
            'area_id'      => Area::orderBy('id')->first()->id,
        ], $extra));
    }

    private function u(string $rol, string $nombre = null): User
    {
        return $this->usuario($rol, ['email' => ($nombre ?? $rol) . '@correo.test']);
    }

    /* ----------------------------- notificaciones ----------------------------- */

    public function test_una_incidencia_critica_avisa_a_operadores_y_administrador_pero_no_a_quien_reporta(): void
    {
        $docente = $this->u('docente');
        $operador = $this->u('operador');
        $admin = $this->u('administrador');
        $tecnico = $this->u('tecnico');

        $inc = $this->crear($docente, ['titulo' => 'Peligro en el tablero', 'descripcion' => 'Salen chispas y humo del tablero eléctrico']);

        $this->assertSame('critica', Notificacion::where('user_id', $operador->id)->firstOrFail()->tipo);
        $this->assertSame($inc->id, Notificacion::where('user_id', $operador->id)->first()->incidencia_id);
        $this->assertSame('info', Notificacion::where('user_id', $admin->id)->firstOrFail()->tipo);
        $this->assertSame(0, Notificacion::where('user_id', $docente->id)->count());
        $this->assertSame(0, Notificacion::where('user_id', $tecnico->id)->count());
    }

    public function test_no_se_avisa_a_cuentas_inactivas(): void
    {
        $op = $this->u('operador');
        $op->forceFill(['estado' => User::INACTIVA])->save();

        $this->crear($this->u('docente'));

        $this->assertSame(0, Notificacion::count());
    }

    public function test_el_ciclo_genera_avisos_para_quien_corresponde(): void
    {
        $docente = $this->u('docente');
        $operador = $this->u('operador');
        $tecnico = $this->u('tecnico');
        $inc = $this->crear($docente);

        $this->actingAs($operador)->post(route('incidencias.asignar', $inc), ['tecnico_id' => $tecnico->id]);
        $this->assertSame('asignacion', Notificacion::where('user_id', $tecnico->id)->firstOrFail()->tipo);
        $this->assertTrue(Notificacion::where('user_id', $docente->id)->where('texto', 'like', '%fue asignada a%')->exists());

        $this->actingAs($tecnico)->post(route('incidencias.iniciar', $inc));
        $this->actingAs($tecnico)->post(route('incidencias.resolver', $inc), ['informe' => 'Se cambió la luminaria y se probó.']);
        $this->assertTrue(Notificacion::where('user_id', $operador->id)->where('texto', 'like', '%Pendiente de cierre%')->exists());

        $this->actingAs($operador)->post(route('incidencias.cerrar', $inc));
        $this->assertTrue(Notificacion::where('user_id', $docente->id)->where('texto', 'like', '%fue cerrada%')->exists());
        // quien ejecuta la acción no se avisa a sí mismo
        $this->assertFalse(Notificacion::where('user_id', $operador->id)->where('texto', 'like', '%fue cerrada%')->exists());
    }

    public function test_la_campana_devuelve_solo_las_notificaciones_propias(): void
    {
        $docente = $this->u('docente');
        $operador = $this->u('operador');
        $this->crear($docente);

        $this->actingAs($operador)->getJson(route('notificaciones.index'))
            ->assertOk()->assertJsonPath('no_leidas', 1)->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.url', route('incidencias.show', Incidencia::first()));

        $this->actingAs($docente)->getJson(route('notificaciones.index'))->assertOk()->assertJsonPath('no_leidas', 0)->assertJsonCount(0, 'items');
    }

    public function test_marcar_como_leidas_y_no_tocar_las_ajenas(): void
    {
        $docente = $this->u('docente');
        $op1 = $this->u('operador', 'op1');
        $op2 = $this->u('operador', 'op2');
        $this->crear($docente);
        $de1 = Notificacion::where('user_id', $op1->id)->first();
        $de2 = Notificacion::where('user_id', $op2->id)->first();

        $this->actingAs($op1)->postJson(route('notificaciones.leer', $de2))->assertNotFound();
        $this->assertFalse($de2->fresh()->leida);

        $this->actingAs($op1)->postJson(route('notificaciones.leer', $de1))->assertOk();
        $this->assertTrue($de1->fresh()->leida);

        $this->actingAs($op2)->postJson(route('notificaciones.leer-todas'))->assertOk();
        $this->assertTrue($de2->fresh()->leida);
    }

    public function test_la_campana_exige_sesion(): void
    {
        $this->getJson(route('notificaciones.index'))->assertUnauthorized();
    }

    /* ----------------------------- panel ----------------------------- */

    public function test_el_panel_muestra_cifras_segun_el_rol(): void
    {
        $a = $this->u('docente', 'a');
        $b = $this->u('docente', 'b');
        $this->crear($a);
        $this->crear($a, ['titulo' => 'Peligro en el tablero', 'descripcion' => 'Salen chispas y humo del tablero']);
        $this->crear($b);

        $this->actingAs($a)->get(route('panel'))->assertOk()
            ->assertSee('id="datos-panel"', false)
            ->assertSee('data-count="2"', false)      // total propio
            ->assertSee('Alertas críticas')
            ->assertSee('Peligro en el tablero');

        $this->actingAs($this->u('operador'))->get(route('panel'))->assertOk()->assertSee('data-count="3"', false)->assertSee('Carga de técnicos');
        $this->actingAs($a)->get(route('panel'))->assertDontSee('Carga de técnicos');
    }

    public function test_el_panel_sin_incidencias_carga(): void
    {
        foreach (['docente', 'operador', 'tecnico', 'administrador'] as $rol) {
            $this->actingAs($this->u($rol))->get(route('panel'))->assertOk();
        }
    }

    public function test_las_cifras_del_panel_son_correctas(): void
    {
        $d = $this->u('docente');
        $this->crear($d);
        $this->crear($d, ['titulo' => 'Peligro en el tablero', 'descripcion' => 'Salen chispas y humo del tablero']);
        Incidencia::first()->update(['estado' => 'Cerrada']);

        $datos = app(\App\Services\Estadisticas::class)->panel($d);

        $this->assertSame(2, $datos['kpis']['total']);
        $this->assertSame(1, $datos['kpis']['resueltas']);
        $this->assertSame(50, $datos['kpis']['tasa']);
        $this->assertSame(1, $datos['kpis']['criticas']);
        $this->assertSame(1, collect($datos['estados'])->firstWhere('etiqueta', 'Cerrada')['valor']);
        $this->assertSame(2, array_sum(array_column($datos['pabellones'], 'valor')));
    }

    /* ----------------------------- reportes ----------------------------- */

    public function test_solo_operador_y_administrador_ven_reportes_y_auditoria(): void
    {
        $usuarios = [];
        foreach (['docente', 'tecnico', 'operador', 'administrador'] as $rol) {
            $usuarios[$rol] = $this->u($rol);
        }

        foreach (['reportes.index', 'reportes.csv', 'auditoria.index'] as $ruta) {
            foreach (['docente', 'tecnico'] as $rol) {
                $this->actingAs($usuarios[$rol])->get(route($ruta))->assertForbidden();
            }
            foreach (['operador', 'administrador'] as $rol) {
                $this->actingAs($usuarios[$rol])->get(route($ruta))->assertOk();
            }
        }
    }

    public function test_el_reporte_calcula_tiempo_medio_y_confianza(): void
    {
        $d = $this->u('docente');
        $op = $this->u('operador');
        $t = $this->u('tecnico');
        $inc = $this->crear($d);
        $this->actingAs($op)->post(route('incidencias.asignar', $inc), ['tecnico_id' => $t->id]);
        $this->actingAs($t)->post(route('incidencias.iniciar', $inc));
        $this->actingAs($t)->post(route('incidencias.resolver', $inc), ['informe' => 'Se cambió la luminaria y se probó.']);

        $r = app(\App\Services\Estadisticas::class)->reporte();

        $this->assertNotNull($r['tiempoMedioHoras']);
        $this->assertGreaterThan(0, $r['confianzaMedia']);
        $this->actingAs($op)->get(route('reportes.index'))->assertOk()->assertSee($inc->codigo);
    }

    public function test_el_csv_exporta_todo_y_neutraliza_formulas(): void
    {
        $d = $this->u('docente');
        $this->crear($d, ['titulo' => '=HYPERLINK("http://malo.test","clic")', 'descripcion' => 'Fórmula maliciosa en el título del reporte.']);
        $this->crear($d, ['titulo' => 'Proyector sin imagen', 'descripcion' => 'El proyector no enciende en el aula.']);

        $r = $this->actingAs($this->u('operador'))->get(route('reportes.csv'));

        $r->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $r->streamedContent();
        $this->assertStringContainsString('Proyector sin imagen', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
        $this->assertStringContainsString('INC-', $csv);
    }

    /* ----------------------------- auditoría ----------------------------- */

    public function test_la_auditoria_lista_y_busca(): void
    {
        $this->crear($this->u('docente'), ['titulo' => 'Grifo goteando en el baño', 'descripcion' => 'El grifo gotea todo el día en el baño.']);
        Auditoria::registrar('Prueba especial', 'detalle único xyz');
        $op = $this->u('operador');

        $this->actingAs($op)->get(route('auditoria.index'))->assertOk()->assertSee('Incidencia registrada')->assertSee('Prueba especial');
        $this->actingAs($op)->get(route('auditoria.index', ['q' => 'xyz']))->assertSee('Prueba especial')->assertDontSee('Incidencia registrada');
    }

    public function test_solo_el_administrador_ve_la_ip_en_la_auditoria(): void
    {
        Auditoria::registrar('Acción con IP', 'x');

        $this->actingAs($this->u('administrador'))->get(route('auditoria.index'))->assertSee('<th>IP</th>', false);
        $this->actingAs($this->u('operador'))->get(route('auditoria.index'))->assertDontSee('<th>IP</th>', false);
    }

    public function test_el_menu_muestra_las_secciones_segun_el_rol(): void
    {
        $this->actingAs($this->u('docente'))->get(route('panel'))->assertDontSee('Reportes')->assertDontSee('Auditoría');
        $this->actingAs($this->u('operador'))->get(route('panel'))->assertSee('Reportes')->assertSee('Auditoría')->assertSee('id="campana"', false);
    }
}
