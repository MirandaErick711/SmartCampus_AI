<?php

namespace Tests\Feature;

use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_inicio_carga(): void
    {
        $this->seed(RolSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Gestión')
            ->assertSee('Acceder')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_el_registro_solo_ofrece_cuatro_roles_y_no_el_de_administrador_ni_estudiante(): void
    {
        $this->seed(RolSeeder::class);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('value="docente"', $html);
        $this->assertStringContainsString('value="administrativo"', $html);
        $this->assertStringContainsString('value="operador"', $html);
        $this->assertStringContainsString('value="tecnico"', $html);
        $this->assertStringNotContainsString('value="administrador"', $html);
        $this->assertStringNotContainsString('value="estudiante"', $html);
    }
}
