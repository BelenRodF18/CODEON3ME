-- ============================================================================
-- FitPower · esquema y datos de la base
--
-- Script idempotente: crea la base desde cero o actualiza una existente, y se
-- puede volver a ejecutar sin duplicar datos.
--
--   1. Tablas          forma final del esquema
--   2. Migraciones     llevan una base vieja a la forma final
--   3. Datos base      roles, cuentas, planes, programas y entrenadores
--   4. Datos de ejemplo socios con plan, programas, rutinas y progreso
--
-- Contraseña de todas las cuentas sembradas: fitpower123
-- ============================================================================

CREATE DATABASE IF NOT EXISTS fitpower CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitpower;
SET NAMES utf8mb4;

-- ============================================================================
-- 1. Tablas (forma final del esquema)
-- ============================================================================

-- Roles del sistema: Administrador, Entrenador, Socio, Personal y Recepcion.
CREATE TABLE IF NOT EXISTS Tipo_usuario (
    ID_tipo INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE,
    Descripcion VARCHAR(255) NULL
);

-- Usuario es el nombre con el que se inicia sesión (ej. "admin", "juan.diaz").
CREATE TABLE IF NOT EXISTS Usuarios (
    ID_usuario INT AUTO_INCREMENT PRIMARY KEY,
    Usuario VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Cambiar_password BOOLEAN NOT NULL DEFAULT FALSE,
    Token_semilla VARCHAR(100) NULL,
    Activo BOOLEAN NOT NULL DEFAULT TRUE,
    ID_tipo INT NOT NULL,
    FOREIGN KEY (ID_tipo) REFERENCES Tipo_usuario(ID_tipo)
);

-- Datos personales de cada cuenta (una persona por usuario).
CREATE TABLE IF NOT EXISTS Persona (
    ID_persona INT AUTO_INCREMENT PRIMARY KEY,
    Peso DECIMAL(5,2) NULL,
    Edad INT NULL,
    Estatura DECIMAL(4,2) NULL,
    Genero VARCHAR(20) NULL,
    Nombre VARCHAR(100) NOT NULL,
    Foto VARCHAR(255) NULL,
    ID_usuario INT NOT NULL UNIQUE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

-- Entrenador_personal: el plan incluye un entrenador asignado al socio.
CREATE TABLE IF NOT EXISTS Plan (
    ID_plan INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE,
    Slug VARCHAR(40) NOT NULL,
    Precio DECIMAL(10,2) NOT NULL,
    Descripcion TEXT NULL,
    Entrenador_personal BOOLEAN NOT NULL DEFAULT FALSE,
    Activo BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uq_plan_slug (Slug)
);

-- Plan contratado por el socio, vencimiento y estado del pago.
CREATE TABLE IF NOT EXISTS Membresias (
    ID_membresia INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    ID_plan INT NOT NULL,
    Fecha_inicio DATE NOT NULL,
    Fecha_vencimiento DATE NOT NULL,
    Estado ENUM('Al dia','Vencida','Bloqueada') NOT NULL DEFAULT 'Al dia',
    Fecha_ultimo_pago DATETIME NULL,
    Monto_ultimo_pago DECIMAL(10,2) NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_plan) REFERENCES Plan(ID_plan)
);

-- Cada pago registrado del socio.
CREATE TABLE IF NOT EXISTS Historial_membresias (
    ID_historial INT AUTO_INCREMENT PRIMARY KEY,
    ID_usuario INT NOT NULL,
    ID_plan INT NOT NULL,
    Fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Monto_abonado DECIMAL(10,2) NOT NULL,
    Fecha_vencimiento_generada DATE NOT NULL,
    Metodo_pago VARCHAR(30) NOT NULL DEFAULT 'Efectivo',
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_plan) REFERENCES Plan(ID_plan)
);

-- Entrenador personal del socio (solo en planes con Entrenador_personal).
CREATE TABLE IF NOT EXISTS Socio_Entrenador (
    ID_socio INT PRIMARY KEY,
    ID_entrenador INT NOT NULL,
    Fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_socio) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE RESTRICT
);

-- Catálogo de ejercicios con el que se arman las rutinas.
CREATE TABLE IF NOT EXISTS Ejercicios (
    ID_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL UNIQUE,
    Grupo_muscular VARCHAR(50) NOT NULL,
    Descripcion TEXT NULL,
    Video_indicativo VARCHAR(255) NULL,
    Activo BOOLEAN NOT NULL DEFAULT TRUE
);

-- Cada programa tiene un entrenador a cargo, que es un usuario real.
CREATE TABLE IF NOT EXISTS Programas (
    ID_programa INT AUTO_INCREMENT PRIMARY KEY,
    Slug VARCHAR(40) NOT NULL,
    Nombre VARCHAR(100) NOT NULL UNIQUE,
    Imagen VARCHAR(255) NULL,
    Historia TEXT NULL,
    Tecnica TEXT NULL,
    Beneficios TEXT NULL,
    ID_entrenador INT NULL,
    Activo BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uq_programas_slug (Slug),
    CONSTRAINT fk_programas_entrenador FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL
);

-- Programas que incluye cada plan.
CREATE TABLE IF NOT EXISTS Plan_Programa (
    ID_plan INT NOT NULL,
    ID_programa INT NOT NULL,
    PRIMARY KEY (ID_plan, ID_programa),
    FOREIGN KEY (ID_plan) REFERENCES Plan(ID_plan) ON DELETE CASCADE,
    FOREIGN KEY (ID_programa) REFERENCES Programas(ID_programa) ON DELETE CASCADE
);

-- Programas en los que está inscripto cada socio (siempre dentro de su plan).
CREATE TABLE IF NOT EXISTS Socio_Programa (
    ID_socio INT NOT NULL,
    ID_programa INT NOT NULL,
    Fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID_socio, ID_programa),
    FOREIGN KEY (ID_socio) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_programa) REFERENCES Programas(ID_programa) ON DELETE CASCADE
);

-- ID_programa NULL = rutina del entrenamiento personal.
CREATE TABLE IF NOT EXISTS Rutinas (
    ID_rutina INT AUTO_INCREMENT PRIMARY KEY,
    Nombre_rutina VARCHAR(100) NOT NULL,
    Descripcion TEXT NULL,
    ID_entrenador INT NOT NULL,
    ID_programa INT NULL,
    Fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Activa BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_rutinas_programa FOREIGN KEY (ID_programa) REFERENCES Programas(ID_programa) ON DELETE SET NULL
);

-- Rutinas asignadas a cada socio.
CREATE TABLE IF NOT EXISTS Usuario_Rutina (
    ID_usuario INT NOT NULL,
    ID_rutina INT NOT NULL,
    Fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Estado VARCHAR(30) NOT NULL DEFAULT 'Asignada',
    PRIMARY KEY (ID_usuario, ID_rutina),
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_rutina) REFERENCES Rutinas(ID_rutina) ON DELETE CASCADE
);

-- Ejercicios de cada rutina con series, repeticiones y descanso.
CREATE TABLE IF NOT EXISTS Rutina_Ejercicio (
    ID_rutina_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    ID_rutina INT NOT NULL,
    ID_ejercicio INT NOT NULL,
    Series INT NOT NULL DEFAULT 3,
    Repeticiones VARCHAR(50) NOT NULL,
    Orden INT NOT NULL,
    Tiempo_descanso INT NOT NULL DEFAULT 90,
    Tiempo_ejecucion INT NULL,
    Notas TEXT NULL,
    FOREIGN KEY (ID_rutina) REFERENCES Rutinas(ID_rutina) ON DELETE CASCADE,
    FOREIGN KEY (ID_ejercicio) REFERENCES Ejercicios(ID_ejercicio) ON DELETE CASCADE
);

-- Series registradas por el socio; Record_personal marca las que superaron su mejor carga.
CREATE TABLE IF NOT EXISTS Registro_Progreso (
    ID_registro INT AUTO_INCREMENT PRIMARY KEY,
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Carga_utilizada DECIMAL(7,2) NOT NULL,
    Repeticiones_realizadas INT NOT NULL,
    Series_realizadas INT NOT NULL DEFAULT 1,
    Record_personal BOOLEAN NOT NULL DEFAULT FALSE,
    Sensaciones_entrenamiento TEXT NULL,
    Tiempo_descanso INT NULL,
    ID_usuario INT NOT NULL,
    ID_ejercicio INT NOT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_ejercicio) REFERENCES Ejercicios(ID_ejercicio) ON DELETE CASCADE
);

-- Reportes de mantenimiento y sugerencias. Ultimo_leido_socio = último mensaje que vio el socio.
CREATE TABLE IF NOT EXISTS Chat (
    ID_chat INT AUTO_INCREMENT PRIMARY KEY,
    Tipo_chat ENUM('Mantenimiento','Sugerencia') NOT NULL DEFAULT 'Mantenimiento',
    Titulo VARCHAR(100) NULL,
    Descripcion TEXT NOT NULL,
    Foto VARCHAR(255) NULL,
    Ubicacion VARCHAR(150) NULL,
    Fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Estado ENUM('Abierto','En revision','En reparacion','Solucionado') NOT NULL DEFAULT 'Abierto',
    ID_usuario INT NOT NULL,
    ID_personal INT NULL,
    Ultimo_leido_socio INT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_personal) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL
);

-- Mensajes de cada conversación (socio, personal, administrador y avisos automáticos).
CREATE TABLE IF NOT EXISTS Mensajes_chat (
    ID_mensaje INT AUTO_INCREMENT PRIMARY KEY,
    ID_chat INT NOT NULL,
    ID_usuario INT NOT NULL,
    Mensaje TEXT NOT NULL,
    Fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_chat) REFERENCES Chat(ID_chat) ON DELETE CASCADE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

-- Libro de canjes: solo se inserta una fila cuando Recepción escanea el QR.
-- Generar/mostrar un QR no escribe en MySQL.
CREATE TABLE IF NOT EXISTS Tokens_QR (
    ID_token INT AUTO_INCREMENT PRIMARY KEY,
    Token_hash VARCHAR(255) NOT NULL UNIQUE,
    ID_usuario INT NOT NULL,
    Fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Fecha_expiracion DATETIME NOT NULL,
    Fecha_uso DATETIME NULL,
    Usado BOOLEAN NOT NULL DEFAULT FALSE,
    ID_recepcion INT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_tokens_recepcion FOREIGN KEY (ID_recepcion) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL
);

-- Historial de ingresos: socio, token, fecha/hora, quién escaneó y resultado.
CREATE TABLE IF NOT EXISTS Registro_Accesos (
    ID_acceso INT AUTO_INCREMENT PRIMARY KEY,
    Fecha_hora_ingreso DATETIME NULL,
    Fecha_hora_salida DATETIME NULL,
    Estado_acceso ENUM('Permitido','Denegado') NOT NULL,
    Motivo VARCHAR(150) NULL,
    ID_usuario INT NOT NULL,
    ID_recepcion INT NULL,
    ID_token INT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_accesos_recepcion FOREIGN KEY (ID_recepcion) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL,
    CONSTRAINT fk_accesos_token FOREIGN KEY (ID_token) REFERENCES Tokens_QR(ID_token) ON DELETE SET NULL,
    INDEX idx_accesos_usuario_fecha (ID_usuario, Fecha_hora_ingreso)
);

-- ============================================================================
-- 2. Migraciones: llevan una base creada con una versión anterior a la forma final.
-- Cada paso primero consulta si hace falta; en una base nueva no hacen nada.
-- ============================================================================

-- Tokens_QR: fecha de uso y quién lo canjeó.
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Tokens_QR' AND COLUMN_NAME='Fecha_uso')=0,
    'ALTER TABLE Tokens_QR ADD COLUMN Fecha_uso DATETIME NULL AFTER Fecha_expiracion', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Tokens_QR' AND COLUMN_NAME='ID_recepcion')=0,
    'ALTER TABLE Tokens_QR ADD COLUMN ID_recepcion INT NULL AFTER Usado', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Tokens_QR' AND CONSTRAINT_NAME='fk_tokens_recepcion')=0,
    'ALTER TABLE Tokens_QR ADD CONSTRAINT fk_tokens_recepcion FOREIGN KEY (ID_recepcion) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- Registro_Accesos: recepcionista, token canjeado e índice de consulta.
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Registro_Accesos' AND COLUMN_NAME='ID_recepcion')=0,
    'ALTER TABLE Registro_Accesos ADD COLUMN ID_recepcion INT NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Registro_Accesos' AND COLUMN_NAME='ID_token')=0,
    'ALTER TABLE Registro_Accesos ADD COLUMN ID_token INT NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Registro_Accesos' AND CONSTRAINT_NAME='fk_accesos_recepcion')=0,
    'ALTER TABLE Registro_Accesos ADD CONSTRAINT fk_accesos_recepcion FOREIGN KEY (ID_recepcion) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Registro_Accesos' AND CONSTRAINT_NAME='fk_accesos_token')=0,
    'ALTER TABLE Registro_Accesos ADD CONSTRAINT fk_accesos_token FOREIGN KEY (ID_token) REFERENCES Tokens_QR(ID_token) ON DELETE SET NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Registro_Accesos' AND INDEX_NAME='idx_accesos_usuario_fecha')=0,
    'CREATE INDEX idx_accesos_usuario_fecha ON Registro_Accesos (ID_usuario, Fecha_hora_ingreso)', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- Plan: slug para la web pública y si incluye entrenador personal.
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Plan' AND COLUMN_NAME='Slug')=0,
    'ALTER TABLE Plan ADD COLUMN Slug VARCHAR(40) NULL AFTER Nombre', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Plan' AND COLUMN_NAME='Entrenador_personal')=0,
    'ALTER TABLE Plan ADD COLUMN Entrenador_personal BOOLEAN NOT NULL DEFAULT FALSE AFTER Descripcion', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Plan' AND INDEX_NAME='uq_plan_slug')=0,
    'CREATE UNIQUE INDEX uq_plan_slug ON Plan (Slug)', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- Programas: el entrenador pasa a ser un usuario (antes era texto suelto).
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='Slug')=0,
    'ALTER TABLE Programas ADD COLUMN Slug VARCHAR(40) NULL AFTER ID_programa', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='Imagen')=0,
    'ALTER TABLE Programas ADD COLUMN Imagen VARCHAR(255) NULL AFTER Nombre', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='ID_entrenador')=0,
    'ALTER TABLE Programas ADD COLUMN ID_entrenador INT NULL AFTER Beneficios', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='Activo')=0,
    'ALTER TABLE Programas ADD COLUMN Activo BOOLEAN NOT NULL DEFAULT TRUE AFTER ID_entrenador', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND INDEX_NAME='uq_programas_slug')=0,
    'CREATE UNIQUE INDEX uq_programas_slug ON Programas (Slug)', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND CONSTRAINT_NAME='fk_programas_entrenador')=0,
    'ALTER TABLE Programas ADD CONSTRAINT fk_programas_entrenador FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='Nombre_entrenador')>0,
    'ALTER TABLE Programas DROP COLUMN Nombre_entrenador', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Programas' AND COLUMN_NAME='Foto_entrenador')>0,
    'ALTER TABLE Programas DROP COLUMN Foto_entrenador', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- Chat: último mensaje que vio el socio (para avisarle las novedades).
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Chat' AND COLUMN_NAME='Ultimo_leido_socio')=0,
    'ALTER TABLE Chat ADD COLUMN Ultimo_leido_socio INT NULL AFTER ID_personal', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- Rutinas: a qué programa pertenecen.
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Rutinas' AND COLUMN_NAME='ID_programa')=0,
    'ALTER TABLE Rutinas ADD COLUMN ID_programa INT NULL AFTER ID_entrenador', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Rutinas' AND CONSTRAINT_NAME='fk_rutinas_programa')=0,
    'ALTER TABLE Rutinas ADD CONSTRAINT fk_rutinas_programa FOREIGN KEY (ID_programa) REFERENCES Programas(ID_programa) ON DELETE SET NULL', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- El login pasa de email a nombre de usuario: "admin@fitpower.com" -> "admin".
-- Si ese nombre ya lo usa otra cuenta, la fila queda como estaba.
UPDATE Usuarios u
LEFT JOIN (SELECT DISTINCT Usuario FROM Usuarios) ocupado
       ON ocupado.Usuario = LOWER(SUBSTRING_INDEX(u.Usuario, '@', 1))
SET u.Usuario = LOWER(SUBSTRING_INDEX(u.Usuario, '@', 1))
WHERE u.Usuario LIKE '%@%'
  AND ocupado.Usuario IS NULL;

-- Persona ya no guarda email.
SET @sql = (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Persona' AND COLUMN_NAME='Correo')>0,
    'ALTER TABLE Persona DROP COLUMN Correo', 'DO 0'));
PREPARE fp_stmt FROM @sql; EXECUTE fp_stmt; DEALLOCATE PREPARE fp_stmt;

-- ============================================================================
-- 3. Datos base: roles, cuentas, planes, programas, entrenadores y ejercicios
-- ============================================================================

-- Los cinco (y únicos) roles del sistema.
INSERT INTO Tipo_usuario (ID_tipo, Nombre, Descripcion) VALUES
(1,'Administrador','Gestiona usuarios, membresías y sugerencias'),
(2,'Entrenador','Dirige programas, crea rutinas y atiende socios'),
(3,'Socio','Consulta rutinas, membresía y rendimiento'),
(4,'Personal','Atiende limpieza y mantenimiento'),
(5,'Recepcion','Escanea el QR de los socios y registra los ingresos')
ON DUPLICATE KEY UPDATE Descripcion = VALUES(Descripcion);

-- Se borra cualquier rol extra que no tenga usuarios.
DELETE t FROM Tipo_usuario t
LEFT JOIN Usuarios u ON u.ID_tipo = t.ID_tipo
WHERE t.Nombre NOT IN ('Administrador','Entrenador','Socio','Personal','Recepcion')
  AND u.ID_usuario IS NULL;

-- Cuentas sembradas: las cinco de prueba, un entrenador por programa y una socia de ejemplo.
-- Solo se crean si no existen: nunca se pisan contraseñas. Contraseña: fitpower123.
DROP TEMPORARY TABLE IF EXISTS fp_seed_usuarios;
CREATE TEMPORARY TABLE fp_seed_usuarios (
    usuario VARCHAR(30) PRIMARY KEY,
    rol VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    edad INT NULL,
    genero VARCHAR(20) NULL,
    peso DECIMAL(5,2) NULL,
    estatura DECIMAL(4,2) NULL,
    foto VARCHAR(255) NULL
);
INSERT INTO fp_seed_usuarios (usuario, rol, nombre, edad, genero, peso, estatura, foto) VALUES
('admin',         'Administrador', 'Administrador General',     35, 'Masculino', NULL,  NULL, NULL),
('entrenador',    'Entrenador',    'Carlos Pérez',              28, 'Masculino', NULL,  NULL, 'entrenador1.jpg'),
('socio',         'Socio',         'Socio Demo',                22, 'Femenino',  72.50, 1.75, NULL),
('personal',      'Personal',      'Personal de Mantenimiento', 30, NULL,        NULL,  NULL, NULL),
('recepcion',     'Recepcion',     'Recepción FitPower',        NULL, NULL,      NULL,  NULL, NULL),
('juan.diaz',     'Entrenador',    'Juan Díaz',                 34, 'Masculino', NULL,  NULL, 'entrenador5.jpg'),
('diego.torres',  'Entrenador',    'Diego Torres',              31, 'Masculino', NULL,  NULL, 'entrenador11.jpg'),
('lucas.gomez',   'Entrenador',    'Lucas Gómez',               29, 'Masculino', NULL,  NULL, 'entrenador7.jpeg'),
('sofia.rey',     'Entrenador',    'Sofía Rey',                 27, 'Femenino',  NULL,  NULL, 'entrenador6.jpg'),
('elena.paz',     'Entrenador',    'Elena Paz',                 33, 'Femenino',  NULL,  NULL, 'entrenador8.jpg'),
('bruno.soler',   'Entrenador',    'Bruno Soler',               36, 'Masculino', NULL,  NULL, 'entrenador9.jpg'),
('matias.rossi',  'Entrenador',    'Matías Rossi',              30, 'Masculino', NULL,  NULL, 'entrenador3.jpg'),
('martina.lopez', 'Socio',         'Martina López',             27, 'Femenino',  61.50, 1.66, NULL);

INSERT INTO Usuarios (Usuario, Password, ID_tipo)
SELECT s.usuario, '$2y$12$fxwrjYYmGi/Uir3wP/chdeVY/p1RvZuI9NfsBkfw1nkejC6BvmKnu', t.ID_tipo
FROM fp_seed_usuarios s
INNER JOIN Tipo_usuario t ON t.Nombre = s.rol
WHERE NOT EXISTS (SELECT 1 FROM Usuarios u WHERE u.Usuario = s.usuario);

INSERT INTO Persona (Nombre, Edad, Genero, Peso, Estatura, Foto, ID_usuario)
SELECT s.nombre, s.edad, s.genero, s.peso, s.estatura, s.foto, u.ID_usuario
FROM fp_seed_usuarios s
INNER JOIN Usuarios u ON u.Usuario = s.usuario
WHERE NOT EXISTS (SELECT 1 FROM Persona p WHERE p.ID_usuario = u.ID_usuario);

-- Foto de los entrenadores que ya existían antes de este script.
UPDATE Persona p
INNER JOIN Usuarios u ON u.ID_usuario = p.ID_usuario
INNER JOIN fp_seed_usuarios s ON s.usuario = u.Usuario
SET p.Foto = s.foto
WHERE p.Foto IS NULL AND s.foto IS NOT NULL;

DROP TEMPORARY TABLE fp_seed_usuarios;

-- Planes: el Básico no incluye entrenador personal; Pro y Elite sí.
INSERT INTO Plan (Nombre, Slug, Precio, Descripcion, Entrenador_personal) VALUES
('Plan Básico', 'basico', 1800, 'Ideal para quienes quieren empezar a entrenar por su cuenta con guía.', FALSE),
('Plan Pro',    'pro',    2500, 'El equilibrio perfecto entre entrenamiento técnico y acompañamiento cercano.', TRUE),
('Plan Elite',  'elite',  4500, 'La experiencia completa de alto rendimiento con personalización total.', TRUE)
ON DUPLICATE KEY UPDATE Slug = VALUES(Slug), Precio = VALUES(Precio), Descripcion = VALUES(Descripcion),
                        Entrenador_personal = VALUES(Entrenador_personal);

-- Programas del gimnasio con el texto que muestra la web.
INSERT INTO Programas (Slug, Nombre, Imagen, Historia, Tecnica, Beneficios) VALUES
('boxeo', 'Boxeo', 'boxeo.jpg',
 'El boxeo tiene raíces milenarias, evolucionando de métodos de defensa a un deporte estratégico. En Fit Power, combinamos tradición con técnica moderna.',
 'Se basa en el juego de pies, el control del centro de gravedad y la ejecución precisa de jabs y rectos.',
 'Mejora cardiovascular, quema de grasa y liberación de adrenalina.'),
('musculacion', 'Musculación', 'musculacion.jpg',
 'La base de toda transformación física. Enfocada en la sobrecarga progresiva y el desarrollo de fibras musculares.',
 'Movimientos controlados, rango de movimiento completo y periodización de cargas.',
 'Aumento de fuerza, mejora de la postura y metabolismo acelerado.'),
('cross', 'Cross Training', 'crosstrainning.jpg',
 'Entrenamiento funcional de alta intensidad que prepara el cuerpo para cualquier demanda física real.',
 'Uso de pesos libres, ejercicios gimnásticos y movimientos explosivos.',
 'Resistencia física extrema, agilidad y potencia muscular.'),
('spinning', 'Spinning', 'spinning.jpg',
 'Cardio de alta intensidad en bicicleta fija, diseñado para desafiar tus límites al ritmo de la música.',
 'Control de cadencia, postura ergonómica y manejo de intensidades.',
 'Salud cardiovascular, quema de calorías y fortalecimiento del tren inferior.'),
('yoga', 'Yoga', 'yoga.jpg',
 'Una práctica ancestral que conecta mente, cuerpo y espíritu mediante asanas y control respiratorio.',
 'Control de la respiración (pranayama) y alineación corporal consciente.',
 'Reducción de cortisol, mejora de la flexibilidad y paz mental.'),
('pilates', 'Pilates', 'pilates.jpg',
 'Creado por Joseph Pilates para la rehabilitación, hoy es clave para la fuerza central (core).',
 'Activación profunda de los músculos estabilizadores del abdomen y pelvis.',
 'Corrección postural, alivio de dolores lumbares y tonicidad.'),
('funcional', 'Entreno Funcional', 'EntranamientoFuncional.jpg',
 'Entrenar para la vida cotidiana. Movimientos naturales que mejoran la eficiencia de tu cuerpo.',
 'Patrones de empuje, tracción, bisagra de cadera y rotación.',
 'Funcionalidad diaria, prevención de lesiones y equilibrio.'),
('natacion', 'Natación', 'natacion.jpg',
 'La disciplina más completa. El agua ofrece una resistencia natural que tonifica sin impacto.',
 'Técnica de brazada, patada y coordinación respiratoria en el agua.',
 'Bajo impacto, resistencia total y relajación muscular.')
ON DUPLICATE KEY UPDATE Slug = VALUES(Slug), Imagen = VALUES(Imagen), Historia = VALUES(Historia),
                        Tecnica = VALUES(Tecnica), Beneficios = VALUES(Beneficios);

-- Entrenador predeterminado de cada programa (solo si no tiene uno: se respeta lo que cambie el administrador).
UPDATE Programas pr
INNER JOIN (
    SELECT 'boxeo' AS slug, 'juan.diaz' AS usuario
    UNION ALL SELECT 'musculacion', 'diego.torres'
    UNION ALL SELECT 'cross',       'lucas.gomez'
    UNION ALL SELECT 'spinning',    'sofia.rey'
    UNION ALL SELECT 'yoga',        'entrenador'
    UNION ALL SELECT 'pilates',     'elena.paz'
    UNION ALL SELECT 'funcional',   'bruno.soler'
    UNION ALL SELECT 'natacion',    'matias.rossi'
) m ON m.slug = pr.Slug
INNER JOIN Usuarios u ON u.Usuario = m.usuario
SET pr.ID_entrenador = u.ID_usuario
WHERE pr.ID_entrenador IS NULL;

-- Programas de cada plan: el Básico incluye tres; Pro y Elite, todos.
INSERT INTO Plan_Programa (ID_plan, ID_programa)
SELECT pl.ID_plan, pr.ID_programa
FROM Plan pl
CROSS JOIN Programas pr
WHERE (pl.Slug IN ('pro', 'elite') OR (pl.Slug = 'basico' AND pr.Slug IN ('spinning', 'yoga', 'funcional')))
  AND NOT EXISTS (SELECT 1 FROM Plan_Programa x WHERE x.ID_plan = pl.ID_plan AND x.ID_programa = pr.ID_programa);

-- Catálogo de ejercicios.
INSERT INTO Ejercicios (Nombre, Grupo_muscular, Descripcion) VALUES
('Sentadilla libre',       'Piernas',   'Trabajo general de piernas'),
('Peso muerto rumano',     'Piernas',   'Bisagra de cadera con foco en isquiotibiales'),
('Press banca',            'Pecho',     'Empuje horizontal'),
('Remo con barra',         'Espalda',   'Tirón horizontal'),
('Press militar',          'Hombros',   'Empuje vertical con barra'),
('Pasadas en cinta',       'Cardio',    'Trabajo de velocidad'),
('Plancha abdominal',      'Core',      'Estabilidad abdominal'),
('Crunch abdominal',       'Core',      'Flexión de tronco'),
('Salto a la soga',        'Cardio',    'Coordinación y resistencia'),
('Sombra de boxeo',        'Boxeo',     'Combinaciones de golpes sin contacto'),
('Trabajo en bolsa',       'Boxeo',     'Potencia y precisión de golpes'),
('Pedaleo suave',          'Cardio',    'Entrada en calor en bicicleta fija'),
('Intervalos en bici',     'Cardio',    'Alternancia de pasadas fuertes y suaves'),
('Subida con resistencia', 'Cardio',    'Pedaleo de pie con carga alta'),
('Saludo al sol',          'Movilidad', 'Secuencia de posturas de yoga')
ON DUPLICATE KEY UPDATE Grupo_muscular = VALUES(Grupo_muscular), Descripcion = VALUES(Descripcion);

-- ============================================================================
-- 4. Datos de ejemplo
--   socio          Socio Demo · Plan Pro · entrenador personal Carlos Pérez
--                  Programas: Yoga (Carlos Pérez) y Entreno Funcional (Bruno Soler)
--   martina.lopez  Martina López · Plan Pro pagado (dos meses de historial)
--                  Entrenador personal: Diego Torres
--                  Programas: Musculación, Spinning y Boxeo, cada uno con su rutina
-- ============================================================================

-- Membresías vigentes de los socios de ejemplo.
INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento, Estado, Fecha_ultimo_pago, Monto_ultimo_pago)
SELECT u.ID_usuario, pl.ID_plan, m.inicio, m.vencimiento, 'Al dia', m.pago, pl.Precio
FROM (
    SELECT 'socio' AS usuario, 'pro' AS plan, CURRENT_DATE AS inicio,
           LAST_DAY(CURRENT_DATE) AS vencimiento, NOW() AS pago
    UNION ALL
    SELECT 'martina.lopez', 'pro', DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01'),
           LAST_DAY(CURRENT_DATE), TIMESTAMP(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), '10:15:00')
) m
INNER JOIN Usuarios u ON u.Usuario = m.usuario
INNER JOIN Plan pl ON pl.Slug = m.plan
WHERE NOT EXISTS (SELECT 1 FROM Membresias x WHERE x.ID_usuario = u.ID_usuario);

-- Pagos registrados (Martina: mes anterior y mes actual).
INSERT INTO Historial_membresias (ID_usuario, ID_plan, Fecha_pago, Monto_abonado, Fecha_vencimiento_generada, Metodo_pago)
SELECT u.ID_usuario, pl.ID_plan, h.pago, pl.Precio, h.vencimiento, h.metodo
FROM (
    SELECT 'socio' AS usuario, 'pro' AS plan, NOW() AS pago,
           LAST_DAY(CURRENT_DATE) AS vencimiento, 'Efectivo' AS metodo
    UNION ALL
    SELECT 'martina.lopez', 'pro', TIMESTAMP(DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01'), '09:40:00'),
           LAST_DAY(CURRENT_DATE - INTERVAL 1 MONTH), 'Efectivo'
    UNION ALL
    SELECT 'martina.lopez', 'pro', TIMESTAMP(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01'), '10:15:00'),
           LAST_DAY(CURRENT_DATE), 'Tarjeta de débito'
) h
INNER JOIN Usuarios u ON u.Usuario = h.usuario
INNER JOIN Plan pl ON pl.Slug = h.plan
WHERE NOT EXISTS (SELECT 1 FROM Historial_membresias x WHERE x.ID_usuario = u.ID_usuario);

-- Entrenador personal de cada socio de ejemplo (ambos con Plan Pro).
INSERT INTO Socio_Entrenador (ID_socio, ID_entrenador)
SELECT so.ID_usuario, en.ID_usuario
FROM (
    SELECT 'socio' AS socio, 'entrenador' AS entrenador
    UNION ALL SELECT 'martina.lopez', 'diego.torres'
) m
INNER JOIN Usuarios so ON so.Usuario = m.socio
INNER JOIN Usuarios en ON en.Usuario = m.entrenador
WHERE NOT EXISTS (SELECT 1 FROM Socio_Entrenador x WHERE x.ID_socio = so.ID_usuario);

-- Inscripción de los socios de ejemplo en programas de su plan.
INSERT INTO Socio_Programa (ID_socio, ID_programa)
SELECT so.ID_usuario, pr.ID_programa
FROM (
    SELECT 'socio' AS socio, 'yoga' AS programa
    UNION ALL SELECT 'socio',         'funcional'
    UNION ALL SELECT 'martina.lopez', 'musculacion'
    UNION ALL SELECT 'martina.lopez', 'spinning'
    UNION ALL SELECT 'martina.lopez', 'boxeo'
) m
INNER JOIN Usuarios so ON so.Usuario = m.socio
INNER JOIN Programas pr ON pr.Slug = m.programa
WHERE NOT EXISTS (SELECT 1 FROM Socio_Programa x WHERE x.ID_socio = so.ID_usuario AND x.ID_programa = pr.ID_programa);

-- Rutinas de ejemplo: las arma el entrenador del programa (sin programa = entrenamiento personal).
DROP TEMPORARY TABLE IF EXISTS fp_seed_rutinas;
CREATE TEMPORARY TABLE fp_seed_rutinas (
    nombre VARCHAR(100) PRIMARY KEY,
    descripcion TEXT NULL,
    entrenador VARCHAR(30) NOT NULL,
    programa VARCHAR(40) NULL,
    socio VARCHAR(30) NOT NULL
);
INSERT INTO fp_seed_rutinas (nombre, descripcion, entrenador, programa, socio) VALUES
('Hipertrofia - Día 1',   'Pecho y espalda con foco en técnica y control.',        'entrenador',   NULL,          'socio'),
('Movilidad y Core',      'Sesión corta para activar y recuperar.',                'entrenador',   'yoga',        'socio'),
('Fuerza tren superior',  'Empujes y tirones con progresión de cargas semanal.',   'diego.torres', 'musculacion', 'martina.lopez'),
('Piernas y core',        'Rutina del entrenamiento personal para tren inferior.', 'diego.torres', NULL,          'martina.lopez'),
('Intervalos en bici',    'Clase de spinning por bloques de intensidad.',          'sofia.rey',    'spinning',    'martina.lopez'),
('Técnica de golpes',     'Guardia, desplazamientos y combinaciones básicas.',     'juan.diaz',    'boxeo',       'martina.lopez');

INSERT INTO Rutinas (Nombre_rutina, Descripcion, ID_entrenador, ID_programa)
SELECT s.nombre, s.descripcion, en.ID_usuario, pr.ID_programa
FROM fp_seed_rutinas s
INNER JOIN Usuarios en ON en.Usuario = s.entrenador
LEFT JOIN Programas pr ON pr.Slug = s.programa
WHERE NOT EXISTS (SELECT 1 FROM Rutinas r WHERE r.Nombre_rutina = s.nombre AND r.ID_entrenador = en.ID_usuario);

-- Rutinas creadas antes de que existiera la relación con programas.
UPDATE Rutinas r
INNER JOIN Usuarios en ON en.ID_usuario = r.ID_entrenador
INNER JOIN fp_seed_rutinas s ON s.nombre = r.Nombre_rutina AND s.entrenador = en.Usuario
INNER JOIN Programas pr ON pr.Slug = s.programa
SET r.ID_programa = pr.ID_programa
WHERE r.ID_programa IS NULL;

-- Cada rutina se asigna a su socio.
INSERT INTO Usuario_Rutina (ID_usuario, ID_rutina)
SELECT so.ID_usuario, r.ID_rutina
FROM fp_seed_rutinas s
INNER JOIN Usuarios en ON en.Usuario = s.entrenador
INNER JOIN Rutinas r ON r.Nombre_rutina = s.nombre AND r.ID_entrenador = en.ID_usuario
INNER JOIN Usuarios so ON so.Usuario = s.socio
WHERE NOT EXISTS (SELECT 1 FROM Usuario_Rutina ur WHERE ur.ID_usuario = so.ID_usuario AND ur.ID_rutina = r.ID_rutina);

-- Ejercicios de cada rutina de ejemplo.
DROP TEMPORARY TABLE IF EXISTS fp_seed_rutina_ejercicios;
CREATE TEMPORARY TABLE fp_seed_rutina_ejercicios (
    rutina VARCHAR(100) NOT NULL,
    ejercicio VARCHAR(100) NOT NULL,
    series INT NOT NULL,
    repeticiones VARCHAR(50) NOT NULL,
    orden INT NOT NULL,
    descanso INT NOT NULL,
    PRIMARY KEY (rutina, orden)
);
INSERT INTO fp_seed_rutina_ejercicios (rutina, ejercicio, series, repeticiones, orden, descanso) VALUES
('Hipertrofia - Día 1',  'Press banca',            4, '8-10',                       1, 120),
('Hipertrofia - Día 1',  'Remo con barra',         4, '10',                         2,  90),
('Movilidad y Core',     'Plancha abdominal',      3, '45 segundos',                1,  60),
('Movilidad y Core',     'Saludo al sol',          3, '5 ciclos',                   2,  45),
('Fuerza tren superior', 'Press banca',            4, '8-10',                       1, 120),
('Fuerza tren superior', 'Remo con barra',         4, '10',                         2,  90),
('Fuerza tren superior', 'Press militar',          3, '10',                         3,  90),
('Piernas y core',       'Sentadilla libre',       4, '8',                          1, 150),
('Piernas y core',       'Peso muerto rumano',     3, '10',                         2, 120),
('Piernas y core',       'Plancha abdominal',      3, '45 segundos',                3,  60),
('Intervalos en bici',   'Pedaleo suave',          1, '10 minutos',                 1,   0),
('Intervalos en bici',   'Intervalos en bici',     8, '30 s fuerte / 30 s suave',   2,  30),
('Intervalos en bici',   'Subida con resistencia', 4, '3 minutos',                  3,  90),
('Técnica de golpes',    'Salto a la soga',        3, '2 minutos',                  1,  60),
('Técnica de golpes',    'Sombra de boxeo',        4, '3 minutos',                  2,  60),
('Técnica de golpes',    'Trabajo en bolsa',       5, '3 minutos',                  3,  60);

INSERT INTO Rutina_Ejercicio (ID_rutina, ID_ejercicio, Series, Repeticiones, Orden, Tiempo_descanso)
SELECT r.ID_rutina, ej.ID_ejercicio, x.series, x.repeticiones, x.orden, x.descanso
FROM fp_seed_rutina_ejercicios x
INNER JOIN fp_seed_rutinas s ON s.nombre = x.rutina
INNER JOIN Usuarios en ON en.Usuario = s.entrenador
INNER JOIN Rutinas r ON r.Nombre_rutina = s.nombre AND r.ID_entrenador = en.ID_usuario
INNER JOIN Ejercicios ej ON ej.Nombre = x.ejercicio
WHERE NOT EXISTS (SELECT 1 FROM Rutina_Ejercicio re WHERE re.ID_rutina = r.ID_rutina AND re.Orden = x.orden);

DROP TEMPORARY TABLE fp_seed_rutina_ejercicios;
DROP TEMPORARY TABLE fp_seed_rutinas;

-- Series cargadas por Martina en las últimas semanas.
INSERT INTO Registro_Progreso (Fecha, Carga_utilizada, Repeticiones_realizadas, Record_personal,
                               Sensaciones_entrenamiento, Tiempo_descanso, ID_usuario, ID_ejercicio)
SELECT NOW() - INTERVAL p.dias DAY, p.carga, p.reps, 1, p.sensacion, p.descanso, u.ID_usuario, ej.ID_ejercicio
FROM (
    SELECT 21 AS dias, 'Press banca' AS ejercicio, 40.0 AS carga, 10 AS reps, 'Cómoda, buena técnica' AS sensacion, 120 AS descanso
    UNION ALL SELECT 20, 'Sentadilla libre', 60.0, 8, 'Exigente al final', 150
    UNION ALL SELECT 14, 'Press banca', 42.5, 8, 'Intensa', 120
    UNION ALL SELECT  7, 'Press banca', 45.0, 6, 'Al límite', 120
    UNION ALL SELECT  6, 'Sentadilla libre', 65.0, 8, 'Muy buena sesión', 150
) p
INNER JOIN Usuarios u ON u.Usuario = 'martina.lopez'
INNER JOIN Ejercicios ej ON ej.Nombre = p.ejercicio
WHERE NOT EXISTS (SELECT 1 FROM Registro_Progreso x WHERE x.ID_usuario = u.ID_usuario);

-- Ingresos anteriores de Martina, registrados por Recepción.
INSERT INTO Registro_Accesos (Fecha_hora_ingreso, Fecha_hora_salida, Estado_acceso, ID_usuario, ID_recepcion)
SELECT TIMESTAMP(CURRENT_DATE - INTERVAL a.dias DAY, a.hora),
       TIMESTAMP(CURRENT_DATE - INTERVAL a.dias DAY, a.hora) + INTERVAL 90 MINUTE,
       'Permitido', so.ID_usuario, re.ID_usuario
FROM (
    SELECT 7 AS dias, '07:30:00' AS hora
    UNION ALL SELECT 6, '18:10:00'
    UNION ALL SELECT 3, '07:45:00'
) a
INNER JOIN Usuarios so ON so.Usuario = 'martina.lopez'
INNER JOIN Usuarios re ON re.Usuario = 'recepcion'
WHERE NOT EXISTS (SELECT 1 FROM Registro_Accesos x WHERE x.ID_usuario = so.ID_usuario);

-- ============================================================================
-- Cierre de las migraciones
-- ============================================================================

-- Con los datos ya cargados, los slugs pasan a ser obligatorios.
ALTER TABLE Plan MODIFY Slug VARCHAR(40) NOT NULL;
ALTER TABLE Programas MODIFY Slug VARCHAR(40) NOT NULL;
