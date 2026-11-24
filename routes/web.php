<?php

use App\Http\Controllers\Auth\CorreoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperarController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\AtencionIncidenciaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\GestionIncidenciaController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Páginas públicas
|--------------------------------------------------------------------------
*/
Route::get('/', LandingController::class)->name('inicio');

// Laravel manda aquí a quien intenta entrar a una página privada sin sesión: se abre el cuadro de login.
Route::get('/acceso', fn () => redirect()->route('inicio', ['acceso' => 'login']))->name('login');

Route::middleware('guest')->group(function () {
    Route::post('/ingresar', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('ingresar');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:6,10')->name('registro');

    Route::post('/correo/reenviar', [CorreoController::class, 'reenviar'])->middleware('throttle:6,10')->name('correo.reenviar');

    // Recuperar contraseña (nombres estándar de Laravel: password.*)
    Route::post('/olvide-clave', [RecuperarController::class, 'solicitar'])->middleware('throttle:6,10')->name('password.email');
    Route::get('/restablecer/{token}', [RecuperarController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer', [RecuperarController::class, 'guardar'])->middleware('throttle:10,10')->name('password.update');
});

// Enlaces de los correos: firmados (no se pueden alterar) y con vencimiento.
Route::get('/correo/verificar/{id}/{hash}', [CorreoController::class, 'verificar'])
    ->middleware(['signed', 'throttle:20,1'])->whereNumber('id')->name('correo.verificar');
Route::get('/cuenta/correo/{id}/{hash}', [CuentaController::class, 'confirmarCorreo'])
    ->middleware(['signed', 'throttle:20,1'])->whereNumber('id')->name('cuenta.correo.confirmar');

/*
|--------------------------------------------------------------------------
| Zona privada (sesión iniciada y cuenta activa)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'activa'])->group(function () {
    Route::post('/salir', [LoginController::class, 'destroy'])->name('logout');

    // "Mi cuenta" siempre disponible, incluso si falta cambiar la contraseña temporal
    Route::get('/cuenta', [CuentaController::class, 'edit'])->name('cuenta.edit');
    Route::put('/cuenta/perfil', [CuentaController::class, 'perfil'])->name('cuenta.perfil');
    Route::put('/cuenta/clave', [CuentaController::class, 'clave'])->middleware('throttle:10,10')->name('cuenta.clave');
    Route::post('/cuenta/correo', [CuentaController::class, 'correo'])->middleware('throttle:5,10')->name('cuenta.correo');

    // Todo lo demás exige haber cambiado la contraseña temporal
    Route::middleware('cambio.clave')->group(function () {
        Route::get('/panel', PanelController::class)->name('panel');

        // --- Incidencias ---
        Route::prefix('incidencias')->name('incidencias.')->group(function () {
            Route::get('/', [IncidenciaController::class, 'index'])->name('index');

            Route::middleware('grupo:reportante,operador,administrador')->group(function () {
                Route::get('/nueva', [IncidenciaController::class, 'create'])->name('create');
                Route::post('/', [IncidenciaController::class, 'store'])->middleware('throttle:20,10')->name('store');
                Route::post('/analizar', [IncidenciaController::class, 'analizar'])->middleware('throttle:40,1')->name('analizar');
            });

            Route::get('/{incidencia}', [IncidenciaController::class, 'show'])->whereNumber('incidencia')->name('show');

            // Operador y administrador
            Route::middleware('grupo:operador,administrador')->group(function () {
                Route::post('/{incidencia}/evaluar', [GestionIncidenciaController::class, 'evaluar'])->name('evaluar');
                Route::post('/{incidencia}/validar', [GestionIncidenciaController::class, 'validar'])->name('validar');
                Route::post('/{incidencia}/reclasificar', [GestionIncidenciaController::class, 'reclasificar'])->name('reclasificar');
                Route::post('/{incidencia}/asignar', [GestionIncidenciaController::class, 'asignar'])->name('asignar');
                Route::post('/{incidencia}/cerrar', [GestionIncidenciaController::class, 'cerrar'])->name('cerrar');
            });

            // Técnico
            Route::middleware('grupo:tecnico')->group(function () {
                Route::post('/{incidencia}/iniciar', [AtencionIncidenciaController::class, 'iniciar'])->name('iniciar');
                Route::post('/{incidencia}/resolver', [AtencionIncidenciaController::class, 'resolver'])->middleware('throttle:20,10')->name('resolver');
            });
        });

        // Fotos privadas (el permiso se revisa en el controlador)
        Route::get('/evidencias/{evidencia}', EvidenciaController::class)->whereNumber('evidencia')->name('evidencias.ver');

        // --- Notificaciones (campana) ---
        Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
            Route::get('/', [NotificacionController::class, 'index'])->name('index');
            Route::post('/leer-todas', [NotificacionController::class, 'leerTodas'])->name('leer-todas');
            Route::post('/{notificacion}/leer', [NotificacionController::class, 'leer'])->whereNumber('notificacion')->name('leer');
        });

        // --- Reportes y auditoría (operador y administrador) ---
        Route::middleware('grupo:operador,administrador')->group(function () {
            Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
            Route::get('/reportes/incidencias.csv', [ReporteController::class, 'csv'])->middleware('throttle:10,1')->name('reportes.csv');
            Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        });

        // --- Ubicaciones (administrador) ---
        Route::middleware('grupo:administrador')->prefix('ubicaciones')->name('ubicaciones.')->group(function () {
            Route::get('/', [UbicacionController::class, 'index'])->name('index');
            Route::post('/campus', [UbicacionController::class, 'campus'])->name('campus');
            Route::post('/pabellones', [UbicacionController::class, 'pabellon'])->name('pabellon');
            Route::post('/ambientes', [UbicacionController::class, 'ubicacion'])->name('ubicacion');
            Route::delete('/campus/{campus}', [UbicacionController::class, 'destroyCampus'])->name('campus.destroy');
            Route::delete('/pabellones/{pabellon}', [UbicacionController::class, 'destroyPabellon'])->name('pabellon.destroy');
            Route::delete('/ambientes/{ubicacion}', [UbicacionController::class, 'destroyUbicacion'])->name('ubicacion.destroy');
        });

        Route::middleware('grupo:administrador')->prefix('usuarios')->name('usuarios.')->group(function () {
            Route::get('/', [UsuarioController::class, 'index'])->name('index');
            Route::post('/invitar', [UsuarioController::class, 'invitar'])->name('invitar');
            Route::post('/relevo', [UsuarioController::class, 'relevo'])->middleware('throttle:5,10')->name('relevo');
            Route::post('/{usuario}/aprobar', [UsuarioController::class, 'aprobar'])->name('aprobar');
            Route::post('/{usuario}/invitacion', [UsuarioController::class, 'reenviarInvitacion'])->name('invitacion');
            Route::post('/{usuario}/restablecer', [UsuarioController::class, 'restablecer'])->middleware('throttle:10,10')->name('restablecer');
            Route::patch('/{usuario}/rol', [UsuarioController::class, 'rol'])->name('rol');
            Route::patch('/{usuario}/estado', [UsuarioController::class, 'estado'])->name('estado');
            Route::delete('/{usuario}', [UsuarioController::class, 'destroy'])->name('destroy');
        });
    });
});
