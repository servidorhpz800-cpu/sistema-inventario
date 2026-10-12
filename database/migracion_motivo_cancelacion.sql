USE compuser_inventario;

ALTER TABLE ordenes
    ADD COLUMN motivo_cancelacion TEXT DEFAULT NULL AFTER descripcion;
