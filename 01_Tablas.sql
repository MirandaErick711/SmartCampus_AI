-- =====================================================================
-- SMARTCAMPUS AI - Estructura de Base de Datos
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

-- 3. TABLA CATEGORIAS
CREATE TABLE Categorias (
    idCategoria INT AUTO_INCREMENT PRIMARY KEY,
    nomCateg VARCHAR(100) NOT NULL,
    icono VARCHAR(50) NULL,
    color VARCHAR(20) NULL
) ENGINE=InnoDB;

-- 4. TABLA SUBCATEGORIAS
CREATE TABLE SubCategorias (
    idSubCateg INT AUTO_INCREMENT PRIMARY KEY,
    idCategoria INT NOT NULL,
    nomSubCatg VARCHAR(120) NOT NULL,
    FOREIGN KEY (idCategoria) REFERENCES Categorias(idCategoria) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. TABLA UBICACIONES
CREATE TABLE Ubicaciones (
    idUbic INT AUTO_INCREMENT PRIMARY KEY,
    campus VARCHAR(50) NOT NULL,
    pabellon VARCHAR(80) NOT NULL,
    piso VARCHAR(20) NOT NULL,
    aula_espacio VARCHAR(80) NOT NULL,
    area_tipo ENUM('Academica', 'Laboratorio', 'Administrativa', 'Biblioteca', 'Servicios Generales', 'Areas comunes') DEFAULT 'Academica'
) ENGINE=InnoDB;

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
