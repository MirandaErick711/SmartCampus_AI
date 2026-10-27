-- =====================================================================
-- SMARTCAMPUS AI - Base de Datos MySQL / MariaDB (XAMPP)
-- Modelo Relacional Normalizado y 100% Acíclico (Sin bucles)
-- Campus: Campus 1 y Campus 2
-- Pabellones: Sistemas, Civil, Enfermería, Agronomía, Derecho
-- Estados: Registrada -> Revisada -> Asignada -> En atención -> Resuelta
-- =====================================================================

DROP DATABASE IF EXISTS smartcampus_db;
CREATE DATABASE smartcampus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcampus_db;

-- 1. TABLA ROLES
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
) ENGINE=InnoDB;

INSERT INTO roles (id, nombre, descripcion) VALUES
(1, 'administrador', 'Superusuario del sistema con acceso total'),
(2, 'operador', 'Gestor de mesa de ayuda, revisa y asigna incidencias'),
(3, 'tecnico', 'Personal de mantenimiento que atiende las incidencias'),
(4, 'reportante', 'Estudiantes, docentes y personal que reportan problemas');

-- 2. TABLA USUARIOS
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    departamento VARCHAR(120),
    activo BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO usuarios (codigo, nombre, email, password_hash, rol_id, departamento) VALUES
('u1', 'Maycol Garcia', 'maycol.garcia@smartcampus.edu.pe', 'demo123', 4, 'Estudiante - Ing. de Sistemas'),
('u2', 'Janei Alburqueque', 'janei.alburqueque@smartcampus.edu.pe', 'demo123', 4, 'Docente - Ciencias e Ingenieria'),
('u3', 'Jorge Avila', 'jorge.avila@smartcampus.edu.pe', 'demo123', 2, 'Mesa de Ayuda Operativa'),
('u4', 'Erick Miranda', 'erick.miranda@smartcampus.edu.pe', 'demo123', 3, 'Mantenimiento e Infraestructura'),
('u5', 'Juan Liñan', 'juan.linan@smartcampus.edu.pe', 'demo123', 3, 'Redes, Conectividad y TI'),
('u6', 'David Valverde', 'david.valverde@smartcampus.edu.pe', 'demo123', 3, 'Mobiliario y Servicios Generales'),
('u7', 'Angel Rojas', 'angel.rojas@smartcampus.edu.pe', 'demo123', 1, 'Administracion General de TI');

-- 3. TABLA CATEGORIAS Y SUBCATEGORIAS (Jerarquía lineal 1 a N sin bucles)
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50),
    color VARCHAR(20)
) ENGINE=InnoDB;

INSERT INTO categorias (id, nombre, icono, color) VALUES
(1, 'Infraestructura', 'building-2', '#FFB800'),
(2, 'Tecnologia', 'monitor-smartphone', '#00E5FF'),
(3, 'Mobiliario', 'armchair', '#00FF66'),
(4, 'Servicios', 'sparkles', '#B026FF');

CREATE TABLE subcategorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO subcategorias (categoria_id, nombre) VALUES
(1, 'Iluminacion / Instalaciones Electricas'),
(1, 'Estructura y Acabados'),
(1, 'Puertas y Ventanas'),
(2, 'Equipamiento / Conectividad'),
(2, 'Software y Sistemas'),
(3, 'Mobiliario de Aula / Laboratorio'),
(3, 'Mobiliario Administrativo'),
(4, 'Mantenimiento / Limpieza'),
(4, 'Seguridad y Vigilancia');

-- 4. TABLA UBICACIONES
CREATE TABLE ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campus VARCHAR(50) NOT NULL,
    pabellon VARCHAR(80) NOT NULL,
    piso VARCHAR(20) NOT NULL,
    aula_espacio VARCHAR(80) NOT NULL,
    area_tipo ENUM('Academica', 'Laboratorio', 'Administrativa', 'Biblioteca', 'Servicios Generales', 'Areas comunes') DEFAULT 'Academica'
) ENGINE=InnoDB;

INSERT INTO ubicaciones (campus, pabellon, piso, aula_espacio, area_tipo) VALUES
-- Campus 1
('Campus 1', 'Pabellon de Sistemas', '3', 'Aula 302', 'Academica'),
('Campus 1', 'Pabellon de Sistemas', '2', 'Aula 201', 'Academica'),
('Campus 1', 'Pabellon de Sistemas', '1', 'Laboratorio D', 'Laboratorio'),
('Campus 1', 'Pabellon de Civil', '2', 'Aula C-201', 'Academica'),
('Campus 1', 'Pabellon de Civil', '1', 'Laboratorio de Estructuras', 'Laboratorio'),
('Campus 1', 'Pabellon de Enfermeria', '1', 'Laboratorio de Simulacion', 'Laboratorio'),
('Campus 1', 'Pabellon de Enfermeria', '2', 'Aula E-202', 'Academica'),
-- Campus 2
('Campus 2', 'Pabellon de Agronoma', '1', 'Laboratorio de Suelos', 'Laboratorio'),
('Campus 2', 'Pabellon de Agronoma', '2', 'Aula AG-201', 'Academica'),
('Campus 2', 'Pabellon de Derecho', '1', 'Sala de Audiencias', 'Academica'),
('Campus 2', 'Pabellon de Derecho', '2', 'Aula D-202', 'Academica');

-- 5. TABLA INCIDENCIAS
-- La tipificación depende únicamente de subcategoria_id (evita redundancia de categoria_id)
CREATE TABLE incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    ubicacion_id INT NOT NULL,
    subcategoria_id INT NOT NULL,
    prioridad ENUM('Baja', 'Media', 'Alta', 'Critica') DEFAULT 'Media',
    estado ENUM('Registrada', 'Revisada', 'Asignada', 'En atencion', 'Resuelta') DEFAULT 'Registrada',
    confianza_ia INT DEFAULT NULL,
    reportante_id INT NOT NULL,
    tecnico_id INT NULL,
    imagen_evidencia LONGTEXT NULL,
    imagen_solucion LONGTEXT NULL,
    informe_tecnico TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id),
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id),
    FOREIGN KEY (reportante_id) REFERENCES usuarios(id),
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- 6. TABLA HISTORIAL DE ESTADOS (TRAZA INMUTABLE - BUCLE ROTO)
-- cambiado_por almacena el nombre/rol del responsable sin forzar clave foránea circular a usuarios
CREATE TABLE historial_incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    estado_anterior VARCHAR(50) NULL,
    estado_nuevo VARCHAR(50) NOT NULL,
    cambiado_por VARCHAR(120) NOT NULL,
    nota TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. TABLA NOTIFICACIONES (Unidireccional: destinada a usuario)
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    texto VARCHAR(255) NOT NULL,
    tipo ENUM('critica', 'asignacion', 'estado', 'info') DEFAULT 'info',
    incidencia_id INT NULL,
    leida BOOLEAN DEFAULT FALSE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- DATOS DE PRUEBA
INSERT INTO incidencias (codigo, titulo, descripcion, ubicacion_id, subcategoria_id, prioridad, estado, confianza_ia, reportante_id, tecnico_id) VALUES
('INC-2026-0001', 'La luminaria del aula 302 no funciona', 'La luminaria principal del aula 302 dejo de encender desde ayer. Los estudiantes no pueden ver bien la pizarra.', 1, 1, 'Alta', 'En atencion', 95, 2, 4),
('INC-2026-0002', 'La silla de Laboratorio D esta deteriorada', 'Una de las sillas de Laboratorio D tiene la pata floja y el respaldo roto. Representa riesgo de caida.', 3, 6, 'Media', 'Registrada', 92, 1, NULL),
('INC-2026-0003', 'Proyector de Pabellon de Civil no enciende', 'El proyector multimedia del aula C-201 no recibe senal de video.', 4, 4, 'Alta', 'Asignada', 90, 2, 5),
('INC-2026-0004', 'Gotera en bano de Pabellon de Agronoma', 'Fuga de agua constante cerca a la puerta principal del pabellon.', 9, 8, 'Critica', 'Revisada', 96, 1, NULL),
('INC-2026-0005', 'Falta mantenimiento de microscopios en Enfermeria', 'Dos equipos del Laboratorio de Simulacion requieren calibracion.', 6, 4, 'Baja', 'Resuelta', 88, 2, 5);

INSERT INTO historial_incidencias (incidencia_id, estado_anterior, estado_nuevo, cambiado_por, nota) VALUES
(1, NULL, 'Registrada', 'Janei Alburqueque', 'Incidencia reportada por el docente'),
(1, 'Registrada', 'Revisada', 'Jorge Avila', 'Operador valido el reporte'),
(1, 'Revisada', 'Asignada', 'Jorge Avila', 'Asignada al tecnico Erick Miranda'),
(1, 'Asignada', 'En atencion', 'Erick Miranda', 'Tecnico inicio revision electrica en el aula 302'),
(2, NULL, 'Registrada', 'Maycol Garcia', 'Reporte creado por estudiante Maycol Garcia'),
(5, 'En atencion', 'Resuelta', 'Juan Liñan', 'Equipos calibrados y operativos');
