-- =====================================================================
-- SMARTCAMPUS AI - Datos Iniciales
-- =====================================================================

USE smartcampus_db;

-- 1. ROLES
INSERT INTO Roles (idRol, nomRol, descripRol) VALUES
(1, 'administrador', 'Superusuario del sistema con acceso total'),
(2, 'operador', 'Gestor de mesa de ayuda, revisa y asigna incidencias'),
(3, 'tecnico', 'Personal de mantenimiento que atiende las incidencias'),
(4, 'reportante', 'Estudiantes, docentes y personal que reportan problemas');

-- 2. USUARIOS
INSERT INTO Usuarios (codUsuario, nomUsuario, email, password, idRol, departamento) VALUES
('u1', 'Maycol García', 'maycol.garcia@smartcampus.edu.pe', 'demo123', 4, 'Estudiante - Ing. de Sistemas'),
('u2', 'Janei Alburqueque', 'janei.alburqueque@smartcampus.edu.pe', 'demo123', 4, 'Docente - Ciencias e Ingeniería'),
('u3', 'Jorge Ávila', 'jorge.avila@smartcampus.edu.pe', 'demo123', 2, 'Mesa de Ayuda Operativa'),
('u4', 'Erick Miranda', 'erick.miranda@smartcampus.edu.pe', 'demo123', 3, 'Mantenimiento e Infraestructura'),
('u5', 'Juan Liñan', 'juan.linan@smartcampus.edu.pe', 'demo123', 3, 'Redes, Conectividad y TI'),
('u6', 'David Valverde', 'david.valverde@smartcampus.edu.pe', 'demo123', 3, 'Mobiliario y Servicios Generales'),
('u7', 'Ángel Rojas', 'angel.rojas@smartcampus.edu.pe', 'demo123', 1, 'Administración General de TI');

-- 3. CATEGORIAS
INSERT INTO Categorias (idCategoria, nomCateg, icono, color) VALUES
(1, 'Infraestructura', 'building-2', '#FFB800'),
(2, 'Tecnología', 'monitor-smartphone', '#00E5FF'),
(3, 'Mobiliario', 'armchair', '#00FF66'),
(4, 'Servicios', 'sparkles', '#B026FF');

-- 4. SUBCATEGORIAS
INSERT INTO SubCategorias (idCategoria, nomSubCatg) VALUES
(1, 'Iluminación / Instalaciones Eléctricas'),
(1, 'Estructura y Acabados'),
(1, 'Puertas y Ventanas'),
(2, 'Equipamiento / Conectividad'),
(2, 'Software y Sistemas'),
(3, 'Mobiliario de Aula / Laboratorio'),
(3, 'Mobiliario Administrativo'),
(4, 'Mantenimiento / Limpieza'),
(4, 'Seguridad y Vigilancia');

-- 5. UBICACIONES
INSERT INTO Ubicaciones (campus, pabellon, piso, aula_espacio, area_tipo) VALUES
-- Campus 1
('Campus 1', 'Pabellón de Sistemas', '3', 'Aula 302', 'Academica'),
('Campus 1', 'Pabellón de Sistemas', '2', 'Aula 201', 'Academica'),
('Campus 1', 'Pabellón de Sistemas', '1', 'Laboratorio D', 'Laboratorio'),
('Campus 1', 'Pabellón de Civil', '2', 'Aula C-201', 'Academica'),
('Campus 1', 'Pabellón de Civil', '1', 'Laboratorio de Estructuras', 'Laboratorio'),
('Campus 1', 'Pabellón de Enfermería', '1', 'Laboratorio de Simulación', 'Laboratorio'),
('Campus 1', 'Pabellón de Enfermería', '2', 'Aula E-202', 'Academica'),
-- Campus 2
('Campus 2', 'Pabellón de Agronomía', '1', 'Laboratorio de Suelos', 'Laboratorio'),
('Campus 2', 'Pabellón de Agronomía', '2', 'Aula AG-201', 'Academica'),
('Campus 2', 'Pabellón de Derecho', '1', 'Sala de Audiencias', 'Academica'),
('Campus 2', 'Pabellón de Derecho', '2', 'Aula D-202', 'Academica');

-- 6. INCIDENCIAS
INSERT INTO Incidencias (codIncdencia, titulo, descripIncidencia, idUbic, idSubCateg, prioridad, estado, idReportante, idTecnico) VALUES
('INC-2026-0001', 'La luminaria del aula 302 no funciona', 'La luminaria principal del aula 302 dejó de encender desde ayer. Los estudiantes no pueden ver bien la pizarra.', 1, 1, 'Alta', 'En atencion', 2, 4),
('INC-2026-0002', 'La silla de Laboratorio D está deteriorada', 'Una de las sillas de Laboratorio D tiene la pata floja y el respaldo roto. Representa riesgo de caída.', 3, 6, 'Media', 'Registrada', 1, NULL),
('INC-2026-0003', 'Proyector de Pabellón de Civil no enciende', 'El proyector multimedia del aula C-201 no recibe señal de video.', 4, 4, 'Alta', 'Asignada', 2, 5),
('INC-2026-0004', 'Gotera en baño de Pabellón de Agronomía', 'Fuga de agua constante cerca a la puerta principal del pabellón.', 9, 8, 'Critica', 'Revisada', 1, NULL),
('INC-2026-0005', 'Falta mantenimiento de microscopios en Enfermería', 'Dos equipos del Laboratorio de Simulación requieren calibración.', 6, 4, 'Baja', 'Resuelta', 2, 5);

-- 7. HISTORIAL DE INCIDENCIAS
INSERT INTO Historial_Incidencias (idIncidencia, estado_anterior, estado_nuevo, cambiado_por, nota) VALUES
(1, NULL, 'Registrada', 'Janei Alburqueque', 'Incidencia reportada por el docente'),
(1, 'Registrada', 'Revisada', 'Jorge Ávila', 'Operador validó el reporte'),
(1, 'Revisada', 'Asignada', 'Jorge Ávila', 'Asignada al técnico Erick Miranda'),
(1, 'Asignada', 'En atencion', 'Erick Miranda', 'Técnico inició revisión eléctrica en el aula 302'),
(2, NULL, 'Registrada', 'Maycol García', 'Reporte creado por estudiante Maycol García'),
(5, 'En atencion', 'Resuelta', 'Juan Liñan', 'Equipos calibrados y operativos');
