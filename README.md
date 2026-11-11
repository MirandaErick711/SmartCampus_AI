# SmartCampus AI

Plataforma web para **reportar, clasificar y atender incidencias** de una universidad (luminarias, redes, mobiliario, agua, seguridad…). Hecha con **Laravel 12** y MySQL, con diseño oscuro (OLED neón) responsivo y todo en español.

## ¿Qué hace?

- **Reportar incidencias** con ubicación (campus → pabellón → piso → aula), descripción y foto opcional.
- **Clasificación asistida:** sugiere categoría, subcategoría y prioridad. Hoy es un **motor de reglas** (palabras clave) que *simula* una IA; **no analiza la foto**. Está aislado en `app/Services/MotorIA.php` para cambiarlo por un modelo real más adelante.
- **Ciclo de 6 estados:** Registrada → En evaluación → Asignada → En atención → Resuelta → Cerrada, con línea de tiempo e historial.
- **5 roles:** Docente y Administrativo (reportan), Operador (evalúa y asigna), Técnico (atiende) y Administrador (todo, incluidas las cuentas y el catálogo de ubicaciones).
- **Cuentas seguras:** registro con confirmación de correo, aprobación del administrador para Operador y Técnico, recuperación de contraseña por correo, bloqueo tras 5 intentos fallidos y relevo de administrador (solo hay uno activo).
- **Panel con gráficos**, **campana de notificaciones**, **auditoría** y **reportes** (con exportación a CSV).
- **Fotos privadas:** solo las ve quien tiene permiso sobre la incidencia.

## Requisitos

- [XAMPP](https://www.apachefriends.org/) (PHP **8.2 o superior** y MySQL) con la extensión `pdo_sqlite` activa (viene por defecto; las pruebas la usan).
- [Composer](https://getcomposer.org/) 2.
- [Git](https://git-scm.com/).
- No hace falta Node ni npm.

## Instalación (primera vez)

1. **Clonar** el repositorio y entrar a la carpeta:
   ```
   git clone URL-DEL-REPOSITORIO
   cd NOMBRE-DE-LA-CARPETA
   ```
2. **Instalar dependencias:**
   ```
   composer install
   ```
3. **Crear tu `.env`** a partir de la plantilla y generar la clave:
   ```
   copy .env.example .env
   php artisan key:generate
   ```
   (En Linux o Mac: `cp .env.example .env`.)
4. **Crear la base de datos.** En phpMyAdmin (<http://localhost/phpmyadmin>) crea una base vacía llamada `smartcampus` con cotejamiento `utf8mb4_unicode_ci`. Asegúrate de que MySQL esté encendido en XAMPP.
5. **Editar tu `.env`** y llenar, como mínimo:
   - `SC_ADMIN_EMAIL`: tu correo real (será tu administrador).
   - `SC_ADMIN_NOMBRE`: tu nombre.
   - `DB_USERNAME` y `DB_PASSWORD` si tu MySQL no usa `root` sin clave.
6. **Crear tablas, catálogos y administrador:**
   ```
   php artisan migrate --seed
   ```
7. **Iniciar el sistema:**
   ```
   php artisan serve
   ```
   y abrir <http://127.0.0.1:8000>.

> Cada persona tiene **su propio `.env`**. Ese archivo **nunca se sube a Git** (ya está excluido) ni se comparte por chat: tiene claves personales.

## Primer ingreso

1. Pulsa **Acceder** e inicia sesión con el correo de `SC_ADMIN_EMAIL` y la clave `Admin2026*` (o la de `SC_ADMIN_CLAVE`).
2. El sistema te obliga a **cambiar esa clave temporal** antes de seguir.
3. En **Usuarios y roles** puedes invitar gente o aprobar solicitudes. En **Ubicaciones**, agregar pabellones y aulas.

## Correos

El sistema envía correos para confirmar cuentas, recuperar contraseñas e invitar usuarios.

### Para probar sin correo real (por defecto)

Con `MAIL_MAILER=log` no se envía nada: cada correo queda escrito en `storage/logs/laravel.log`. Abre ese archivo, busca el enlace (por ejemplo `.../correo/verificar/...`) y ábrelo en el navegador. Sirve para probar con correos inventados.

### Correos reales con Gmail

1. En tu cuenta de Google activa la **verificación en dos pasos** (Seguridad).
2. En la misma sección crea una **contraseña de aplicación** (*Contraseñas de aplicaciones*). Google te muestra **16 letras** una sola vez; cópialas **sin espacios**. No es la clave normal de tu Gmail.
3. En tu `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=tucorreo@gmail.com
   MAIL_PASSWORD=las16letrassinespacios
   MAIL_FROM_ADDRESS=tucorreo@gmail.com
   ```
4. Ejecuta `php artisan config:clear` y reinicia `php artisan serve`.
5. **Prueba:** cierra sesión → **¿Olvidaste tu contraseña?** → escribe tu correo. Debe llegar un correo de SmartCampus AI (revisa *Spam*).

**Importante:** que `APP_URL` en el `.env` sea `http://127.0.0.1:8000` (o la dirección con la que abres el sistema). Los botones de los correos usan esa dirección; si está mal, el botón dice "no se puede acceder a este sitio".

## Pruebas automáticas

```
php artisan test
```

Usan una base de datos temporal en memoria y no tocan tu MySQL. **Antes de la primera vez**, abre `phpunit.xml` y comprueba que estas dos líneas estén **activas** (sin `<!-- -->`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Si estuvieran comentadas, las pruebas se ejecutarían contra tu MySQL y **borrarían tus datos**.

## Problemas frecuentes

| Qué ves | Qué hacer |
|---|---|
| `ERR_CONNECTION_REFUSED` en el navegador | `php artisan serve` no está corriendo. Ábrelo de nuevo. |
| Error de conexión a la base de datos | Enciende MySQL en XAMPP y revisa que exista la base `smartcampus` y los datos `DB_*` del `.env`. |
| `could not find driver` al probar | En `C:\xampp\php\php.ini` quita el `;` de `extension=pdo_sqlite` y reinicia. |
| Error de certificado al enviar correo (`unable to get local issuer certificate`) | En `php.ini`: `openssl.cafile="C:\xampp\apache\bin\curl-ca-bundle.crt"` y reinicia. |
| El botón del correo no abre | Revisa `APP_URL` y que el servidor esté corriendo (ver sección Correos). |
| No puedo entrar con la clave del administrador | Corre `php artisan migrate:fresh --seed` (**borra todos los datos**) o usa **¿Olvidaste tu contraseña?**. |
| `php artisan db:show` falla en XAMPP | Es un problema conocido de XAMPP; no afecta al sistema. |

## Estructura

```
app/Http/Controllers   controladores (acceso, incidencias, panel, reportes…)
app/Services           reglas de negocio (IncidenciaService, MotorIA, Estadisticas…)
app/Models, Enums      modelos y estados/prioridades
app/Policies           quién puede ver cada incidencia
database/migrations    tablas      database/seeders   roles, administrador y catálogos
resources/views        pantallas (Blade)
public/css, public/js  diseño y comportamiento
tests                  pruebas automáticas
```

## Seguridad

Consultas preparadas (Eloquent), escape automático de textos y cabecera CSP contra scripts ajenos, token CSRF en cada formulario, contraseñas cifradas, bloqueo por intentos, respuestas que no revelan si un correo existe, enlaces de correo firmados y con vencimiento, roles validados en el servidor y fotos en disco privado con verificación del contenido real.

## Trabajo en equipo (Git)

- Nunca subas `.env`, `vendor/` ni `storage/logs/`. Antes de cada commit, mira `git status`.
- Trabaja en una **rama propia** y abre un *Pull Request* hacia `main`.
- Si cambias la base de datos, hazlo con una **migración nueva**, no editando las anteriores.

## Publicarlo en un servidor

Usa `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con tu dominio **https**, `SESSION_SECURE_COOKIE=true` y un usuario de MySQL propio (no `root`). Apunta el servidor web a la carpeta `public/`. Instala con `composer install --no-dev --optimize-autoloader`, ejecuta `php artisan migrate --force` (**nunca** `migrate:fresh`) y `php artisan config:cache`. Conserva la carpeta `storage/app/private` (fotos) y haz copias de respaldo de la base.
