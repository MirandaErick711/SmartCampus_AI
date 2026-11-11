<?php

namespace Tests\Feature;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Area;
use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Evidencia;
use App\Models\Incidencia;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\IncidenciaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class IncidenciasTest extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolSeeder::class, CatalogoSeeder::class]);
        Notification::fake();
        Storage::fake('local');
    }

    /* ----------------------------- ayudas ----------------------------- */

    private function foto(string $nombre = 'foto.png'): UploadedFile
    {
        // PNG real de 1×1 (no necesita la extensión GD)
        return UploadedFile::fake()->createWithContent($nombre, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'titulo'       => 'Luminaria parpadea en el aula',
            'descripcion'  => 'La luminaria del techo parpadea y a veces no funciona desde ayer.',
            'ubicacion_id' => Ubicacion::orderBy('id')->first()->id,
            'area_id'      => Area::orderBy('id')->first()->id,
        ], $extra);
    }

    private function crear(User $autor, array $extra = []): Incidencia
    {
        return app(IncidenciaService::class)->crear($autor, $this->datos($extra));
    }

    private function docente(string $correo = 'docente@correo.test'): User
    {
        return $this->usuario('docente', ['email' => $correo]);
    }

    private function tecnico(string $correo = 'tecnico@correo.test'): User
    {
        return $this->usuario('tecnico', ['email' => $correo]);
    }

    /* ----------------------------- reportar ----------------------------- */

    public function test_un_docente_registra_una_incidencia_con_foto(): void
    {
        $docente = $this->docente();

        $r = $this->actingAs($docente)->post(route('incidencias.store'), $this->datos(['foto' => $this->foto()]));

        $inc = Incidencia::firstOrFail();
        $r->assertRedirect(route('incidencias.show', $inc));
        $this->assertMatchesRegularExpression('/^INC-\d{4}-0001$/', $inc->codigo);
        $this->assertSame(EstadoIncidencia::Registrada, $inc->estado);
        $this->assertSame($docente->id, $inc->reportante_id);
        $this->assertNotNull($inc->analisis);
        $this->assertSame(1, $inc->historial()->count());

        $ev = $inc->evidencias()->firstOrFail();
        $this->assertSame('reporte', $ev->tipo);
        $this->assertSame('image/png', $ev->mime);
        Storage::disk('local')->assertExists($ev->ruta);
    }

    public function test_la_ia_completa_categoria_y_prioridad_cuando_no_se_eligen(): void
    {
        $inc = $this->crear($this->docente(), ['titulo' => 'Peligro en el tablero', 'descripcion' => 'Salen chispas y humo del tablero eléctrico']);

        $this->assertSame('Infraestructura', $inc->categoria->nombre);
        $this->assertSame('Instalaciones eléctricas', $inc->subcategoria->nombre);
        $this->assertSame(Prioridad::Critica, $inc->prioridad);
        $this->assertFalse($inc->analisis->modificada);
    }

    public function test_si_el_usuario_cambia_la_clasificacion_queda_marcada_como_modificada(): void
    {
        $cat = Categoria::where('nombre', 'Limpieza')->first();

        $inc = $this->crear($this->docente(), ['categoria_id' => $cat->id, 'prioridad' => 'Baja']);

        $this->assertSame('Limpieza', $inc->categoria->nombre);
        $this->assertTrue($inc->analisis->modificada);
    }

    public function test_un_tecnico_no_puede_reportar(): void
    {
        $t = $this->tecnico();

        $this->actingAs($t)->get(route('incidencias.create'))->assertForbidden();
        $this->actingAs($t)->post(route('incidencias.store'), $this->datos())->assertForbidden();
        $this->assertSame(0, Incidencia::count());
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $docente = $this->docente();

        $this->actingAs($docente)
            ->post(route('incidencias.store'), $this->datos(['titulo' => 'abc', 'ubicacion_id' => 99999, 'area_id' => '']))
            ->assertSessionHasErrors(['titulo', 'ubicacion_id', 'area_id']);

        $this->actingAs($docente)
            ->post(route('incidencias.store'), $this->datos(['foto' => UploadedFile::fake()->createWithContent('virus.php', '<?php echo 1;')]))
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Incidencia::count());
    }

    public function test_la_subcategoria_debe_pertenecer_a_la_categoria(): void
    {
        $limpieza = Categoria::where('nombre', 'Limpieza')->first();
        $ajena = Categoria::where('nombre', 'Seguridad')->first()->subcategorias()->first();

        $this->actingAs($this->docente())
            ->post(route('incidencias.store'), $this->datos(['categoria_id' => $limpieza->id, 'subcategoria_id' => $ajena->id]))
            ->assertSessionHasErrors('subcategoria_id');
    }

    public function test_las_etiquetas_html_se_eliminan_y_nunca_se_ejecutan(): void
    {
        $docente = $this->docente();

        $this->actingAs($docente)->post(route('incidencias.store'), $this->datos(['titulo' => '<script>alert(1)</script>Luminaria rota']));

        $inc = Incidencia::firstOrFail();
        $this->assertStringNotContainsString('<script', $inc->titulo);
        $this->actingAs($docente)->get(route('incidencias.show', $inc))->assertOk()->assertDontSee('<script>alert', false);
    }

    public function test_el_codigo_no_se_puede_forzar_desde_el_formulario(): void
    {
        $this->actingAs($this->docente())->post(route('incidencias.store'), $this->datos(['codigo' => 'HACK', 'estado' => 'Cerrada', 'tecnico_id' => 1, 'reportante_id' => 999]));

        $inc = Incidencia::firstOrFail();
        $this->assertNotSame('HACK', $inc->codigo);
        $this->assertSame(EstadoIncidencia::Registrada, $inc->estado);
        $this->assertNull($inc->tecnico_id);
    }

    /* ----------------------------- visibilidad ----------------------------- */

    public function test_cada_persona_ve_solo_lo_que_le_corresponde(): void
    {
        $a = $this->docente('a@correo.test');
        $b = $this->docente('b@correo.test');
        $propia = $this->crear($a, ['titulo' => 'Incidencia de la docente A']);
        $ajena = $this->crear($b, ['titulo' => 'Incidencia de la docente B']);

        $this->actingAs($a)->get(route('incidencias.show', $propia))->assertOk();
        $this->actingAs($a)->get(route('incidencias.show', $ajena))->assertForbidden();
        $this->actingAs($a)->get(route('incidencias.index'))->assertOk()->assertSee('Incidencia de la docente A')->assertDontSee('Incidencia de la docente B');

        $this->actingAs($this->usuario('operador'))->get(route('incidencias.index'))->assertSee('docente A')->assertSee('docente B');
        $this->actingAs($this->tecnico())->get(route('incidencias.index'))->assertDontSee('docente A');
    }

    public function test_hay_que_iniciar_sesion(): void
    {
        $inc = $this->crear($this->docente());

        $this->get(route('incidencias.index'))->assertRedirect(route('login'));
        $this->get(route('incidencias.show', $inc))->assertRedirect(route('login'));
    }

    public function test_la_bandeja_filtra_por_estado_y_texto(): void
    {
        $d = $this->docente();
        $this->crear($d, ['titulo' => 'Proyector sin imagen']);
        $otra = $this->crear($d, ['titulo' => 'Grifo goteando']);
        $otra->update(['estado' => EstadoIncidencia::Cerrada]);

        $this->actingAs($d)->get(route('incidencias.index', ['estado' => 'Cerrada']))->assertSee('Grifo goteando')->assertDontSee('Proyector sin imagen');
        $this->actingAs($d)->get(route('incidencias.index', ['q' => 'Proyector']))->assertSee('Proyector sin imagen')->assertDontSee('Grifo goteando');
        $this->actingAs($d)->get(route('incidencias.index', ['estado' => 'No existe']))->assertSessionHasErrors('estado');
    }

    /* ----------------------------- ciclo de vida ----------------------------- */

    public function test_ciclo_completo_registrada_a_cerrada(): void
    {
        $docente = $this->docente();
        $operador = $this->usuario('operador');
        $tecnico = $this->tecnico();
        $inc = $this->crear($docente);

        $this->actingAs($operador)->post(route('incidencias.validar', $inc))->assertSessionHas('exito');
        $this->assertSame(EstadoIncidencia::EnEvaluacion, $inc->fresh()->estado);
        $this->assertTrue($inc->analisis()->first()->validada);

        $this->actingAs($operador)->post(route('incidencias.asignar', $inc), ['tecnico_id' => $tecnico->id])->assertSessionHas('exito');
        $inc->refresh();
        $this->assertSame(EstadoIncidencia::Asignada, $inc->estado);
        $this->assertSame($tecnico->id, $inc->tecnico_id);

        $this->actingAs($tecnico)->post(route('incidencias.iniciar', $inc))->assertSessionHas('exito');
        $this->assertSame(EstadoIncidencia::EnAtencion, $inc->fresh()->estado);

        $this->actingAs($tecnico)->post(route('incidencias.resolver', $inc), ['informe' => 'corto'])->assertSessionHasErrors('informe');
        $this->actingAs($tecnico)->post(route('incidencias.resolver', $inc), ['informe' => 'Se cambió la luminaria y se probó el encendido.', 'foto' => $this->foto('solucion.png')])->assertSessionHas('exito');
        $inc->refresh();
        $this->assertSame(EstadoIncidencia::Resuelta, $inc->estado);
        $this->assertNotNull($inc->informe_tecnico);
        $this->assertSame('solucion', $inc->evidencias()->latest('id')->first()->tipo);

        $this->actingAs($tecnico)->post(route('incidencias.cerrar', $inc))->assertForbidden(); // el técnico no cierra
        $this->actingAs($operador)->post(route('incidencias.cerrar', $inc))->assertSessionHas('exito');
        $this->assertSame(EstadoIncidencia::Cerrada, $inc->fresh()->estado);

        // Registrada, En evaluación, Asignada, En atención, Resuelta, Cerrada
        $this->assertSame(6, $inc->historial()->count());
        $this->assertTrue(Auditoria::where('accion', 'Técnico asignado')->exists());
        $this->assertTrue(Auditoria::where('accion', 'Incidencia registrada')->exists());
    }

    public function test_el_reportante_no_puede_gestionar(): void
    {
        $d = $this->docente();
        $inc = $this->crear($d);

        foreach (['evaluar', 'validar', 'cerrar'] as $accion) {
            $this->actingAs($d)->post(route("incidencias.{$accion}", $inc))->assertForbidden();
        }
        $this->actingAs($d)->post(route('incidencias.asignar', $inc), ['tecnico_id' => 1])->assertForbidden();
        $this->actingAs($d)->post(route('incidencias.iniciar', $inc))->assertForbidden();
    }

    public function test_no_se_puede_cerrar_si_no_esta_resuelta(): void
    {
        $inc = $this->crear($this->docente());

        $this->actingAs($this->usuario('operador'))->post(route('incidencias.cerrar', $inc))->assertSessionHas('error');
        $this->assertSame(EstadoIncidencia::Registrada, $inc->fresh()->estado);
    }

    public function test_solo_se_asigna_a_tecnicos_activos(): void
    {
        $op = $this->usuario('operador');
        $inc = $this->crear($this->docente());
        $otroDocente = $this->docente('otro@correo.test');
        $inactivo = $this->tecnico('inactivo@correo.test');
        $inactivo->forceFill(['estado' => User::INACTIVA])->save();

        $this->actingAs($op)->post(route('incidencias.asignar', $inc), ['tecnico_id' => $otroDocente->id])->assertSessionHas('error');
        $this->actingAs($op)->post(route('incidencias.asignar', $inc), ['tecnico_id' => $inactivo->id])->assertSessionHas('error');
        $this->actingAs($op)->post(route('incidencias.asignar', $inc), [])->assertSessionHasErrors('tecnico_id');
        $this->assertNull($inc->fresh()->tecnico_id);
    }

    public function test_un_tecnico_no_puede_tocar_incidencias_ajenas(): void
    {
        $inc = $this->crear($this->docente());
        $asignado = $this->tecnico('uno@correo.test');
        $otro = $this->tecnico('dos@correo.test');
        $this->actingAs($this->usuario('operador'))->post(route('incidencias.asignar', $inc), ['tecnico_id' => $asignado->id]);

        $this->actingAs($otro)->post(route('incidencias.iniciar', $inc))->assertForbidden();
        $this->actingAs($otro)->get(route('incidencias.show', $inc))->assertForbidden();
        $this->assertSame(EstadoIncidencia::Asignada, $inc->fresh()->estado);
    }

    public function test_no_se_puede_saltar_pasos_del_ciclo(): void
    {
        $inc = $this->crear($this->docente());
        $tecnico = $this->tecnico();
        $inc->update(['tecnico_id' => $tecnico->id]); // asignado, pero la incidencia sigue «Registrada»

        $this->actingAs($tecnico)->post(route('incidencias.iniciar', $inc))->assertSessionHas('error');
        $this->actingAs($tecnico)->post(route('incidencias.resolver', $inc), ['informe' => 'Informe de prueba largo'])->assertSessionHas('error');
        $this->assertSame(EstadoIncidencia::Registrada, $inc->fresh()->estado);
    }

    public function test_reclasificar_marca_el_analisis_como_modificado_y_valida_la_subcategoria(): void
    {
        $op = $this->usuario('operador');
        $inc = $this->crear($this->docente());
        $seg = Categoria::where('nombre', 'Seguridad')->first();
        $vig = $seg->subcategorias()->where('nombre', 'Vigilancia')->first();

        $this->actingAs($op)->post(route('incidencias.reclasificar', $inc), ['categoria_id' => $seg->id, 'subcategoria_id' => $vig->id, 'prioridad' => 'Alta'])->assertSessionHas('exito');
        $inc->refresh();
        $this->assertSame($seg->id, $inc->categoria_id);
        $this->assertSame(Prioridad::Alta, $inc->prioridad);
        $this->assertTrue($inc->analisis->modificada);
        $this->assertFalse($inc->analisis->validada);
        $this->assertSame(EstadoIncidencia::EnEvaluacion, $inc->estado);

        $ajena = Categoria::where('nombre', 'Limpieza')->first()->subcategorias()->first();
        $this->actingAs($op)->post(route('incidencias.reclasificar', $inc), ['categoria_id' => $seg->id, 'subcategoria_id' => $ajena->id, 'prioridad' => 'Alta'])->assertSessionHasErrors('subcategoria_id');
    }

    /* ----------------------------- fotos privadas ----------------------------- */

    public function test_las_fotos_solo_las_ve_quien_tiene_permiso(): void
    {
        $dueno = $this->docente('dueno@correo.test');
        $inc = app(IncidenciaService::class)->crear($dueno, $this->datos(), $this->foto());
        $ev = Evidencia::firstOrFail();

        $this->actingAs($dueno)->get(route('evidencias.ver', $ev))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->usuario('operador'))->get(route('evidencias.ver', $ev))->assertOk();
        $this->actingAs($this->docente('intruso@correo.test'))->get(route('evidencias.ver', $ev))->assertForbidden();
        $this->actingAs($this->tecnico())->get(route('evidencias.ver', $ev))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get(route('evidencias.ver', $ev))->assertRedirect(route('login'));
    }

    public function test_la_foto_se_guarda_en_el_disco_privado_con_nombre_aleatorio(): void
    {
        app(IncidenciaService::class)->crear($this->docente(), $this->datos(), $this->foto('mi foto <b>.png'));

        $ev = Evidencia::firstOrFail();
        $this->assertStringStartsWith('evidencias/', $ev->ruta);
        $this->assertStringNotContainsString('mi foto', $ev->ruta);
        $this->assertFalse(file_exists(public_path($ev->ruta)));
    }

    /* ----------------------------- vista previa IA y panel ----------------------------- */

    public function test_la_vista_previa_de_ia_devuelve_la_clasificacion(): void
    {
        $this->actingAs($this->docente())
            ->postJson(route('incidencias.analizar'), ['titulo' => 'Sin internet', 'descripcion' => 'No hay wifi en el aula, sin internet', 'tiene_foto' => true])
            ->assertOk()
            ->assertJsonPath('categoria', 'Tecnología')
            ->assertJsonPath('subcategoria', 'Conectividad')
            ->assertJsonPath('prioridad', 'Alta')
            ->assertJsonStructure(['categoria_id', 'subcategoria_id', 'confianza', 'hits', 'objetos', 'explicacion']);

        $this->assertSame(0, Incidencia::count()); // es solo una vista previa
    }

    public function test_la_vista_previa_exige_texto_y_rol_permitido(): void
    {
        $this->actingAs($this->docente())->postJson(route('incidencias.analizar'), [])->assertStatus(422);
        $this->actingAs($this->tecnico())->postJson(route('incidencias.analizar'), ['titulo' => 'x'])->assertForbidden();
    }

    public function test_el_panel_y_el_formulario_cargan_para_cada_rol(): void
    {
        $this->crear($this->docente());

        foreach (['docente', 'administrativo', 'operador', 'tecnico', 'administrador'] as $rol) {
            $u = $this->usuario($rol, ['email' => "panel-{$rol}@correo.test"]);
            $this->actingAs($u)->get(route('panel'))->assertOk();
            $this->actingAs($u)->get(route('incidencias.index'))->assertOk();
            if ($rol !== 'tecnico') {
                $this->actingAs($u)->get(route('incidencias.create'))->assertOk()->assertSee('datos-ubicaciones', false);
            }
        }
    }
}
