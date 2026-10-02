USE compuser_inventario;

ALTER TABLE reportes
    ADD COLUMN IF NOT EXISTS movimiento_id INT DEFAULT NULL AFTER equipo_id,
    ADD COLUMN IF NOT EXISTS cliente_nombre VARCHAR(150) DEFAULT NULL AFTER equipo_id,
    ADD COLUMN IF NOT EXISTS cliente_numero VARCHAR(40) DEFAULT NULL AFTER cliente_nombre;

ALTER TABLE reportes
    ADD INDEX idx_reportes_movimiento_id (movimiento_id),
    ADD CONSTRAINT fk_reportes_movimiento FOREIGN KEY (movimiento_id) REFERENCES equipos_tecnico(id) ON DELETE SET NULL;

ALTER TABLE equipos_tecnico
    ADD COLUMN IF NOT EXISTS cliente_nombre VARCHAR(150) DEFAULT NULL AFTER orden_id,
    ADD COLUMN IF NOT EXISTS cliente_numero VARCHAR(40) DEFAULT NULL AFTER cliente_nombre;

CREATE TABLE IF NOT EXISTS reporte_evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    foto_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reporte_evidencias_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE equipos_tecnico et
JOIN ordenes o ON o.id = et.orden_id
SET et.cliente_nombre = COALESCE(et.cliente_nombre, o.cliente_nombre),
    et.cliente_numero = COALESCE(et.cliente_numero, o.cliente_numero)
WHERE et.cliente_nombre IS NULL OR et.cliente_numero IS NULL;

UPDATE reportes r
JOIN ordenes o ON o.id = r.orden_id
SET r.cliente_nombre = COALESCE(r.cliente_nombre, o.cliente_nombre),
    r.cliente_numero = COALESCE(r.cliente_numero, o.cliente_numero)
WHERE r.cliente_nombre IS NULL OR r.cliente_numero IS NULL;

INSERT INTO reporte_evidencias (reporte_id, foto_url)
SELECT r.id, r.foto_url
FROM reportes r
WHERE r.foto_url IS NOT NULL AND r.foto_url <> ''
    AND NOT EXISTS (
        SELECT 1 FROM reporte_evidencias re
        WHERE re.reporte_id = r.id AND re.foto_url = r.foto_url
    );