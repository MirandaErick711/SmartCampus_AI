<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\MensajeCorreo;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class RecuperacionTest extends TestCase
{
    use RefreshDatabase, CreaUsuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Notification::fake();
    }

    public function test_se_envia_el_enlace_a_una_cuenta_activa(): void
    {
        $u = $this->usuario();

        $this->post('/olvide-clave', ['email' => 'docente@correo.test'])
            ->assertRedirect(route('inicio'))
            ->assertSessionHas('exito');

        $this->assertStringContainsString('/restablecer/', $this->enlaceEnviadoA($u));
    }

    public function test_la_respuesta_es_la_misma_si_el_correo_no_existe_y_no_se_envia_nada(): void
    {
        $this->usuario();

        $this->post('/olvide-clave', ['email' => 'docente@correo.test']);
        $existe = session('exito');

        Notification::fake();   // se reinicia el registro de envíos
        $this->post('/olvide-clave', ['email' => 'nadie@correo.test']);
        $noExiste = session('exito');

        $this->assertNotEmpty($existe);

        $this->assertSame($existe, $noExiste);
        Notification::assertNothingSent();
    }

    public function test_cuentas_no_activas_no_reciben_enlace(): void
    {
        $u = $this->usuario('docente', ['estado' => User::INACTIVA]);

        $this->post('/olvide-clave', ['email' => 'docente@correo.test']);

        Notification::assertNothingSent();
    }

    public function test_restablecer_con_el_enlace_cambia_la_clave_y_el_enlace_es_de_un_solo_uso(): void
    {
        $u = $this->usuario('docente', ['debe_cambiar_clave' => true]);
        $token = Password::createToken($u);

        $datos = ['token' => $token, 'email' => 'docente@correo.test', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026'];

        $this->post('/restablecer', $datos)->assertRedirect(route('inicio'))->assertSessionHas('exito');
        $this->assertTrue(Hash::check('ClaveNueva2026', $u->fresh()->password));
        $this->assertFalse($u->fresh()->debe_cambiar_clave);

        // El mismo enlace ya no sirve
        $this->post('/restablecer', array_merge($datos, ['password' => 'OtraClave2026', 'password_confirmation' => 'OtraClave2026']))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('ClaveNueva2026', $u->fresh()->password));
    }

    public function test_restablecer_exige_una_clave_segura(): void
    {
        $u = $this->usuario();
        $token = Password::createToken($u);

        $this->post('/restablecer', ['token' => $token, 'email' => 'docente@correo.test', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');
    }

    public function test_un_enlace_falso_es_rechazado(): void
    {
        $this->usuario();

        $this->post('/restablecer', ['token' => 'token-inventado', 'email' => 'docente@correo.test', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026'])
            ->assertSessionHasErrors('email');
    }

    public function test_la_pagina_del_enlace_se_muestra(): void
    {
        $this->get('/restablecer/abc123?email=docente@correo.test')
            ->assertOk()
            ->assertSee('Elige tu contraseña');
    }
}
