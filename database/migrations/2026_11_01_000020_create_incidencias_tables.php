<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 20)->nullable()->unique();           // INC-2026-0001 (se completa al crear)
            $t->foreignId('reportante_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $t->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $t->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $t->foreignId('subcategoria_id')->nullable()->constrained('subcategorias')->nullOnDelete();
            $t->string('estado', 20)->default('Registrada')->index();
            $t->string('prioridad', 10)->default('Baja');
            $t->string('titulo', 180);
            $t->text('descripcion');
            $t->unsignedTinyInteger('confianza_ia')->nullable();
            $t->text('informe_tecnico')->nullable();
            $t->timestamps();
            $t->index(['reportante_id', 'estado']);
            $t->index(['tecnico_id', 'estado']);
        });

        Schema::create('evidencias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $t->string('tipo', 10);                                    // reporte | solucion
            $t->string('ruta', 255);                                   // dentro del disco privado
            $t->string('mime', 50);
            $t->unsignedInteger('tamano');
            $t->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('historial', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $t->string('estado', 20);
            $t->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nota', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('analisis_ia', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incidencia_id')->unique()->constrained('incidencias')->cascadeOnDelete();
            $t->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $t->foreignId('subcategoria_id')->nullable()->constrained('subcategorias')->nullOnDelete();
            $t->string('prioridad', 10);
            $t->unsignedTinyInteger('confianza');
            $t->string('palabras_clave', 500)->nullable();
            $t->string('objetos', 500)->nullable();
            $t->text('explicacion');
            $t->boolean('validada')->default(false);
            $t->boolean('modificada')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_ia');
        Schema::dropIfExists('historial');
        Schema::dropIfExists('evidencias');
        Schema::dropIfExists('incidencias');
    }
};
