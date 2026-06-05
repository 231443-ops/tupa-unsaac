-- Base de datos TUPA UNSAAC
-- Sistema de Texto Único de Procedimientos Administrativos

CREATE DATABASE IF NOT EXISTS tupa_unsaac CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tupa_unsaac;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(15) UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'jefe', 'operador', 'usuario') DEFAULT 'usuario',
    dependencia_id INT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de dependencias/oficinas
CREATE TABLE IF NOT EXISTS dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    codigo VARCHAR(20) UNIQUE,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de procedimientos TUPA
CREATE TABLE IF NOT EXISTS procedimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(300) NOT NULL,
    descripcion TEXT,
    requisitos TEXT,
    dependencia_id INT,
    costo DECIMAL(10,2) DEFAULT 0.00,
    plazo_dias INT DEFAULT 30,
    base_legal TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dependencia_id) REFERENCES dependencias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de trámites (solicitudes de usuarios)
CREATE TABLE IF NOT EXISTS tramites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_expediente VARCHAR(30) UNIQUE,
    usuario_id INT NOT NULL,
    procedimiento_id INT NOT NULL,
    estado ENUM('pendiente', 'en_proceso', 'observado', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    observaciones TEXT,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (procedimiento_id) REFERENCES procedimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de historial de estados de trámites
CREATE TABLE IF NOT EXISTS historial_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tramite_id INT NOT NULL,
    estado_anterior ENUM('pendiente', 'en_proceso', 'observado', 'aprobado', 'rechazado'),
    estado_nuevo ENUM('pendiente', 'en_proceso', 'observado', 'aprobado', 'rechazado') NOT NULL,
    comentario TEXT,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla de archivos adjuntos de trámites
CREATE TABLE IF NOT EXISTS archivos_tramite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tramite_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    tamanio INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de pagos asociados a un trámite
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tramite_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo ENUM('efectivo', 'transferencia', 'tarjeta') DEFAULT 'efectivo',
    numero_operacion VARCHAR(50),
    estado ENUM('pendiente', 'pagado', 'anulado') DEFAULT 'pendiente',
    fecha_pago TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de notificaciones para los usuarios
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tramite_id INT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT,
    leida TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de comentarios/mensajes sobre un trámite
CREATE TABLE IF NOT EXISTS comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tramite_id INT NOT NULL,
    usuario_id INT NULL,
    mensaje TEXT NOT NULL,
    interno TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tramite_id) REFERENCES tramites(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Foreign key de usuarios a dependencias (para operadores/jefes asignados)
ALTER TABLE usuarios ADD FOREIGN KEY (dependencia_id) REFERENCES dependencias(id) ON DELETE SET NULL;

-- Índices para optimización
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_dni ON usuarios(dni);
CREATE INDEX idx_usuarios_dependencia ON usuarios(dependencia_id);
CREATE INDEX idx_tramites_usuario ON tramites(usuario_id);
CREATE INDEX idx_tramites_estado ON tramites(estado);
CREATE INDEX idx_tramites_expediente ON tramites(numero_expediente);
CREATE INDEX idx_procedimientos_dependencia ON procedimientos(dependencia_id);
CREATE INDEX idx_historial_tramite ON historial_estados(tramite_id);
CREATE INDEX idx_archivos_tramite ON archivos_tramite(tramite_id);
CREATE INDEX idx_pagos_tramite ON pagos(tramite_id);
CREATE INDEX idx_notificaciones_usuario ON notificaciones(usuario_id);
CREATE INDEX idx_comentarios_tramite ON comentarios(tramite_id);

-- ============================================================
-- Datos de prueba (seed)
-- ============================================================

-- Dependencias
INSERT INTO dependencias (nombre, codigo, descripcion) VALUES
('Facultad de Ingeniería Eléctrica, Electrónica, Informática y Mecánica', 'FIEEIM', 'Trámites académicos de la facultad'),
('Oficina de Servicios Académicos', 'OSA', 'Constancias, certificados y registros académicos');

-- Procedimientos del catálogo TUPA
INSERT INTO procedimientos (codigo, nombre, descripcion, requisitos, dependencia_id, costo, plazo_dias, base_legal) VALUES
('P-001', 'Constancia de matrícula', 'Emisión de constancia de matrícula del semestre vigente', 'Recibo de pago\nCopia de DNI', 2, 15.00, 5, 'Reglamento académico UNSAAC'),
('P-002', 'Certificado de estudios', 'Certificado oficial de estudios por ciclo o consolidado', 'Recibo de pago\nCopia de DNI\nSolicitud dirigida al decano', 1, 35.00, 10, 'Reglamento académico UNSAAC');

-- Usuarios de prueba
-- admin@unsaac.edu.pe  -> contraseña: Admin123!
-- 231443@unsaac.edu.pe -> contraseña: Student123!
INSERT INTO usuarios (dni, nombre, email, password, rol, dependencia_id) VALUES
('00000000', 'Administrador TUPA', 'admin@unsaac.edu.pe', '$2y$10$LCPZ0wYPy.R4ePUWCg8HjOwJJYaPRU/xsjA/Et.kbyJgvDlLC0.n2', 'admin', NULL),
('23144300', 'Gerald Benjamín Huanto Ayma', '231443@unsaac.edu.pe', '$2y$10$TgtOAGC3J84U9QjnRJXAhuv0jhyXSvrckWl/ffWzXktc3BjMUW5o.', 'usuario', NULL);
