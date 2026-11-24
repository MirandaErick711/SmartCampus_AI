<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('texto', 255);
            $t->string('tipo', 12)->default('info');                 // critica, asignacion, estado, info
            $t->foreignId('incidencia_id')->nullable()->constrained('incidencias')->nullOnDelete();
            $t->boolean('leida')->default(false);
            $t->timestamps();
            $t->index(['user_id', 'leida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
