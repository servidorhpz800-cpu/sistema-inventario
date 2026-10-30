USE compuser_inventario;

ALTER TABLE equipos_tecnico
    ADD COLUMN IF NOT EXISTS ciudad VARCHAR(120) DEFAULT NULL AFTER cliente_numero,
    ADD COLUMN IF NOT EXISTS domicilio_calles VARCHAR(180) DEFAULT NULL AFTER ciudad;

UPDATE equipos_tecnico et
JOIN reportes r ON r.movimiento_id = et.id
SET et.ciudad = COALESCE(NULLIF(et.ciudad, ''), r.ciudad),
    et.domicilio_calles = COALESCE(NULLIF(et.domicilio_calles, ''), r.domicilio_calles)
WHERE et.ciudad IS NULL OR et.domicilio_calles IS NULL;
