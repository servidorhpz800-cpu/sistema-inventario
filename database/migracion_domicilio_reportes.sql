USE compuser_inventario;

-- Las órdenes ya no requieren un equipo único; el despacho se controla aparte.
ALTER TABLE ordenes MODIFY COLUMN equipo_id INT NULL;

-- Datos del cliente y domicilio de cada orden
ALTER TABLE ordenes
    ADD COLUMN IF NOT EXISTS cliente_nombre VARCHAR(150) DEFAULT NULL AFTER tecnico_id,
    ADD COLUMN IF NOT EXISTS cliente_numero VARCHAR(40) DEFAULT NULL AFTER cliente_nombre,
    ADD COLUMN IF NOT EXISTS calle VARCHAR(180) DEFAULT NULL AFTER cliente_numero,
    ADD COLUMN IF NOT EXISTS numero_exterior VARCHAR(30) DEFAULT NULL AFTER calle,
    ADD COLUMN IF NOT EXISTS colonia VARCHAR(120) DEFAULT NULL AFTER numero_exterior,
    ADD COLUMN IF NOT EXISTS referencias TEXT DEFAULT NULL AFTER colonia,
    ADD COLUMN IF NOT EXISTS ubicacion_url VARCHAR(500) DEFAULT NULL AFTER referencias,
    ADD COLUMN IF NOT EXISTS ip_asignada VARCHAR(45) DEFAULT NULL AFTER ubicacion_url;

-- Información ampliada del trabajo técnico
ALTER TABLE reportes
    ADD COLUMN IF NOT EXISTS actividad_realizada TEXT DEFAULT NULL AFTER titulo,
    ADD COLUMN IF NOT EXISTS materiales TEXT DEFAULT NULL AFTER actividad_realizada,
    ADD COLUMN IF NOT EXISTS resultado TEXT DEFAULT NULL AFTER materiales,
    ADD COLUMN IF NOT EXISTS latitud DECIMAL(10, 7) DEFAULT NULL AFTER descripcion,
    ADD COLUMN IF NOT EXISTS longitud DECIMAL(10, 7) DEFAULT NULL AFTER latitud,
    ADD COLUMN IF NOT EXISTS foto_url VARCHAR(255) DEFAULT NULL AFTER longitud;

-- Estados posibles del equipo reportado por el técnico
ALTER TABLE reportes
    MODIFY COLUMN estado ENUM('normal', 'revision', 'danado', 'dañado', 'no_serve', 'reparacion') NOT NULL DEFAULT 'normal';

CREATE TABLE IF NOT EXISTS equipos_tecnico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    tecnico_id INT NOT NULL,
    orden_id INT DEFAULT NULL,
    estado ENUM('portado', 'utilizado', 'no_utilizado', 'falla', 'devuelto') NOT NULL DEFAULT 'portado',
    observaciones TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (orden_id) REFERENCES ordenes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bitácora central de cambios, visible únicamente para administradores desde la aplicación.
CREATE TABLE IF NOT EXISTS actividad_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    usuario_nombre VARCHAR(150) NOT NULL,
    rol VARCHAR(30) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(80) NOT NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id INT DEFAULT NULL,
    detalle TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actividad_fecha (created_at),
    INDEX idx_actividad_modulo (modulo),
    INDEX idx_actividad_entidad (entidad, entidad_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
