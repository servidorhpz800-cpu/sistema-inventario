USE compuser_inventario;

-- Ejecutar una sola vez sobre una base existente; los índices ya están en schema.sql.
ALTER TABLE equipos
    ADD INDEX idx_equipos_serial (serial),
    ADD INDEX idx_equipos_estado_updated (estado, updated_at);

ALTER TABLE ordenes
    ADD INDEX idx_ordenes_tecnico_estado_created (tecnico_id, estado, created_at),
    ADD INDEX idx_ordenes_estado_created (estado, created_at);

ALTER TABLE reportes
    ADD INDEX idx_reportes_tecnico_created (tecnico_id, created_at);

ALTER TABLE equipos_tecnico
    ADD INDEX idx_equipos_tecnico_updated (tecnico_id, updated_at);