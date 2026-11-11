<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MensajeCorreo;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Notification::fake();
    }

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'María López',
            'email' => 'maria@correo.test',
            'rol' => 'docente',
            'departamento' => 'Ingeniería',
            'password' => 'Clave1234',
            'password_confirmation' => 'Clave1234',
        ], $cambios);
    }

    public function test_un_docente_se_registra_queda_activo_y_recibe_el_correo_de_confirmacion(): void
    {
        $this->post('/registro', $this->datosRegistro())->assertRedirect(route('inicio'));

        $u = User::where('email', 'maria@correo.test')->firstOrFail();
        $this->assertSame(User::ACTIVA, $u->estado);
        $this->assertNull($u->email_verified_at);
        $this->assertNotSame('Clave1234', $u->password);          // la contraseña se guarda cifrada

        $this->assertStringContainsString('/correo/verificar/', $this->enlaceEnviadoA($u));
    }

    public function test_un_tecnico_queda_pendiente_de_aprobacion(): void
    {
        $this->post('/registro', $this->datosRegistro(['rol' => 'tecnico']));

        $this->assertSame(User::PENDIENTE, User::where('email', 'maria@correo.test')->value('estado'));
    }

    public function test_no_se_puede_registrar_como_administrador(): void
    {
        $this->post('/registro', $this->datosRegistro(['rol' => 'administrador']))
            ->assertSessionHasErrors(['rol'], null, 'registro');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_registro_rechaza_scripts_en_el_nombre(): void
    {
        $this->post('/registro', $this->datosRegistro(['nombres' => '<script>alert(1)</script>']))
            ->assertSessionHasErrors(['nombres'], null, 'registro');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_registro_exige_contrasena_con_letras_y_numeros(): void
    {
        $this->post('/registro', $this->datosRegistro(['password' => 'solo letras', 'password_confirmation' => 'solo letras']))
            ->assertSessionHasErrors(['password'], null, 'registro');
    }

    public function test_no_se_repite_el_correo(): void
    {
        $this->usuario('docente', ['email' => 'maria@correo.test']);

        $this->post('/registro', $this->datosRegistro())
            ->assertSessionHasErrors(['email'], null, 'registro');
    }

    public function test_el_enlace_de_confirmacion_funciona_y_no_se_puede_alterar(): void
    {
        $u = $this->usuario('docente', ['email_verified_at' => null]);

        $this->get($u->urlConfirmacionCorreo() . 'x')->assertForbidden();
        $this->assertNull($u->fresh()->email_verified_at);

        $this->get($u->urlConfirmacionCorreo())->assertRedirect(route('inicio'));
        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_no_puede_entrar_sin_confirmar_el_correo(): void
    {
        $this->usuario('docente', ['email_verified_at' => null]);

        $this->post('/ingresar', ['email' => 'docente@correo.test', 'password' => 'Clave1234'])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
    }

    public function test_login_correcto_lleva_al_panel(): void
    {
        $u = $this->usuario();

        $this->post('/ingresar', ['email' => 'DOCENTE@correo.test', 'password' => 'Clave1234'])
            ->assertRedirect(route('panel'));
        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->fresh()->ultimo_acceso);
    }

    public function test_login_con_contrasena_incorrecta_falla(): void
    {
        $this->usuario();

        $this->post('/ingresar', ['email' => 'docente@correo.test', 'password' => 'otraClave99'])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
    }

    public function test_cuentas_pendientes_inactivas_e_invitadas_no_entran(): void
    {
        foreach ([User::PENDIENTE, User::INACTIVA, User::INVITADA, User::CERRADA] as $i => $estado) {
            $this->usuario('docente', ['email' => "u$i@correo.test", 'estado' => $estado]);

            $this->post('/ingresar', ['email' => "u$i@correo.test", 'password' => 'Clave1234'])
                ->assertSessionHasErrors(['email'], null, 'login');
            $this->assertGuest();
        }
    }

    public function test_se_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/ingresar', ['email' => 'docente@correo.test', 'password' => 'mala-clave-1']);
        }

        // Ya bloqueado: ni con la contraseña correcta entra
        $this->post('/ingresar', ['email' => 'docente@correo.test', 'password' => 'Clave1234'])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
    }

    public function test_un_intento_de_inyeccion_sql_no_entra_y_no_rompe_nada(): void
    {
        $this->usuario();

        $this->post('/ingresar', ['email' => "' OR 1=1 --", 'password' => "' OR '1'='1"])
            ->assertSessionHasErrors(['email'], null, 'login');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_las_paginas_privadas_piden_iniciar_sesion(): void
    {
        $this->get('/panel')->assertRedirect(route('login'));
        $this->get('/usuarios')->assertRedirect(route('login'));
        $this->get('/cuenta')->assertRedirect(route('login'));
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs($this->usuario());

        $this->post('/salir')->assertRedirect(route('inicio'));
        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_pierde_el_acceso_de_inmediato(): void
    {
        $this->actingAs($this->usuario('docente', ['estado' => User::INACTIVA]));

        $this->get('/panel')->assertRedirect(route('inicio'));
        $this->assertGuest();
    }

    public function test_quien_tiene_clave_temporal_debe_cambiarla_primero(): void
    {
        $u = $this->usuario('docente', ['debe_cambiar_clave' => true]);
        $this->actingAs($u);

        $this->get('/panel')->assertRedirect(route('cuenta.edit'));
        $this->get('/cuenta')->assertOk();

        $this->put('/cuenta/clave', [
            'clave_actual' => 'Clave1234',
            'password' => 'NuevaClave5678',
            'password_confirmation' => 'NuevaClave5678',
        ])->assertRedirect(route('panel'));

        $this->assertFalse($u->fresh()->debe_cambiar_clave);
        $this->get('/panel')->assertOk();
    }

    public function test_no_se_puede_cambiar_la_clave_con_la_actual_equivocada(): void
    {
        $this->actingAs($this->usuario());

        $this->put('/cuenta/clave', [
            'clave_actual' => 'equivocada1',
            'password' => 'NuevaClave5678',
            'password_confirmation' => 'NuevaClave5678',
        ])->assertSessionHasErrors(['clave_actual'], null, 'clave');
    }

    public function test_cambio_de_correo_requiere_confirmar_el_nuevo(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);

        $this->post('/cuenta/correo', ['email' => 'nuevo@correo.test', 'clave_actual' => 'Clave1234'])
            ->assertSessionHasNoErrors();

        $this->assertSame('docente@correo.test', $u->fresh()->email);            // todavía no cambia
        $this->assertSame('nuevo@correo.test', $u->fresh()->email_pendiente);

        $url = null;
        Notification::assertSentOnDemand(MensajeCorreo::class, function ($n) use (&$url) {
            $url = $n->botonUrl;
            return true;
        });

        $this->get($url)->assertRedirect(route('cuenta.edit'));
        $this->assertSame('nuevo@correo.test', $u->fresh()->email);
        $this->assertNull($u->fresh()->email_pendiente);
    }

    public function test_el_nombre_se_muestra_escapado_y_no_ejecuta_html(): void
    {
        $admin = $this->usuario('administrador');
        $this->usuario('docente', ['nombres' => '<script>alert(1)</script>']);   // escrito directo en la base

        $this->actingAs($admin)->get('/usuarios')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
