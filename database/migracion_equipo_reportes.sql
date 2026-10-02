USE compuser_inventario;

ALTER TABLE reportes
    ADD COLUMN equipo_id INT DEFAULT NULL AFTER orden_id,
    ADD INDEX idx_reportes_equipo_id (equipo_id),
    ADD CONSTRAINT fk_reportes_equipo FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL;

-- Recupera el equipo de reportes anteriores solo cuando hay un unico despacho
-- del mismo tecnico relacionado con esa orden.
UPDATE reportes r
JOIN equipos_tecnico et
    ON et.tecnico_id = r.tecnico_id
    AND et.orden_id = r.orden_id
LEFT JOIN equipos_tecnico otro
    ON otro.tecnico_id = r.tecnico_id
    AND otro.orden_id = r.orden_id
    AND otro.id <> et.id
SET r.equipo_id = et.equipo_id
WHERE r.equipo_id IS NULL
    AND r.orden_id IS NOT NULL
    AND otro.id IS NULL;