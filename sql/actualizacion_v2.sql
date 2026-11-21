SET NAMES utf8mb4;
-- ===== Actualización v2: opciones, cupones, zonas, repartidores, recuperación de clave, pagos en línea =====
-- (Si instalas desde cero con database.sql, este bloque ya está incluido.)

ALTER TABLE productos
  ADD COLUMN detalle TEXT NULL AFTER descripcion,
  ADD COLUMN ingredientes VARCHAR(255) NULL AFTER detalle,
  ADD COLUMN precio_promo DECIMAL(12,0) NULL AFTER precio;

CREATE TABLE grupos_opciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  tipo ENUM('unica','multiple') NOT NULL DEFAULT 'unica',
  obligatorio TINYINT(1) NOT NULL DEFAULT 0,
  orden INT NOT NULL DEFAULT 0,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE opciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  extra DECIMAL(12,0) NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (grupo_id) REFERENCES grupos_opciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cupones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  tipo ENUM('porcentaje','monto','envio_gratis') NOT NULL,
  valor DECIMAL(12,0) NOT NULL DEFAULT 0,
  minimo DECIMAL(12,0) NOT NULL DEFAULT 0,
  usos_max INT NOT NULL DEFAULT 0,
  usos INT NOT NULL DEFAULT 0,
  uno_por_usuario TINYINT(1) NOT NULL DEFAULT 1,
  vence DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE zonas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  costo DECIMAL(12,0) NOT NULL DEFAULT 0,
  minutos_extra INT NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE repartidores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (token_hash),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE pedidos
  MODIFY pago ENUM('efectivo','transferencia','tarjeta','bancard') NOT NULL,
  ADD COLUMN cupon VARCHAR(30) NULL,
  ADD COLUMN descuento DECIMAL(12,0) NOT NULL DEFAULT 0,
  ADD COLUMN zona_id INT NULL,
  ADD COLUMN repartidor_id INT NULL,
  ADD COLUMN pago_estado ENUM('no_aplica','pendiente','pagado','fallido') NOT NULL DEFAULT 'no_aplica',
  ADD COLUMN bancard_intentos INT NOT NULL DEFAULT 0;

ALTER TABLE pedido_items ADD COLUMN opciones VARCHAR(500) NULL;

-- Datos de ejemplo
INSERT INTO zonas (nombre,costo,minutos_extra) VALUES ('Centro (hasta 2 km)',8000,0),('Barrios cercanos (2-5 km)',15000,10),('Zona lejana (5-8 km)',25000,20);
INSERT INTO repartidores (nombre,telefono) VALUES ('Repartidor 1','0981000001');
INSERT INTO cupones (codigo,tipo,valor,minimo,uno_por_usuario) VALUES ('BIENVENIDO10','porcentaje',10,30000,1),('ENVIOGRATIS','envio_gratis',0,50000,0);

UPDATE productos SET detalle='Nuestra hamburguesa de la casa: carne de res 120 g a la plancha, queso derretido, lechuga fresca y tomate en pan brioche tostado.', ingredientes='Pan brioche, carne de res, queso, lechuga, tomate' WHERE nombre='Clásica';
UPDATE productos SET precio_promo=32000 WHERE nombre='Doble Cheddar';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Tamaño','unica',1,1 FROM productos WHERE nombre='Clásica';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Extras','multiple',0,2 FROM productos WHERE nombre='Clásica';
INSERT INTO grupos_opciones (producto_id,nombre,tipo,obligatorio,orden)
  SELECT id,'Quitar ingredientes','multiple',0,3 FROM productos WHERE nombre='Clásica';
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Simple',0 FROM grupos_opciones WHERE nombre='Tamaño' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Doble carne',10000 FROM grupos_opciones WHERE nombre='Tamaño' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Huevo frito',3000 FROM grupos_opciones WHERE nombre='Extras' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Panceta',5000 FROM grupos_opciones WHERE nombre='Extras' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Sin cebolla',0 FROM grupos_opciones WHERE nombre='Quitar ingredientes' LIMIT 1;
INSERT INTO opciones (grupo_id,nombre,extra) SELECT id,'Sin tomate',0 FROM grupos_opciones WHERE nombre='Quitar ingredientes' LIMIT 1;
