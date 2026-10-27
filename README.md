# SmartCampus AI · Sistema de Gestion de Incidencias Universitarias

> • README con requisitos, instalación, configuración de BD, pasos para ejecutar y credenciales de demostración si se requieren (nunca publicar secretos reales).

---

## 1. Requisitos
Para la ejecucion del proyecto en entorno local se requiere:
- **Servidor Web:** Apache 2.4 o superior (incluido en XAMPP).
- **Interprete:** PHP 8.0 o superior (con extensiones `pdo`, `pdo_mysql`, `mbstring` habilitadas por defecto).
- **Motor de Base de Datos:** MySQL 5.7+ o MariaDB 10.4+ (puerto estandar 3306).
- **Navegador Web:** Google Chrome, Mozilla Firefox, Microsoft Edge o cualquier navegador moderno compatible con HTML5/CSS3.

---

## 2. Instalacion
1. Descargar o clonar el repositorio del proyecto.
2. Copiar la carpeta del proyecto dentro del directorio publico de XAMPP:
   ```text
   C:\xampp\htdocs\SmartCampus_AI\
   ```
3. Iniciar los servicios de **Apache** y **MySQL** desde el Panel de Control de XAMPP.

---

## 3. Configuracion de BD
1. Abrir phpMyAdmin (`http://localhost/phpmyadmin`) o la consola de comandos de MySQL:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root
   ```
2. Ejecutar los scripts SQL ubicados en la carpeta `database/` en el siguiente orden estricto:
   - **Paso 1 (Estructura y Tablas en 3FN):**
     ```sql
     SOURCE C:/xampp/htdocs/SmartCampus_AI/database/01_Tablas.sql;
     ```
   - **Paso 2 (Datos iniciales de campus, aulas, categorias y usuarios):**
     ```sql
     SOURCE C:/xampp/htdocs/SmartCampus_AI/database/02_Datos.sql;
     ```
   - **Paso 3 (Opcional - Consultas de verificacion):**
     ```sql
     SOURCE C:/xampp/htdocs/SmartCampus_AI/database/03_Consulta.sql;
     ```
3. El archivo de conexion centralizado se encuentra en `includes/conexion.php` y utiliza los parametros estandar de desarrollo local:
   - Host: `localhost`
   - Base de Datos: `smartcampus_db`
   - Usuario: `root`
   - Clave: `""` (vacia)
   - Charset: `utf8mb4`

---

## 4. Pasos para Ejecutar
1. Verificar que los modulos Apache y MySQL esten corriendo en verde en el panel de XAMPP.
2. Abrir el navegador e ingresar a la URL principal del sistema:
   ```text
   http://localhost/SmartCampus_AI/
   ```
3. El sistema redirigira automaticamente al modulo de inicio de sesion (`index.php`).
4. Iniciar sesion con las credenciales de demostracion indicadas abajo, o utilizar los botones de acceso rapido de un solo clic.

---

## 5. Credenciales de Demostracion (Sin secretos reales)
*(Ambiente de pruebas educativas para evaluacion academica. Ninguna contrasena contiene secretos de produccion ni credenciales personales).*

- **Contrasena general para todos los perfiles:** `demo123`

| Rol en el Sistema | Correo Institucional de Prueba | Codigo | Permisos y Alcance |
|---|---|---|---|
| **Reportante** (Estudiante) | `maycol.garcia@smartcampus.edu.pe` | `u1` | Registro de incidencias y seguimiento de sus propios tickets. |
| **Reportante** (Docente) | `janei.alburqueque@smartcampus.edu.pe` | `u2` | Registro de incidencias en aulas y seguimiento propio. |
| **Operador** (Mesa de Ayuda) | `jorge.avila@smartcampus.edu.pe` | `u3` | Dashboard general, revision de estado y asignacion de personal tecnico. |
| **Tecnico** (Mantenimiento TI) | `erick.miranda@smartcampus.edu.pe` | `u4` | Bandeja de tareas asignadas, cambio a 'En atencion' y resolucion con informe. |
| **Tecnico** (Redes y Conectividad) | `juan.linan@smartcampus.edu.pe` | `u5` | Atencion de tickets de conectividad, equipos y telecomunicaciones. |
| **Tecnico** (Servicios Generales) | `david.valverde@smartcampus.edu.pe` | `u6` | Atencion de mobiliario, infraestructura fisica y mantenimiento. |
| **Administrador** (TI Campus) | `angel.rojas@smartcampus.edu.pe` | `u7` | Control total, gestion y alta de usuarios, auditoria y reportes. |

---

## 6. Estructura de Carpetas del Proyecto
```text
SmartCampus_AI/
├── assets/
│   ├── css/styles.css           # Estilos Dark OLED + Neon
│   ├── img/logo.svg             # Logotipo oficial del sistema
│   └── uploads/                 # Directorio de evidencias fotograficas
├── database/
│   ├── 01_Tablas.sql            # DDL estructurado y normalizado (3FN)
│   ├── 02_Datos.sql             # DML con datos de prueba iniciales
│   └── 03_Consulta.sql          # Consultas SQL para trazabilidad y KPIs
├── evidencias/                  # Carpeta de capturas de prueba para entrega
├── includes/
│   ├── conexion.php             # Conexion centralizada mediante PDO
│   ├── auth.php                 # Control de sesiones y seguridad RBAC
│   ├── header.php               # Encabezado HTML y navegacion superior
│   ├── sidebar.php              # Menu lateral adaptativo segun rol
│   └── footer.php               # Pie de pagina y cierre de etiquetas
├── index.php                    # Inicio de sesion y acceso rapido
├── dashboard.php                # Panel de control con metricas en vivo (Operador / Admin)
├── registrar.php                # Formulario de registro de averias (Reportante / Admin)
├── bandeja.php                  # Bandeja de seguimiento de incidencias segun rol
├── detalle.php                  # Ficha completa, gestion de cambios y trazabilidad
├── usuarios.php                 # Gestion y alta de usuarios (Admin)
├── ubicaciones.php              # Directorio de campus, pabellones y aulas
├── reportes.php                 # Analitica de rendimiento por tecnico y categoria
├── logout.php                   # Cierre seguro de sesion
└── README.md                    # Documentacion y guia de ejecucion
```
