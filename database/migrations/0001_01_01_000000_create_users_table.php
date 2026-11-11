<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Reemplaza a la migración de usuarios que trae Laravel.
 * Crea: roles, users, password_reset_tokens y sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('nombre', 60);
            $table->string('grupo', 20);                         // reportante | operador | tecnico | administrador
            $table->boolean('requiere_aprobacion')->default(false);
            $table->boolean('registro_publico')->default(true);  // ¿se puede elegir al registrarse?
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 120);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_pendiente', 190)->nullable();  // nuevo correo a la espera de confirmación
            $table->string('password');
            $table->foreignId('rol_id')->constrained('roles');
            $table->string('departamento', 120)->nullable();
            // pendiente (espera aprobación) | activa | inactiva | invitada (aún no activó su cuenta) | cerrada
            $table->string('estado', 20)->default('activa')->index();
            $table->boolean('debe_cambiar_clave')->default(false);
            $table->timestamp('ultimo_acceso')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
