<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use App\Notifications\MensajeCorreo;
use Database\Seeders\AdministradorSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class AdministradorTest extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Notification::fake();
    }

    public function test_el_seeder_crea_el_administrador_inicial_una_sola_vez(): void
    {
        config(['smartcampus.admin_email' => 'dueno@correo.test', 'smartcampus.admin_clave' => 'Temporal2026']);

        $this->seed(AdministradorSeeder::class);
        $this->seed(AdministradorSeeder::class);

        $admin = User::where('email', 'dueno@correo.test')->firstOrFail();
        $this->assertTrue($admin->esAdministrador());
        $this->assertTrue($admin->debe_cambiar_clave);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Temporal2026', $admin->password));
        $this->assertSame(1, User::count());
    }

    public function test_el_seeder_exige_un_correo_real(): void
    {
        config(['smartcampus.admin_email' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdministradorSeeder::class);
    }

    public function test_solo_el_administrador_entra_a_usuarios(): void
    {
        $this->actingAs($this->usuario('docente'))->get('/usuarios')->assertForbidden();
        $this->actingAs($this->usuario('operador'))->get('/usuarios')->assertForbidden();
        $this->actingAs($this->usuario('administrador'))->get('/usuarios')->assertOk();
    }

    public function test_un_docente_no_puede_usar_las_acciones_del_administrador(): void
    {
        $docente = $this->usuario('docente');
        $otro = $this->usuario('tecnico', ['email' => 'otro@correo.test', 'estado' => User::PENDIENTE]);

        $this->actingAs($docente)->post("/usuarios/{$otro->id}/aprobar")->assertForbidden();
        $this->assertSame(User::PENDIENTE, $otro->fresh()->estado);
    }

    public function test_aprobar_y_rechazar_solicitudes(): void
    {
        $admin = $this->usuario('administrador');
        $a = $this->usuario('tecnico', ['email' => 'a@correo.test', 'estado' => User::PENDIENTE]);
        $b = $this->usuario('operador', ['email' => 'b@correo.test', 'estado' => User::PENDIENTE]);

        $this->actingAs($admin)->post("/usuarios/{$a->id}/aprobar")->assertSessionHas('exito');
        $this->assertSame(User::ACTIVA, $a->fresh()->estado);

        $this->actingAs($admin)->delete("/usuarios/{$b->id}")->assertSessionHas('exito');
        $this->assertDatabaseMissing('users', ['email' => 'b@correo.test']);
    }

    public function test_no_se_puede_eliminar_una_cuenta_activa(): void
    {
        $admin = $this->usuario('administrador');
        $docente = $this->usuario('docente');

        $this->actingAs($admin)->delete("/usuarios/{$docente->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['email' => 'docente@correo.test']);
    }

    public function test_cambiar_rol_pero_nunca_a_administrador(): void
    {
        $admin = $this->usuario('administrador');
        $docente = $this->usuario('docente');
        $operador = Rol::where('slug', 'operador')->value('id');
        $rolAdmin = Rol::where('slug', 'administrador')->value('id');

        $this->actingAs($admin)->patch("/usuarios/{$docente->id}/rol", ['rol_id' => $operador])->assertSessionHas('exito');
        $this->assertSame($operador, $docente->fresh()->rol_id);

        $this->actingAs($admin)->patch("/usuarios/{$docente->id}/rol", ['rol_id' => $rolAdmin])->assertSessionHasErrors('rol_id');
        $this->assertSame($operador, $docente->fresh()->rol_id);
    }

    public function test_el_administrador_no_puede_modificarse_a_si_mismo(): void
    {
        $admin = $this->usuario('administrador');
        $operador = Rol::where('slug', 'operador')->value('id');

        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/rol", ['rol_id' => $operador])->assertStatus(422);
        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/estado")->assertStatus(422);
        $this->assertSame('administrador', $admin->fresh()->rol->slug);
    }

    public function test_desactivar_y_reactivar_una_cuenta(): void
    {
        $admin = $this->usuario('administrador');
        $docente = $this->usuario('docente');

        $this->actingAs($admin)->patch("/usuarios/{$docente->id}/estado");
        $this->assertSame(User::INACTIVA, $docente->fresh()->estado);

        $this->actingAs($admin)->patch("/usuarios/{$docente->id}/estado");
        $this->assertSame(User::ACTIVA, $docente->fresh()->estado);
    }

    public function test_el_administrador_puede_enviar_un_enlace_de_recuperacion_a_otro_usuario(): void
    {
        $admin = $this->usuario('administrador');
        $docente = $this->usuario('docente');

        $this->actingAs($admin)->post("/usuarios/{$docente->id}/restablecer")->assertSessionHas('exito');

        $this->assertStringContainsString('/restablecer/', $this->enlaceEnviadoA($docente));
    }

    public function test_invitar_a_un_usuario_y_que_active_su_cuenta(): void
    {
        $admin = $this->usuario('administrador');
        $rolTecnico = Rol::where('slug', 'tecnico')->value('id');

        $this->actingAs($admin)->post('/usuarios/invitar', [
            'nombres' => 'Luis Quispe', 'email' => 'luis@correo.test', 'rol_id' => $rolTecnico, 'departamento' => 'Redes',
        ])->assertSessionHas('exito');

        $luis = User::where('email', 'luis@correo.test')->firstOrFail();
        $this->assertSame(User::INVITADA, $luis->estado);

        $url = $this->enlaceEnviadoA($luis);
        preg_match('#/restablecer/([^?]+)\?email=#', $url, $m);

        auth()->logout();
        $this->post('/restablecer', ['token' => $m[1], 'email' => 'luis@correo.test', 'password' => 'ClaveLuis2026', 'password_confirmation' => 'ClaveLuis2026'])
            ->assertRedirect(route('inicio'));

        $luis = $luis->fresh();
        $this->assertSame(User::ACTIVA, $luis->estado);
        $this->assertNotNull($luis->email_verified_at);
        $this->assertTrue(Hash::check('ClaveLuis2026', $luis->password));
    }

    public function test_no_se_puede_invitar_con_rol_de_administrador(): void
    {
        $admin = $this->usuario('administrador');
        $rolAdmin = Rol::where('slug', 'administrador')->value('id');

        $this->actingAs($admin)->post('/usuarios/invitar', [
            'nombres' => 'Intruso Uno', 'email' => 'intruso@correo.test', 'rol_id' => $rolAdmin,
        ])->assertSessionHasErrors(['rol_id'], null, 'invitar');

        $this->assertDatabaseMissing('users', ['email' => 'intruso@correo.test']);
    }

    public function test_el_relevo_cierra_la_cuenta_del_administrador_anterior(): void
    {
        $viejo = $this->usuario('administrador', ['email' => 'viejo@correo.test']);

        $this->actingAs($viejo)->post('/usuarios/relevo', [
            'nombres' => 'Nueva Admin', 'email' => 'nueva@correo.test', 'clave_actual' => 'Clave1234',
        ])->assertSessionHas('exito');

        $nueva = User::where('email', 'nueva@correo.test')->firstOrFail();
        $this->assertSame(User::INVITADA, $nueva->estado);
        $this->assertSame(User::ACTIVA, $viejo->fresh()->estado);          // todavía sigue siendo administrador

        preg_match('#/restablecer/([^?]+)\?email=#', $this->enlaceEnviadoA($nueva), $m);

        auth()->logout();
        $this->post('/restablecer', ['token' => $m[1], 'email' => 'nueva@correo.test', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026'])
            ->assertRedirect(route('inicio'));

        $this->assertSame(User::ACTIVA, $nueva->fresh()->estado);
        $this->assertSame(User::CERRADA, $viejo->fresh()->estado);

        // La cuenta cerrada ya no puede ingresar
        $this->post('/ingresar', ['email' => 'viejo@correo.test', 'password' => 'Clave1234'])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();

        // Y queda un solo administrador activo
        $activos = User::whereHas('rol', fn ($q) => $q->where('slug', 'administrador'))->where('estado', User::ACTIVA)->count();
        $this->assertSame(1, $activos);
    }

    public function test_el_relevo_exige_la_contrasena_del_administrador(): void
    {
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->post('/usuarios/relevo', [
            'nombres' => 'Nueva Admin', 'email' => 'nueva@correo.test', 'clave_actual' => 'equivocada1',
        ])->assertSessionHasErrors(['clave_actual'], null, 'relevo');

        $this->assertDatabaseMissing('users', ['email' => 'nueva@correo.test']);
    }
}
