-- =====================================================================
-- SMARTCAMPUS AI - Base de Datos MySQL / MariaDB (XAMPP)
-- Nomenclatura exacta sincronizada con el Diagrama Entidad-Relación
-- =====================================================================

DROP DATABASE IF EXISTS smartcampus_db;
CREATE DATABASE smartcampus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcampus_db;

-- 1. TABLA ROLES
CREATE TABLE Roles (
    idRol INT AUTO_INCREMENT PRIMARY KEY,
    nomRol VARCHAR(50) NOT NULL UNIQUE,
    descripRol VARCHAR(150) NULL
) ENGINE=InnoDB;

INSERT INTO Roles (idRol, nomRol, descripRol) VALUES
(1, 'administrador', 'Superusuario del sistema con acceso total'),
(2, 'operador', 'Gestor de mesa de ayuda, revisa y asigna incidencias'),
(3, 'tecnico', 'Personal de mantenimiento que atiende las incidencias'),
(4, 'reportante', 'Estudiantes, docentes y personal que reportan problemas');

-- 2. TABLA USUARIOS
CREATE TABLE Usuarios (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    idRol INT NOT NULL,
    codUsuario VARCHAR(20) UNIQUE,
    nomUsuario VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    departamento VARCHAR(120) NULL,
    activo BOOLEAN DEFAULT TRUE,
    creadoUsr TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idRol) REFERENCES Roles(idRol) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO Usuarios (codUsuario, nomUsuario, email, password, idRol, departamento) VALUES
('u1', 'Maycol Garcia', 'maycol.garcia@smartcampus.edu.pe', 'demo123', 4, 'Estudiante - Ing. de Sistemas'),
('u2', 'Janei Alburqueque', 'janei.alburqueque@smartcampus.edu.pe', 'demo123', 4, 'Docente - Ciencias e Ingenieria'),
('u3', 'Jorge Avila', 'jorge.avila@smartcampus.edu.pe', 'demo123', 2, 'Mesa de Ayuda Operativa'),
('u4', 'Erick Miranda', 'erick.miranda@smartcampus.edu.pe', 'demo123', 3, 'Mantenimiento e Infraestructura'),
('u5', 'Juan Liñan', 'juan.linan@smartcampus.edu.pe', 'demo123', 3, 'Redes, Conectividad y TI'),
('u6', 'David Valverde', 'david.valverde@smartcampus.edu.pe', 'demo123', 3, 'Mobiliario y Servicios Generales'),
('u7', 'Angel Rojas', 'angel.rojas@smartcampus.edu.pe', 'demo123', 1, 'Administracion General de TI');

-- 3. TABLA CATEGORIAS
CREATE TABLE Categorias (
    idCategoria INT AUTO_INCREMENT PRIMARY KEY,
    nomCateg VARCHAR(100) NOT NULL,
    icono VARCHAR(50) NULL,
    color VARCHAR(20) NULL
) ENGINE=InnoDB;

INSERT INTO Categorias (idCategoria, nomCateg, icono, color) VALUES
(1, 'Infraestructura', 'building-2', '#FFB800'),
(2, 'Tecnologia', 'monitor-smartphone', '#00E5FF'),
(3, 'Mobiliario', 'armchair', '#00FF66'),
(4, 'Servicios', 'sparkles', '#B026FF');

-- 4. TABLA SUBCATEGORIAS
CREATE TABLE SubCategorias (
    idSubCateg INT AUTO_INCREMENT PRIMARY KEY,
    idCategoria INT NOT NULL,
    nomSubCatg VARCHAR(120) NOT NULL,
    FOREIGN KEY (idCategoria) REFERENCES Categorias(idCategoria) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO SubCategorias (idCategoria, nomSubCatg) VALUES
(1, 'Iluminacion / Instalaciones Electricas'),
(1, 'Estructura y Acabados'),
(1, 'Puertas y Ventanas'),
(2, 'Equipamiento / Conectividad'),
(2, 'Software y Sistemas'),
(3, 'Mobiliario de Aula / Laboratorio'),
(3, 'Mobiliario Administrativo'),
(4, 'Mantenimiento / Limpieza'),
(4, 'Seguridad y Vigilancia');

-- 5. TABLA UBICACIONES
CREATE TABLE Ubicaciones (
    idUbic INT AUTO_INCREMENT PRIMARY KEY,
    campus VARCHAR(50) NOT NULL,
    pabellon VARCHAR(80) NOT NULL,
    piso VARCHAR(20) NOT NULL,
    aula_espacio VARCHAR(80) NOT NULL,
    area_tipo ENUM('Academica', 'Laboratorio', 'Administrativa', 'Biblioteca', 'Servicios Generales', 'Areas comunes') DEFAULT 'Academica'
) ENGINE=InnoDB;

INSERT INTO Ubicaciones (campus, pabellon, piso, aula_espacio, area_tipo) VALUES
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

-- 6. TABLA INCIDENCIAS
CREATE TABLE Incidencias (
    idIncidencia INT AUTO_INCREMENT PRIMARY KEY,
    idReportante INT NOT NULL,
    idTecnico INT NULL,
    idSubCateg INT NOT NULL,
    idUbic INT NOT NULL,
    codIncdencia VARCHAR(30) NOT NULL UNIQUE,
    titulo VARCHAR(200) NOT NULL,
    descripIncidencia TEXT NOT NULL,
    prioridad ENUM('Baja', 'Media', 'Alta', 'Critica') DEFAULT 'Media',
    estado ENUM('Registrada', 'Revisada', 'Asignada', 'En atencion', 'Resuelta') DEFAULT 'Registrada',
    imagen_evidencia LONGTEXT NULL,
    imagen_solucion LONGTEXT NULL,
    informe_tecnico TEXT NULL,
    creadoIncd TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (idReportante) REFERENCES Usuarios(idUsuario),
    FOREIGN KEY (idTecnico) REFERENCES Usuarios(idUsuario),
    FOREIGN KEY (idSubCateg) REFERENCES SubCategorias(idSubCateg),
    FOREIGN KEY (idUbic) REFERENCES Ubicaciones(idUbic)
) ENGINE=InnoDB;

-- 7. TABLA HISTORIAL_INCIDENCIAS
CREATE TABLE Historial_Incidencias (
    idHistorial INT AUTO_INCREMENT PRIMARY KEY,
    idIncidencia INT NOT NULL,
    estado_anterior VARCHAR(50) NULL,
    estado_nuevo VARCHAR(50) NOT NULL,
    cambiado_por VARCHAR(120) NOT NULL,
    nota TEXT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idIncidencia) REFERENCES Incidencias(idIncidencia) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. TABLA NOTIFICACIONES
CREATE TABLE Notificaciones (
    idNotif INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    texto VARCHAR(255) NOT NULL,
    tipo ENUM('critica', 'asignacion', 'estado', 'info') DEFAULT 'info',
    idIncd INT NULL,
    leida BOOLEAN DEFAULT FALSE,
    creadoNotf TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idUsuario) REFERENCES Usuarios(idUsuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- SEEDERS DE PRUEBA
INSERT INTO Incidencias (codIncdencia, titulo, descripIncidencia, idUbic, idSubCateg, prioridad, estado, idReportante, idTecnico) VALUES
('INC-2026-0001', 'La luminaria del aula 302 no funciona', 'La luminaria principal del aula 302 dejo de encender desde ayer. Los estudiantes no pueden ver bien la pizarra.', 1, 1, 'Alta', 'En atencion', 2, 4),
('INC-2026-0002', 'La silla de Laboratorio D esta deteriorada', 'Una de las sillas de Laboratorio D tiene la pata floja y el respaldo roto. Representa riesgo de caida.', 3, 6, 'Media', 'Registrada', 1, NULL),
('INC-2026-0003', 'Proyector de Pabellon de Civil no enciende', 'El proyector multimedia del aula C-201 no recibe senal de video.', 4, 4, 'Alta', 'Asignada', 2, 5),
('INC-2026-0004', 'Gotera en bano de Pabellon de Agronoma', 'Fuga de agua constante cerca a la puerta principal del pabellon.', 9, 8, 'Critica', 'Revisada', 1, NULL),
('INC-2026-0005', 'Falta mantenimiento de microscopios en Enfermeria', 'Dos equipos del Laboratorio de Simulacion requieren calibracion.', 6, 4, 'Baja', 'Resuelta', 2, 5);

INSERT INTO Historial_Incidencias (idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota) VALUES
(1, NULL, 'Registrada', 'Janei Alburqueque', 'Incidencia reportada por el docente'),
(1, 'Registrada', 'Revisada', 'Jorge Avila', 'Operador valido el reporte'),
(1, 'Revisada', 'Asignada', 'Jorge Avila', 'Asignada al tecnico Erick Miranda'),
(1, 'Asignada', 'En atencion', 'Erick Miranda', 'Tecnico inicio revision electrica en el aula 302'),
(2, NULL, 'Registrada', 'Maycol Garcia', 'Reporte creado por estudiante Maycol Garcia'),
(5, 'En atencion', 'Resuelta', 'Juan Liñan', 'Equipos calibrados y operativos');
