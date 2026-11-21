SET NAMES utf8mb4;
-- ===== Actualización v3: panel del repartidor, tiempos de entrega, código de país para WhatsApp =====
-- (Si instalas desde cero con database.sql, este bloque ya está incluido.)

ALTER TABLE usuarios MODIFY rol ENUM('cliente','empleado','admin','repartidor') NOT NULL DEFAULT 'cliente';

ALTER TABLE repartidores
  ADD COLUMN usuario_id INT NULL,
  ADD UNIQUE KEY uq_rep_usuario (usuario_id),
  ADD CONSTRAINT fk_rep_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE pedidos
  ADD COLUMN asignado_en DATETIME NULL,
  ADD COLUMN entregado_en DATETIME NULL;

UPDATE pedidos SET entregado_en = actualizado WHERE estado = 'entregado' AND entregado_en IS NULL;
UPDATE pedidos SET asignado_en = actualizado WHERE repartidor_id IS NOT NULL AND asignado_en IS NULL;

INSERT IGNORE INTO configuracion (clave, valor) VALUES ('codigo_pais', '595');
