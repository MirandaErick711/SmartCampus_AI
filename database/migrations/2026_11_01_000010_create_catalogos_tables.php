<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 120)->unique();
            $t->timestamps();
        });

        Schema::create('pabellones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campus_id')->constrained('campus')->restrictOnDelete();
            $t->string('nombre', 120);
            $t->string('descripcion', 255)->nullable();
            $t->unsignedTinyInteger('pisos')->default(1);
            $t->timestamps();
            $t->unique(['campus_id', 'nombre']);
        });

        Schema::create('ubicaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pabellon_id')->constrained('pabellones')->restrictOnDelete();
            $t->unsignedTinyInteger('piso');
            $t->string('tipo', 20)->default('aula');   // aula, laboratorio, area_comun, otro
            $t->string('nombre', 120);
            $t->timestamps();
            $t->unique(['pabellon_id', 'piso', 'nombre']);
        });

        Schema::create('areas', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 100)->unique();
        });

        Schema::create('categorias', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 100)->unique();
        });

        Schema::create('subcategorias', function (Blueprint $t) {
            $t->id();
            $t->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $t->string('nombre', 100);
            $t->unique(['categoria_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcategorias');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('ubicaciones');
        Schema::dropIfExists('pabellones');
        Schema::dropIfExists('campus');
    }
};
