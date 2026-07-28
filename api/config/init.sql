CREATE DATABASE IF NOT EXISTS fitpower CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitpower;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS Tipo_usuario (
    ID_tipo INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE,
    Descripcion VARCHAR(255) NULL
);

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

CREATE TABLE IF NOT EXISTS Persona (
    ID_persona INT AUTO_INCREMENT PRIMARY KEY,
    Peso DECIMAL(5,2) NULL,
    Edad INT NULL,
    Estatura DECIMAL(4,2) NULL,
    Genero VARCHAR(20) NULL,
    Nombre VARCHAR(100) NOT NULL,
    Correo VARCHAR(100) NOT NULL UNIQUE,
    Foto VARCHAR(255) NULL,
    ID_usuario INT NOT NULL UNIQUE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Plan (
    ID_plan INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE,
    Precio DECIMAL(10,2) NOT NULL,
    Descripcion TEXT NULL,
    Activo BOOLEAN NOT NULL DEFAULT TRUE
);

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

CREATE TABLE IF NOT EXISTS Socio_Entrenador (
    ID_socio INT PRIMARY KEY,
    ID_entrenador INT NOT NULL,
    Fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_socio) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Ejercicios (
    ID_ejercicio INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL UNIQUE,
    Grupo_muscular VARCHAR(50) NOT NULL,
    Descripcion TEXT NULL,
    Video_indicativo VARCHAR(255) NULL,
    Activo BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS Rutinas (
    ID_rutina INT AUTO_INCREMENT PRIMARY KEY,
    Nombre_rutina VARCHAR(100) NOT NULL,
    Descripcion TEXT NULL,
    ID_entrenador INT NOT NULL,
    Fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Activa BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (ID_entrenador) REFERENCES Usuarios(ID_usuario) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Usuario_Rutina (
    ID_usuario INT NOT NULL,
    ID_rutina INT NOT NULL,
    Fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Estado VARCHAR(30) NOT NULL DEFAULT 'Asignada',
    PRIMARY KEY (ID_usuario, ID_rutina),
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_rutina) REFERENCES Rutinas(ID_rutina) ON DELETE CASCADE
);

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
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE,
    FOREIGN KEY (ID_personal) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS Mensajes_chat (
    ID_mensaje INT AUTO_INCREMENT PRIMARY KEY,
    ID_chat INT NOT NULL,
    ID_usuario INT NOT NULL,
    Mensaje TEXT NOT NULL,
    Fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ID_chat) REFERENCES Chat(ID_chat) ON DELETE CASCADE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Tokens_QR (
    ID_token INT AUTO_INCREMENT PRIMARY KEY,
    Token_hash VARCHAR(255) NOT NULL UNIQUE,
    ID_usuario INT NOT NULL,
    Fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Fecha_expiracion DATETIME NOT NULL,
    Usado BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Registro_Accesos (
    ID_acceso INT AUTO_INCREMENT PRIMARY KEY,
    Fecha_hora_ingreso DATETIME NULL,
    Fecha_hora_salida DATETIME NULL,
    Estado_acceso ENUM('Permitido','Denegado') NOT NULL,
    Motivo VARCHAR(150) NULL,
    ID_usuario INT NOT NULL,
    FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS Programas (
    ID_programa INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL UNIQUE,
    Historia TEXT NULL,
    Tecnica TEXT NULL,
    Beneficios TEXT NULL,
    Nombre_entrenador VARCHAR(100) NULL,
    Foto_entrenador VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS Plan_Programa (
    ID_plan INT NOT NULL,
    ID_programa INT NOT NULL,
    PRIMARY KEY (ID_plan, ID_programa),
    FOREIGN KEY (ID_plan) REFERENCES Plan(ID_plan) ON DELETE CASCADE,
    FOREIGN KEY (ID_programa) REFERENCES Programas(ID_programa) ON DELETE CASCADE
);

INSERT INTO Tipo_usuario (ID_tipo, Nombre, Descripcion) VALUES
(1,'Administrador','Gestiona usuarios, membresías y sugerencias'),
(2,'Entrenador','Crea rutinas y atiende socios'),
(3,'Socio','Consulta rutinas, membresía y rendimiento'),
(4,'Personal','Atiende limpieza y mantenimiento')
ON DUPLICATE KEY UPDATE Descripcion = VALUES(Descripcion);

INSERT INTO Usuarios (ID_usuario, Usuario, Password, ID_tipo)
SELECT 1,'admin@fitpower.com','$2y$12$fxwrjYYmGi/Uir3wP/chdeVY/p1RvZuI9NfsBkfw1nkejC6BvmKnu',1
WHERE NOT EXISTS (SELECT 1 FROM Usuarios WHERE Usuario='admin@fitpower.com');
INSERT INTO Usuarios (ID_usuario, Usuario, Password, ID_tipo)
SELECT 2,'entrenador@fitpower.com','$2y$12$fxwrjYYmGi/Uir3wP/chdeVY/p1RvZuI9NfsBkfw1nkejC6BvmKnu',2
WHERE NOT EXISTS (SELECT 1 FROM Usuarios WHERE Usuario='entrenador@fitpower.com');
INSERT INTO Usuarios (ID_usuario, Usuario, Password, ID_tipo)
SELECT 3,'socio@fitpower.com','$2y$12$fxwrjYYmGi/Uir3wP/chdeVY/p1RvZuI9NfsBkfw1nkejC6BvmKnu',3
WHERE NOT EXISTS (SELECT 1 FROM Usuarios WHERE Usuario='socio@fitpower.com');
INSERT INTO Usuarios (ID_usuario, Usuario, Password, ID_tipo)
SELECT 4,'personal@fitpower.com','$2y$12$fxwrjYYmGi/Uir3wP/chdeVY/p1RvZuI9NfsBkfw1nkejC6BvmKnu',4
WHERE NOT EXISTS (SELECT 1 FROM Usuarios WHERE Usuario='personal@fitpower.com');

INSERT INTO Persona (Nombre, Correo, Edad, Genero, ID_usuario)
SELECT 'Administrador General','admin@fitpower.com',35,'Masculino',1 WHERE NOT EXISTS (SELECT 1 FROM Persona WHERE ID_usuario=1);
INSERT INTO Persona (Nombre, Correo, Edad, Genero, ID_usuario)
SELECT 'Carlos Pérez','entrenador@fitpower.com',28,'Masculino',2 WHERE NOT EXISTS (SELECT 1 FROM Persona WHERE ID_usuario=2);
INSERT INTO Persona (Nombre, Correo, Edad, Genero, Peso, Estatura, ID_usuario)
SELECT 'Socio Demo','socio@fitpower.com',22,'Femenino',72.5,1.75,3 WHERE NOT EXISTS (SELECT 1 FROM Persona WHERE ID_usuario=3);
INSERT INTO Persona (Nombre, Correo, Edad, ID_usuario)
SELECT 'Personal de Mantenimiento','personal@fitpower.com',30,4 WHERE NOT EXISTS (SELECT 1 FROM Persona WHERE ID_usuario=4);
UPDATE Persona SET Nombre='Carlos Pérez' WHERE ID_usuario=2;
UPDATE Persona SET Nombre='Socio Demo' WHERE ID_usuario=3;

INSERT INTO Plan (Nombre, Precio, Descripcion) VALUES
('Plan Básico',1800,'Acceso a programas básicos'),('Plan Pro',2500,'Incluye entrenador personal'),('Plan Elite',4500,'Seguimiento completo')
ON DUPLICATE KEY UPDATE Precio=VALUES(Precio), Descripcion=VALUES(Descripcion);
INSERT INTO Ejercicios (Nombre, Grupo_muscular, Descripcion) VALUES
('Sentadilla libre','Piernas','Trabajo general de piernas'),('Press banca','Pecho','Empuje horizontal'),('Remo con barra','Espalda','Tirón horizontal'),('Pasadas en cinta','Cardio','Trabajo de velocidad'),('Plancha abdominal','Core','Estabilidad abdominal'),('Crunch abdominal','Core','Flexión de tronco')
ON DUPLICATE KEY UPDATE Grupo_muscular=VALUES(Grupo_muscular), Descripcion=VALUES(Descripcion);
INSERT INTO Socio_Entrenador (ID_socio, ID_entrenador)
SELECT 3,2 WHERE NOT EXISTS (SELECT 1 FROM Socio_Entrenador WHERE ID_socio=3);
INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento, Estado, Fecha_ultimo_pago, Monto_ultimo_pago)
SELECT 3,ID_plan,CURRENT_DATE,LAST_DAY(CURRENT_DATE),'Al dia',NOW(),Precio FROM Plan WHERE Nombre='Plan Pro'
AND NOT EXISTS (SELECT 1 FROM Membresias WHERE ID_usuario=3);

INSERT INTO Rutinas (Nombre_rutina, Descripcion, ID_entrenador)
SELECT 'Hipertrofia - Día 1','Pecho y espalda con foco en técnica y control.',2
WHERE NOT EXISTS (SELECT 1 FROM Rutinas WHERE Nombre_rutina='Hipertrofia - Día 1');
INSERT INTO Rutinas (Nombre_rutina, Descripcion, ID_entrenador)
SELECT 'Movilidad y Core','Sesión corta para activar y recuperar.',2
WHERE NOT EXISTS (SELECT 1 FROM Rutinas WHERE Nombre_rutina='Movilidad y Core');
INSERT INTO Usuario_Rutina (ID_usuario, ID_rutina)
SELECT 3,ID_rutina FROM Rutinas WHERE Nombre_rutina IN ('Hipertrofia - Día 1','Movilidad y Core')
AND NOT EXISTS (SELECT 1 FROM Usuario_Rutina ur WHERE ur.ID_usuario=3 AND ur.ID_rutina=Rutinas.ID_rutina);
INSERT INTO Rutina_Ejercicio (ID_rutina, ID_ejercicio, Series, Repeticiones, Orden, Tiempo_descanso)
SELECT r.ID_rutina,e.ID_ejercicio,4,'8-10',1,120 FROM Rutinas r JOIN Ejercicios e ON e.Nombre='Press banca' WHERE r.Nombre_rutina='Hipertrofia - Día 1' AND NOT EXISTS (SELECT 1 FROM Rutina_Ejercicio re WHERE re.ID_rutina=r.ID_rutina);
INSERT INTO Rutina_Ejercicio (ID_rutina, ID_ejercicio, Series, Repeticiones, Orden, Tiempo_descanso)
SELECT r.ID_rutina,e.ID_ejercicio,4,'10',2,90 FROM Rutinas r JOIN Ejercicios e ON e.Nombre='Remo con barra' WHERE r.Nombre_rutina='Hipertrofia - Día 1' AND (SELECT COUNT(*) FROM Rutina_Ejercicio re WHERE re.ID_rutina=r.ID_rutina)=1;
INSERT INTO Rutina_Ejercicio (ID_rutina, ID_ejercicio, Series, Repeticiones, Orden, Tiempo_descanso)
SELECT r.ID_rutina,e.ID_ejercicio,3,'45 segundos',1,60 FROM Rutinas r JOIN Ejercicios e ON e.Nombre='Plancha abdominal' WHERE r.Nombre_rutina='Movilidad y Core' AND NOT EXISTS (SELECT 1 FROM Rutina_Ejercicio re WHERE re.ID_rutina=r.ID_rutina);
