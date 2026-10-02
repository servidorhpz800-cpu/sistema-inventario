USE compuser_inventario;

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    numero VARCHAR(40) NOT NULL UNIQUE,
    calle VARCHAR(180) NOT NULL,
    numero_exterior VARCHAR(30) DEFAULT NULL,
    colonia VARCHAR(120) DEFAULT NULL,
    referencias TEXT DEFAULT NULL,
    ubicacion_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clientes_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO clientes (nombre, numero, calle, numero_exterior, colonia, referencias, ubicacion_url)
SELECT
    COALESCE(NULLIF(MAX(cliente_nombre), ''), 'Cliente'),
    cliente_numero,
    COALESCE(NULLIF(MAX(calle), ''), 'Sin domicilio'),
    NULLIF(MAX(numero_exterior), ''),
    NULLIF(MAX(colonia), ''),
    MAX(referencias),
    MAX(ubicacion_url)
FROM ordenes
WHERE cliente_numero IS NOT NULL AND cliente_numero <> ''
GROUP BY cliente_numero
ON DUPLICATE KEY UPDATE numero = VALUES(numero);

ALTER TABLE ordenes
    ADD COLUMN cliente_id INT DEFAULT NULL AFTER tecnico_id,
    ADD INDEX idx_ordenes_cliente_id (cliente_id),
    ADD CONSTRAINT fk_ordenes_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL;

UPDATE ordenes o
JOIN clientes c ON c.numero = o.cliente_numero
SET o.cliente_id = c.id
WHERE o.cliente_numero IS NOT NULL AND o.cliente_numero <> '';