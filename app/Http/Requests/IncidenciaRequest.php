<?php

namespace App\Http\Requests;

use App\Enums\Prioridad;
use App\Models\Subcategoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Datos del formulario "Reportar incidencia". El que reporta nunca decide estado, técnico ni código. */
class IncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->grupo(), ['reportante', 'operador', 'administrador'], true);
    }

    protected function prepareForValidation(): void
    {
        // Sin etiquetas HTML (además, Blade escapa todo al mostrarlo).
        $this->merge([
            'titulo'      => trim(strip_tags((string) $this->input('titulo'))),
            'descripcion' => trim(strip_tags((string) $this->input('descripcion'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'titulo'          => ['required', 'string', 'min:5', 'max:180'],
            'descripcion'     => ['required', 'string', 'min:10', 'max:4000'],
            'ubicacion_id'    => ['required', 'integer', 'exists:ubicaciones,id'],
            'area_id'         => ['required', 'integer', 'exists:areas,id'],
            'categoria_id'    => ['nullable', 'integer', 'exists:categorias,id'],
            'subcategoria_id' => ['nullable', 'integer', function (string $a, mixed $v, \Closure $fallar) {
                if ($v && ! Subcategoria::where('id', $v)->where('categoria_id', $this->input('categoria_id'))->exists()) {
                    $fallar('La subcategoría no pertenece a la categoría elegida.');
                }
            }],
            'prioridad'       => ['nullable', Rule::enum(Prioridad::class)],
            'foto'            => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    public function attributes(): array
    {
        return [
            'titulo' => 'título', 'descripcion' => 'descripción', 'ubicacion_id' => 'ubicación',
            'area_id' => 'área afectada', 'categoria_id' => 'categoría', 'subcategoria_id' => 'subcategoría',
            'prioridad' => 'prioridad', 'foto' => 'fotografía',
        ];
    }
}
