<?php

namespace App\Services;

use App\Enums\Prioridad;

/**
 * Motor de IA multimodal (simulado, basado en reglas).
 * Analiza texto + presencia de foto + ubicación y sugiere categoría, subcategoría, prioridad y confianza.
 * Está aislado aquí para poder cambiarlo algún día por un modelo real (visión + lenguaje) sin tocar el resto.
 */
class MotorIA
{
    /** Categoría => subcategoría => palabras clave (sin tildes, en minúscula). */
    public const REGLAS = [
        'Infraestructura' => [
            'Iluminación'              => ['luminaria', 'foco', 'luz', 'lampara', 'fluorescente'],
            'Instalaciones eléctricas' => ['enchufe', 'cable', 'tomacorriente', 'interruptor', 'tablero', 'electrico'],
            'Estructura y acabados'    => ['techo', 'puerta', 'ventana', 'pared', 'grieta', 'gotera'],
        ],
        'Tecnología' => [
            'Conectividad'       => ['red', 'internet', 'wifi', 'router', 'conexion'],
            'Equipos de cómputo' => ['computadora', 'pc', 'monitor', 'impresora', 'teclado', 'mouse'],
        ],
        'Equipamiento' => [
            'Proyectores'            => ['proyector', 'ecran', 'pantalla'],
            'Equipos de laboratorio' => ['microscopio', 'balanza', 'osciloscopio', 'multimetro'],
        ],
        'Servicios' => [
            'Agua'                  => ['agua', 'bano', 'grifo', 'desague', 'inodoro', 'lavatorio', 'fuga'],
            'Mantenimiento general' => ['mantenimiento', 'ascensor', 'ventilador', 'aire acondicionado'],
        ],
        'Seguridad' => [
            'Puertas y accesos' => ['seguridad', 'cerradura', 'candado', 'acceso', 'torniquete'],
            'Vigilancia'        => ['vigilancia', 'camara', 'robo', 'vigilante'],
        ],
        'Limpieza' => [
            'Residuos'              => ['basura', 'residuo', 'tacho'],
            'Limpieza de ambientes' => ['limpieza', 'sucio', 'polvo', 'olor'],
        ],
        'Mobiliario' => [
            'Sillas y mesas'      => ['silla', 'carpeta', 'mesa', 'escritorio'],
            'Pizarras y estantes' => ['pizarra', 'estante', 'casillero', 'armario'],
        ],
    ];

    /** "Objetos detectados" en la foto (simulado según la categoría). */
    private const OBJETOS = [
        'Infraestructura' => ['Luminaria / punto de luz', 'Instalación eléctrica', 'Superficie de techo o pared', 'Marco de puerta'],
        'Tecnología'      => ['Equipo electrónico', 'Puerto / cableado de red', 'Estación de trabajo', 'Pantalla o monitor'],
        'Equipamiento'    => ['Pantalla o proyector', 'Equipo de laboratorio', 'Soporte de equipo', 'Instrumento de medición'],
        'Servicios'       => ['Instalación sanitaria', 'Tubería o grifo', 'Área común', 'Punto de mantenimiento'],
        'Seguridad'       => ['Punto de acceso', 'Cerradura o puerta', 'Cámara o sensor', 'Zona de control'],
        'Limpieza'        => ['Zona de residuos', 'Superficie sucia', 'Área común', 'Tachos y contenedores'],
        'Mobiliario'      => ['Silla o carpeta', 'Superficie de mesa', 'Estructura metálica / madera', 'Pizarra'],
    ];

    /** Palabras que indican urgencia, de la más grave a la menos grave. */
    private const URGENCIA = [
        'Crítica' => ['humo', 'chispa', 'fuego', 'incendio', 'cortocircuito', 'fuga', 'inundacion', 'descarga', 'peligro', 'riesgo', 'emergencia', 'gas', 'expuesto', 'electrocut'],
        'Alta'    => ['no funciona', 'no enciende', 'no prende', 'sin luz', 'sin internet', 'sin conexion', 'apagon', 'roto', 'rota', 'caido', 'caida', 'urgente', 'no hay', 'danado', 'danada', 'atascado', 'goteras'],
        'Media'   => ['deteriorada', 'deteriorado', 'lento', 'lenta', 'falla', 'parpadea', 'intermitente', 'desgastado', 'desgastada', 'sucio', 'suelto', 'floja', 'flojo', 'acumulada', 'acumulado'],
    ];

    /** Palabras clave por categoría (para mostrar en el catálogo). */
    public function palabrasPorCategoria(): array
    {
        $salida = [];
        foreach (self::REGLAS as $categoria => $subs) {
            $salida[$categoria] = array_values(array_merge(...array_values($subs)));
        }

        return $salida;
    }

    /**
     * @return array{categoria:string,subcategoria:string,prioridad:Prioridad,confianza:int,hits:array,objetos:array,explicacion:array}
     */
    public function analizar(string $titulo, string $descripcion, bool $tieneImagen, string $pabellon = '', string $aula = '', string $area = ''): array
    {
        $texto = $this->normalizar($titulo . ' ' . $descripcion);

        // 1) Categoría: la que más palabras clave acumula
        $mejor = null;
        $aciertos = [];
        foreach (self::REGLAS as $categoria => $subs) {
            $hits = [];
            foreach ($subs as $palabras) {
                foreach ($palabras as $p) {
                    if ($this->coincide($texto, $p)) {
                        $hits[] = $p;
                    }
                }
            }
            if (count($hits) > count($aciertos)) {
                $mejor = $categoria;
                $aciertos = $hits;
            }
        }
        $reconocida = $mejor !== null;
        $mejor ??= 'Servicios';

        // 2) Subcategoría
        $subMejor = $reconocida ? array_key_first(self::REGLAS[$mejor]) : 'Mantenimiento general';
        $subPuntos = 0;
        if ($reconocida) {
            foreach (self::REGLAS[$mejor] as $sub => $palabras) {
                $n = count(array_filter($palabras, fn ($p) => $this->coincide($texto, $p)));
                if ($n > $subPuntos) {
                    $subPuntos = $n;
                    $subMejor = $sub;
                }
            }
        }

        // 3) Confianza
        $confianza = $reconocida
            ? min(98, 72 + count($aciertos) * 7 + ($tieneImagen ? 8 : 0) + $this->huella($texto) % 4)
            : ($tieneImagen ? 56 : 44);

        // 4) Prioridad por términos de urgencia
        $prioridad = Prioridad::Baja;
        $motivo = null;
        foreach (self::URGENCIA as $nivel => $palabras) {
            foreach ($palabras as $p) {
                if ($this->coincide($texto, $p) || (mb_strlen($p) > 6 && str_contains($texto, $p))) {
                    $prioridad = Prioridad::from($nivel);
                    $motivo = $p;
                    break 2;
                }
            }
        }

        // 5) Objetos "detectados" en la foto
        $objetos = self::OBJETOS[$mejor];
        usort($objetos, fn ($a, $b) => $this->huella($a . $texto) <=> $this->huella($b . $texto));
        $objetos = $tieneImagen ? array_slice($objetos, 0, 2) : [];

        $ubicacion = $pabellon !== '' ? $pabellon . ' · ' . ($aula !== '' ? $aula : '—') : 'ubicación sin completar';
        $explicacion = [
            $reconocida
                ? 'Texto: se identificaron ' . count($aciertos) . ' palabra(s) clave (' . implode(', ', $aciertos) . ') asociadas a «' . $mejor . '».'
                : 'Texto: no se hallaron palabras clave concluyentes; se aplicó la categoría por defecto con baja confianza. Revisa la clasificación manualmente.',
            $tieneImagen
                ? 'Evidencia visual: la fotografía fue analizada y es coherente con ' . mb_strtolower(implode(' y ', $objetos)) . '.'
                : 'Evidencia visual: no se adjuntó fotografía; el análisis se basa solo en el texto (menor confianza).',
            'Características: ' . $ubicacion . ($area !== '' ? ' · área «' . $area . '»' : '') . '. '
                . ($motivo ? 'Urgencia detectada por «' . $motivo . '».' : 'Sin términos de urgencia: prioridad baja.')
                . ' Prioridad sugerida: ' . $prioridad->value . '.',
        ];

        return [
            'categoria'    => $mejor,
            'subcategoria' => $subMejor,
            'prioridad'    => $prioridad,
            'confianza'    => $confianza,
            'hits'         => $aciertos,
            'objetos'      => $objetos,
            'explicacion'  => $explicacion,
        ];
    }

    private function normalizar(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** ¿Aparece la palabra completa (con plural simple)? */
    private function coincide(string $texto, string $palabra): bool
    {
        return (bool) preg_match('/(^|[^a-z0-9])' . preg_quote($palabra, '/') . '(s|es)?([^a-z0-9]|$)/', $texto);
    }

    /** Número estable derivado del texto (da variedad sin ser aleatorio). */
    private function huella(string $s): int
    {
        $a = 0;
        foreach (str_split($s) as $c) {
            $a = ($a + ord($c)) % 997;
        }

        return $a;
    }
}
