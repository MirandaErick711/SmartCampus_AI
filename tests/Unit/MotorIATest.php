<?php

namespace Tests\Unit;

use App\Enums\Prioridad;
use App\Services\MotorIA;
use PHPUnit\Framework\TestCase;

class MotorIATest extends TestCase
{
    private MotorIA $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor = new MotorIA();
    }

    public function test_clasifica_por_palabras_clave(): void
    {
        $r = $this->motor->analizar('Luminaria parpadea', 'El foco del aula parpadea y la luz se apaga', false);

        $this->assertSame('Infraestructura', $r['categoria']);
        $this->assertSame('Iluminación', $r['subcategoria']);
        $this->assertSame(Prioridad::Media, $r['prioridad']);
        $this->assertContains('luminaria', $r['hits']);
    }

    public function test_detecta_urgencia_critica_aunque_haya_tildes_y_plurales(): void
    {
        $r = $this->motor->analizar('Peligro eléctrico', 'Salen chispas y humo del tablero', false);

        $this->assertSame(Prioridad::Critica, $r['prioridad']);
        $this->assertSame('Instalaciones eléctricas', $r['subcategoria']);
    }

    public function test_la_foto_sube_la_confianza_y_agrega_objetos(): void
    {
        $sin = $this->motor->analizar('Proyector', 'El proyector no enciende', false);
        $con = $this->motor->analizar('Proyector', 'El proyector no enciende', true);

        $this->assertGreaterThan($sin['confianza'], $con['confianza']);
        $this->assertSame([], $sin['objetos']);
        $this->assertCount(2, $con['objetos']);
        $this->assertSame(Prioridad::Alta, $con['prioridad']);
    }

    public function test_sin_palabras_conocidas_usa_categoria_por_defecto_con_baja_confianza(): void
    {
        $r = $this->motor->analizar('Asunto raro', 'Algo que no se entiende bien', false);

        $this->assertSame('Servicios', $r['categoria']);
        $this->assertSame(44, $r['confianza']);
        $this->assertSame(Prioridad::Baja, $r['prioridad']);
    }

    public function test_no_confunde_partes_de_otras_palabras(): void
    {
        // "red" no debe activarse dentro de "reducido"
        $r = $this->motor->analizar('Espacio reducido', 'El pasillo es muy reducido', false);

        $this->assertNotContains('red', $r['hits']);
    }

    public function test_el_catalogo_de_palabras_cubre_las_siete_categorias(): void
    {
        $this->assertCount(7, $this->motor->palabrasPorCategoria());
    }
}
